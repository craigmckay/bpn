<?php 
include_once __DIR__ . '/bpnbins/bpn_db.php';

$bin_no = NULL; if(!empty($_GET["bin_no"])) $bin_no = bpn_int($_GET["bin_no"], 0);
$bin_name = NULL;

if (!empty($bin_no)) {
  $stmt = $conn->prepare("SELECT bin_name FROM bin WHERE active=1 AND bin_no=?");
  $stmt->bind_param("i", $bin_no);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $bin_name = $row["bin_name"];
  }
  $stmt->close();
}
if (empty($bin_no) || empty($bin_name)) {
?>
<html>
<head>
<?php bpn_analytics_tag(); ?>
<title>Brechin Path Network - Empty Waste Bin</title>
<style>
h1, p {
  font-family: "Arial";
}
</style>
</head>
<body>
<h1>The QR code you have scanned is incorrect.  Please email <a href="mailto:<?=bpn_h(BPN_NOTIFY_EMAIL)?>"><?=bpn_h(BPN_NOTIFY_EMAIL)?></a>.</h1>
<?php
} else {
  $stmt = $conn->prepare(
    "SELECT COUNT(*) recent_reports " .
    "FROM report r " .
    "WHERE bin_no=? " .
    "AND TIMESTAMPDIFF(MINUTE,reported_date,NOW()) < 5");
  $stmt->bind_param("i", $bin_no);
  $stmt->execute();
  $result = $stmt->get_result();
  if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
      if ($row['recent_reports']==0) {
        // REMOTE_ADDR and especially HTTP_USER_AGENT are attacker-controlled.
        $remote_addr = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 50);
        $user_agent  = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000);

        $insert = $conn->prepare("INSERT INTO report (bin_no, remote_addr, http_user_agent) VALUES (?, ?, ?)");
        $insert->bind_param("iss", $bin_no, $remote_addr, $user_agent);
        $insert->execute();
        $insert->close();
        $conn->close();

        $to_email = BPN_NOTIFY_EMAIL;
        $subject = "Empty Waste Bin (" . $bin_no . ") - " . $bin_name;
        $message = $subject . "\r\n\r\nNeed the map? https://southesk.com/bpn \r\n\r\nhttps://southesk.com/bpnbins/ \r\n\r\n";
        $headers = ""; //"From: craigamckay@gmail.com";
        mail($to_email,$subject,$message,$headers);
      }
    }
  }
?>
<html>
<head>
<title>Empty Waste Bin #<?=bpn_h($bin_no)?> at <?=bpn_h($bin_name)?></title>
<style>
h1, h2, h3, p {
  font-family: "Arial";
}
</style>
</head>

<body>
  <h1>Brechin Path Network Bin #<?=bpn_h($bin_no)?> at <?=bpn_h($bin_name)?></h1>
  
  <h2>Thank you for reporting this Brechin Path Network Bin needs to be emptied... a <u>volunteer</u> will attend to it soon.</h2>
  
  <h3>Want to know more, or get involved?  Please email <a href="mailto:<?=bpn_h(BPN_NOTIFY_EMAIL)?>?Subject=More%20about%20Brechin%20Path%20Network%20Bins"><?=bpn_h(BPN_NOTIFY_EMAIL)?></a>.</h3>

  <h3><a href="/bpnbins/">View the full stats</a></h3>
<?php
}
?>
  <center><img style="max-width:100%" src="/bpnbins/bpn.png"></center>
</body> 
</html>

