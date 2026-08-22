<?php
/**
 * Router untuk PHP built-in server:
 *   php -S 0.0.0.0:8000 -t /var/www/opi-dashboard router.php
 * Blokir akses web langsung ke folder data/ (berisi hash kredensial, voucher, member).
 */
$uri = urldecode((string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'));

if (preg_match('#^/data(/|$)#', $uri)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Forbidden');
}

// File/folder yang benar-benar ada disajikan apa adanya (.php tetap dieksekusi server)
$path = __DIR__ . $uri;
if ($uri !== '/' && (is_file($path) || is_dir($path))) {
    return false;
}

require __DIR__ . '/index.php';
