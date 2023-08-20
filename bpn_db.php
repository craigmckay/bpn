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
