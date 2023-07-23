<?php
$servername = "localhost";
$username = "southesk_bpn";
$password = "__REMOVED_SEE_config.php__";

// Create connection
$conn = new mysqli($servername, $username, $password, $username);

// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}
?>
<html>
<head>
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-MPXXSQYB9E"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-MPXXSQYB9E');  
</script>
<title>Waste Bin Status</title>
<style>
h1, h2, p, label, dt, td, th {
  font-family: "Arial";
}
h1, label, dt {
  font-size: 3.5vw;
}
h2 {
  font-size: 3.0vw;
}
td, th {
  font-size: 2.5vw;
}
input[type=radio] {
  border: 0px;
  width: 4em;
  height: 4em;
}


</style>
</head>

<body>
<?
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
  <div><h2>Bins maybe needing emptying</h2>
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
    "    INNER JOIN bin b ON b.bin_no=r.bin_no AND b.bin_no NOT IN (31) " .
    "    WHERE report_id IN ( " .
    "      SELECT MAX(report_id) " .
    "      FROM report " .
    "      GROUP BY bin_no " .
    "    ) " .
    "    AND b.active=1 ".
    ") t " .
    "WHERE DATEDIFF(NOW(), latest_activity)>7 " .
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

 $conn->close();
?>
</body>
</html>