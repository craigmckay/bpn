<?php 
include_once __DIR__ . '/bpn_util.php';

// $period and $bin_no are interpolated into conditionally-built query
// fragments below, so they cannot be bound as parameters. They are forced
// into a safe shape here instead.
$person = NULL; if(!empty($_GET["person"])) $person = bpn_initials($_GET["person"]);
$period = 0; if(!empty($_GET["period"])) $period = bpn_int($_GET["period"], 0);
$bin_no = NULL; if(!empty($_GET["bin_no"])) $bin_no = bpn_int($_GET["bin_no"], 0);

bpn_head('Waste Bin Stats', true);
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  // Absolute paths written by PHP. Deriving them from location.pathname broke
  // as soon as the pretty URLs added a path segment (/bpnbins/stats/bin).
  const STATS_URL = "<?=bpn_url('bpnbins/')?>";
  const BIN_URL   = "<?=bpn_url('bpnbins/stats/bin')?>";

  function refreshPeriod(period) {
    top.location.href = BIN_URL + "?bin_no=" + <?=bpn_int($bin_no, 0)?> + "&period=" + period;
  }
  function refreshAll() {
    top.location.href = STATS_URL;
  }
</script>
<div class="chart">
  <canvas id="chrtBin"></canvas>
</div>

<div class="buttons">
<input type=button class="btn btn--small" id="stats_all" name="stats_all" value="All Stats" onClick="refreshAll();">
<input type=button class="btn btn--small" id="period_all" name="period_all" value="All time" onClick="refreshPeriod(0);">
<input type=button class="btn btn--small" id="period_1y" name="period_1y" value="1 year" onClick="refreshPeriod(12);">
<input type=button class="btn btn--small" id="period_6m" name="period_6m" value="6 months" onClick="refreshPeriod(6);">
<input type=button class="btn btn--small" id="period_3m" name="period_3m" value="3 months" onClick="refreshPeriod(3);">
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
        "sc.name contents, ".
        "DATE_FORMAT(e.emptied_date, '%Y-%m') emptied_month, ".
        "COUNT(*) empty_count ".
      "FROM bin b ".
      "INNER JOIN empty e ON e.bin_no=b.bin_no ".
      "INNER JOIN status sc ON sc.status_id=e.contents_id ".
      "WHERE b.bin_no='" . $bin_no . "' " .
      (($period==0) ? "" : "AND e.emptied_date >= (NOW() - INTERVAL " . $period . " MONTH) ") .
      "GROUP BY sc.name, DATE_FORMAT(e.emptied_date, '%Y-%m') ".
      "UNION ".
      "SELECT c.name contents, DATE_FORMAT(e.emptied_date, '%Y-%m') emptied_month, 0 empty_count ".
      "FROM status c, empty e ".
      "WHERE c.type_id=1 AND e.bin_no='" . $bin_no . "' " .
      (($period==0) ? "" : "AND e.emptied_date >= (NOW() - INTERVAL " . $period . " MONTH) ") .
      "GROUP BY c.name, DATE_FORMAT(e.emptied_date, '%Y-%m') ".
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
      "INNER JOIN status sc ON sc.status_id=e.contents_id ".
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
<?php bpn_foot(); ?>
