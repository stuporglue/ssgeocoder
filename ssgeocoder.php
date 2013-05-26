<?php

/**
 * The Stupid Simple Geocoder
 *
 * @class ssgeocoder
 *
 * @copyright Copyright 2013, Michael Moore <stuporglue@gmail.com>
 *
 * @license GNU GPL Version 3
 */
class ssgeocoder {
    var $sqlite;
    var $boundSelect;
    var $boundInsert;

    /**
     * @brief Initialize database connection (and database, if needed)
     *
     * @param $database (optional) The file name for the sqlite3 database
     */
    public function __construct($database = NULL){
        try {
            if(is_null($database)){
                $this->sqlite = new PDO('sqlite:'.__DIR__.'/ssgeocoder.sqlite3');
            }else{
                $this->sqlite = new PDO('sqlite:' . $database);
            }
        } catch (Exception $e){
            error_log($e->getMessage());
        }

        try {
            if(isset($this->sqlite)){
                $this->sqlite->exec('CREATE TABLE IF NOT EXISTS  geocoded (
                    id INTEGER PRIMARY KEY NOT NULL UNIQUE,
                    name VARCHAR,
                    fully_qualified_name VARCHAR,
                    lat NUMERIC,
                    lng NUMERIC
                )');
                $this->sqlite->exec('CREATE TABLE IF NOT EXISTS couldntfind (
                    id INTEGER PRIMARY KEY NOT NULL UNIQUE,
                    name VARCHAR,
                    lasttry INTEGER 
                )');

                $this->boundFailInsert = $this->sqlite->prepare("INSERT INTO couldntfind (name,lasttry) VALUES (:name,:lasttry)");
                $this->boundFailSelect = $this->sqlite->prepare("SELECT name,lasttry FROM couldntfind WHERE name LIKE :name");
                $this->boundSelect = $this->sqlite->prepare("SELECT fully_qualified_name,lat,lng FROM geocoded WHERE name LIKE :name LIMIT 1");
                $this->boundInsert = $this->sqlite->prepare("INSERT INTO geocoded (name,fully_qualified_name,lat,lng) VALUES (:name,:fully_qualified_name,:lat,:lng)");
            }
        } catch (Exception $e){
            error_log($e->getMessage());
        }
    }


    /**
     * @brief Make a hash of GeoJSON features from an array of names
     *
     * @param $placename (Array|String) An string or array of strings to geocode (city level or higher)
     *
     * @note Places not found are not returned!
     *
     * @return If an array of places is passed, a possibly empty array of GeoJSON features is returned. 
     * If a string is passed a single feature or FALSE is returned
     */
    public function geocode($placenames){
        // Make strings to arrays
        $single = FALSE;
        if(!is_array($placenames)){
            $single = TRUE;
            $placenames = Array($placenames);
        }

        // Unique the place names
        $placenames = array_unique($placenames);

        // Results array
        $features = Array();

        // Create/open Sqlite database
        foreach($placenames as $placename){
            // Check database cache for placename
            // If found, add to $features
            if($res = $this->dbLookup($placename)){
                $features[$placename] = $res;
                continue;
            }

            // Try geocoding now
            if($res = $this->_geocode($placename)){
                $features[$placename] = $res;
                continue;
            }
        }

        if($single){
            if(count($features) > 0){
                return array_shift($features);
            }else{
                return FALSE;
            }
        }

        return $features;
    }

    /**
     * @brief Lookup a single name in the database
     *
     * @param $placename (required) The name of the place to look up
     *
     * @return A single GeoJSON feature if found in the database or FALSE
     */
    private function dbLookup($placename){
        try {
            if(isset($this->boundSelect)){
                $this->boundSelect->bindParam(":name",$placename);
                $this->boundSelect->execute();
                if($foundPlace = $this->boundSelect->fetch(PDO::FETCH_LAZY)){
                    // Return an array compatible with a GeoJSON feature
                    return $this->makeFeature($placename,$foundPlace->fully_qualified_name,$foundPlace->lng,$foundPlace->lat);
                }
            }
        } catch (Exception $e){
            console_log($e->getMessage());
        }
        return FALSE;
    }

    /**
     * @brief Use a REST service to geocode a single place name
     *
     * @param $placename (required) The name of a place to try geocoding (city level or higher)
     *
     * @note Inserts the geocoding result (success or failure) into the database
     *
     * @return A single GeoJSON feature on success or FALSE otherwise
     */
    private function _geocode($placename){
        try {
            if(isset($this->boundFailSelect)){
                // Check if we've geocoded them before and failed
                $this->boundFailSelect->bindParam(":name",$placename);
                $this->boundFailSelect->execute();
                if($failure = $this->boundFailSelect->fetch(PDO::FETCH_LAZY)){
                    return FALSE;
                }
            }
        } catch (Exception $e){
            console_log($e->getMessage());
        }

        // Escape the place name the way the geocoder wants it
        $escapedplacename = preg_replace("/,[^ ]/",", ",$placename); // geocoder wants spaces after commas
        $escapedplacename = urlencode($escapedplacename);            // Escape for URL

        // Build the URL
        $url = "http://services.gisgraphy.com/fulltext/fulltextsearch?q=$escapedplacename&placetype=City&placetype=Adm&placetype=Country&placetype=PoliticalEntity&from=1&to=1&format=json";

        // Use file_get_contents for simplicity. 
        $json = file_get_contents($url);
        // Sample response: {responseHeader":{"status":0,"QTime":34},"response":{"numFound":2,"start":0,"maxScore":6.8620453,"docs":[{"feature_id":4997249,"name":"Ironwood","lat":46.45466995239258,"lng":-90.17101287841797,"placetype":"City","country_code":"US","country_flag_url":"/images/flags/US.png","feature_class":"P","feature_code":"PPL","name_ascii":"Ironwood","elevation":459,"gtopo30":459,"timezone":"America/Menominee","population":5387,"fully_qualified_name":"Ironwood, Gogebic County, Michigan","google_map_url":"http://maps.google.com/maps?f=q&amp;ie=UTF-8&amp;iwloc=addr&amp;om=1&amp;z=12&amp;q=Ironwood&amp;ll=46.48466995239258,-90.17101287841797","yahoo_map_url":"http://maps.yahoo.com/broadband?mag=6&amp;mvt=m&amp;lon=-90.17101287841797&amp;lat=46.45466995239258","country_name":"United States","zipcode":["49938","49938"],"score":6.8620453}]},"spellcheck":{"suggestions":[]}}
        $ret = json_decode($json); // turn response into json

        // Check if we got anything. Give up if we didn't
        if($ret->response->numFound == 0){
            try {
                if(isset($this->boundFailInsert)){
                    // There's nothing we can do. Take a note, then carry on 
                    $this->boundFailInsert->bindParam(':name',$placename);
                    $time = time();
                    $this->boundFailInsert->bindParam(':lasttry',$time);
                    $this->boundFailInsert->execute();
                }
            } catch (Exception $e){
                console_log($e->getMessage());
            }
            return FALSE;
        }

        try {
            $foundPlace = $ret->response->docs[0];
            if(isset($this->boundInsert)){
                // Insert the first result
                $this->boundInsert->bindParam(":name",$placename);
                $this->boundInsert->bindParam(":fully_qualified_name",$foundPlace->fully_qualified_name);
                $this->boundInsert->bindParam(":lat",$foundPlace->lat);
                $this->boundInsert->bindParam(":lng",$foundPlace->lng);
                $this->boundInsert->execute();
            }
        } catch (Exception $e){
            console_log($e->getMessage());
        }

        // This is lazy, but we're going to immediately fetch it from the db again.
        return $this->makeFeature($placename,$foundPlace->fully_qualified_name,$foundPlace->lng,$foundPlace->lat);
    }

    /**
     * @brief Format a single feature from a placename and coordinates
     *
     * @param $placename (required) The string which was geocoded
     *
     * @param $lng (required) The longitude
     *
     * @param $lat (required) The latitude
     */
    private function makeFeature($placename,$fully_qualified_name,$lng,$lat){
            return Array(
                'type' => 'Feature',
                'geometry' => Array(
                    'type' => 'Point',
                    'coordinates' => Array(
                        $lng,
                        $lat
                    )
                ),
                'properties' => Array(
                    'placename' => $placename,
                    'fully_qualified_name' => $fully_qualified_name
                )
            );
    }
}
