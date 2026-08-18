<?php
/**
 * Volunteer landing page for an "I emptied this bin" QR scan.
 *
 * The volunteer is identified by a token in the URL (person.person_key).
 * They pick a bin and how full it was; that writes a row to `empty` and
 * emails the group.
 *
 * The bin list is read from the database - a bin with active=0 simply stops
 * appearing, so retiring one is a data change, not an edit to this file.
 */

include_once __DIR__ . '/bpnbins/bpn_util.php';

$person   = NULL; if (!empty($_GET["person"]))    $person   = trim($_GET["person"]);
$bin_no   = NULL; if (!empty($_POST["bin_no"]))   $bin_no   = bpn_int($_POST["bin_no"], 0);
$contents_id  = bpn_status_id($conn, $_POST["contents_id"]  ?? null, BPN_CONTENTS,  BPN_DEFAULT_CONTENTS);
$condition_id = bpn_status_id($conn, $_POST["condition_id"] ?? null, BPN_CONDITION, BPN_DEFAULT_CONDITION);
$comments     = mb_substr(trim((string) ($_POST["comments"] ?? '')), 0, 1000);

$person_id = NULL;
$person_name = NULL;
if (!empty($person)) {
  $person_key = substr($person, 0, 8);
  $stmt = $conn->prepare("SELECT person_id, person_name FROM person WHERE person_key=?");
  $stmt->bind_param("s", $person_key);
  $stmt->execute();
  $result = $stmt->get_result();
  if ($row = $result->fetch_assoc()) {
    $person_id   = $row["person_id"];
    $person_name = $row["person_name"];
  }
  $stmt->close();
}

/* ------------------------------------------------------------------------ */

if (empty($person_id)) {

  bpn_head('Waste Bins - Brechin Path Network');
  ?>
  <div class="banner banner--error">
    <h1>We don't recognise that code</h1>
    <p class="lede">This link identifies you as one of the volunteers, and it
    doesn't match anyone on our list.</p>
    <p>If your card has stopped working, email
    <a href="mailto:<?=bpn_h(BPN_NOTIFY_EMAIL)?>"><?=bpn_h(BPN_NOTIFY_EMAIL)?></a>
    and we'll sort you out a new one.</p>
  </div>
  <?php
  bpn_foot();
  exit;
}

/* --- pick a bin ---------------------------------------------------------- */

if (empty($bin_no)) {

  $bins = array();
  $res = $conn->query("SELECT bin_no, bin_name FROM bin WHERE active=1 ORDER BY bin_no");
  while ($row = $res->fetch_assoc()) {
    $bins[] = $row;
  }

  // First name only - "Hello Craig!" reads better than the full name.
  $first_name = strtok(trim($person_name), ' ');

  bpn_head('Which bin? - ' . $first_name, true);
  ?>
  <h1>Hello <?=bpn_h($first_name)?>!</h1>
  <p class="lede">Which bin have you emptied?</p>

  <form method="post">
    <fieldset class="choices-group">
      <legend class="visually-hidden">Bin</legend>
      <!-- The grid lives on this div, not the fieldset. Older mobile browsers
           ignore display:grid on a fieldset and fall back to one column. -->
      <div class="choices choices--bins">
        <?php foreach ($bins as $b) { ?>
          <label class="choice">
            <input type="radio" name="bin_no" value="<?=bpn_h($b['bin_no'])?>">
            <span><span class="bin-no"><?=bpn_h($b['bin_no'])?></span>
            <?=bpn_h($b['bin_name'])?></span>
          </label>
        <?php } ?>
      </div>
    </fieldset>

    <?php
      bpn_status_radios('contents_id',  bpn_status_list($conn, BPN_CONTENTS),
                        BPN_DEFAULT_CONTENTS,  'How full was it?');
      bpn_status_radios('condition_id', bpn_status_list($conn, BPN_CONDITION),
                        BPN_DEFAULT_CONDITION, 'What condition is the bin in?');
    ?>

    <div class="field">
      <label for="comments">Comments <span class="muted">(optional)</span></label>
      <textarea id="comments" name="comments" maxlength="1000"
                placeholder="Anything worth passing on?"></textarea>
    </div>

    <button type="submit" class="btn">Emptied</button>
  </form>
  <?php
  bpn_foot();
  $conn->close();
  exit;
}

/* --- record the empty ---------------------------------------------------- */

$stmt = $conn->prepare(
  "SELECT b.bin_name, " .
  "(SELECT COUNT(*) FROM empty WHERE bin_no=b.bin_no AND person_id=? " .
  " AND TIMESTAMPDIFF(MINUTE,emptied_date,NOW()) < 15) recent_empties " .
  "FROM bin b WHERE b.active=1 AND b.bin_no=?");
$stmt->bind_param("ii", $person_id, $bin_no);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
  bpn_head('Waste Bins - Brechin Path Network');
  ?>
  <div class="banner banner--error">
    <h1>That bin isn't on the list</h1>
    <p>It may have been retired. Please let
    <a href="mailto:<?=bpn_h(BPN_NOTIFY_EMAIL)?>"><?=bpn_h(BPN_NOTIFY_EMAIL)?></a>
    know.</p>
  </div>
  <?php
  bpn_foot();
  $conn->close();
  exit;
}

$bin_name  = $row['bin_name'];
$contents  = bpn_status_name($conn, $contents_id);
$condition = bpn_status_name($conn, $condition_id);

if ($row['recent_empties'] == 0) {
  $insert = $conn->prepare(
    "INSERT INTO empty (bin_no, person_id, contents_id, condition_id, comments) " .
    "VALUES (?, ?, ?, ?, ?)");
  $insert->bind_param("iiiis", $bin_no, $person_id, $contents_id, $condition_id, $comments);
  $insert->execute();
  $insert->close();

  $subject = $person_name . " has emptied Bin (" . $bin_no . ") - " . $bin_name . " - " . $contents;
  if ($condition_id != BPN_DEFAULT_CONDITION) {
    $subject .= " [" . $condition . "]";
  }

  $text  = $subject . "\r\n\r\n";
  if ($condition_id != BPN_DEFAULT_CONDITION) {
    $text .= "Condition: " . $condition . "\r\n\r\n";
  }
  if ($comments !== '') {
    $text .= "Comments:\r\n" . $comments . "\r\n\r\n";
  }
  $text .= "Map:   https://southesk.com/bpn\r\n";
  $text .= "Stats: https://southesk.com/bpnbins/\r\n";

  $html  = '<div style="font-family:Arial,sans-serif;color:#1b1b1b">';
  $html .= '<p style="font-size:20px;margin:0 0 16px"><strong>' . bpn_h($person_name)
         . '</strong> has emptied Bin <strong>' . bpn_h($bin_no) . '</strong> &mdash; '
         . bpn_h($bin_name) . ' (' . bpn_h($contents) . ')</p>';
  if ($condition_id != BPN_DEFAULT_CONDITION) {
    $html .= '<p style="font-size:22px;font-weight:bold;color:#E31E24;margin:0 0 16px">'
           . bpn_h($condition) . '</p>';
  }
  if ($comments !== '') {
    $html .= '<p style="font-size:16px;margin:0 0 4px;color:#555">Comments</p>';
    $html .= '<p style="font-size:18px;line-height:1.4;border-left:4px solid #006633;'
           . 'padding-left:12px;margin:0 0 16px">' . nl2br(bpn_h($comments)) . '</p>';
  }
  $html .= '<p style="font-size:14px"><a href="https://southesk.com/bpn">Map</a>'
         . ' &nbsp;|&nbsp; <a href="https://southesk.com/bpnbins/">Stats</a></p>';
  $html .= '</div>';

  bpn_mail(BPN_NOTIFY_EMAIL, $subject, $text, $html);
}

bpn_head('Bin ' . $bin_no . ' emptied - thank you', true);
?>

<h1>Thank you, <?=bpn_h($person_name)?></h1>

<div class="banner banner--ok">
  <p class="lede" style="margin:0">
    <span class="bin-no"><?=bpn_h($bin_no)?></span>
    <span class="bin-name"><?=bpn_h($bin_name)?></span>
    &mdash; <span class="contents"><?=bpn_h($contents)?></span>
    <?php if ($condition_id != BPN_DEFAULT_CONDITION) { ?>
      &mdash; <span class="contents"><?=bpn_h($condition)?></span>
    <?php } ?>
  </p>
</div>

<?php
if ($comments !== '') {
  echo '<div class="panel note"><p style="margin:0"><strong>Your comments:</strong> '
     . nl2br(bpn_h($comments)) . '</p></div>';
}

if ($row['recent_empties'] > 0) {
  echo '<p class="muted">You logged this one in the last few minutes, so we '
     . 'haven\'t recorded it twice.</p>';
}

recent_empties($conn, $person_id);
?>

<p><a class="btn btn--small" href="<?=bpn_url('bpnbins/')?>">View the full stats</a></p>

<?php
table_bins_empty_probably($conn);
bpn_foot();
$conn->close();
