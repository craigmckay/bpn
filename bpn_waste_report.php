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
  $sql = "INSERT INTO report (bin_no, remote_addr, http_user_agent) VALUES ('" . $bin_no . "', '" . 
    $_SERVER['REMOTE_ADDR'] . "', '" . $_SERVER['HTTP_USER_AGENT'] . "');";

  $conn->query($sql);
  $conn->close();

  $to_email = "brechinpathnetwork@googlegroups.com";
  $subject = "Empty Waste Bin (" . $bin_no . ") - " . $bin_name;
  $message = $subject . "\r\n\r\nNeed the map? https://southesk.com/bpn \r\n\r\n";
  $headers = ""; //"From: craigamckay@gmail.com";
  mail($to_email,$subject,$message,$headers);
?>
<html>
<head>
<title>Empty Waste Bin #<?=$bin_no?> at <?=$bin_name?></title>
<style>
h1, h2, p {
  font-family: "Arial";
}
</style>
</head>

<body>
  <h1>Brechin Path Network Bin #<?=$bin_no?> at <?=$bin_name?></h1>
  <h2>Thank you for reporting this Brechin Path Network Bin needs to be emptied.</h2>
  <p>Someone will attend to it soon.</p>
  <p>Have a good day!</p>
<?
}
?>
  <center><img style="max-width:100%" src="bpn.png"></center>
</body> 
</html>

