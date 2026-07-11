<?php
/**
 * Page de diagnostic FCM — accessible sans connexion
 * Aide à diagnostiquer les problèmes de getToken() bloqué
 */
$enable_firebase_notifications = false;
$cfg = require __DIR__ . '/config/firebase_config.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Diagnostic FCM</title>
    <style>
        body { font-family: monospace; background: #111; color: #eee; padding: 20px; }
        h1 { color: #4af; }
        .ok { color: #4f4; }
        .fail { color: #f44; }
        .warn { color: #fa4; }
        .info { color: #aaa; }
        pre { background: #222; padding: 12px; border-radius: 6px; overflow-x: auto; white-space: pre-wrap; }
        button { background: #3564a6; color: #fff; border: none; padding: 10px 20px; margin: 6px 4px; cursor: pointer; border-radius: 4px; font-size: 14px; }
        button:hover { background: #2d5690; }
        #log { margin-top: 16px; }
        .step { border-left: 3px solid #4af; padding-left: 12px; margin: 8px 0; }
    </style>
</head>
<body>
<h1>Diagnostic notifications FCM</h1>
<p class="info">Projet : <strong><?php echo htmlspecialchars($cfg['projectId']); ?></strong> | SW : <code>/firebase-messaging-sw.js</code></p>

<div>
    <button id="btn-run-all">▶ Lancer tous les tests</button>
    <button id="btn-clear-idb" style="background:#a44;">🗑 Vider IndexedDB Firebase</button>
    <button id="btn-unregister-sw">🔧 Désinscrire tous les SW</button>
    <button id="btn-get-token">🔑 Obtenir token FCM</button>
</div>

<div style="margin:16px 0; background:#1a1a1a; padding:16px; border-radius:8px; border:1px solid #333;">
    <p style="color:#fa4; margin:0 0 10px;">⚠ La clé API actuelle est rejetée par Google. Collez la bonne apiKey depuis Firebase Console :</p>
    <p style="color:#aaa; font-size:12px; margin:0 0 8px;">Firebase Console → Paramètres du projet → Vos applications → ⚙ → Config → copiez <code>apiKey</code></p>
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <input id="input-apikey" type="text" placeholder="AIzaSy…" value="<?php echo htmlspecialchars($cfg['apiKey']); ?>"
            style="flex:1; min-width:300px; background:#111; color:#eee; border:1px solid #555; padding:8px; border-radius:4px; font-family:monospace; font-size:13px;" />
        <button id="btn-test-key" style="background:#2a7">🧪 Tester cette clé</button>
        <button id="btn-apply-key" style="background:#555;">💾 Utiliser pour getToken</button>
    </div>
    <p id="key-test-result" style="margin:8px 0 0; font-size:13px;"></p>
</div>
<pre id="log"></pre>

<script src="https://www.gstatic.com/firebasejs/12.9.0/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/12.9.0/firebase-messaging-compat.js"></script>
<script>
var cfg = <?php echo json_encode([
    'apiKey' => $cfg['apiKey'],
    'authDomain' => $cfg['authDomain'],
    'projectId' => $cfg['projectId'],
    'storageBucket' => $cfg['storageBucket'],
    'messagingSenderId' => $cfg['messagingSenderId'],
    'appId' => $cfg['appId'],
], JSON_UNESCAPED_SLASHES); ?>;
var VAPID = <?php echo json_encode(trim($cfg['vapidKey'])); ?>;

var logEl = document.getElementById('log');
function log(type, msg) {
    var colors = { ok: '#4f4', fail: '#f44', warn: '#fa4', info: '#aaa', hdr: '#4af' };
    var prefix = { ok: '[OK]   ', fail: '[FAIL] ', warn: '[WARN] ', info: '[    ] ', hdr: '=== ' };
    var suffix = { hdr: ' ===' };
    logEl.innerHTML += '<span style="color:' + (colors[type]||'#eee') + '">'
        + (prefix[type]||'') + escHtml(msg) + (suffix[type]||'') + '</span>\n';
    logEl.scrollTop = logEl.scrollHeight;
    console.log((prefix[type]||'') + msg);
}
function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

/* ---- Vider IndexedDB Firebase ---- */
function clearFirebaseIDB() {
    var names = [
        'firebase-installations-database',
        'firebase-messaging-database',
        'firebase-heartbeat-database',
        'firebaseLocalStorageDb',
    ];
    var promises = names.map(function(name) {
        return new Promise(function(resolve) {
            var req = indexedDB.deleteDatabase(name);
            req.onsuccess = function() { log('ok', 'IDB supprimée : ' + name); resolve(); };
            req.onerror = function() { log('warn', 'IDB non trouvée ou erreur : ' + name); resolve(); };
            req.onblocked = function() { log('warn', 'IDB bloquée (onglet ouvert ?) : ' + name); resolve(); };
        });
    });
    return Promise.all(promises);
}

/* ---- Désinscrire tous SW ---- */
function unregisterAllSW() {
    return navigator.serviceWorker.getRegistrations().then(function(regs) {
        if (regs.length === 0) { log('info', 'Aucun SW actif'); return; }
        return Promise.all(regs.map(function(reg) {
            log('warn', 'Suppression SW : ' + (reg.active||{}).scriptURL + ' scope=' + reg.scope);
            return reg.unregister();
        })).then(function() { log('ok', 'Tous les SW supprimés'); });
    });
}

/* ---- Test réseau Firebase Installations ---- */
function testInstallationsApi() {
    log('info', 'Test Firebase Installations API…');
    var url = 'https://firebaseinstallations.googleapis.com/v1/projects/' + cfg.projectId + '/installations';
    return fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'x-goog-api-key': cfg.apiKey,
        },
        body: JSON.stringify({
            fid: 'c8d8e8f0a1b2c3d4e5f6789012345678',
            authVersion: 'FIS_v2',
            appId: cfg.appId,
            sdkVersion: 'w:12.9.0',
        }),
    }).then(function(r) {
        return r.text().then(function(body) {
            if (r.status === 200 || r.status === 201) {
                log('ok', 'Installations API : ' + r.status + ' OK');
            } else if (r.status === 409) {
                log('ok', 'Installations API : 409 (déjà enregistré) — clé API valide ✓');
            } else {
                log('fail', 'Installations API : HTTP ' + r.status + ' — ' + body.substring(0,200));
            }
        });
    }).catch(function(err) {
        log('fail', 'Installations API : erreur réseau — ' + err.message);
    });
}

/* ---- Convertit une clé VAPID base64url en Uint8Array ---- */
function urlBase64ToUint8Array(base64String) {
    var padding = '='.repeat((4 - base64String.length % 4) % 4);
    var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    var rawData = window.atob(base64);
    var array = new Uint8Array(rawData.length);
    for (var i = 0; i < rawData.length; i++) { array[i] = rawData.charCodeAt(i); }
    return array;
}

/* ---- Test PushManager.subscribe() direct ---- */
function testPushSubscribe(registration) {
    log('hdr', 'Test PushManager.subscribe() direct (timeout 15s)');
    if (!registration || !registration.pushManager) {
        log('fail', 'Pas de pushManager sur la registration');
        return Promise.resolve();
    }
    return new Promise(function(resolve) {
        var done = false;
        var timer = setTimeout(function() {
            if (done) return;
            done = true;
            log('fail', 'pushManager.subscribe() : TIMEOUT 15s — c\'est l\'étape qui bloque');
            log('warn', '→ Cause probable : WNS/Push Service d\'Edge bloqué, ou réseau filtré');
            log('warn', '→ Essayez avec Chrome, ou vérifiez que microsoft.com et fcm.googleapis.com sont accessibles');
            resolve();
        }, 15000);

        registration.pushManager.getSubscription().then(function(existing) {
            if (existing) {
                log('info', 'Souscription existante — unsubscribe…');
                return existing.unsubscribe();
            }
        }).then(function() {
            log('info', 'Appel pushManager.subscribe()…');
            return registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(VAPID)
            });
        }).then(function(sub) {
            if (done) return;
            done = true;
            clearTimeout(timer);
            log('ok', 'pushManager.subscribe() OK — endpoint: ' + sub.endpoint.substring(0, 50) + '…');
            resolve(sub);
        }).catch(function(err) {
            if (done) return;
            done = true;
            clearTimeout(timer);
            log('fail', 'pushManager.subscribe() erreur : ' + (err && err.message ? err.message : err));
            resolve();
        });
    });
}

/* ---- Test FCM Registration API ---- */
function testFcmRegistrationApi() {
    log('hdr', 'Test FCM Registration API (réseau)');
    return fetch('https://fcmregistrations.googleapis.com/v1/projects/' + cfg.projectId + '/registrations', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'x-goog-api-key': cfg.apiKey,
        },
        body: '{}',
    }).then(function(r) {
        if (r.status === 400 || r.status === 401 || r.status === 403 || r.status === 200) {
            log('ok', 'FCM Registration API accessible : HTTP ' + r.status + ' (endpoint joignable)');
        } else {
            log('warn', 'FCM Registration API : HTTP ' + r.status);
        }
    }).catch(function(err) {
        log('fail', 'FCM Registration API : erreur réseau — ' + err.message + ' (endpoint bloqué ?)');
    });
}

/* ---- Tests de base ---- */
function runBasicChecks() {
    log('hdr', 'Vérifications de base');
    log(window.isSecureContext ? 'ok' : 'fail', 'Contexte sécurisé : ' + window.isSecureContext);
    log('Notification' in window ? 'ok' : 'fail', 'Notification API disponible : ' + ('Notification' in window));
    log('serviceWorker' in navigator ? 'ok' : 'fail', 'ServiceWorker disponible');
    log('PushManager' in window ? 'ok' : 'fail', 'PushManager disponible');
    log('info', 'Permission notifications : ' + (typeof Notification !== 'undefined' ? Notification.permission : 'N/A'));
    log('info', 'apiKey (12 car.) : ' + cfg.apiKey.substring(0,12) + '…');
    log('info', 'projectId : ' + cfg.projectId);
    log('info', 'VAPID (20 car.) : ' + VAPID.substring(0,20) + '…');
    log('info', 'VAPID longueur : ' + VAPID.length + ' chars');
}

/* ---- SW list ---- */
function listSW() {
    return navigator.serviceWorker.getRegistrations().then(function(regs) {
        log('hdr', 'Service Workers actifs (' + regs.length + ')');
        regs.forEach(function(reg) {
            var script = (reg.active || reg.installing || reg.waiting || {}).scriptURL || '?';
            log('info', script + ' scope=' + reg.scope);
        });
        return regs;
    });
}

/* ---- getToken ---- */
function doGetToken() {
    log('hdr', 'getToken FCM (nettoyage IDB + timeout 25s)');

    /* 1. Vider l'IDB Firebase pour repartir proprement */
    return clearFirebaseIDB().then(function() {
        /* 2. Désinscrire tous les SW */
        return navigator.serviceWorker.getRegistrations().then(function(regs) {
            return Promise.all(regs.map(function(r) { return r.unregister(); }));
        });
    }).then(function() {
        /* 3. Initialiser Firebase */
        try {
            firebase.app(); /* déjà initialisée ? */
            log('info', 'Firebase déjà initialisée (app existante)');
        } catch(e) {
            firebase.initializeApp(cfg);
            log('ok', 'Firebase app initialisée');
        }
        var messaging = firebase.messaging();

        /* 4. Enregistrer le SW */
        log('info', 'Enregistrement SW…');
        return navigator.serviceWorker.register('/firebase-messaging-sw.js', { scope: '/' })
            .then(function(reg) {
                log('ok', 'SW enregistré scope=' + reg.scope);
                return new Promise(function(resolve) {
                    /* Attendre que le SW soit actif */
                    if (reg.active) { resolve(reg); return; }
                    var sw = reg.installing || reg.waiting;
                    if (!sw) { resolve(reg); return; }
                    sw.addEventListener('statechange', function() {
                        if (sw.state === 'activated') resolve(reg);
                    });
                    setTimeout(function() { resolve(reg); }, 5000);
                });
            })
            .then(function(reg) {
                log('ok', 'SW actif : ' + (reg.active||{}).scriptURL);

                /* 5. Vérifier que push s'abonne bien directement */
                log('info', 'Vérification push subscription directe…');
                return reg.pushManager.getSubscription().then(function(sub) {
                    if (sub) { log('info', 'Souscription existante — unsubscribe…'); return sub.unsubscribe(); }
                }).then(function() {
                    return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlBase64ToUint8Array(VAPID) });
                }).then(function(sub) {
                    log('ok', 'Push subscription OK : ' + sub.endpoint.substring(0,50) + '…');
                    return sub.unsubscribe(); /* nettoyer pour que Firebase en crée une nouvelle */
                }).then(function() {
                    return reg;
                });
            })
            .then(function(reg) {
                /* 6. Appel getToken Firebase */
                log('info', 'Appel messaging.getToken() — timeout 25s…');
                var start = Date.now();
                var tokenPromise = messaging.getToken({ vapidKey: VAPID, serviceWorkerRegistration: reg });
                var timeout = new Promise(function(_, reject) {
                    setTimeout(function() { reject(new Error('TIMEOUT 25s — Firebase SDK bloqué (vérifiez Réseau F12)')); }, 25000);
                });
                return Promise.race([tokenPromise, timeout]).then(function(token) {
                    var elapsed = ((Date.now()-start)/1000).toFixed(1);
                    if (!token) {
                        log('fail', 'getToken retourné null (pas de token)');
                        return;
                    }
                    log('ok', '✓ TOKEN FCM obtenu en ' + elapsed + 's');
                    log('ok', token.substring(0, 40) + '…');
                    log('info', 'Copiez ce token et enregistrez-le via POST /api/save_fcm_token.php');
                });
            });
    }).catch(function(err) {
        log('fail', 'getToken : ' + (err && err.message ? err.message : String(err)));
        if (err && err.message && err.message.includes('TIMEOUT')) {
            log('warn', '→ Ouvrez F12 > Réseau, cherchez une requête bloquée vers:');
            log('warn', '  firebaseinstallations.googleapis.com');
            log('warn', '  fcmregistrations.googleapis.com');
        }
    });
}

/* ---- Test clé API personnalisée ---- */
function testCustomKey(key) {
    var resultEl = document.getElementById('key-test-result');
    resultEl.style.color = '#aaa';
    resultEl.textContent = 'Test en cours…';
    var url = 'https://firebaseinstallations.googleapis.com/v1/projects/' + cfg.projectId + '/installations';
    return fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'x-goog-api-key': key,
        },
        body: JSON.stringify({
            fid: 'c8d8e8f0a1b2c3d4e5f6789012345678',
            authVersion: 'FIS_v2',
            appId: cfg.appId,
            sdkVersion: 'w:12.9.0',
        }),
    }).then(function(r) {
        return r.text().then(function(body) {
            if (r.status === 200 || r.status === 201 || r.status === 409) {
                resultEl.style.color = '#4f4';
                resultEl.textContent = '✓ Clé valide (HTTP ' + r.status + ') — copiez-la dans config/firebase_config.php';
                log('ok', 'Clé testée valide : ' + key.substring(0,12) + '… (HTTP ' + r.status + ')');
            } else {
                resultEl.style.color = '#f44';
                resultEl.textContent = '✗ Clé invalide (HTTP ' + r.status + ') — ' + body.substring(0, 150);
                log('fail', 'Clé invalide : ' + key.substring(0,12) + '… → HTTP ' + r.status);
            }
        });
    }).catch(function(err) {
        resultEl.style.color = '#f44';
        resultEl.textContent = '✗ Erreur réseau : ' + err.message;
    });
}

document.getElementById('btn-test-key').addEventListener('click', function() {
    testCustomKey(document.getElementById('input-apikey').value.trim());
});
document.getElementById('btn-apply-key').addEventListener('click', function() {
    cfg.apiKey = document.getElementById('input-apikey').value.trim();
    log('warn', 'apiKey temporairement remplacée par : ' + cfg.apiKey.substring(0,12) + '…');
    log('info', 'Cliquez "🔑 Obtenir token FCM" pour tester, puis mettez à jour config/firebase_config.php');
});

/* ---- Boutons ---- */
document.getElementById('btn-clear-idb').addEventListener('click', function() {
    log('hdr', 'Nettoyage IndexedDB Firebase');
    clearFirebaseIDB().then(function() { log('ok', 'Nettoyage terminé — rechargez la page'); });
});
document.getElementById('btn-unregister-sw').addEventListener('click', function() {
    unregisterAllSW();
});
document.getElementById('btn-get-token').addEventListener('click', function() {
    doGetToken();
});
document.getElementById('btn-run-all').addEventListener('click', function() {
    logEl.innerHTML = '';
    runBasicChecks();
    testInstallationsApi()
        .then(testFcmRegistrationApi)
        .then(listSW)
        .then(function() {
            log('hdr', 'IndexedDB Firebase');
            return new Promise(function(resolve) {
                var req = indexedDB.open('firebase-installations-database');
                req.onsuccess = function(e) {
                    var db = e.target.result;
                    log('warn', 'firebase-installations-database existe (version ' + db.version + ') — données ancien projet potentiellement présentes');
                    db.close();
                    resolve();
                };
                req.onerror = function() { log('ok', 'Pas de firebase-installations-database'); resolve(); };
            });
        })
        .then(function() {
            /* Test pushManager directement — révèle si c'est le bloc Push qui coince */
            return navigator.serviceWorker.register('/firebase-messaging-sw.js', { scope: '/' })
                .then(function(reg) { return navigator.serviceWorker.ready.then(function(r) { return r; }); })
                .then(function(reg) { return testPushSubscribe(reg); });
        });
});
</script>
</body>
</html>
