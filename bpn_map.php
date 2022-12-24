<?php
require ("DBController.php");

define("API_KEY", "AIzaSyCmRusl91AsyaSSbjS0fHHNGS925PQX9tE");

$dbController = new DBController();

$query = "SELECT * FROM bin where latitude is not null and longitude is not null";
$countryResult = $dbController->runQuery($query);

?>
<html>
<head>
<title>Show Path on Google Map using Javascript API</title>
<style>
body {
	font-family: Arial;
}

#map-layer {
	margin: 20px 0px;
	max-width: 600px;
	min-height: 400;
}
</style>
</head>
<body>
	<h1>Show Path on Google Map using Javascript API</h1>
	<div id="map-layer"></div>
	<script>
      	var map;
		var pathCoordinates = Array();
      	function initMap() {
        	  	var countryLength
            	var mapLayer = document.getElementById("map-layer"); 
            	var centerCoordinates = new google.maps.LatLng(56.733, -2.638);
        		var defaultOptions = { center: centerCoordinates, zoom: 12 }
        		map = new google.maps.Map(mapLayer, defaultOptions);
        		geocoder = new google.maps.Geocoder();
        	    <?php
            if (! empty($countryResult)) {
            ?>
            countryLength = <?php echo count($countryResult); ?>
            <?php
                foreach ($countryResult as $k => $v) 
                {
            ?>  
             	geocoder.geocode( { 'address': '<?php echo $countryResult[$k]["bin_name"]; ?>' }, function(LocationResult, status) {
        				if (status == google.maps.GeocoderStatus.OK) {
        					var latitude = <?php echo $countryResult[$k]["latitude"]; ?>;
        					var longitude = <?php echo $countryResult[$k]["longitude"]; ?>;
        					pathCoordinates.push({lat: latitude, lng: longitude});
        					
    						new google.maps.Marker({
                    	        position: new google.maps.LatLng(latitude, longitude),
                    	        map: map,
                    	        title: '<?php echo $countryResult[$k]["bin_name"]; ?>'
                    	    });
                    	    
        					if(countryLength == pathCoordinates.length) {
            					drawPath();
        					}
        			        
        				} 
        			});
        	    <?php
                }
            }
            ?>	
      	}
        	function drawPath() {
            	new google.maps.Polyline({
                  path: pathCoordinates,
                  geodesic: true,
                  strokeColor: '#FF0000',
                  strokeOpacity: 1,
                  strokeWeight: 4,
                  map: map
            });
        }
	</script>
	<script async defer
		src="https://maps.googleapis.com/maps/api/js?key=<?php echo API_KEY; ?>&callback=initMap">
    </script>
</body>
</html>