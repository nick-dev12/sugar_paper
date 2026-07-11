<?php
/**
 * Cache fichier des clés publiques Google (Firebase ID tokens).
 * Permet la connexion Google/Apple quand le VPS bloque les appels sortants vers googleapis.com.
 */
declare(strict_types=1);

use Kreait\Firebase\JWT\Action\FetchGooglePublicKeys;
use Kreait\Firebase\JWT\Action\FetchGooglePublicKeys\Handler;
use Kreait\Firebase\JWT\Contract\Expirable;
use Kreait\Firebase\JWT\Contract\Keys;
use Kreait\Firebase\JWT\Error\FetchingGooglePublicKeysFailed;
use Kreait\Firebase\JWT\Keys\ExpiringKeys;
use Psr\Clock\ClockInterface;

final class ColobanesFirebaseGoogleKeysFileHandler implements Handler
{
    private const CACHE_FILE = __DIR__ . '/../config/firebase_google_public_keys_cache.json';

    /** Utiliser le cache disque même expiré si le réseau est coupé (rotation Google ~ quelques jours). */
    private const STALE_GRACE_SECONDS = 604800;

    /** @var Handler */
    private $networkHandler;

    /** @var ClockInterface */
    private $clock;

    public function __construct(Handler $networkHandler, ClockInterface $clock)
    {
        $this->networkHandler = $networkHandler;
        $this->clock = $clock;
    }

    public function handle(FetchGooglePublicKeys $action): Keys
    {
        $now = $this->clock->now();
        $nowTs = $now->getTimestamp();
        $cached = $this->loadCacheFile();

        if ($cached !== null && $cached['expires_at'] > $nowTs) {
            return $this->keysFromCache($cached, $cached['expires_at']);
        }

        // Cache expiré mais encore utilisable : pas d'appel réseau pendant la connexion (cron : sync_firebase_google_keys_cache.php).
        if ($cached !== null && !empty($cached['keys'])) {
            $staleAge = $nowTs - (int) $cached['expires_at'];
            if ($staleAge >= 0 && $staleAge < self::STALE_GRACE_SECONDS) {
                $staleUntil = $nowTs + self::STALE_GRACE_SECONDS;
                $expires = max((int) $cached['expires_at'], $staleUntil);

                return $this->keysFromCache($cached, $expires);
            }
        }

        try {
            $keys = $this->networkHandler->handle($action);
            $this->persistKeys($keys);

            return $keys;
        } catch (FetchingGooglePublicKeysFailed $e) {
            if ($cached !== null && !empty($cached['keys'])) {
                $staleUntil = $nowTs + self::STALE_GRACE_SECONDS;
                $expires = max((int) $cached['expires_at'], $staleUntil);

                return $this->keysFromCache($cached, $expires);
            }
            throw $e;
        }
    }

    /**
     * @return array{keys: array<string, string>, expires_at: int}|null
     */
    private function loadCacheFile(): ?array
    {
        if (!is_readable(self::CACHE_FILE)) {
            return null;
        }

        $raw = file_get_contents(self::CACHE_FILE);
        if ($raw === false || $raw === '') {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['keys']) || !is_array($data['keys'])) {
            return null;
        }

        $keys = [];
        foreach ($data['keys'] as $kid => $pem) {
            if (!is_string($kid) || $kid === '' || !is_string($pem) || trim($pem) === '') {
                continue;
            }
            $keys[$kid] = trim($pem);
        }

        if ($keys === []) {
            return null;
        }

        return [
            'keys' => $keys,
            'expires_at' => (int) ($data['expires_at'] ?? 0),
        ];
    }

    private function persistKeys(Keys $keys): void
    {
        $all = $keys->all();
        if ($all === []) {
            return;
        }

        $expiresAt = time() + 3600;
        if ($keys instanceof Expirable) {
            $expiresAt = $keys->expiresAt()->getTimestamp();
        }

        $payload = [
            'keys' => $all,
            'expires_at' => $expiresAt,
            'updated_at' => date('c'),
        ];

        $dir = dirname(self::CACHE_FILE);
        if (!is_dir($dir)) {
            return;
        }

        file_put_contents(
            self::CACHE_FILE,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }

    /**
     * @param array{keys: array<string, string>, expires_at: int} $cached
     */
    private function keysFromCache(array $cached, int $expiresTimestamp): Keys
    {
        return ExpiringKeys::withValuesAndExpirationTime(
            $cached['keys'],
            (new \DateTimeImmutable())->setTimestamp($expiresTimestamp)
        );
    }
}
