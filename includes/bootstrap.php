<?php
/**
 * Bootstrap: loaded at the top of every page.
 * Loads configuration, sends security headers, starts a hardened session.
 */
declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('SESSION_IDLE_SECONDS', 1800); // log out after 30 minutes of inactivity

$configFile = ROOT_PATH . '/config/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Configuration missing: copy config/config.sample.php to config/config.php and fill in your database details.');
}
$GLOBALS['app_config'] = require $configFile;

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/products.php';

date_default_timezone_set((string) config('app.timezone', 'Asia/Dhaka'));

// Never show raw PHP errors to visitors unless debugging.
error_reporting(E_ALL);
ini_set('display_errors', config('app.debug', false) ? '1' : '0');
ini_set('log_errors', '1');

// ---- Security headers -------------------------------------------------------
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
if (config('app.csp', true)) {
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; "
        . "style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; "
        . "script-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
}

// ---- Session ----------------------------------------------------------------
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('SHELFWISE_SID');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $isHttps,
    'httponly' => true,      // JavaScript cannot read the session cookie
    'samesite' => 'Lax',     // basic CSRF defence for cross-site requests
]);
session_start();
