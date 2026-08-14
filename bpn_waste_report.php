<?php
/**
 * Public landing page for a "this bin needs emptying" QR scan.
 *
 * Two stages:
 *   GET  ?bin_no=N   the scan itself. Logs a report, emails the group, and
 *                    offers an optional form for more detail.
 *   POST             that form coming back. Updates the report just logged
 *                    and sends a second, louder email.
 *
 * The scan alone is enough - someone can walk away at that point and the bin
 * still gets reported. The form is a bonus, not a requirement.
 */

include_once __DIR__ . '/bpnbins/bpn_util.php';

$bin_no    = bpn_int($_GET['bin_no'] ?? $_POST['bin_no'] ?? null, 0);
$report_id = bpn_int($_POST['report_id'] ?? null, 0);
$status_id = bpn_report_status_id($conn, $_POST['status_id'] ?? null);
$comments  = trim((string) ($_POST['comments'] ?? ''));
$comments  = mb_substr($comments, 0, 1000);

$is_send   = ($_SERVER['REQUEST_METHOD'] === 'POST');
$bin_name  = null;
$sent      = false;

if (!empty($bin_no)) {
    $stmt = $conn->prepare("SELECT bin_name FROM bin WHERE active=1 AND bin_no=?");
    $stmt->bind_param("i", $bin_no);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $bin_name = $row['bin_name'];
    }
    $stmt->close();
}

/* ------------------------------------------------------------------ */

if (empty($bin_no) || empty($bin_name)) {

    bpn_head('Brechin Path Network - Waste Bins');
    ?>
    <div class="banner banner--error">
      <h1>That QR code didn't work</h1>
      <p class="lede">We couldn't match it to a bin on the path network. The
      code may be damaged, or the bin may have been retired.</p>
      <p>Please let us know at
      <a href="mailto:<?=bpn_h(BPN_NOTIFY_EMAIL)?>"><?=bpn_h(BPN_NOTIFY_EMAIL)?></a>
      and we'll get it replaced &mdash; it helps if you can tell us roughly
      where the bin is.</p>
    </div>
    <?php
    bpn_map();
    bpn_foot();
    exit;
}

/* --- the form coming back ----------------------------------------------- */

if ($is_send && $report_id > 0) {

    // The report_id arrives in a hidden field, so it is untrusted. Only allow
    // an update to a report for THIS bin, logged in the last hour - otherwise
    // someone could rewrite the history of any bin by editing the form.
    $upd = $conn->prepare(
        "UPDATE report SET status_id=?, comments=?, updated_date=NOW() " .
        "WHERE report_id=? AND bin_no=? " .
        "AND TIMESTAMPDIFF(MINUTE, reported_date, NOW()) < 60");
    $upd->bind_param("isii", $status_id, $comments, $report_id, $bin_no);
    $upd->execute();
    $changed = $upd->affected_rows;
    $upd->close();

    if ($changed > 0) {
        $sent = true;
        $status = bpn_status_name($conn, $status_id);

        $subject = "BIN " . $status . " - Bin " . $bin_no . " " . $bin_name;

        $text  = strtoupper($status) . "\r\n\r\n";
        $text .= "Bin " . $bin_no . " - " . $bin_name . "\r\n\r\n";
        if ($comments !== '') {
            $text .= "Reporter's comments:\r\n" . $comments . "\r\n\r\n";
        }
        $text .= "Map:   https://southesk.com/bpn\r\n";
        $text .= "Stats: https://southesk.com/bpnbins/\r\n";

        $h_status   = bpn_h($status);
        $h_bin      = bpn_h($bin_no);
        $h_binname  = bpn_h($bin_name);
        $h_comments = nl2br(bpn_h($comments));

        $html  = '<div style="font-family:Arial,sans-serif;color:#1b1b1b">';
        $html .= '<p style="font-size:28px;font-weight:bold;color:#E31E24;margin:0 0 8px">'
               . $h_status . '</p>';
        $html .= '<p style="font-size:20px;margin:0 0 16px">Bin <strong>' . $h_bin
               . '</strong> &mdash; ' . $h_binname . '</p>';
        if ($comments !== '') {
            $html .= '<p style="font-size:16px;margin:0 0 4px;color:#555">Reporter\'s comments</p>';
            $html .= '<p style="font-size:20px;line-height:1.4;border-left:4px solid #006633;'
                   . 'padding-left:12px;margin:0 0 16px">' . $h_comments . '</p>';
        }
        $html .= '<p style="font-size:14px"><a href="https://southesk.com/bpn">Map</a>'
               . ' &nbsp;|&nbsp; <a href="https://southesk.com/bpnbins/">Stats</a></p>';
        $html .= '</div>';

        bpn_mail(BPN_NOTIFY_EMAIL, $subject, $text, $html);
    }
}

/* --- a fresh scan -------------------------------------------------------- */

if (!$is_send) {

    $stmt = $conn->prepare(
        "SELECT report_id FROM report " .
        "WHERE bin_no=? AND TIMESTAMPDIFF(MINUTE, reported_date, NOW()) < 5 " .
        "ORDER BY report_id DESC LIMIT 1");
    $stmt->bind_param("i", $bin_no);
    $stmt->execute();
    $recent = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($recent) {
        // Someone else scanned this bin moments ago. Don't log it twice or
        // send another email, but let this person add detail to that report.
        $report_id = (int) $recent['report_id'];
    } else {
        $remote_addr = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 50);
        $user_agent  = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000);

        $insert = $conn->prepare(
            "INSERT INTO report (bin_no, remote_addr, http_user_agent, status_id) " .
            "VALUES (?, ?, ?, " . BPN_DEFAULT_CONTENTS . ")");
        $insert->bind_param("iss", $bin_no, $remote_addr, $user_agent);
        $insert->execute();
        $report_id = (int) $conn->insert_id;
        $insert->close();

        $subject = "Empty Waste Bin (" . $bin_no . ") - " . $bin_name;
        $text  = $subject . "\r\n\r\n";
        $text .= "Map:   https://southesk.com/bpn\r\n";
        $text .= "Stats: https://southesk.com/bpnbins/\r\n";

        $html  = '<div style="font-family:Arial,sans-serif;color:#1b1b1b">';
        $html .= '<p style="font-size:20px;margin:0 0 16px">Bin <strong>' . bpn_h($bin_no)
               . '</strong> &mdash; ' . bpn_h($bin_name) . ' needs emptying.</p>';
        $html .= '<p style="font-size:14px"><a href="https://southesk.com/bpn">Map</a>'
               . ' &nbsp;|&nbsp; <a href="https://southesk.com/bpnbins/">Stats</a></p>';
        $html .= '</div>';

        bpn_mail(BPN_NOTIFY_EMAIL, $subject, $text, $html);
    }
}

bpn_head('Bin ' . $bin_no . ' - ' . $bin_name);
?>

<h1>Thanks &mdash; that's reported</h1>

<div class="banner banner--ok">
  <p class="lede" style="margin:0">
    <span class="bin-no"><?=bpn_h($bin_no)?></span>
    <span class="bin-name"><?=bpn_h($bin_name)?></span>
  </p>
</div>

<?php if ($sent) { ?>

  <p class="lede">Your update has been sent to the volunteers &mdash; thank you
  for taking the time.</p>

  <div class="panel note">
    <p style="margin:0"><strong>Reported as:</strong> <?=bpn_h($status)?></p>
    <?php if ($comments !== '') { ?>
      <p style="margin:0.5em 0 0"><strong>Your comments:</strong>
      <?=nl2br(bpn_h($comments))?></p>
    <?php } ?>
  </div>

<?php } else { ?>

  <p class="lede">A volunteer has been notified and will come and empty it.
  You don't need to do anything else.</p>

  <h2>Anything else wrong with it?</h2>
  <p class="muted">Only if you have a moment &mdash; it helps us know whether
  to bring a bag or a toolkit.</p>

  <form method="post" action="bpn_waste_report.php">
    <input type="hidden" name="bin_no" value="<?=bpn_h($bin_no)?>">
    <input type="hidden" name="report_id" value="<?=bpn_h($report_id)?>">

    <?php bpn_status_select('status_id',
            bpn_status_list($conn, null, true),
            BPN_DEFAULT_CONTENTS,
            "What's wrong with it?"); ?>

    <div class="field">
      <label for="comments">Comments</label>
      <textarea id="comments" name="comments" maxlength="1000"
                placeholder="Anything the volunteers should know before they set off?"></textarea>
    </div>

    <button type="submit" class="btn">Send this to the volunteers</button>
  </form>

<?php } ?>

<h3>Want to get involved?</h3>
<p>The bins are emptied entirely by volunteers. If you'd like to help, email
<a href="mailto:<?=bpn_h(BPN_NOTIFY_EMAIL)?>?Subject=More%20about%20Brechin%20Path%20Network%20Bins"><?=bpn_h(BPN_NOTIFY_EMAIL)?></a>.</p>

<p><a href="<?=bpn_url('bpnbins/')?>">See how the network is doing</a></p>

<?php
bpn_map();
bpn_foot();
$conn->close();
