<?php
/**
 * Configuration template.
 *
 * Copy this file to config.php and fill in the real values.
 * config.php is gitignored and must never be committed.
 *
 *     cp config.sample.php config.php
 */

return [
    // Database
    'host'     => 'localhost',
    'database' => 'southesk_bpn',
    'username' => 'southesk_bpn',
    'password' => '',
    'charset'  => 'utf8mb4',

    // Where bin reports and empty notifications are sent. Required.
    'notify_email' => 'brechinpathnetwork@googlegroups.com',

    // Google Analytics measurement ID, e.g. 'G-XXXXXXXXXX'.
    // Leave empty to emit no analytics markup - recommended for local work.
    'analytics_id' => '',
];
