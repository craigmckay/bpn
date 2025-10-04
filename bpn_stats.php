<?php 
include_once 'bpn_db.php';

$person = NULL; if(!empty($_GET["person"])) $person = $_GET["person"];
$period = 0; if(!empty($_GET["period"])) $period = $_GET["period"];
$bin_no = NULL; if(!empty($_GET["bin_no"])) $bin_no = $_GET["bin_no"];
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
  height: 44vh;
  margin: auto;
}

#chrtPerson, #chrtBin {
  width: 80vw;  
}

#buttons {
  margin: auto;
  padding-top: 1vh;
}

.button {
  background-color: #046DFF;
  border: none;
  color: white;
  padding: 10px;
  text-align: center;
  text-decoration: none;
  display: inline-block;
  font-size: 12px;
  margin: 5px 10px 10px 10px;
  border-radius: 20px;
}
.button:hover {
  background-color: #046DAA;
}


</style>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  function refreshPeriod(period) {
    const urlPieces = [location.protocol, '//', location.host, location.pathname]
    let url = urlPieces.join('')
    top.location.href=url + "?period=" + period;
  }
  function refreshPerson(person) {
    const urlPieces = [location.protocol, '//', location.host, location.pathname]
    let url = urlPieces.join('')
    top.location.href=url + "?person=" + person <?php echo (($period==0) ? "" : "+ \"&period=".$period . "\"")?>;
  }
  function refreshBin(bin_no) {
    const path = location.pathname.substring(0, location.pathname.lastIndexOf('/')) + "/";
    const urlPieces = [location.protocol, '//', location.host, path, 'bpn_stats_bin.php']
    let url = urlPieces.join('')
    top.location.href=url + "?bin_no=" + bin_no <?php echo (($period==0) ? "" : "+ \"&period=".$period . "\"")?>;
  }
</script>
</head>

<body>

<div id="div">
  <canvas id="chrtPerson"></canvas>
</div>

<div id="buttons">
<input type=button class="button" id="period_all" name="period_all" value="All time" onClick="refreshPeriod(0);">
<input type=button class="button" id="period_1y" name="period_1y" value="1 year" onClick="refreshPeriod(12);">
<input type=button class="button" id="period_3m" name="period_3m" value="3 months" onClick="refreshPeriod(3);">
<input type=button class="button" id="period_1m" name="period_1m" value="1 month" onClick="refreshPeriod(1);">
</div>

<div id=debug></div>

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
$sql = 
  "SELECT t.contents, t.initials, SUM(t.empty_count) empty_count, MIN(t.total_count) total_count ".
  "FROM ( ".
    "SELECT ".
      "IFNULL(e.contents, 'Full') contents, ".
      "p.initials, ".
      "COUNT(*) empty_count, ".
      "(SELECT COUNT(*) FROM empty e2 INNER JOIN bin b2 ON b2.bin_no=e2.bin_no WHERE e2.person_id=e.person_id " .
        (($period==0) ? "" : " AND e2.emptied_date >= (NOW() - INTERVAL " . $period . " MONTH)"). ") total_count ".
      "FROM person p ".
      "INNER JOIN empty e ON p.person_id=e.person_id ".
      "INNER JOIN bin b ON b.bin_no=e.bin_no ".
      (($period==0) ? "" : "WHERE e.emptied_date >= (NOW() - INTERVAL " . $period . " MONTH) ") .
      "GROUP BY ".
        "e.contents, ".
        "p.initials ".
    "UNION ".
    "SELECT c.contents, p.initials, 0 empty_count, ".
    "(SELECT COUNT(*) FROM empty e2 INNER JOIN bin b2 ON b2.bin_no=e2.bin_no WHERE e2.person_id=p.person_id " .
        (($period==0) ? "" : " AND e2.emptied_date >= (NOW() - INTERVAL " . $period . " MONTH)"). ") total_count ".
    "FROM contents c, person p ".
    ") t ".
    "GROUP BY t.contents, t.initials " .
    "HAVING SUM(t.total_count)>0 " .
    "ORDER BY t.contents, t.total_count DESC, t.initials ";

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
?>
];


var people = [
<?php
$sql = 
  "SELECT p.initials, ".
    "(SELECT COUNT(*) FROM empty e2 INNER JOIN bin b2 ON b2.bin_no=e2.bin_no WHERE e2.person_id=p.person_id " .
        (($period==0) ? "" : " AND e2.emptied_date >= (NOW() - INTERVAL " . $period . " MONTH)"). ") total_count ".
  "FROM person p ".
  "WHERE (SELECT COUNT(*) FROM empty e2 INNER JOIN bin b2 ON b2.bin_no=e2.bin_no WHERE e2.person_id=p.person_id " .
        (($period==0) ? "" : " AND e2.emptied_date >= (NOW() - INTERVAL " . $period . " MONTH)"). ") > 0 ".
  "ORDER BY 2 DESC, 1";
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
        text: "Most empties by person and status (including bins now inactive)"
      },
      legend: {
        display: true
      },
    },
    responsive: true,
    scales: {
      x: {
        stacked: true,
        grid: {
          display: false
        }
      },
      y: {
        stacked: true,
        ticks: {
          precision: 0
        }
      }
    }        
 }
})

ctx.onclick = function(evt) {
  const points = chrtPerson.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, true);

  if (points.length) {
    const firstPoint = points[0];
    const label = chrtPerson.data.labels[firstPoint.index];
    refreshPerson(label);
  }
};

var ctx = document.getElementById('chrtBin');

<?php
$sql = 
  "SELECT t.contents, t.bin_no, SUM(t.empty_count) empty_count, MIN(t.total_count) total_count ".
    "FROM ( ".
      "SELECT ".
        "IFNULL(e.contents, 'Full') contents, ".
        "e.bin_no, ".
        "COUNT(*) empty_count, ".
        "(SELECT COUNT(*) FROM empty e2 ".
       (empty($person) ? "" : "INNER JOIN person p ON p.person_id=e2.person_id AND p.initials='" . $person . "' ").     
        "WHERE e2.bin_no=e.bin_no " .
        (($period==0) ? "" : " AND e2.emptied_date >= (NOW() - INTERVAL " . $period . " MONTH)"). ") total_count ".
      "FROM bin b ".
      "INNER JOIN empty e ON e.bin_no=b.bin_no ".
      (empty($person) ? "" : "INNER JOIN person p ON p.person_id=e.person_id AND p.initials='" . $person . "' ").     
      "WHERE b.active=1 " .
      (($period==0) ? "" : "AND e.emptied_date >= (NOW() - INTERVAL " . $period . " MONTH) ") .
      "GROUP BY ".
      "e.contents, ".
      "e.bin_no ".
      "UNION ".
      "SELECT c.contents, b.bin_no, 0 empty_count, ".
      "(SELECT COUNT(*) FROM empty e2 ".
      (empty($person) ? "" : "INNER JOIN person p ON p.person_id=e2.person_id AND p.initials='" . $person . "' ").     
      "WHERE e2.bin_no=b.bin_no " .
        (($period==0) ? "" : " AND e2.emptied_date >= (NOW() - INTERVAL " . $period . " MONTH)"). ") total_count ".
      "FROM contents c, bin b " .
      "WHERE b.active=1 " .
    ") t ".
    "GROUP BY t.contents, t.bin_no " .
    "HAVING SUM(t.total_count)>0 " .
    "ORDER BY t.contents, t.total_count DESC, t.bin_no";
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
$sql = 
  "SELECT b.bin_no, b.bin_name, 0 empty_count, ".
  "(SELECT COUNT(*) FROM empty e2 ".
  (empty($person) ? "" : "INNER JOIN person p ON p.person_id=e2.person_id AND p.initials='" . $person . "' ").     
  "WHERE e2.bin_no=b.bin_no " .
   (($period==0) ? "" : " AND e2.emptied_date >= (NOW() - INTERVAL " . $period . " MONTH)"). ") total_count ".
  "FROM bin b " .
  "WHERE b.active=1 " .
  "ORDER BY 4 DESC, 1";
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

var chrtBin = new Chart(ctx, {
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
<? if (empty($person)) {?>
          text: "Empties by bin and status (only currently active)"
<? } else { ?>
          text: "Empties by bin and status (only currently active) for <?=$person?>"
<? }?>
        },
    },
    responsive: true,
    scales: {
      x: {
        stacked: true,
        grid: {
          display: false
        },
        
        ticks: {
          minRotation: 90,
          maxRotation: 90,
          autoSkip: false,
          callback: function(value, index, values) {
            return this.getLabelForValue(value).split(" - ")[0];
          }
        }
      },
      y: {
        stacked: true,
        ticks: {
          precision: 0
        }
      }
    }
 }
})

ctx.onclick = function(evt) {
  const points = chrtBin.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, true);

  if (points.length) {
    var firstPoint = points[0];
    var label = chrtBin.data.labels[firstPoint.index];
    var bin = label.split(" ");
    refreshBin(bin[0]);
  }
}
</script>

<?
$conn->close();
?>
</body> 
</html>