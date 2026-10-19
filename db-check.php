<?php
/**
 * Diagnostic Railway — à supprimer après configuration.
 * Ouvre : https://TON-URL/db-check.php
 */
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$keys = [
    'MYSQLHOST', 'MYSQL_HOST', 'MYSQLPORT', 'MYSQL_PORT',
    'MYSQLUSER', 'MYSQL_USER', 'MYSQLPASSWORD', 'MYSQL_PASSWORD',
    'MYSQLDATABASE', 'MYSQL_DATABASE', 'MYSQL_URL', 'MYSQLURL', 'DATABASE_URL',
    'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER',
    'RAILWAY_ENVIRONMENT', 'RAILWAY_SERVICE_NAME',
];

echo "Congo Events — diagnostic DB\n";
echo str_repeat('=', 40) . "\n\n";

foreach ($keys as $k) {
    $v = $_ENV[$k] ?? $_SERVER[$k] ?? getenv($k);
    if ($v === false || $v === null || $v === '') {
        echo "$k = (absent)\n";
        continue;
    }
    if (stripos($k, 'PASS') !== false || stripos($k, 'URL') !== false) {
        echo "$k = (défini, " . strlen((string) $v) . " car.)\n";
    } else {
        echo "$k = $v\n";
    }
}

echo "\n";
require_once __DIR__ . '/config/database.php';
echo 'DB_HOST constant = ' . DB_HOST . "\n";
echo 'DB_PORT constant = ' . DB_PORT . "\n";
echo 'DB_NAME constant = ' . DB_NAME . "\n";
echo 'DB_USER constant = ' . DB_USER . "\n";
echo 'DB_PASS constant = ' . (DB_PASS !== '' ? '(défini)' : '(vide)') . "\n";

echo "\nTest PDO…\n";
try {
    $pdo = db();
    echo "OK — connexion réussie.\n";
    $n = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
    echo "Tables dans la base : $n\n";
} catch (Throwable $e) {
    echo 'ÉCHEC : ' . $e->getMessage() . "\n";
}
