<?php 
include_once __DIR__ . '/bpn_util.php';

// $person and $period are interpolated into conditionally-built query
// fragments below, so they cannot be bound as parameters. They are forced
// into a safe shape here instead: initials are letters only, period is an
// integer number of months.
$person = NULL; if(!empty($_GET["person"])) $person = bpn_initials($_GET["person"]);
$period = 0; if(!empty($_GET["period"])) $period = bpn_int($_GET["period"], 0);
$bin_no = NULL; if(!empty($_GET["bin_no"])) $bin_no = bpn_int($_GET["bin_no"], 0);

bpn_head('Waste Bin Stats', true);
?>
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
<div class="chart">
  <canvas id="chrtPerson"></canvas>
</div>

<div class="buttons">
<input type=button class="btn btn--small" id="period_all" name="period_all" value="All time" onClick="refreshPeriod(0);">
<input type=button class="btn btn--small" id="period_1y" name="period_1y" value="1 year" onClick="refreshPeriod(12);">
<input type=button class="btn btn--small" id="period_3m" name="period_3m" value="3 months" onClick="refreshPeriod(3);">
<input type=button class="btn btn--small" id="period_1m" name="period_1m" value="1 month" onClick="refreshPeriod(1);">
</div>

<div id=debug></div>

<div class="chart">
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
      "sc.name contents, ".
      "p.initials, ".
      "COUNT(*) empty_count, ".
      "(SELECT COUNT(*) FROM empty e2 INNER JOIN bin b2 ON b2.bin_no=e2.bin_no WHERE e2.person_id=e.person_id " .
        (($period==0) ? "" : " AND e2.emptied_date >= (NOW() - INTERVAL " . $period . " MONTH)"). ") total_count ".
      "FROM person p ".
      "INNER JOIN empty e ON p.person_id=e.person_id ".
      "INNER JOIN status sc ON sc.status_id=e.contents_id ".
      "INNER JOIN bin b ON b.bin_no=e.bin_no ".
      (($period==0) ? "" : "WHERE e.emptied_date >= (NOW() - INTERVAL " . $period . " MONTH) ") .
      "GROUP BY ".
        "sc.name, ".
        "p.initials ".
    "UNION ".
    "SELECT c.name contents, p.initials, 0 empty_count, ".
    "(SELECT COUNT(*) FROM empty e2 INNER JOIN bin b2 ON b2.bin_no=e2.bin_no WHERE e2.person_id=p.person_id " .
        (($period==0) ? "" : " AND e2.emptied_date >= (NOW() - INTERVAL " . $period . " MONTH)"). ") total_count ".
    "FROM status c, person p WHERE c.type_id=1 ".
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
        "sc.name contents, ".
        "e.bin_no, ".
        "COUNT(*) empty_count, ".
        "(SELECT COUNT(*) FROM empty e2 ".
       (empty($person) ? "" : "INNER JOIN person p ON p.person_id=e2.person_id AND p.initials='" . $person . "' ").     
        "WHERE e2.bin_no=e.bin_no " .
        (($period==0) ? "" : " AND e2.emptied_date >= (NOW() - INTERVAL " . $period . " MONTH)"). ") total_count ".
      "FROM bin b ".
      "INNER JOIN empty e ON e.bin_no=b.bin_no ".
      "INNER JOIN status sc ON sc.status_id=e.contents_id ".
      (empty($person) ? "" : "INNER JOIN person p ON p.person_id=e.person_id AND p.initials='" . $person . "' ").     
      "WHERE b.active=1 " .
      (($period==0) ? "" : "AND e.emptied_date >= (NOW() - INTERVAL " . $period . " MONTH) ") .
      "GROUP BY ".
      "sc.name, ".
      "e.bin_no ".
      "UNION ".
      "SELECT c.name contents, b.bin_no, 0 empty_count, ".
      "(SELECT COUNT(*) FROM empty e2 ".
      (empty($person) ? "" : "INNER JOIN person p ON p.person_id=e2.person_id AND p.initials='" . $person . "' ").     
      "WHERE e2.bin_no=b.bin_no " .
        (($period==0) ? "" : " AND e2.emptied_date >= (NOW() - INTERVAL " . $period . " MONTH)"). ") total_count ".
      "FROM status c, bin b " .
      "WHERE b.active=1 AND c.type_id=1 " .
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

<?php
$conn->close();
?>
<?php bpn_foot(); ?>
