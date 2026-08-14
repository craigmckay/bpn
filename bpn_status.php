<?php
include_once 'bpn_db.php';
include_once 'bpn_util.php';
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
<title>Waste Bin Status</title>
<style>
h1, h2, p, label, dt, td, th {
  font-family: "Arial";
}
h1, label, dt {
  font-size: 3.5vw;
}
h2 {
  font-size: 3.0vw;
}
td, th {
  font-size: 2.5vw;
}
input[type=radio] {
  border: 0px;
  width: 4em;
  height: 4em;
}
</style>
</head>

<body>
<?php
  table_bins_empty_probably($conn);
  table_bins_empty_old($conn);

  $conn->close();
?>
</body>
</html>