<?php
/**
 * Configuration mise à jour obligatoire — apps mobiles Android / iOS.
 */

if (!function_exists('app_mobile_version_config_path')) {
    function app_mobile_version_config_path(): string
    {
        return dirname(__DIR__) . '/config/app_mobile_version.json';
    }
}

if (!function_exists('app_mobile_version_defaults')) {
    /**
     * @return array<string, mixed>
     */
    function app_mobile_version_defaults(): array
    {
        return [
            'force_update' => false,
            'android' => [
                'min_build' => 0,
                'min_version' => '1.0.0',
            ],
            'ios' => [
                'min_build' => 0,
                'min_version' => '1.0.0',
            ],
            'store_android' => 'https://play.google.com/store/apps/details?id=com.colobanes.app',
            'store_ios' => 'https://apps.apple.com/us/app/colobanes/id6771130832',
            'title' => 'Mise à jour requise',
            'message' => 'Une nouvelle version de COLObanes est disponible. Veuillez mettre à jour l\'application pour continuer.',
        ];
    }
}

if (!function_exists('app_mobile_version_load')) {
    /**
     * @return array<string, mixed>
     */
    function app_mobile_version_load(): array
    {
        $defaults = app_mobile_version_defaults();
        $path = app_mobile_version_config_path();
        if (!is_file($path)) {
            return $defaults;
        }
        $raw = file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return $defaults;
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return $defaults;
        }

        $out = $defaults;
        $out['force_update'] = !empty($data['force_update']);
        foreach (['android', 'ios'] as $platform) {
            if (!empty($data[$platform]) && is_array($data[$platform])) {
                $out[$platform]['min_build'] = max(0, (int) ($data[$platform]['min_build'] ?? 0));
                $ver = trim((string) ($data[$platform]['min_version'] ?? ''));
                if ($ver !== '') {
                    $out[$platform]['min_version'] = $ver;
                }
            }
        }
        foreach (['store_android', 'store_ios', 'title', 'message'] as $key) {
            $val = trim((string) ($data[$key] ?? ''));
            if ($val !== '') {
                $out[$key] = $val;
            }
        }
        return $out;
    }
}

if (!function_exists('app_mobile_version_save')) {
    /**
     * @param array<string, mixed> $input
     */
    function app_mobile_version_save(array $input): bool
    {
        $defaults = app_mobile_version_defaults();
        $payload = app_mobile_version_defaults();
        $payload['force_update'] = !empty($input['force_update']);
        $payload['android']['min_build'] = max(0, (int) ($input['android_min_build'] ?? 0));
        $payload['ios']['min_build'] = max(0, (int) ($input['ios_min_build'] ?? 0));
        $payload['android']['min_version'] = trim((string) ($input['android_min_version'] ?? $defaults['android']['min_version']));
        $payload['ios']['min_version'] = trim((string) ($input['ios_min_version'] ?? $defaults['ios']['min_version']));
        $payload['store_android'] = trim((string) ($input['store_android'] ?? $defaults['store_android']));
        $payload['store_ios'] = trim((string) ($input['store_ios'] ?? $defaults['store_ios']));
        $payload['title'] = trim((string) ($input['title'] ?? $defaults['title']));
        $payload['message'] = trim((string) ($input['message'] ?? $defaults['message']));

        if ($payload['title'] === '' || $payload['message'] === '') {
            return false;
        }
        if ($payload['store_android'] === '' || $payload['store_ios'] === '') {
            return false;
        }

        $path = app_mobile_version_config_path();
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return false;
        }

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }
        return file_put_contents($path, $json . "\n", LOCK_EX) !== false;
    }
}

if (!function_exists('app_mobile_version_normalize_platform')) {
    function app_mobile_version_normalize_platform(string $platform): string
    {
        $platform = strtolower(trim($platform));
        if ($platform === 'android') {
            return 'android';
        }
        if ($platform === 'ios' || $platform === 'iphone' || $platform === 'ipad') {
            return 'ios';
        }
        return '';
    }
}

if (!function_exists('app_mobile_version_check')) {
    /**
     * @return array<string, mixed>
     */
    function app_mobile_version_check(string $platform, int $installed_build): array
    {
        $cfg = app_mobile_version_load();
        $platform = app_mobile_version_normalize_platform($platform);
        $installed_build = max(0, $installed_build);

        $platform_cfg = ($platform === 'ios') ? $cfg['ios'] : $cfg['android'];
        $min_build = (int) ($platform_cfg['min_build'] ?? 0);
        $min_version = (string) ($platform_cfg['min_version'] ?? '1.0.0');
        $force = !empty($cfg['force_update']);
        $store_url = ($platform === 'ios')
            ? (string) $cfg['store_ios']
            : (string) $cfg['store_android'];

        $update_required = $force && $min_build > 0 && $installed_build < $min_build;

        return [
            'platform' => $platform !== '' ? $platform : 'android',
            'force_update' => $force,
            'min_build' => $min_build,
            'min_version' => $min_version,
            'installed_build' => $installed_build,
            'update_required' => $update_required,
            'store_url' => $store_url,
            'title' => (string) $cfg['title'],
            'message' => (string) $cfg['message'],
        ];
    }
}

if (!function_exists('app_mobile_version_public_payload')) {
    /**
     * Payload API sans build installé (consultation admin / app).
     *
     * @return array<string, mixed>
     */
    function app_mobile_version_public_payload(): array
    {
        $cfg = app_mobile_version_load();
        return [
            'force_update' => !empty($cfg['force_update']),
            'android' => $cfg['android'],
            'ios' => $cfg['ios'],
            'store_android' => (string) $cfg['store_android'],
            'store_ios' => (string) $cfg['store_ios'],
            'title' => (string) $cfg['title'],
            'message' => (string) $cfg['message'],
        ];
    }
}
