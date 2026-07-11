/**
 * Serveur Socket.io — diffusion positions livreurs en temps réel.
 * Authentification déléguée à PHP (api/tracking/*).
 *
 * Webuzo (samapiece.com) : Apache PHP écoute sur 127.0.0.1:8081 (HTTP).
 * Port 8082 = HTTPS interne — ne pas utiliser pour les appels Node → PHP.
 */
require('dotenv').config();

const http = require('http');
const https = require('https');
const { URL } = require('url');
const { Server } = require('socket.io');

const PORT = parseInt(process.env.TRACKING_PORT || '3001', 10);
const PHP_BASE = (process.env.TRACKING_PHP_BASE || 'http://127.0.0.1:8081').replace(/\/$/, '');
const PHP_HOST = (process.env.TRACKING_PHP_HOST || 'samapiece.com').trim();
const INTERNAL_SECRET = process.env.TRACKING_INTERNAL_SECRET || '';
const SOCKET_PATH = process.env.TRACKING_SOCKET_PATH || '/socket.io';

const corsOrigins = (process.env.TRACKING_CORS_ORIGINS || '')
  .split(',')
  .map((s) => s.trim())
  .filter(Boolean);

if (!INTERNAL_SECRET) {
  console.error('[tracking] TRACKING_INTERNAL_SECRET manquant dans .env');
  process.exit(1);
}

const server = http.createServer((req, res) => {
  if (req.url === '/health' || req.url === '/health/') {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ ok: true, service: 'tracking-socket' }));
    return;
  }
  res.writeHead(404);
  res.end('Not found');
});

const io = new Server(server, {
  path: SOCKET_PATH,
  cors: {
    origin: corsOrigins.length ? corsOrigins : true,
    methods: ['GET', 'POST'],
    credentials: true,
  },
  pingInterval: 25000,
  pingTimeout: 20000,
});

async function callPhp(path, payload) {
  const baseUrl = new URL(PHP_BASE);
  const isHttps = baseUrl.protocol === 'https:';
  const transport = isHttps ? https : http;
  const body = JSON.stringify({
    ...payload,
    internal_secret: INTERNAL_SECRET,
  });

  const port = baseUrl.port
    ? parseInt(baseUrl.port, 10)
    : (isHttps ? 443 : 80);

  const options = {
    hostname: baseUrl.hostname,
    port,
    path,
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Content-Length': Buffer.byteLength(body),
      'X-Tracking-Secret': INTERNAL_SECRET,
    },
  };

  /* fetch() ignore l'en-tête Host — obligatoire sur Webuzo (vhost samapiece.com) */
  if (PHP_HOST) {
    options.headers.Host = PHP_HOST;
  }

  return new Promise(function (resolve, reject) {
    const req = transport.request(options, function (res) {
      let raw = '';
      res.on('data', function (chunk) {
        raw += chunk;
      });
      res.on('end', function () {
        let data = {};
        try {
          data = JSON.parse(raw);
        } catch (_) {
          data = {};
        }
        resolve({ status: res.statusCode || 0, data });
      });
    });

    req.on('error', reject);
    req.write(body);
    req.end();
  });
}

async function checkPhpConnection() {
  if (!PHP_HOST) {
    console.warn('[tracking] TRACKING_PHP_HOST vide — requis sur Webuzo (ex. samapiece.com)');
    return;
  }
  try {
    const { status } = await callPhp('/api/tracking/verify-livreur.php', { token: '__startup_check__' });
    if (status === 200 || status === 401 || status === 403) {
      console.log(`[tracking] PHP joignable (${PHP_BASE}, Host: ${PHP_HOST})`);
      return;
    }
    console.warn(`[tracking] PHP réponse inattendue HTTP ${status} (${PHP_BASE})`);
  } catch (err) {
    console.error(`[tracking] PHP injoignable (${PHP_BASE}, Host: ${PHP_HOST}): ${err.message}`);
  }
}

async function verifyLivreurToken(token) {
  const { status, data } = await callPhp('/api/tracking/verify-livreur.php', { token });
  if (status !== 200 || !data.valid) {
    return null;
  }
  return data;
}

async function verifyWatchToken(token, commandeId, blId) {
  const payload = { token };
  if (blId) {
    payload.bl_id = blId;
  } else {
    payload.commande_id = commandeId;
  }
  const { status, data } = await callPhp('/api/tracking/verify-watch.php', payload);
  if (status !== 200 || !data.valid) {
    return null;
  }
  return data;
}

async function persistPosition(payload) {
  try {
    await callPhp('/api/tracking/persist-position.php', payload);
  } catch (err) {
    console.error('[tracking] persist error', err.message);
  }
}

io.use(async (socket, next) => {
  try {
    const auth = socket.handshake.auth || {};
    const role = auth.role || '';

    if (role === 'livreur') {
      const token = auth.token || '';
      const info = await verifyLivreurToken(token);
      if (!info) {
        return next(new Error('livreur_unauthorized'));
      }
      socket.data.role = 'livreur';
      socket.data.livreurId = info.livreur_id;
      socket.data.livreurNom = info.livreur_nom || '';
      socket.data.commandeId = info.commande_id || null;
      socket.data.trackingActive = !!info.tracking_active;
      socket.data.livreurToken = token;
      return next();
    }

    if (role === 'watch') {
      const token = auth.token || '';
      const commandeId = parseInt(auth.commande_id, 10) || 0;
      const blId = parseInt(auth.bl_id, 10) || 0;
      if (!token || (!commandeId && !blId)) {
        return next(new Error('watch_invalid_params'));
      }
      const info = await verifyWatchToken(token, commandeId, blId);
      if (!info) {
        return next(new Error('watch_unauthorized'));
      }
      socket.data.role = 'watch';
      socket.data.commandeId = info.commande_id || null;
      socket.data.blId = info.bl_id || null;
      socket.data.watchType = info.type || 'admin';
      socket.data.watchMeta = info;
      return next();
    }

    return next(new Error('role_required'));
  } catch (err) {
    console.error('[tracking] auth middleware', err.message);
    return next(new Error('auth_failed'));
  }
});

io.on('connection', (socket) => {
  const role = socket.data.role;

  if (role === 'livreur') {
    const livreurId = socket.data.livreurId;
    socket.join(`livreur_${livreurId}`);

    if (socket.data.commandeId) {
      socket.join(`commande_${socket.data.commandeId}`);
    }

    socket.emit('livreur:ready', {
      livreur_id: livreurId,
      commande_id: socket.data.commandeId,
      tracking_active: socket.data.trackingActive,
    });
  }

  if (role === 'watch') {
    const commandeId = socket.data.commandeId;
    const blId = socket.data.blId;
    if (commandeId) {
      socket.join(`commande_${commandeId}`);
    }
    if (blId) {
      socket.join(`bl_${blId}`);
    }

    const meta = socket.data.watchMeta || {};
    socket.emit('watch:ready', {
      commande_id: commandeId,
      bl_id: blId,
      numero_commande: meta.numero_commande || null,
      tracking_active: !!meta.tracking_active,
      delivery_latitude: meta.delivery_latitude,
      delivery_longitude: meta.delivery_longitude,
      adresse_livraison: meta.adresse_livraison || '',
      last_position: meta.last_position || null,
    });
  }

  socket.on('livreur:position', async (raw) => {
    if (socket.data.role !== 'livreur') {
      return;
    }

    const livreurId = socket.data.livreurId;
    const commandeId = parseInt(raw && raw.commande_id, 10) || socket.data.commandeId;
    const latitude = raw && raw.latitude;
    const longitude = raw && raw.longitude;

    if (!commandeId || latitude == null || longitude == null) {
      socket.emit('livreur:error', { message: 'position_invalid' });
      return;
    }

    const payload = {
      livreur_id: livreurId,
      commande_id: commandeId,
      latitude,
      longitude,
      accuracy: raw.accuracy != null ? raw.accuracy : null,
      speed: raw.speed != null ? raw.speed : null,
      heading: raw.heading != null ? raw.heading : null,
      recorded_at: new Date().toISOString(),
      livreur_nom: socket.data.livreurNom || '',
    };

    io.to(`commande_${commandeId}`).emit('position:update', payload);

    persistPosition({
      livreur_id: livreurId,
      commande_id: commandeId,
      latitude: payload.latitude,
      longitude: payload.longitude,
      accuracy: payload.accuracy,
      speed: payload.speed,
      heading: payload.heading,
    });
  });

  socket.on('livreur:join_commande', async (raw) => {
    if (socket.data.role !== 'livreur') {
      return;
    }
    const commandeId = parseInt(raw && raw.commande_id, 10);
    if (!commandeId) {
      return;
    }

    const info = await verifyLivreurToken(socket.data.livreurToken || '');
    if (!info || info.commande_id !== commandeId) {
      socket.emit('livreur:error', { message: 'commande_not_active' });
      return;
    }

    socket.data.commandeId = commandeId;
    socket.join(`commande_${commandeId}`);
    socket.emit('livreur:joined', { commande_id: commandeId });
  });

  socket.on('disconnect', (reason) => {
    console.log(`[tracking] disconnect role=${role} id=${socket.id} reason=${reason}`);
  });
});

server.listen(PORT, '127.0.0.1', () => {
  console.log(`[tracking] Socket.io écoute sur 127.0.0.1:${PORT} path=${SOCKET_PATH}`);
  console.log(`[tracking] PHP cible: ${PHP_BASE} (Host: ${PHP_HOST || 'non défini'})`);
  checkPhpConnection();
});
