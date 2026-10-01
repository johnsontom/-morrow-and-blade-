<?php
/**
 * Application configuration.
 *
 * The database runs in Docker (see docker-compose.yml) and is published on
 * port 3307, so it never collides with the MariaDB that ships with XAMPP on
 * 3306. Change these values before uploading to a hosted server.
 */

declare(strict_types=1);

// ---------------------------------------------------------------- database
// Docker defaults: MariaDB on 127.0.0.1 port 3307 - deliberately not 3306,
// which belongs to XAMPP - database morrow_and_blade_php, user salon. A
// hosting panel or CI that sets environment variables overrides them,
// without touching this file.
$dbHost = getenv('DB_HOST');
$dbPort = getenv('DB_PORT');
$dbName = getenv('DB_NAME');
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASS');

define('DB_HOST', $dbHost === false || $dbHost === '' ? '127.0.0.1' : $dbHost);
define('DB_PORT', $dbPort === false || $dbPort === '' ? '3307' : $dbPort);
define('DB_NAME', $dbName === false || $dbName === '' ? 'morrow_and_blade_php' : $dbName);
define('DB_USER', $dbUser === false || $dbUser === '' ? 'salon' : $dbUser);
// Only false falls back to the default, so an explicitly empty DB_PASS still
// means an empty password (what the XAMPP root account uses).
define('DB_PASS', $dbPass === false ? 'salon' : $dbPass);

// ------------------------------------------------------------------ brand
define('SITE_NAME', 'Morrow & Blade');
define('SITE_TAGLINE', 'Modern craft. Personal service.');
define('SITE_INSTAGRAM', '@morrowandblade');
define('SITE_TIMEZONE', 'Europe/London');

// -------------------------------------------------------------- behaviour
define('DEBUG', true);
// Deliberately not CURRENCY_SYMBOL: PHP 8.1+ on Linux already defines that
// for nl_langinfo(), and redefining it makes PHP emit a warning that sends
// output early and breaks sessions and file uploads.
define('SITE_CURRENCY', 'GBP ');

// ----------------------------------------------------------- ai assistant
// Optional. Put a key in config/local.php (which is git-ignored) or set the
// OPENAI_API_KEY environment variable. Without a key the assistant still
// answers, using the built-in lookup engine instead of the model.
$localConfig = __DIR__ . '/local.php';
if (is_file($localConfig)) {
    require $localConfig;
}

defined('AI_API_KEY') || define('AI_API_KEY', (string) (getenv('OPENAI_API_KEY') ?: ''));
defined('AI_MODEL') || define('AI_MODEL', (string) (getenv('AI_MODEL') ?: 'gpt-4o-mini'));
defined('AI_ENDPOINT') || define('AI_ENDPOINT', 'https://api.openai.com/v1/chat/completions');

date_default_timezone_set('UTC');

// Work out the URL path the app is served from, so links keep working whether
// this sits in /barber-salon, at the document root, or on a live domain.
$documentRoot = str_replace('\\', '/', rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/'));
$appRoot = str_replace('\\', '/', dirname(__DIR__));
$basePath = ($documentRoot !== '' && strpos($appRoot, $documentRoot) === 0)
    ? substr($appRoot, strlen($documentRoot))
    : '';
define('BASE_PATH', rtrim($basePath, '/'));

if (DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

require_once __DIR__ . '/../app/DB.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/Uploads.php';
require_once __DIR__ . '/../app/Ai/Grounding.php';
require_once __DIR__ . '/../app/Ai/Assistant.php';
require_once __DIR__ . '/../app/dao/SalonDAO.php';
require_once __DIR__ . '/../app/dao/BranchDAO.php';
require_once __DIR__ . '/../app/dao/ServiceDAO.php';
require_once __DIR__ . '/../app/dao/BarberDAO.php';
require_once __DIR__ . '/../app/dao/BookingDAO.php';
require_once __DIR__ . '/../app/dao/AccountDAO.php';
require_once __DIR__ . '/../app/dao/StaffDAO.php';
require_once __DIR__ . '/../app/dao/MessageDAO.php';
require_once __DIR__ . '/../app/dao/ReportDAO.php';

