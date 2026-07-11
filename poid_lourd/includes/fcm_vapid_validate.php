<?php
/**
 * Valide une clé VAPID publique (Web Push).
 * Retourne ['valid' => bool, 'bytes' => int, 'chars' => int, 'message' => string]
 */
function fcm_validate_vapid_key($key) {
    $key = trim((string) $key);
    $chars = strlen($key);

    if ($key === '') {
        return ['valid' => false, 'bytes' => 0, 'chars' => 0, 'message' => 'Clé VAPID vide'];
    }

    if (!preg_match('/^[A-Za-z0-9_-]+$/', $key)) {
        return ['valid' => false, 'bytes' => 0, 'chars' => $chars, 'message' => 'Caractères invalides dans la clé VAPID'];
    }

    $padding = str_repeat('=', (4 - $chars % 4) % 4);
    $decoded = base64_decode(strtr($key . $padding, '-_', '+/'), true);
    $bytes = $decoded !== false ? strlen($decoded) : 0;

    if ($bytes !== 65) {
        return [
            'valid' => false,
            'bytes' => $bytes,
            'chars' => $chars,
            'message' => "Clé VAPID invalide : {$chars} caractères, {$bytes} octets décodés (attendu : 87 car. / 65 octets). "
                . 'Recopiez la clé avec le bouton Copier dans Firebase Console > Cloud Messaging > Web Push certificates, '
                . 'ou générez une nouvelle paire de clés.',
        ];
    }

    if (ord($decoded[0]) !== 4) {
        return [
            'valid' => false,
            'bytes' => $bytes,
            'chars' => $chars,
            'message' => 'Clé VAPID : format EC P-256 invalide (premier octet != 0x04)',
        ];
    }

    return [
        'valid' => true,
        'bytes' => $bytes,
        'chars' => $chars,
        'message' => 'Clé VAPID valide',
    ];
}
