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

    // URL prefix the site is served under.
    //   ''      production - the project sits at the document root
    //   '/bpn'  local XAMPP - served from htdocs\bpn
    // Get this wrong and the stylesheet 404s, so every page renders unstyled.
    'base_path' => '',

    // Where bin reports and empty notifications are sent. Required.
    'notify_email' => 'brechinpathnetwork@googlegroups.com',

    // Google Analytics measurement ID, e.g. 'G-XXXXXXXXXX'.
    // Leave empty to emit no analytics markup - recommended for local work.
    'analytics_id' => '',

    // Optional From: address on outgoing mail. Leave empty for PHP's default,
    // which is the long-standing behaviour. Setting it usually helps mail
    // reach the group rather than a spam folder.
    'from_email' => '',
];
