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
$features[] = $feature;

// Put the features into a GeoJSON array and convert to JSON
$geojson = Array(
    'type' => 'FeatureCollection',
    'features' => $features
);

$jsonString = json_encode($geojson);

print $jsonString;
