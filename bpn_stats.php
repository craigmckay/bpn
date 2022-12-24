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
?>
<html>
<head>
<title>Waste Bin Stats</title>
<style>

html {
  height: 100vh;
}

body {
  display: flex;
  flex-direction: column;
  flex-wrap: wrap;
  flex-grow: 1;
}

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
  font-size: 3.5vw;
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

#div {
  height: 47vh;
  margin: auto;
}

#chrtPerson, #chrtBin {
  width: 80vw;  
}


</style>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>

<div id="div">
  <canvas id="chrtPerson"></canvas>
</div>

<p></p>

<div id="div">
  <canvas id="chrtBin"></canvas>
</div>

<script>  
const chartColors = {
    red: 'rgb(255, 99, 132)',
    orange: 'rgb(255, 159, 64)',
    yellow: 'rgb(255, 205, 86)',
    green: 'rgb(75, 192, 192)',
    blue: 'rgb(54, 162, 235)',
    purple: 'rgb(153, 102, 255)',
    grey: 'rgb(201, 203, 207)'
};
var ctx = document.getElementById('chrtPerson');

<?php
$sql = "SELECT t.contents, t.initials, SUM(t.empty_count) empty_count, MIN(t.total_count) total_count ".
" FROM ( ".
"   SELECT  ".
"         IFNULL(e.contents, 'Full') contents,  ".
"         p.initials,  ".
"         COUNT(*) empty_count, ".
"         (SELECT COUNT(*) FROM empty e2 INNER JOIN bin b2 ON b2.bin_no=e2.bin_no WHERE e2.person_id=e.person_id AND b2.active=1) total_count ".
"        FROM person p   ".
"        LEFT JOIN empty e ON p.person_id=e.person_id   ".
"        GROUP BY   ".
"         e.contents,  ".
"         p.initials ".
"   UNION ".
"   SELECT c.contents, p.initials, 0 empty_count, ".
"    (SELECT COUNT(*) FROM empty e2 INNER JOIN bin b2 ON b2.bin_no=e2.bin_no WHERE e2.person_id=p.person_id AND b2.active=1) total_count ".
"     FROM contents c, person p ".
"  ) t     ".
"  GROUP BY t.contents, t.initials" .
"  HAVING SUM(t.total_count)>0 " .
"  ORDER BY t.contents, t.total_count DESC, t.initials";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    $prev_list = "";
    while ($row = $result->fetch_assoc()) {
      if ($row["contents"] != $prev_list) {
        if ($prev_list != "") echo "];\r\n";
        echo "var " . strtolower($row["contents"]) . " = [" . $row["empty_count"]; 
      } else {
        echo ", " . $row["empty_count"]; 
      }
      $prev_list = $row["contents"];
    }
}
echo "];\r\n";
?>

var people = [
<?php
$sql = "   SELECT p.initials, ".
"    (SELECT COUNT(*) FROM empty e2 INNER JOIN bin b2 ON b2.bin_no=e2.bin_no WHERE e2.person_id=p.person_id AND b2.active=1) total_count ".
"  FROM person p ".
"  WHERE (SELECT COUNT(*) FROM empty e2 INNER JOIN bin b2 ON b2.bin_no=e2.bin_no WHERE e2.person_id=p.person_id AND b2.active=1) > 0 ".
"  ORDER BY 2 DESC, 1";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    $prev_list = "";
    while ($row = $result->fetch_assoc()) {
      if ($prev_list != "") echo ", ";
      echo "'" . $row["initials"] . "'"; 
      $prev_list = $row["initials"];
    }
}
?>
];

var chrtPerson = new Chart(ctx, {
 type: 'bar',
 data: {
    labels: people,
    datasets: [
    {
      label: 'Overflowing',
      data: overflowing,
      backgroundColor: chartColors.red,
    },
    {
      label: 'Full',
      data: full,
      backgroundColor: chartColors.orange,
    },
    {
      label: 'Some',
      data: some,
      backgroundColor: chartColors.yellow,
    },
    {
      label: 'None',
      data: empty,
      backgroundColor: chartColors.grey,
    }
  ]
 },
 options: {
    plugins: {
        title: {
          display: true,
          text: "Most empties by person and status"
        },
        legend: {
            display: true
        },
    },
    responsive: true,
        scales: {
          x: {
            stacked: true,
          },
          y: {
            stacked: true
          }
        }        
 }
})

var ctx = document.getElementById('chrtBin');

<?php
$sql = "SELECT t.contents, t.bin_no, SUM(t.empty_count) empty_count, MIN(t.total_count) total_count ".
" FROM ( ".
"   SELECT  ".
"         IFNULL(e.contents, 'Full') contents, ".
"         e.bin_no, ".
"         COUNT(*) empty_count, ".
"         (SELECT COUNT(*) FROM empty e2 WHERE e2.bin_no=e.bin_no) total_count ".
"        FROM bin b ".
"        LEFT JOIN empty e ON e.bin_no=b.bin_no ".
"        WHERE b.active=1 " .
"        GROUP BY   ".
"         e.contents,  ".
"         e.bin_no ".
"   UNION ".
"   SELECT c.contents, b.bin_no, 0 empty_count, ".
"    (SELECT COUNT(*) FROM empty e2 WHERE e2.bin_no=b.bin_no) total_count ".
"     FROM contents c, bin b " .
"     WHERE b.active=1 " .
"  ) t     ".
"  GROUP BY t.contents, t.bin_no" .
"  HAVING SUM(t.total_count)>0 " .
"  ORDER BY t.contents, t.total_count DESC, t.bin_no";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    $prev_list = "";
    while ($row = $result->fetch_assoc()) {
      if ($row["contents"] != $prev_list) {
        if ($prev_list != "") echo "];\r\n";
        echo "var " . strtolower($row["contents"]) . " = [" . $row["empty_count"]; 
      } else {
        echo ", " . $row["empty_count"]; 
      }
      $prev_list = $row["contents"];
    }
}
echo "];\r\n";
?>

var bins = [
<?php
$sql = "   SELECT b.bin_no, b.bin_name, 0 empty_count, ".
"    (SELECT COUNT(*) FROM empty e2 WHERE e2.bin_no=b.bin_no) total_count ".
"     FROM bin b " .
"     WHERE b.active=1 " .
"  ORDER BY 4 DESC, 1";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    $prev_list = "";
    while ($row = $result->fetch_assoc()) {
      if ($prev_list != "") echo ", ";
      echo "'" . $row["bin_no"] . " - " . $row["bin_name"] . "'"; 
      $prev_list = $row["bin_no"];
    }
}
?>
];

var chrtPerson = new Chart(ctx, {
 type: 'bar',
 data: {
    labels: bins,
    datasets: [
    {
      label: 'Overflowing',
      data: overflowing,
      backgroundColor: chartColors.red,
    },
    {
      label: 'Full',
      data: full,
      backgroundColor: chartColors.orange,
    },
    {
      label: 'Some',
      data: some,
      backgroundColor: chartColors.yellow,
    },
    {
      label: 'None',
      data: empty,
      backgroundColor: chartColors.grey,
    }
  ]
 },
 options: {
    plugins: {
        title: {
          display: true,
          text: "Empties by bin and status"
        },
    },
    responsive: true,
    scales: {
      x: {
        stacked: true,
        ticks: {
          minRotation: 90,
          maxRotation: 90,
          autoSkip: false,
          // Include a dollar sign in the ticks
          callback: function(value, index, values) {
            return this.getLabelForValue(value).split(" - ")[0];
          }
        }
      },
      y: {
        stacked: true
      }
    }
 }
})
</script>

<?
$conn->close();
?>
</body> 
</html>