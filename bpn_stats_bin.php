<?php 
include_once 'bpn_db.php';

// $period and $bin_no are interpolated into conditionally-built query
// fragments below, so they cannot be bound as parameters. They are forced
// into a safe shape here instead.
$person = NULL; if(!empty($_GET["person"])) $person = bpn_initials($_GET["person"]);
$period = 0; if(!empty($_GET["period"])) $period = bpn_int($_GET["period"], 0);
$bin_no = NULL; if(!empty($_GET["bin_no"])) $bin_no = bpn_int($_GET["bin_no"], 0);
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

#chrtBin, #chrtBin {
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
    top.location.href=url + "?bin_no=" + <?=$bin_no?> + "&period=" + period;
  }
  function refreshAll() {
    const path = location.pathname.substring(0, location.pathname.lastIndexOf('/')) + "/";
    const urlPieces = [location.protocol, '//', location.host, path, 'bpn_stats.php']
    top.location.href=urlPieces.join('');
  }
</script>
</head>

<body>

<div id="div">
  <canvas id="chrtBin"></canvas>
</div>

<div id="buttons">
<input type=button class="button" id="stats_all" name="stats_all" value="All Stats" onClick="refreshAll();">
<input type=button class="button" id="period_all" name="period_all" value="All time" onClick="refreshPeriod(0);">
<input type=button class="button" id="period_1y" name="period_1y" value="1 year" onClick="refreshPeriod(12);">
<input type=button class="button" id="period_6m" name="period_6m" value="6 months" onClick="refreshPeriod(6);">
<input type=button class="button" id="period_3m" name="period_3m" value="3 months" onClick="refreshPeriod(3);">
</div>

<div id=debug></div>

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
var ctx = document.getElementById('chrtBin');

<?php
$sql = 
  "SELECT t.contents, t.emptied_month, SUM(t.empty_count) empty_count ".
    "FROM ( ".
      "SELECT ".
        "IFNULL(e.contents, 'Full') contents, ".
        "DATE_FORMAT(e.emptied_date, '%Y-%m') emptied_month, ".
        "COUNT(*) empty_count ".
      "FROM bin b ".
      "INNER JOIN empty e ON e.bin_no=b.bin_no ".
      "WHERE b.bin_no='" . $bin_no . "' " .
      (($period==0) ? "" : "AND e.emptied_date >= (NOW() - INTERVAL " . $period . " MONTH) ") .
      "GROUP BY e.contents, DATE_FORMAT(e.emptied_date, '%Y-%m') ".
      "UNION ".
      "SELECT c.contents, DATE_FORMAT(e.emptied_date, '%Y-%m') emptied_month, 0 empty_count ".
      "FROM contents c, empty e ".
      "WHERE e.bin_no='" . $bin_no . "' " .
      (($period==0) ? "" : "AND e.emptied_date >= (NOW() - INTERVAL " . $period . " MONTH) ") .
      "GROUP BY c.contents, DATE_FORMAT(e.emptied_date, '%Y-%m') ".
      ") t ".
    "GROUP BY t.contents, t.emptied_month " .
    "ORDER BY t.contents, t.emptied_month";

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
  "SELECT DATE_FORMAT(e.emptied_date, '%Y-%m') emptied_month, COUNT(e.emptied_date) empty_count, ".
  "(SELECT COUNT(*) FROM empty e2 ".
  "WHERE e2.bin_no=b.bin_no " .
   (($period==0) ? "" : " AND e2.emptied_date >= (NOW() - INTERVAL " . $period . " MONTH)"). ") total_count ".
  "FROM bin b " .
  "INNER JOIN empty e ON e.bin_no=b.bin_no ".
  "WHERE b.bin_no='" . $bin_no . "' " .
  (($period==0) ? "" : "AND e.emptied_date >= (NOW() - INTERVAL " . $period . " MONTH) ") .
  "GROUP BY DATE_FORMAT(e.emptied_date, '%Y-%m') ".
  "ORDER BY 3 DESC, 1";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    $prev_list = "";
    while ($row = $result->fetch_assoc()) {
      if ($prev_list != "") echo ", ";
      echo "'" . $row["emptied_month"] . "'"; 
      $prev_list = $row["emptied_month"];
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
<?php if (empty($person)) {?>
          text: "Empties by bin and status (only currently active)"
<?php } else { ?>
          text: "Empties by bin and status (only currently active) for <?=$person?>"
<?php }?>
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
/*
ctx.onclick = function(evt) {
  const points = chrtBin.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, true);

  if (points.length) {
    var firstPoint = points[0];
    var label = chrtBin.data.labels[firstPoint.index];
    var bin = label.split(" ");
    refreshBin(bin[0]);
  }
}
*/
</script>

<?php
$conn->close();
?>
</body> 
</html>