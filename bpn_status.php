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
<title>Waste Bin Status</title>
<style>
h1, h2, p, label, dt, td, th {
  font-family: "Arial";
}
h1, label, dt {
  font-size: 4vw;
}
h2 {
  font-size: 3.5vw;
}
td, th {
  font-size: 3vw;
}
input[type=radio] {
  border: 0px;
  width: 4em;
  height: 4em;
}

span.bin {
  background-color: #FCC135;
  color: black;
  padding-left: 0.5vw;
  padding-right: 0.5vw;
  border: 5px solid #E31E24;
  font-size: 4vw;
}

span.binname {
  background-color: #006633;
  color: white;
  padding: 0.5vw;
  font-size: 4vw;
}

span.contents {
  color: #E31E24;
}

input[type=submit] {
  background-color: #006633;
  border: none;
  color: white;
  padding: 20px;
  text-align: center;
  text-decoration: none;
  display: inline-block;
  font-size: 6vw;
  margin: 4px 2px;
  border-radius: 12px;
}

dt {
   text-indent: -12vw;
   padding-left: 12vw;
   padding-bottom: 1vw;
}

</style>
</head>

<body>
<?
  $sql = 
    "SELECT r.bin_no, b.bin_name, " .
    "  DATE_FORMAT(r.reported_date, '%d %b %l:%i %p') last_scanned, ".
    "  DATE_FORMAT((SELECT MAX(emptied_date) FROM empty WHERE bin_no=r.bin_no), '%d %b %l:%i %p') last_emptied " .
    "FROM report r " .
    "INNER JOIN bin b ON b.bin_no=r.bin_no " .
    "WHERE report_id IN ( " .
    "  SELECT MAX(report_id) " .
    "  FROM report " .
    "  WHERE (SELECT MAX(emptied_date) FROM empty WHERE bin_no=report.bin_no) < reported_date " .
    "  GROUP BY bin_no " .
    ") " .
    "ORDER BY r.reported_date ";
  $result = $conn->query($sql);
  if ($result->num_rows > 0) {
?>
  <div><h2>Bins maybe needing emptying</h2>
  <table border=1 cellpadding=10 cellspacing=0>
  <tr><th>Bin</th><th>Last Scanned</th><th>Last Emptied</th></tr>
<?      
    while ($row = $result->fetch_assoc()) {
      echo "<tr><td>#<b>" . $row["bin_no"] . "</b>&nbsp;" . $row["bin_name"] . "</td><td>" . $row["last_scanned"] . "</td><td>" . $row["last_emptied"] . "</td></tr>";
    }
?>
      </table></div>
<?
  }

  $sql = 
    "SELECT " . 
    "  bin_no, bin_name, " . 
    "  DATE_FORMAT(last_scanned, '%d %b %l:%i %p') last_scanned, " . 
    "  DATE_FORMAT(last_emptied, '%d %b %l:%i %p') last_emptied " . 
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
    ") t " . 
    "WHERE DATEDIFF(NOW(), latest_activity)>7 " . 
    "ORDER BY latest_activity";
  $result = $conn->query($sql);
  if ($result->num_rows > 0) {
?>
  <div><h2>Over a week since scanned or emptied</h2>
  <table border=1 cellpadding=10 cellspacing=0>
  <tr><th>Bin</th><th>Last Scanned</th><th>Last Emptied</th></tr>
<?      
    while ($row = $result->fetch_assoc()) {
      echo "<tr><td>#<b>" . $row["bin_no"] . "</b>&nbsp;" . $row["bin_name"] . "</td><td>" . $row["last_scanned"] . "</td><td>" . $row["last_emptied"] . "</td></tr>";
    }
?>
      </table></div>
<?
  }

 $conn->close();
?>
</body> 
</html>