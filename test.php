<?php

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
