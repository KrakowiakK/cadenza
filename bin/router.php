<?php

// Router for PHP's built-in server: serve existing files (media, built assets), send the rest to Symfony.
$path = (string) parse_url($_SERVER['REQUEST_URI'], \PHP_URL_PATH);
$root = (string) realpath($_SERVER['DOCUMENT_ROOT']);
$file = realpath($root.rawurldecode($path));
if (false !== $file && is_file($file) && str_starts_with($file, $root.\DIRECTORY_SEPARATOR)) {
    // Built assets are text and carry a hash in their name: the built-in server would send them uncompressed and
    // uncached, so they are sent from here, compressed, and kept by the browser for good.
    $types = ['css' => 'text/css; charset=UTF-8', 'js' => 'text/javascript; charset=UTF-8', 'json' => 'application/json', 'svg' => 'image/svg+xml', 'map' => 'application/json'];
    $type = $types[strtolower(pathinfo($file, \PATHINFO_EXTENSION))] ?? null;
    if (null === $type || !str_starts_with($path, '/assets/')) {
        return false;
    }
    header('Content-Type: '.$type);
    header('X-Content-Type-Options: nosniff');
    header('Vary: Accept-Encoding');
    header(1 === preg_match('/-[A-Za-z0-9_-]{7,}\.[a-z]+$/', $file) ? 'Cache-Control: public, max-age=31536000, immutable' : 'Cache-Control: public, max-age=300');
    $body = (string) file_get_contents($file);
    if (\function_exists('gzencode') && str_contains((string) ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''), 'gzip')) {
        header('Content-Encoding: gzip');
        $body = (string) gzencode($body, 6);
    }
    header('Content-Length: '.\strlen($body));
    echo $body;

    return true;
}
$_SERVER['SCRIPT_FILENAME'] = $_SERVER['DOCUMENT_ROOT'].'/index.php';
require $_SERVER['SCRIPT_FILENAME'];
