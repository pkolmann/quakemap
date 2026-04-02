#!/usr/bin/php
<?php

use JetBrains\PhpStorm\NoReturn;

$appDir = dirname(__FILE__);
require_once $appDir . '/config.php';

#[NoReturn]
function printHelp(): void
{
    echo "Usage: fetch.php [options]\n";
    echo "Options:\n";
    echo "  -h, --help      Show this help message\n";
    echo "  -f, --force     Force download even if not modified\n";
    echo "  -d, --debug     Enable debug mode\n";
    exit(0);
}

function getKeyValue($source, $key, $data, $returnNullOnFail = false) {
    if (!isset($data[$key])) {
        if ($returnNullOnFail) {
            return null;
        }
        print "Error fetching $source data: No $key\n";
        print_r($data);
        die(-2);
    }
    return $data[$key];
}

$opt = getopt('dfh:', ['debug', 'force', 'help']);
$FORCE = isset($opt['f']) || isset($opt['force']);
$DEBUG = isset($opt['d']) || isset($opt['debug']);
if (isset($opt['h']) || isset($opt['help'])) {
    printHelp();
}

// connect to database
$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (mysqli_connect_errno()) {
    die("Failed to connect to MySQL: " . mysqli_connect_error());
}

$stats = [
    'Geosphere' => [
        'inserted' => 0,
        'updated' => 0,
        'mag_inserted' => 0,
        'mag_updated' => 0,
    ],
    'USGS' => [
        'inserted' => 0,
        'updated' => 0,
        'mag_inserted' => 0,
        'mag_updated' => 0,
    ]
];

$source = null;
$source_id = null;
$getQuake = $db->prepare(<<<SQL
    SELECT quake_id, time,
           latitude, longitude, depth, magnitude_ml,
           location, region, comment, url, author
    FROM quakes
    WHERE source = ? AND source_id = ?
    LIMIT 1
SQL);


$quake_id = null;
$getMags = $db->prepare(<<<SQL
    SELECT mag_id, type, value
    FROM magnitudes
    WHERE quake_id = ?
SQL);
$getMags->bind_param("i", $quake_id);

$time = null;
$lat = null;
$lng = null;
$depth = null;
$magnitude_ml = null;
$location = null;
$region = null;
$comment = null;
$url = null;
$author = null;
$insertQuake = $db->prepare(<<<SQL
    INSERT INTO quakes (source, source_id, time, latitude, longitude, depth, magnitude_ml,
        location, region, comment, url, author)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
SQL);
$insertQuake->bind_param("ssdddddsssss", $source, $source_id, $time, $lat, $lng, $depth, $magnitude_ml,
    $location, $region, $comment, $url, $author);

$setQuake = $db->prepare(<<<SQL
    UPDATE quakes
    SET time = ?, latitude = ?, longitude = ?, depth = ?, magnitude_ml = ?,
        location = ?, region = ?, comment = ?, url = ?, author = ?
    WHERE quake_id = ?
SQL);
$setQuake->bind_param("dddddsssssi", $time, $lat, $lng, $depth, $magnitude_ml,
    $location, $region, $comment, $url, $author, $quake_id);

$magType = null;
$magValue = null;
$insertMag = $db->prepare(<<<SQL
    INSERT INTO magnitudes (quake_id, type, value)
    VALUES (?, ?, ?)
SQL);

$mag_id = null;
$setMag = $db->prepare(<<<SQL
    UPDATE magnitudes
    SET type = ?, value = ?
    WHERE mag_id = ?
SQL);

function fetchQuake($getQuake, $source, $source_id) {
    $getQuake->bind_param("ss", $source, $source_id);
    $getQuake->execute();
    $result = $getQuake->get_result();
    if ($result->num_rows == 0) {
        $result->free();
        return null;
    }
    $row = $result->fetch_assoc();
    $result->free();
    return $row;
}

function fetchMags($getMags, $quake_id) {
    $getMags->bind_param("s", $quake_id);
    $getMags->execute();
    $result = $getMags->get_result();
    if ($result->num_rows == 0) {
        $result->free();
        return null;
    }
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
    return $rows;
}

function processQuake(
    $DEBUG,
    $db,
    $getQuake,
    $setQuake,
    $insertQuake,
    $getMags,
    $setMag,
    $insertMag,
    $source,
    $source_id,
    $time,
    $lat,
    $lng,
    $depth,
    $magType,
    $magnitude_ml,
    $magnitudes,
    $location,
    $region,
    $comment,
    $url,
    $author,
    &$stats
): void
{
    $stored = fetchQuake($getQuake, $source, $source_id);
    if ($stored) {
        $update = false;
        $quake_id = $stored['quake_id'];
        if ($stored['time'] != $time) {
            print "Time changed from {$stored['time']} to $time\n";
            $update = true;
        }
        if ($stored['latitude'] != $lat) {
            print "Latitude changed from {$stored['latitude']} to $lat\n";
            $update = true;
        }
        if ($stored['longitude'] != $lng) {
            print "Longitude changed from {$stored['longitude']} to $lng\n";
            $update = true;
        }
        if ($stored['depth'] != $depth) {
            print "Depth changed from {$stored['depth']} to $depth\n";
            $update = true;
        }
        if ($stored['magnitude_ml'] != $magnitude_ml) {
            print "Magnitude (ML) changed from {$stored['magnitude_ml']} to $magnitude_ml\n";
            $update = true;
        }
        if ($stored['location'] != $location) {
            print "Location changed from {$stored['location']} to $location\n";
            $update = true;
        }
        if ($stored['region'] != $region) {
            print "Region changed from {$stored['region']} to $region\n";
            $update = true;
        }
        if ($stored['comment'] != $comment) {
            print "Comment changed from {$stored['comment']} to $comment\n";
            $update = true;
        }
        if ($stored['url'] != $url) {
            print "URL changed from {$stored['url']} to $url\n";
            $update = true;
        }
        if ($stored['author'] != $author) {
            print "Author changed from {$stored['author']} to $author\n";
            $update = true;
        }
        if ($update) {
            $result = $setQuake->execute();
            if (!$result) {
                print "Error updating Quake:\n";
                print $setQuake->error;
                print "\n\n";
                exit(-3);
            }
            $stats[$source]['updated']++;
        }
    } else {
        if ($DEBUG) {
            print "Inserting Geosphere data:\n";
            print <<<SQL
                INSERT INTO quakes (source, source_id, time, latitude, longitude, depth, magnitude_ml,
                    location, region, comment, url, author)
                VALUES ('$source', '$source_id', $time, $lat, $lng, $depth, $magnitude_ml,
                    '$location', '$region', '$comment', '$url', '$author')
            SQL;
        }
        $result = $insertQuake->execute();
        if (!$result) {
            print "Error inserting Quake:\n";
            print $insertQuake->error;
            print "\n\n";
            exit(-4);
        }
        $stats[$source]['inserted']++;
        $quake_id = $db->insert_id;
    }

    if (is_array($magnitudes)) {
        $storedMags = fetchMags($getMags, $quake_id);

        if (is_array($storedMags)) {
            foreach ($magnitudes as $mkey => $magnitude) {
                foreach ($storedMags as $storedMag) {
                    if ($magnitude[1] == $storedMag['type']) {
                        if ($magnitude[0] != $storedMag['value']) {
                            print "Magnitude {$storedMag['type']} changed from {$storedMag['value']} to {$magnitude[0]}\n";
                            $mag_id = $storedMag['mag_id'];
                            $magType = $magnitude[1];
                            $magValue = $magnitude[0];
                            $setMag->bind_param("sdi", $magType, $magValue, $mag_id);
                            $result = $setMag->execute();
                            if (!$result) {
                                print "Error updating Magnitude:\n";
                                print $setMag->error;
                                print "\n\n";
                                exit(-5);
                            }
                            $stats[$source]['mag_updated']++;
                        }
                        unset($storedMags[$magType]);
                        unset($magnitudes[$mkey]);
                    }
                }
            }
        }
        foreach ($magnitudes as $magnitude) {
            $magType = $magnitude[1];
            $magValue = $magnitude[0];
            $insertMag->bind_param("isd", $quake_id, $magType, $magValue);
            $result = $insertMag->execute();
            if (!$result) {
                print "Error updating Magnitude:\n";
                print $insertMag->error;
                print "\n\n";
                exit(-6);
            }
            $stats[$source]['mag_inserted']++;
        }
    }
}

function fetchData($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $data = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($data, true);

    if (is_null($data)) {
        print "Error fetching Geosphere data:\n";
        print json_last_error_msg();
        print "\n\n";
        return null;
    }
    return $data;
}

# ================================================
# Import Geosphere Data
$data = fetchData("https://www.geosphere.at/data/earthquakes");
if (is_null($data)) {
    goto usgs;
}

$source = 'Geosphere';
foreach ($data as $row) {
    $source_id = getKeyValue($source, 'event_id', $row);
    $time = strtotime(getKeyValue($source, 'datetime_utc', $row));
    $lat = getKeyValue($source, 'lat', $row);
    $lng = getKeyValue($source, 'lon', $row);
    $depth = getKeyValue($source, 'depth', $row);
    $magnitudes = getKeyValue($source, 'magnitudes', $row);
    $magnitude_ml = null;
    if (is_array($magnitudes)) {
        foreach ($magnitudes as $magnitude) {
            if ($magnitude[1] == 'ml') {
                $magnitude_ml = $magnitude[0];
                break;
            }
        }
    }
    $location = getKeyValue($source, 'epicenter', $row);
    $region = getKeyValue($source, 'region', $row);
    $comment = getKeyValue($source, 'maptitle', $row);
    $url = null;
    $author = getKeyValue($source, 'author', $row);

    processQuake(
        $DEBUG,
        $db,
        $getQuake,
        $setQuake,
        $insertQuake,
        $getMags,
        $setMag,
        $insertMag,
        $source,
        $source_id,
        $time,
        $lat,
        $lng,
        $depth,
        $magType,
        $magnitude_ml,
        $magnitudes,
        $location,
        $region,
        $comment,
        $url,
        $author,
        $stats
    );
}

# ================================================
# Import USGS data
usgs:
$data = fetchData('https://earthquake.usgs.gov/earthquakes/feed/v1.0/summary/all_month.geojson');
if (is_null($data)) {
    goto finish;
}

$source = 'USGS';
if (!isset($data['features'])) {
    print "Error fetching USGS data: no features\n";
    goto finish;
}
foreach ($data['features'] as $row) {
    if (!isset($row['properties']) || !isset($row['geometry'])) {
        print "Error fetching USGS data: no properties or geometry\n";
        print_r($row);
        continue;
    }
    $source_id = getKeyValue($source, 'id', $row);
    $time = getKeyValue($source, 'time', $row['properties']) / 1000;
    $lat = null;
    $lng = null;
    $depth = null;

    $geometry = getKeyValue($source, 'geometry', $row);
    $lat = null;
    $lng = null;
    $depth = null;
    if (!is_null($geometry)) {
        $coordinates = getKeyValue($source, 'coordinates', $geometry);
        if (is_array($coordinates)) {
            if (isset($coordinates[0])) {
                $lat = $coordinates[0];
            }
            if (isset($coordinates[1])) {
                $lng = $coordinates[1];
            }
            if (isset($coordinates[2])) {
                $depth = $coordinates[2];
            }
        } else {
            print "Error fetching USGS data: Coordinates is not an array\n";
            print_r($coordinates);
            die();
        }
    }
    if (is_null($lat) || is_null($lng)) {
        print "Error fetching USGS data: No coordinates found.\n";
        print_r($row);
        die();
    }

    $magnitude_ml = null;
    $magType = getKeyValue($source, 'magType', $row['properties'], true);
    $magValue = getKeyValue($source, 'mag', $row['properties'], true);
    $magnitudes = [];
    if (!is_null($magType)) {
        $magnitudes = [[$magValue, $magType]];
        if ($magType == 'ml') {
            $magnitude_ml = $magValue;
        }
    }

    $location = null;
    $region = null;
    $comment = getKeyValue($source, 'title', $row['properties']);
    $url = getKeyValue($source, 'url', $row['properties']);
    $author = getKeyValue($source, 'sources', $row['properties']);

    processQuake(
        $DEBUG,
        $db,
        $getQuake,
        $setQuake,
        $insertQuake,
        $getMags,
        $setMag,
        $insertMag,
        $source,
        $source_id,
        $time,
        $lat,
        $lng,
        $depth,
        $magType,
        $magnitude_ml,
        $magnitudes,
        $location,
        $region,
        $comment,
        $url,
        $author,
        $stats
    );
}

finish:
print_r($stats);