<?php
/**
 * Which bins probably need emptying, and which have gone quiet.
 * Served at /bpnbins/status via the rewrite in .htaccess.
 */

include_once __DIR__ . '/bpn_util.php';

bpn_head('Waste Bin Status', true);
?>

<h1>Bin status</h1>

<?php
table_bins_empty_probably($conn);
table_bins_empty_old($conn);
?>

<p><a class="btn btn--small" href="<?=bpn_url('bpnbins/')?>">View the full stats</a></p>

<?php
bpn_foot();
$conn->close();
