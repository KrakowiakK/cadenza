<?php

// Router for PHP's built-in server: serve existing files (media, built assets), send the rest to Symfony.
$file = $_SERVER['DOCUMENT_ROOT'].parse_url($_SERVER['REQUEST_URI'], \PHP_URL_PATH);
if (is_file($file)) {
    return false;
}
$_SERVER['SCRIPT_FILENAME'] = $_SERVER['DOCUMENT_ROOT'].'/index.php';
require $_SERVER['SCRIPT_FILENAME'];
