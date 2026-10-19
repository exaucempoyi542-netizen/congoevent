<?php
/**
 * Routeur pour le serveur PHP intégré (Railway).
 * Sert les fichiers existants, sinon renvoie 404.
 */
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$file = __DIR__ . $uri;

if ($uri !== '/' && is_file($file)) {
    return false; // laisser PHP servir le fichier statique / script
}

if ($uri === '/' || $uri === '') {
    require __DIR__ . '/index.php';
    return true;
}

http_response_code(404);
echo '404 Not Found';
return true;
