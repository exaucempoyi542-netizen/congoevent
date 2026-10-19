<?php
/**
 * Configuration base de données - ResaSalle RDC / Congo Events
 */

/** Charge un fichier .env simple (local) si présent — non utilisé sur Railway. */
(static function (): void {
    $envFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
    if (!is_readable($envFile)) {
        return;
    }
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, " \t\"'");
        if ($name !== '' && getenv($name) === false && !isset($_ENV[$name]) && !isset($_SERVER[$name])) {
            putenv("$name=$value");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
})();

/** Lit une variable d'environnement (Railway, Docker, Apache, .env). */
function app_env(string $key, ?string $default = null): ?string
{
    foreach ([$key, strtoupper($key), strtolower($key)] as $k) {
        $v = $_ENV[$k] ?? $_SERVER[$k] ?? getenv($k);
        if ($v !== false && $v !== null && $v !== '') {
            return (string) $v;
        }
    }
    return $default;
}

/** Parse mysql://user:pass@host:port/db */
function app_parse_mysql_url(string $url): ?array
{
    $parts = parse_url($url);
    if (!$parts || empty($parts['host'])) {
        return null;
    }
    return [
        'host' => (string) $parts['host'],
        'port' => (string) ($parts['port'] ?? '3306'),
        'user' => isset($parts['user']) ? rawurldecode((string) $parts['user']) : 'root',
        'pass' => isset($parts['pass']) ? rawurldecode((string) $parts['pass']) : '',
        'name' => isset($parts['path']) ? ltrim((string) $parts['path'], '/') : 'resasalle_rdc',
    ];
}

$urlCfg = null;
foreach (['MYSQL_URL', 'MYSQLURL', 'DATABASE_URL', 'DB_URL'] as $urlKey) {
    $raw = app_env($urlKey);
    if ($raw) {
        $urlCfg = app_parse_mysql_url($raw);
        if ($urlCfg) {
            break;
        }
    }
}

define('DB_HOST', app_env('MYSQLHOST') ?: (app_env('MYSQL_HOST') ?: (app_env('DB_HOST') ?: ($urlCfg['host'] ?? '127.0.0.1'))));
define('DB_PORT', app_env('MYSQLPORT') ?: (app_env('MYSQL_PORT') ?: (app_env('DB_PORT') ?: ($urlCfg['port'] ?? '3306'))));
define('DB_NAME', app_env('MYSQLDATABASE') ?: (app_env('MYSQL_DATABASE') ?: (app_env('DB_NAME') ?: ($urlCfg['name'] ?? 'resasalle_rdc'))));
define('DB_USER', app_env('MYSQLUSER') ?: (app_env('MYSQL_USER') ?: (app_env('DB_USER') ?: ($urlCfg['user'] ?? 'root'))));
define('DB_PASS', app_env('MYSQLPASSWORD') ?: (app_env('MYSQL_PASSWORD') ?: (app_env('DB_PASS') ?: ($urlCfg['pass'] ?? ''))));
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'Congo Events');
define('APP_BRAND', 'Congo Events');
define('APP_LOGO', 'img/logo-congo-events-v2.png');

define('MAIL_HOST', app_env('MAIL_HOST', 'smtp.gmail.com'));
define('MAIL_PORT', (int) app_env('MAIL_PORT', '587'));
define('MAIL_USER', app_env('MAIL_USER', ''));
define('MAIL_PASS', app_env('MAIL_PASS', ''));
define('MAIL_FROM', app_env('MAIL_FROM') ?: (app_env('MAIL_USER', '')));
define('MAIL_FROM_NAME', app_env('MAIL_FROM_NAME', 'Congo Events'));

define('SASPAY_API_KEY', app_env('SASPAY_API_KEY', ''));
define('SASPAY_WEBHOOK_SECRET', app_env('SASPAY_WEBHOOK_SECRET', ''));
define('APP_PUBLIC_URL', app_env('APP_PUBLIC_URL', ''));

// Détection fiable du chemin public (ex: /focus en local, vide sur Railway)
if (!defined('APP_URL')) {
    $appUrl = '';
    $docRoot = isset($_SERVER['DOCUMENT_ROOT'])
        ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: '')
        : '';
    $projectRoot = str_replace('\\', '/', realpath(__DIR__ . '/..') ?: '');

    if ($docRoot !== '' && $projectRoot !== '' && str_starts_with($projectRoot, $docRoot)) {
        $appUrl = substr($projectRoot, strlen($docRoot));
    } else {
        $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        if (str_ends_with($script, '/admin') || str_ends_with($script, '/proprietaire')) {
            $script = dirname($script);
        }
        $appUrl = $script;
    }

    if ($appUrl === '/' || $appUrl === '\\' || $appUrl === '.') {
        $appUrl = '';
    }
    define('APP_URL', $appUrl);
}

date_default_timezone_set('Africa/Kinshasa');

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 8,
        ];

        $last = null;
        for ($i = 0; $i < 3; $i++) {
            try {
                $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                break;
            } catch (PDOException $e) {
                $last = $e;
                usleep(500000);
            }
        }

        if ($pdo === null) {
            $onRailway = app_env('RAILWAY_ENVIRONMENT') !== null
                || app_env('RAILWAY_PROJECT_ID') !== null
                || (DB_HOST !== '127.0.0.1' && DB_HOST !== 'localhost');
            $missingMysqlVars = app_env('MYSQLHOST') === null && app_env('MYSQL_URL') === null && app_env('MYSQLURL') === null;

            http_response_code(503);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Base de données indisponible</title>';
            echo '<style>body{font-family:Segoe UI,sans-serif;max-width:640px;margin:3rem auto;padding:0 1rem;color:#1f2937}';
            echo 'code{background:#f3f4f6;padding:.15rem .4rem;border-radius:6px}ol{line-height:1.6}</style></head><body>';

            if ($missingMysqlVars || DB_HOST === '127.0.0.1' || DB_HOST === 'localhost') {
                echo '<h1>Base MySQL non configurée</h1>';
                echo '<p>Congo Events tente encore <code>' . htmlspecialchars(DB_HOST . ':' . DB_PORT) . '</code> (valeurs locales).</p>';
                echo '<p>Sur <strong>Railway</strong>, lie les variables du service MySQL au service web :</p>';
                echo '<ol>';
                echo '<li>Ouvre le service <strong>web</strong> (congoevent) → onglet <strong>Variables</strong>.</li>';
                echo '<li>Ajoute une référence vers MySQL : <code>MYSQLHOST</code>, <code>MYSQLPORT</code>, <code>MYSQLUSER</code>, <code>MYSQLPASSWORD</code>, <code>MYSQLDATABASE</code>.</li>';
                echo '<li>Ou colle <code>MYSQL_URL</code> = variable <code>MYSQL_URL</code> du service MySQL.</li>';
                echo '<li>Redéploie, puis importe <code>database/schema.sql</code> dans MySQL.</li>';
                echo '</ol>';
            } else {
                echo '<h1>Connexion MySQL impossible</h1>';
                echo '<p>Hôte : <code>' . htmlspecialchars(DB_HOST . ':' . DB_PORT) . '</code> — base <code>' . htmlspecialchars(DB_NAME) . '</code>.</p>';
                echo '<ol>';
                echo '<li>Vérifie que le service <strong>MySQL</strong> est Online sur Railway.</li>';
                echo '<li>Importe le schéma <code>database/schema.sql</code> si ce n’est pas déjà fait.</li>';
                echo '<li>Attends 30 s puis recharge.</li>';
                echo '</ol>';
            }

            if ($last) {
                echo '<p style="color:#6b7280;font-size:.9rem">Détail : ' . htmlspecialchars($last->getMessage()) . '</p>';
            }
            echo '</body></html>';
            exit;
        }
    }

    return $pdo;
}
