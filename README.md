ssgeocoder
==========

Stupid Simple Geocoder -- A no-configuration Geocoder using GeoNames with an SQLite cache behind it

Stupid Simple Geocoder has a single method named _geocode_

The only option is to set the path to the SQLite database in the constructor. 

Features
--------

ssgeocoder tries to open an SQLite database to cache results in, but will still work if that fails.

Geocode results are stored in the database (if possible).

The SQLite cache is checked (if possible) before making a web request.


Tutorial
--------

test.php geocodes 4 existing places and fails to geocode one. It then puts 
them into a geojson structured array and turns it into GeoJSON.

    require_once('ssgeocoder.php');
    $ssgeocoder = new ssgeocoder();

    // Fetch an array of features
    $places = Array('Ironwood, MI, USA','Sacramento, MG, Brazil','Provo','Lostlandneverfoundcity');
    $features = $ssgeocoder->geocode($places);

    // Geocode a single place
    $singlePlace = 'Vienna, Austria';
    $feature = $ssgeocoder->geocode($singlePlace);

    // Combine the single feature with the others
    $features[$singlePlace] = $feature;

    // Put the features into a GeoJSON array and convert to JSON
    // Note that the $features array is actually a hash, and we just want the values
    // piece of that since json_encode will make it an object if we include
    // the keys
    $geojson = Array(
        'type' => 'FeatureCollection',
        'features' => array_values($features)
    );

    $jsonString = json_encode($geojson);

    print $jsonString;
