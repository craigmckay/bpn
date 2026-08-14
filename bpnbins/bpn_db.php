<?php
/**
 * Shared bootstrap: configuration, database connection and helpers.
 *
 * Every page includes this file first. Settings live in config.php, which is
 * gitignored. See config.sample.php for the expected shape.
 */

$configFile = __DIR__ . '/config.php';

if (!file_exists($configFile)) {
    die('Missing config.php - copy config.sample.php to config.php and set your database credentials.');
}

$config = require $configFile;

/**
 * Address that bin reports and empty notifications are sent to.
 * Required - there is no sensible default, and silently mailing nobody would
 * be worse than failing here.
 */
if (empty($config['notify_email'])) {
    die("Missing 'notify_email' in config.php - see config.sample.php.");
}
define('BPN_NOTIFY_EMAIL', $config['notify_email']);

/**
 * Google Analytics measurement ID. Optional: leave it empty (or omit it) and
 * no analytics markup is emitted at all, which is what you want locally.
 */
define('BPN_ANALYTICS_ID', $config['analytics_id'] ?? '');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(
        $config['host'],
        $config['username'],
        $config['password'],
        $config['database']
    );
    $conn->set_charset($config['charset'] ?? 'utf8mb4');
} catch (mysqli_sql_exception $e) {
    // Log the detail, show the user nothing useful to an attacker.
    error_log('BPN database connection failed: ' . $e->getMessage());
    http_response_code(503);
    die('Database unavailable. Please try again later.');
}

/**
 * Input validation helpers.
 *
 * Anything arriving from $_GET, $_POST or $_SERVER is untrusted. Values that
 * cannot be passed as a bound parameter (because they sit inside a query
 * fragment that is built conditionally) must be forced into a safe shape here
 * before they go anywhere near a query.
 */

/** Coerce to a plain integer, or return $default if it isn't numeric. */
function bpn_int($value, $default = 0) {
    return is_numeric($value) ? (int) $value : $default;
}

/** Restrict to the four values held in the `contents` lookup table. */
function bpn_contents($value) {
    $allowed = array('Empty', 'Some', 'Full', 'Overflowing');
    return in_array($value, $allowed, true) ? $value : 'Full';
}

/** Initials are up to 4 letters (see the `initials` column on `person`). */
function bpn_initials($value) {
    return preg_match('/^[A-Za-z]{1,4}$/', (string) $value) ? (string) $value : null;
}

/** Escape for output in HTML. */
function bpn_h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Emit the Google Analytics tag, or nothing if no measurement ID is set.
 * Call this inside <head>.
 */
function bpn_analytics_tag() {
    if (BPN_ANALYTICS_ID === '') {
        return;
    }
    $id = bpn_h(BPN_ANALYTICS_ID);
    echo <<<HTML
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id={$id}"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', '{$id}');
</script>
HTML;
}
