<?php 
include_once 'bpn_db.php';

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
  <div><h2>Bins probably needing emptying</h2>
  <table border=1 cellpadding=10 cellspacing=0>
  <tr><th>Bin</th><th>Last Scanned</th><th>Last Emptied</th><th>Indicator</th></tr>
<?
    while ($row = $result->fetch_assoc()) {
      echo "<tr><td>#<b>" . $row["bin_no"] . "</b>&nbsp;" . $row["bin_name"] . 
        "</td><td>" . $row["last_scanned"] . "</td><td>" . $row["last_emptied"] . 
        "</td><td><div><div style=\"float:left;background-color:rgb(255, 159, 64);height:30px;width:" . ($row["days_since_last_emptied"]*3) . "px\"></div><div style=\"float:left;background-color:rgb(255, 99, 132); height:30px;width:" . ($row["scans_since_emptied"]*10) . "px\"></div></div></td></tr>";
    }
?>
      </table></div>
<?
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
  <div><h2>Over a week since scanned or emptied</h2>
  <table border=1 cellpadding=10 cellspacing=0>
  <tr><th>Bin</th><th>Last Scanned</th><th>Last Emptied</th><th>Indicator</th></tr>
<?
    while ($row = $result->fetch_assoc()) {
      echo "<tr><td>#<b>" . $row["bin_no"] . "</b>&nbsp;" . $row["bin_name"] . "</td><td>" . 
        $row["last_scanned"] . "</td><td>" . $row["last_emptied"] . "</td><td><div><div style=\"float:left;background-color:rgb(255, 159, 64);height:30px;width:" . ($row["days_since_latest_activity"]*10) . "px\"></div></div></td></tr>";
    }
?>
      </table></div>
<?
  }
}
?>
