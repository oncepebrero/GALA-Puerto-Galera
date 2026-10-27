<?php
declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
$GLOBALS['GALA'] = require ROOT_PATH . '/config/config.php';

if ($GLOBALS['GALA']['debug']) {
    ini_set('display_errors', '1'); error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0'); error_reporting(E_ALL);
    ini_set('log_errors', '1');
}
mb_internal_encoding('UTF-8');
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/feedback_lib.php';
require_once __DIR__ . '/reviews_ui.php';

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => $secure]);
session_name('gala_sid');
if (session_status() === PHP_SESSION_NONE) session_start();

/* Security headers. The CSP allows only what the site really uses:
   Google Maps embeds, and its own scripts, fonts and images. */
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; font-src 'self'; img-src 'self' data:; frame-src https://www.google.com https://maps.google.com; connect-src 'self'; form-action 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'");
    header('Content-Type: text/html; charset=utf-8');
}

/** True when the visitor is on the same computer as the server (Laragon on your own PC). */
function gala_is_local(): bool { return in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true); }

function gala_log(string $line): void
{
    $dir = ROOT_PATH . '/logs';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    if (is_dir($dir) && !is_file($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Require all denied\n");
    @file_put_contents($dir . '/error.log', '[' . date('Y-m-d H:i:s') . '] ' . $line . "\n", FILE_APPEND | LOCK_EX);
}

set_exception_handler(function (Throwable $t) {
    error_log('[GALA] ' . $t);
    gala_log(get_class($t) . ': ' . $t->getMessage() . ' in ' . basename($t->getFile()) . ':' . $t->getLine() . ' (' . ($_SERVER['REQUEST_URI'] ?? '') . ')');
    if (!headers_sent()) http_response_code(500);
    $show = $GLOBALS['GALA']['debug'] || gala_is_local();      // technical details only on your own computer
    $dbg = $show ? '<pre style="white-space:pre-wrap;background:#f4f6f8;padding:1rem;border-radius:12px;font-size:14px">' . htmlspecialchars(get_class($t) . ': ' . $t->getMessage() . "\n" . basename($t->getFile()) . ' line ' . $t->getLine(), ENT_QUOTES) . '</pre>' : '';
    $fix = (str_contains($_SERVER['REQUEST_URI'] ?? '', '/admin') && $show) ? '<p><a href="' . htmlspecialchars(url('admin/repair.php'), ENT_QUOTES) . '">Open the database checkup and repair tool</a></p>' : '';
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Something went wrong</title>'
       . '<body style="font:18px/1.6 system-ui;max-width:40rem;margin:10vh auto;padding:0 1.5rem"><h1>Something went wrong</h1>'
       . '<p>This page could not be loaded. Please try again in a moment.' . ($show ? '' : ' If you run this site, check <code>logs/error.log</code>.') . '</p>' . $dbg . $fix;
    exit;
});
