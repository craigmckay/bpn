<?php
include_once __DIR__ . '/bpn_db.php';

/**
 * Open a page: doctype, head, stylesheet, analytics, and the opening <body>.
 *
 * Every page calls this so the viewport tag and charset can never go missing
 * again. $wide widens the container for the chart pages.
 */
function bpn_head($title, $wide = false) {
    $t = bpn_h($title);
    $cls = $wide ? 'wrap wrap--wide' : 'wrap';
    echo "<!DOCTYPE html>\n";
    echo "<html lang=\"en\">\n<head>\n";
    echo "<meta charset=\"utf-8\">\n";
    echo "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n";
    echo "<title>{$t}</title>\n";
    // Cache-bust on the file's modification time. Without this, phones hold
    // on to an old stylesheet for days and edits appear to do nothing - or
    // worse, half-apply, because new class names match nothing.
    $css = __DIR__ . '/bpn.css';
    $v   = file_exists($css) ? filemtime($css) : '1';
    echo "<link rel=\"stylesheet\" href=\"" . bpn_url('bpnbins/bpn.css') . "?v={$v}\">\n";
    bpn_analytics_tag();
    echo "\n</head>\n<body>\n<div class=\"{$cls}\">\n";
}

/** Close the page opened by bpn_head(). */
function bpn_foot() {
    echo "</div>\n</body>\n</html>\n";
}

/**
 * A <select> built from a status list.
 *
 * @param array $rows      from bpn_status_list()
 * @param int   $selected  status_id to preselect
 */
function bpn_status_select($name, $rows, $selected, $label) {
    $n = bpn_h($name);
    echo '<div class="field">' . "\n";
    echo '  <label for="' . $n . '">' . bpn_h($label) . "</label>\n";
    echo '  <select id="' . $n . '" name="' . $n . '">' . "\n";
    foreach ($rows as $r) {
        $sel = ((int) $r['status_id'] === (int) $selected) ? ' selected' : '';
        echo '    <option value="' . bpn_h($r['status_id']) . '"' . $sel . '>'
           . bpn_h($r['name']) . "</option>\n";
    }
    echo "  </select>\n</div>\n";
}

/**
 * A radio group built from a status list.
 *
 * The grid goes on the inner div, never on the fieldset - browsers before
 * roughly 2020 ignore display:grid on a fieldset and collapse to one column.
 */
function bpn_status_radios($name, $rows, $selected, $label) {
    $n = bpn_h($name);
    echo '<fieldset class="choices-group">' . "\n";
    echo '  <legend class="field-label">' . bpn_h($label) . "</legend>\n";
    echo '  <div class="choices choices--inline">' . "\n";
    foreach ($rows as $r) {
        $id  = $n . '_' . (int) $r['status_id'];
        $sel = ((int) $r['status_id'] === (int) $selected) ? ' checked' : '';
        echo '    <label class="choice" for="' . $id . '">' . "\n";
        echo '      <input type="radio" id="' . $id . '" name="' . $n . '" value="'
           . bpn_h($r['status_id']) . '"' . $sel . '>' . "\n";
        echo '      <span>' . bpn_h($r['name']) . "</span>\n";
        echo "    </label>\n";
    }
    echo "  </div>\n</fieldset>\n";
}

/** The map image, shown at the foot of the public-facing pages. */
function bpn_map() {
    echo '<img class="map" src="' . bpn_url('bpnbins/bpn.png')
       . '" alt="Map of the Brechin Path Network">' . "\n";
}

/**
 * Send a multipart/alternative email: HTML for clients that render it, plain
 * text for those that don't. Sending both means the message is readable
 * everywhere rather than arriving as a wall of markup.
 */
function bpn_mail($to, $subject, $text, $html) {
    $boundary = '=_bpn_' . bin2hex(random_bytes(12));

    $headers = array(
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    );
    if (BPN_FROM_EMAIL !== '') {
        $headers[] = 'From: ' . BPN_FROM_EMAIL;
    }

    $eol = "\r\n";
    $body  = '--' . $boundary . $eol;
    $body .= 'Content-Type: text/plain; charset=UTF-8' . $eol;
    $body .= 'Content-Transfer-Encoding: 8bit' . $eol . $eol;
    $body .= $text . $eol . $eol;
    $body .= '--' . $boundary . $eol;
    $body .= 'Content-Type: text/html; charset=UTF-8' . $eol;
    $body .= 'Content-Transfer-Encoding: 8bit' . $eol . $eol;
    $body .= $html . $eol . $eol;
    $body .= '--' . $boundary . '--' . $eol;

    // Subject lines must not contain newlines - that would let a header be
    // injected. Nothing user-supplied reaches it today, but belt and braces.
    $subject = str_replace(array("\r", "\n"), ' ', $subject);

    return mail($to, $subject, $body, implode($eol, $headers));
}

function table_bins_empty_probably($conn) {  
  $sql =
    "SELECT " .
      "bin_no, bin_name, last_scanned, last_emptied, " .
      "days_since_last_emptied, scans_since_emptied, " .
      "(days_since_last_emptied*3) + (scans_since_emptied*10) indicator " .
    "FROM ( " .
      "SELECT r.bin_no, b.bin_name, " .
      "  DATE_FORMAT(r.reported_date, '%d %b %H:%i') last_scanned, ".
      "  DATE_FORMAT((SELECT MAX(emptied_date) FROM empty WHERE bin_no=r.bin_no), '%d %b %H:%i') last_emptied, " .    
      "  DATEDIFF(NOW(),(SELECT MAX(emptied_date) FROM empty WHERE bin_no=r.bin_no)) days_since_last_emptied, " .    
      "  (SELECT COUNT(*) " .
      "   FROM report r2 " .
      "   WHERE r2.bin_no=r.bin_no " .
      "   AND (SELECT MAX(emptied_date) FROM empty WHERE bin_no=r.bin_no) < r2.reported_date) scans_since_emptied " .
      "FROM report r " .
      "INNER JOIN bin b ON b.bin_no=r.bin_no " .
      "WHERE report_id IN ( " .
      "  SELECT MAX(report_id) " .
      "  FROM report " .
      "  WHERE (SELECT MAX(emptied_date) FROM empty WHERE bin_no=report.bin_no) < report.reported_date " .
      "  GROUP BY bin_no " .
      ") " .
      "AND b.active=1 ".
    ") t ".
    "ORDER BY 7 DESC, 3 ASC, 1 ASC ";
  $result = $conn->query($sql);
  if ($result->num_rows > 0) {
?>
  <h2>Bins probably needing emptying</h2>
  <div class="table-scroll">
  <table>
  <tr><th>Bin</th><th>Last scanned</th><th>Last emptied</th><th>Indicator</th></tr>
<?php
    while ($row = $result->fetch_assoc()) {
      echo "<tr><td><span class=\"bin-no\">" . bpn_h($row["bin_no"]) . "</span> " . bpn_h($row["bin_name"]) .
        "</td><td>" . bpn_h($row["last_scanned"]) . "</td><td>" . bpn_h($row["last_emptied"]) .
        "</td><td><span class=\"bar bar--age\" style=\"width:" . ((int)$row["days_since_last_emptied"]*3) .
        "px\"></span><span class=\"bar bar--scan\" style=\"width:" . ((int)$row["scans_since_emptied"]*10) .
        "px\"></span></td></tr>";
    }
?>
      </table></div>
<?php
  }
}

function table_bins_empty_old($conn) {  
  $sql =
    "SELECT " .
    "  bin_no, bin_name, " .
    "  DATE_FORMAT(last_scanned, '%d %b %H:%i') last_scanned, " .
    "  DATE_FORMAT(last_emptied, '%d %b %H:%i') last_emptied, " .
    "  DATEDIFF(NOW(),latest_activity) days_since_latest_activity ".
    "FROM ( " .
    "SELECT r.bin_no, b.bin_name, " .
    "      r.reported_date last_scanned, " .
    "      (SELECT MAX(emptied_date) FROM empty WHERE bin_no=r.bin_no) last_emptied, " .
    "      GREATEST(r.reported_date, (SELECT MAX(emptied_date) FROM empty WHERE bin_no=r.bin_no)) latest_activity " .
    "    FROM report r " .
    "    INNER JOIN bin b ON b.bin_no=r.bin_no " .
    "    WHERE report_id IN ( " .
    "      SELECT MAX(report_id) " .
    "      FROM report " .
    "      GROUP BY bin_no " .
    "    ) " .
    "    AND b.active=1 ".
    ") t " .
    "WHERE DATEDIFF(NOW(), latest_activity)>7 " .
    "AND last_emptied > last_scanned ".
    "ORDER BY latest_activity";
  $result = $conn->query($sql);
  if ($result->num_rows > 0) {
?>
  <h2>Over a week since scanned or emptied</h2>
  <div class="table-scroll">
  <table>
  <tr><th>Bin</th><th>Last scanned</th><th>Last emptied</th><th>Indicator</th></tr>
<?php
    while ($row = $result->fetch_assoc()) {
      echo "<tr><td><span class=\"bin-no\">" . bpn_h($row["bin_no"]) . "</span> " . bpn_h($row["bin_name"]) .
        "</td><td>" . bpn_h($row["last_scanned"]) . "</td><td>" . bpn_h($row["last_emptied"]) .
        "</td><td><span class=\"bar bar--age\" style=\"width:" . ((int)$row["days_since_latest_activity"]*10) .
        "px\"></span></td></tr>";
    }
?>
      </table></div>
<?php
  }
}

function recent_empties($conn, $person_id) {  
    $stmt = $conn->prepare(
      "SELECT DATE_FORMAT(e.emptied_date, '%l:%i %p') emptied_time, e.bin_no, b.bin_name, " .
      "sc.name contents, cond.name bin_condition " .
      "FROM empty e INNER JOIN bin b ON b.bin_no=e.bin_no " .
      "INNER JOIN status sc ON sc.status_id=e.contents_id " .
      "INNER JOIN status cond ON cond.status_id=e.condition_id " .
      "WHERE e.person_id=? " .
      "AND e.emptied_date >= DATE_SUB(NOW(), INTERVAL 2 HOUR) " .
      "ORDER BY e.empty_id DESC");
    $stmt->bind_param("i", $person_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
?>
      <h2>Your empties in the last two hours</h2>
      <div class="table-scroll">
      <table>
<?php
      while ($row = $result->fetch_assoc()) {
        // Only mention the condition when it is something worth mentioning.
        $cond = ($row["bin_condition"] === 'OK') ? ''
              : ' <span class="contents">' . bpn_h($row["bin_condition"]) . '</span>';
        echo "<tr><td>" . bpn_h($row["emptied_time"]) .
          "</td><td><span class=\"bin-no\">" . bpn_h($row["bin_no"]) . "</span> " . bpn_h($row["bin_name"]) .
          "</td><td>" . bpn_h($row["contents"]) . $cond . "</td></tr>";
      }
      $stmt->close();
?>
      </table></div>
<?php      
    }   
}
?>
