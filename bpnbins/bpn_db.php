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

/**
 * Optional From: address for outgoing mail. Left empty the behaviour is
 * unchanged from before - PHP uses the server default, which is what has
 * been working. Setting it usually improves deliverability.
 */
define('BPN_FROM_EMAIL', $config['from_email'] ?? '');

/**
 * URL prefix the site is served under.
 *
 * Empty on production, where the project sits at the document root, so links
 * are /bpnbins/... Locally XAMPP serves it from htdocs\bpn, so it needs to be
 * '/bpn' or every absolute link 404s - which is what silently happens if this
 * is wrong: the stylesheet fails to load and the page renders unstyled.
 */
define('BPN_BASE', rtrim($config['base_path'] ?? '', '/'));

/** Build a site-absolute URL, honouring BPN_BASE. */
function bpn_url($path) {
    return BPN_BASE . '/' . ltrim($path, '/');
}

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

/**
 * Status lookup.
 *
 * One table, two domains, separated by type_id:
 *   Contents  - how full the bin is      (Empty / Some / Full / Overflowing)
 *   Condition - what state the bin is in (OK / Damaged / Missing)
 *
 * They are not the same thing, so a volunteer picks one of each. A member of
 * the public gets a single list drawn from both, because they will not fill
 * in two fields for a bin they are only walking past.
 */
define('BPN_CONTENTS',  1);
define('BPN_CONDITION', 2);

define('BPN_DEFAULT_CONTENTS',  3); // Full
define('BPN_DEFAULT_CONDITION', 5); // OK

/**
 * Rows from `status`, ordered for display.
 *
 * @param int|null $type       BPN_CONTENTS, BPN_CONDITION, or null for both
 * @param bool     $reportOnly only values offered to the public on a scan
 */
function bpn_status_list($conn, $type = null, $reportOnly = false) {
    $sql = "SELECT status_id, type_id, name FROM status WHERE 1=1";
    if ($type !== null) { $sql .= " AND type_id=?"; }
    if ($reportOnly)    { $sql .= " AND report_show=1"; }
    $sql .= " ORDER BY type_id, sort_order";

    $stmt = $conn->prepare($sql);
    if ($type !== null) { $stmt->bind_param("i", $type); }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/**
 * Coerce a submitted status_id to one that genuinely exists in the given
 * domain, falling back to $default. Never trust the posted value: a status_id
 * from the wrong type would put a Condition in a Contents column.
 */
function bpn_status_id($conn, $value, $type, $default, $reportOnly = false) {
    $id = bpn_int($value, 0);
    if ($id <= 0) { return $default; }

    $sql = "SELECT status_id FROM status WHERE status_id=? AND type_id=?";
    if ($reportOnly) { $sql .= " AND report_show=1"; }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $id, $type);
    $stmt->execute();
    $ok = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $ok ? $id : $default;
}

/** As above, but the public list spans both types. */
function bpn_report_status_id($conn, $value) {
    $id = bpn_int($value, 0);
    if ($id <= 0) { return BPN_DEFAULT_CONTENTS; }

    $stmt = $conn->prepare("SELECT status_id FROM status WHERE status_id=? AND report_show=1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $ok = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $ok ? $id : BPN_DEFAULT_CONTENTS;
}

/** Look up a single status name, for emails and confirmation messages. */
function bpn_status_name($conn, $status_id) {
    $stmt = $conn->prepare("SELECT name FROM status WHERE status_id=?");
    $stmt->bind_param("i", $status_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? $row['name'] : '';
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
