<?php 
include_once 'bpn_db.php';

$bin_no = NULL; if(!empty($_GET["bin_no"])) $bin_no = $_GET["bin_no"];
$bin_name = NULL;

if (!empty($bin_no)) {
  $sql = "SELECT bin_name FROM bin WHERE active=1 AND bin_no=" . $bin_no;
  $result = $conn->query($sql);

  if ($result->num_rows > 0) {
    $row = mysqli_fetch_assoc($result);
    $bin_name = $row["bin_name"];
  }
}
if (empty($bin_no) || empty($bin_name)) {
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
<title>Brechin Path Network - Empty Waste Bin</title>
<style>
h1, p {
  font-family: "Arial";
}
</style>
</head>
<body>
<h1>The QR code you have scanned is incorrect.  Please email <a href="mailto:brechinpathnetwork@googlegroups.com">brechinpathnetwork@googlegroups.com</a>.</h1>
<?
} else {
  $sql =
    "SELECT COUNT(*) recent_reports " .
    "FROM report r " .
    "WHERE bin_no='" . $bin_no . "' " .
    "AND TIMESTAMPDIFF(MINUTE,reported_date,NOW()) < 5";
  $result = $conn->query($sql);
  if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
      if ($row['recent_reports']==0) {
        $sql = "INSERT INTO report (bin_no, remote_addr, http_user_agent) VALUES ('" . $bin_no . "', '" . 
          $_SERVER['REMOTE_ADDR'] . "', '" . $_SERVER['HTTP_USER_AGENT'] . "');";

        $conn->query($sql);
        $conn->close();

        $to_email = "brechinpathnetwork@googlegroups.com";
        $subject = "Empty Waste Bin (" . $bin_no . ") - " . $bin_name;
        $message = $subject . "\r\n\r\nNeed the map? https://southesk.com/bpn \r\n\r\nhttps://southesk.com/bpn_stats.php \r\n\r\n";
        $headers = ""; //"From: craigamckay@gmail.com";
        mail($to_email,$subject,$message,$headers);
      }
    }
  }
?>
<html>
<head>
<title>Empty Waste Bin #<?=$bin_no?> at <?=$bin_name?></title>
<style>
h1, h2, h3, p {
  font-family: "Arial";
}
</style>
</head>

<body>
  <h1>Brechin Path Network Bin #<?=$bin_no?> at <?=$bin_name?></h1>
  
  <h2>Thank you for reporting this Brechin Path Network Bin needs to be emptied... a <u>volunteer</u> will attend to it soon.</h2>
  
  <h3>Want to know more, or get involved?  Please email <a href="mailto:brechinpathnetwork@googlegroups.com?Subject=More%20about%20Brechin%20Path%20Network%20Bins">brechinpathnetwork@googlegroups.com</a>.</h3>

  <h3><a href="bpn_stats.php">View the full stats</a></h3>
<?
}
?>
  <center><img style="max-width:100%" src="bpn.png"></center>
</body> 
</html>

