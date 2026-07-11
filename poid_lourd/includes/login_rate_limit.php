<?php
/**
 * Limitation des tentatives de connexion par identifiant (email ou téléphone).
 * - 1er cycle : 7 échecs → blocage 15 min ; avertissement à partir du 4e échec
 * - Après déblocage : 2 échecs → blocage 30 min, puis 60 min, etc. (durée ×2)
 */

if (!defined('LOGIN_RATE_MAX_ATTEMPTS')) {
    define('LOGIN_RATE_MAX_ATTEMPTS', 7);
}
if (!defined('LOGIN_RATE_WARN_AFTER')) {
    define('LOGIN_RATE_WARN_AFTER', 4);
}
if (!defined('LOGIN_RATE_SUBSEQUENT_MAX_ATTEMPTS')) {
    define('LOGIN_RATE_SUBSEQUENT_MAX_ATTEMPTS', 2);
}
if (!defined('LOGIN_RATE_LOCK_SECONDS')) {
    define('LOGIN_RATE_LOCK_SECONDS', 900);
}
if (!defined('LOGIN_RATE_MAX_LOCKOUT_LEVEL')) {
    define('LOGIN_RATE_MAX_LOCKOUT_LEVEL', 10);
}

function login_attempt_normalize_identifier($raw)
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return '';
    }
    if (strpos($raw, 'email:') === 0 || strpos($raw, 'phone:') === 0) {
        return $raw;
    }
    if (strpos($raw, '@') !== false) {
        $email = strtolower($raw);
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? 'email:' . $email : '';
    }
    $digits = preg_replace('/\D/', '', $raw);
    return $digits !== '' ? 'phone:' . $digits : '';
}

/**
 * Identifiant lié à la requête courante (email ou téléphone soumis).
 */
function login_attempt_current_identifier()
{
    return isset($_SESSION['login_rate_identifier'])
        ? (string) $_SESSION['login_rate_identifier']
        : '';
}

function login_attempt_bind_identifier($identifier)
{
    $normalized = login_attempt_normalize_identifier($identifier);
    if ($normalized === '') {
        return;
    }
    $_SESSION['login_rate_identifier'] = $normalized;
    login_attempt_load_state_to_session();
}

/**
 * Extrait l'identifiant depuis le POST de connexion.
 */
function login_attempt_extract_identifier_from_post()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return '';
    }
    $mode = isset($_POST['login_mode']) ? trim((string) $_POST['login_mode']) : 'email';
    if ($mode === 'phone') {
        $tel = isset($_POST['telephone']) ? trim((string) $_POST['telephone']) : '';
        return login_attempt_normalize_identifier($tel);
    }
    $email = isset($_POST['email']) ? trim((string) $_POST['email']) : '';
    return login_attempt_normalize_identifier($email);
}

/**
 * Clé de persistance (empreinte identifiant).
 */
function login_attempt_client_key($identifier = null)
{
    if ($identifier === null) {
        $identifier = login_attempt_current_identifier();
    } else {
        $identifier = login_attempt_normalize_identifier($identifier);
    }
    if ($identifier === '') {
        return '';
    }
    $salt = 'colobanes_login_rate_v2';
    $secret_file = __DIR__ . '/../config/login_rate_secret.php';
    if (is_file($secret_file)) {
        $cfg = require $secret_file;
        if (is_array($cfg) && !empty($cfg['ip_salt'])) {
            $salt = (string) $cfg['ip_salt'];
        }
    }
    return hash('sha256', $identifier . '|' . $salt);
}

function login_attempt_db_ensure_table()
{
    static $ok = null;
    if ($ok === true) {
        return true;
    }
    global $db;
    if (!isset($db) || !($db instanceof PDO)) {
        if (file_exists(__DIR__ . '/../conn/conn.php')) {
            require_once __DIR__ . '/../conn/conn.php';
        }
    }
    if (!isset($db) || !($db instanceof PDO)) {
        $ok = false;
        return false;
    }
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS `login_rate_limit_ip` (
            `client_key` char(64) NOT NULL,
            `fail_count` int(10) unsigned NOT NULL DEFAULT 0,
            `lockout_level` int(10) unsigned NOT NULL DEFAULT 0,
            `lock_until` int(10) unsigned NOT NULL DEFAULT 0,
            `lock_duration` int(10) unsigned NOT NULL DEFAULT 0,
            `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`client_key`),
            KEY `idx_lock_until` (`lock_until`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $ok = true;
        return true;
    } catch (PDOException $e) {
        $ok = false;
        return false;
    }
}

/**
 * @return array{fail_count:int,lockout_level:int,lock_until:int,lock_duration:int}|null
 */
function login_attempt_db_load()
{
    $key = login_attempt_client_key();
    if ($key === '') {
        return null;
    }
    if (!login_attempt_db_ensure_table()) {
        return null;
    }
    global $db;
    try {
        $stmt = $db->prepare('
            SELECT fail_count, lockout_level, lock_until, lock_duration
            FROM login_rate_limit_ip
            WHERE client_key = :k
            LIMIT 1
        ');
        $stmt->execute(['k' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        return [
            'fail_count' => (int) ($row['fail_count'] ?? 0),
            'lockout_level' => (int) ($row['lockout_level'] ?? 0),
            'lock_until' => (int) ($row['lock_until'] ?? 0),
            'lock_duration' => (int) ($row['lock_duration'] ?? 0),
        ];
    } catch (PDOException $e) {
        return null;
    }
}

function login_attempt_db_save()
{
    $key = login_attempt_client_key();
    if ($key === '') {
        return false;
    }
    if (!login_attempt_db_ensure_table()) {
        return false;
    }
    global $db;
    try {
        $stmt = $db->prepare('
            INSERT INTO login_rate_limit_ip (client_key, fail_count, lockout_level, lock_until, lock_duration, updated_at)
            VALUES (:k, :fc, :lv, :lu, :ld, NOW())
            ON DUPLICATE KEY UPDATE
                fail_count = VALUES(fail_count),
                lockout_level = VALUES(lockout_level),
                lock_until = VALUES(lock_until),
                lock_duration = VALUES(lock_duration),
                updated_at = NOW()
        ');
        return $stmt->execute([
            'k' => $key,
            'fc' => login_attempt_fail_count_raw(),
            'lv' => (int) ($_SESSION['login_lockout_level'] ?? 0),
            'lu' => (int) ($_SESSION['login_lock_until'] ?? 0),
            'ld' => (int) ($_SESSION['login_lock_duration'] ?? 0),
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

function login_attempt_db_clear()
{
    $key = login_attempt_client_key();
    if ($key === '') {
        return false;
    }
    if (!login_attempt_db_ensure_table()) {
        return false;
    }
    global $db;
    try {
        $stmt = $db->prepare('DELETE FROM login_rate_limit_ip WHERE client_key = :k');
        return $stmt->execute(['k' => $key]);
    } catch (PDOException $e) {
        return false;
    }
}

function login_attempt_load_state_to_session()
{
    if (login_attempt_current_identifier() === '') {
        return;
    }
    $row = login_attempt_db_load();
    if ($row === null) {
        unset(
            $_SESSION['login_fail_count'],
            $_SESSION['login_lock_until'],
            $_SESSION['login_lockout_level'],
            $_SESSION['login_lock_duration']
        );
        return;
    }
    $now = time();
    $db_lock = (int) $row['lock_until'];
    if ($db_lock > $now) {
        $_SESSION['login_lock_until'] = $db_lock;
        $_SESSION['login_lock_duration'] = (int) $row['lock_duration'];
        $_SESSION['login_lockout_level'] = (int) $row['lockout_level'];
        $_SESSION['login_fail_count'] = 0;
        return;
    }
    unset($_SESSION['login_lock_until'], $_SESSION['login_lock_duration']);
    $_SESSION['login_fail_count'] = (int) $row['fail_count'];
    $_SESSION['login_lockout_level'] = (int) $row['lockout_level'];
}

function login_attempt_fail_count_raw()
{
    return (int) ($_SESSION['login_fail_count'] ?? 0);
}

function login_attempt_lock_duration_for_level($level)
{
    $level = max(0, min((int) LOGIN_RATE_MAX_LOCKOUT_LEVEL, (int) $level));
    $mult = 1 << $level;
    $duration = (int) LOGIN_RATE_LOCK_SECONDS * $mult;
    return min($duration, 86400 * 7);
}

function login_attempt_max_before_lock()
{
    $level = (int) ($_SESSION['login_lockout_level'] ?? 0);
    return $level === 0 ? (int) LOGIN_RATE_MAX_ATTEMPTS : (int) LOGIN_RATE_SUBSEQUENT_MAX_ATTEMPTS;
}

function login_attempt_lockout_level()
{
    login_attempt_unlock_if_expired();
    return (int) ($_SESSION['login_lockout_level'] ?? 0);
}

function login_attempt_unlock_if_expired()
{
    if (login_attempt_current_identifier() === '') {
        return;
    }
    login_attempt_load_state_to_session();

    if (empty($_SESSION['login_lock_until'])) {
        login_attempt_db_save();
        return;
    }
    if (time() >= (int) $_SESSION['login_lock_until']) {
        unset(
            $_SESSION['login_lock_until'],
            $_SESSION['login_fail_count'],
            $_SESSION['login_lock_duration']
        );
        login_attempt_db_save();
    }
}

function login_attempt_is_locked()
{
    if (login_attempt_current_identifier() === '') {
        return false;
    }
    login_attempt_unlock_if_expired();
    if (empty($_SESSION['login_lock_until'])) {
        return false;
    }
    return time() < (int) $_SESSION['login_lock_until'];
}

function login_attempt_remaining_seconds()
{
    if (login_attempt_current_identifier() === '') {
        return 0;
    }
    login_attempt_unlock_if_expired();
    if (empty($_SESSION['login_lock_until'])) {
        return 0;
    }
    return max(0, (int) $_SESSION['login_lock_until'] - time());
}

function login_attempt_fail_count()
{
    if (login_attempt_current_identifier() === '') {
        return 0;
    }
    login_attempt_unlock_if_expired();
    return login_attempt_fail_count_raw();
}

function login_attempt_remaining_before_lock()
{
    if (login_attempt_is_locked()) {
        return 0;
    }
    return max(0, login_attempt_max_before_lock() - login_attempt_fail_count());
}

function login_attempt_show_warning()
{
    if (login_attempt_current_identifier() === '') {
        return false;
    }
    if (login_attempt_is_locked()) {
        return false;
    }
    return login_attempt_fail_count() >= (int) LOGIN_RATE_WARN_AFTER;
}

function login_attempt_active_lock_duration_seconds()
{
    if (!login_attempt_is_locked()) {
        return 0;
    }
    $stored = (int) ($_SESSION['login_lock_duration'] ?? 0);
    if ($stored > 0) {
        return $stored;
    }
    $level = max(0, login_attempt_lockout_level() - 1);
    return login_attempt_lock_duration_for_level($level);
}

function login_attempt_clear()
{
    unset(
        $_SESSION['login_fail_count'],
        $_SESSION['login_lock_until'],
        $_SESSION['login_lockout_level'],
        $_SESSION['login_lock_duration']
    );
    login_attempt_db_clear();
}

function login_attempt_register_failure()
{
    if (login_attempt_current_identifier() === '') {
        return;
    }
    login_attempt_unlock_if_expired();
    if (login_attempt_is_locked()) {
        return;
    }
    $n = login_attempt_fail_count_raw() + 1;
    $_SESSION['login_fail_count'] = $n;
    $max = login_attempt_max_before_lock();
    if ($n >= $max) {
        $level = (int) ($_SESSION['login_lockout_level'] ?? 0);
        $duration = login_attempt_lock_duration_for_level($level);
        $_SESSION['login_lock_until'] = time() + $duration;
        $_SESSION['login_lock_duration'] = $duration;
        $_SESSION['login_lockout_level'] = $level + 1;
        $_SESSION['login_fail_count'] = 0;
    }
    login_attempt_db_save();
}

function login_attempt_format_remaining($seconds)
{
    $seconds = max(0, (int) $seconds);
    $m = intdiv($seconds, 60);
    $s = $seconds % 60;
    if ($m <= 0) {
        return $s . ' s';
    }
    if ($s === 0) {
        return $m . ' min';
    }
    return $m . ' min ' . str_pad((string) $s, 2, '0', STR_PAD_LEFT) . ' s';
}

function login_attempt_warning_message()
{
    if (!login_attempt_show_warning()) {
        return '';
    }
    $rem = login_attempt_remaining_before_lock();
    if ($rem <= 0) {
        return '';
    }
    $label = $rem > 1 ? 'tentatives' : 'tentative';
    return 'Attention : il vous reste ' . $rem . ' ' . $label . ' avant un blocage temporaire de la connexion.';
}

function login_safe_html_message($message)
{
    $message = trim((string) $message);
    if ($message === '') {
        return '';
    }
    $parts = preg_split('/<br\s*\/?>/i', $message) ?: [];
    $safe = [];
    foreach ($parts as $part) {
        $part = trim((string) $part);
        if ($part === '') {
            continue;
        }
        $safe[] = htmlspecialchars($part, ENT_QUOTES, 'UTF-8');
    }
    return implode('<br>', $safe);
}

function login_attempt_locked_result_array()
{
    $rem = login_attempt_remaining_seconds();
    return [
        'success' => false,
        'message' => 'Trop de tentatives de connexion incorrectes. Réessayez dans ' . login_attempt_format_remaining($rem) . '.',
        'type' => null,
        'admin' => null,
        'user' => null,
        'vendeur_collaborateur' => null,
        'rate_limited' => true,
        'remaining_seconds' => $rem,
    ];
}

function login_failure_result_array($message)
{
    login_attempt_register_failure();
    if (login_attempt_is_locked()) {
        return login_attempt_locked_result_array();
    }
    return [
        'success' => false,
        'message' => $message,
        'type' => null,
        'admin' => null,
        'user' => null,
        'vendeur_collaborateur' => null,
    ];
}
