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

$person = NULL; if(!empty($_GET["person"])) $person = $_GET["person"];
$bin_no = NULL; if(!empty($_POST["bin_no"])) $bin_no = $_POST["bin_no"];
$contents = NULL; if(!empty($_POST["contents"])) $contents = $_POST["contents"];

$sql = "SELECT person_id, person_name FROM person WHERE person_key='" . SUBSTR($person,0,8) . "'";
$person_id = NULL;
$person_name = NULL;
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    if ($row = $result->fetch_assoc()) {
      $person_id = $row["person_id"];
      $person_name = $row["person_name"];
    }
}
?>
<html>
<head>
<title>Waste Bin Emptied<? if (!empty($person_name)) { echo " by " . $person_name; }?></title>
<style>
h1, h2, p, label, dt {
  font-family: "Arial";
}
h1, label, dt {
  font-size: 4vw;
}
input[type=radio] {
  border: 0px;
  width: 4em;
  height: 4em;
}

span {
  background-color: #FCC135;
  color: black;
  padding-left: 0.5vw;
  padding-right: 0.5vw;
  border: 5px solid #E31E24;
  font-size: 4vw;
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
if (empty($person_id)) {
?>
<p>Sorry, you have an invalid QR code.</p>
<?
} else if (empty($bin_no)) {
?>
<form method="post">
  <div id=binlist style="border:1px">
    <table border="0" width="100%" height="100%">
      <tr>      
        <td valign="top">
          <dl>
            <dt><input type="radio" id="bin_no[1]" name="bin_no" value="1"><label for="bin_no[1]"><span>1</span> Drumachlie</label></dt>
            <dt><input type="radio" id="bin_no[2]" name="bin_no" value="2"><label for="bin_no[2]"><span>2</span> Bluebell Bridge</label></dt>
            <dt><input type="radio" id="bin_no[3]" name="bin_no" value="3"><label for="bin_no[3]"><span>3</span> Trinity Leuchland Junction</label></dt>
            <dt><input type="radio" id="bin_no[4]" name="bin_no" value="4"><label for="bin_no[4]"><span>4</span> Leuchland</label></dt>
            <dt><input type="radio" id="bin_no[5]" name="bin_no" value="5"><label for="bin_no[5]"><span>5</span> Dalgety Bottom</label></dt>
            <dt><input type="radio" id="bin_no[6]" name="bin_no" value="6"><label for="bin_no[6]"><span>6</span> Dalgety Corner</label></dt>
            <dt><input type="radio" id="bin_no[7]" name="bin_no" value="7"><label for="bin_no[7]"><span>7</span> Hillhead</label></dt>
            <dt><input type="radio" id="bin_no[8]" name="bin_no" value="8"><label for="bin_no[8]"><span>8</span> Rough Moss Top</label></dt>
            <dt><input type="radio" id="bin_no[9]" name="bin_no" value="9"><label for="bin_no[9]"><span>9</span> Rough Moss Bottom</label></dt>
            <dt><input type="radio" id="bin_no[10]" name="bin_no" value="10"><label for="bin_no[10]"><span>10</span> Burghill West Bench</label></dt>
            <dt><input type="radio" id="bin_no[11]" name="bin_no" value="11"><label for="bin_no[11]"><span>11</span> Stannochy Pink Cottage</label></dt>
            <dt><input type="radio" id="bin_no[12]" name="bin_no" value="12"><label for="bin_no[12]"><span>12</span> Aberlemno Toll</label></dt>
            <dt><input type="radio" id="bin_no[13]" name="bin_no" value="13"><label for="bin_no[13]"><span>13</span> Pittendreich</label></dt>
            <dt><input type="radio" id="bin_no[14]" name="bin_no" value="14"><label for="bin_no[14]"><span>14</span> Pittendreich Grosefield Halfway</label></dt>
            <dt><input type="radio" id="bin_no[15]" name="bin_no" value="15"><label for="bin_no[15]"><span>15</span> Grosefield</label></dt>
            <dt><input type="radio" id="bin_no[16]" name="bin_no" value="16"><label for="bin_no[16]"><span>16</span> Parkend</label></dt>
            <dt><input type="radio" id="bin_no[17]" name="bin_no" value="17"><label for="bin_no[17]"><span>17</span> Limefield</label></dt>
            <dt><input type="radio" id="bin_no[18]" name="bin_no" value="18"><label for="bin_no[18]"><span>18</span> Trinity</label></dt>
            <dt><input type="radio" id="bin_no[19]" name="bin_no" value="19"><label for="bin_no[19]"><span>19</span> Tilygloom</label></dt>
            <dt><input type="radio" id="bin_no[20]" name="bin_no" value="20"><label for="bin_no[20]"><span>20</span> Pitforthie</label></dt>

          </dl>
        </td><td valign="top">
          <dl>
            <dt><input type="radio" id="bin_no[21]" name="bin_no" value="21"><label for="bin_no[21]"><span>21</span> Brechin Bridge</label></dt>
            <dt><input type="radio" id="bin_no[22]" name="bin_no" value="22"><label for="bin_no[22]"><span>22</span> Mid Wee Wood</label></dt>
            <dt><input type="radio" id="bin_no[23]" name="bin_no" value="23"><label for="bin_no[23]"><span>23</span> Rugby Pitch NE</label></dt>
            <dt><input type="radio" id="bin_no[24]" name="bin_no" value="24"><label for="bin_no[24]"><span>24</span> Rugby Pitch SE</label></dt>
            <dt><input type="radio" id="bin_no[25]" name="bin_no" value="25"><label for="bin_no[25]"><span>25</span> Mains of Pitforthie</label></dt>
            <dt><input type="radio" id="bin_no[26]" name="bin_no" value="26"><label for="bin_no[26]"><span>26</span> Eggbox</label></dt>
            <dt><input type="radio" id="bin_no[27]" name="bin_no" value="27"><label for="bin_no[27]"><span>27</span> Andover Railway</label></dt>
            <dt><input type="radio" id="bin_no[28]" name="bin_no" value="28"><label for="bin_no[28]"><span>28</span> BMX Track</label></dt>
            <dt><input type="radio" id="bin_no[29]" name="bin_no" value="29"><label for="bin_no[29]"><span>29</span> Park Rd Drumachlie Steps</label></dt>
            <dt><input type="radio" id="bin_no[30]" name="bin_no" value="30"><label for="bin_no[30]"><span>30</span> Drumachlie Railway Bridge</label></dt>
            <dt><input type="radio" id="bin_no[31]" name="bin_no" value="31"><label for="bin_no[31]"><span>31</span> Skinners Burn</label></dt>
            <dt><input type="radio" id="bin_no[32]" name="bin_no" value="32"><label for="bin_no[32]"><span>32</span> Brechin Bridge Half-way</label></dt>
            <dt><input type="radio" id="bin_no[33]" name="bin_no" value="33"><label for="bin_no[33]"><span>33</span> Slaughterhouse</label></dt>
            <dt><input type="radio" id="bin_no[34]" name="bin_no" value="34"><label for="bin_no[34]"><span>34</span> Rugby Pitch North</label></dt>
          </dl>
        </td>
      </tr>
      <tr>
      <td colspan=2 align="center">
        <input type="radio" id="contents[0]" name="contents" value="Empty"><label for="contents[0]">Empty</label>
        <input type="radio" id="contents[1]" name="contents" value="Some"><label for="contents[1]">Some</label>
        <input type="radio" id="contents[2]" name="contents" value="Full" checked="checked"><label for="contents[2]">Full</label>
        <input type="radio" id="contents[3]" name="contents" value="Overflowing"><label for="contents[3]">Overflow</label>
      </td>
      </tr>
      <tr>
      <td colspan=2 align="center"><input type=submit value="Emptied"></td>
      </tr>
    </table>
  </div>
</form>
<?
} else {
  $sql = "SELECT bin_name FROM bin WHERE bin_no=" . $bin_no;
  $result = $conn->query($sql);
  
  if ($result->num_rows > 0) {
    if ($row = $result->fetch_assoc()) {
      $bin_name = $row["bin_name"];
    }
    
    if(empty($_POST["contents"])) $contents = "Full";
    
    $sql = "INSERT INTO empty (bin_no, person_id, contents) VALUES ('" . $bin_no . "', '"  . $person_id . "', '"  . $contents . "');";
    $conn->query($sql);
    $conn->close();
    
    //$to_email = "brechinpathnetwork@googlegroups.com";
    $to_email = "craig@southesk.com";
    $subject = $person_name . " has emptied Bin (" . $bin_no . ") - " . $bin_name . " - " . $contents;
    $message = $subject . "\r\n\r\nNeed the map? https://southesk.com/bpn \r\n\r\n";
    $headers = ""; //"From: craigamckay@gmail.com";
    mail($to_email,$subject,$message,$headers);

    echo "<h1>Thank you, " . $person_name . ", for empting Bin #$bin_no " . $bin_name . " (" . $contents . ")!</h1>";
  }
}
?>
</body> 
</html>
