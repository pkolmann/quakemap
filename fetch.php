<?php

function human_filesize($bytes, $decimals = 2) {
  $sz = 'BKMGTP';
  $factor = floor((strlen($bytes) - 1) / 3);
  return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
}

$url = 'http://geoweb.zamg.ac.at/static/event/lastmonth.json';
#$url = 'http://geoweb.zamg.ac.at/static/event/lastweek.json';
$file = __DIR__.'/lastmonth.json';
$log = __DIR__.'/log.txt';
$headers = get_headers($url, true);
$lastMod = strtotime($headers['Last-Modified']);
if (file_exists($file)) {
    $lastModLocal = filemtime($file);
}

$lastModLocal = 0;
if ($lastModLocal < $lastMod) {
    $data = file_get_contents($url);
    if (json_decode($data)) {
        file_put_contents($log, date("Y-m-d H:i:s").": Download OK, ".human_filesize(strlen($data))."\n", FILE_APPEND);
        if (checkGeoJSON($data)) {
            file_put_contents($log, "    Reordered coords.\n", FILE_APPEND);
        }
        file_put_contents($file, $data);
    } else {
        file_put_contents($log, date("Y-m-d H:i:s").": Download failed\n", FILE_APPEND);
    }
} else {
        file_put_contents($log, date("Y-m-d H:i:s").": No Download needed\n", FILE_APPEND);
}

file_put_contents($log, "    last mod rem: ".date("Y-m-d H:i:s\n", $lastMod), FILE_APPEND);
file_put_contents($log, "    last mod loc: ".date("Y-m-d H:i:s\n", $lastModLocal), FILE_APPEND);
file_put_contents($log, "------------------\n", FILE_APPEND);


function checkGeoJSON(& $data) {
    $json = json_decode($data, true);
    if (!array_key_exists('features', $json)) {
        return false;
    }

    $max = [0, 0];
    foreach ($json['features'] as $f) {
        if (!array_key_exists('geometry', $f) || !array_key_exists('coordinates', $f['geometry'])) {
            continue;
        }
        if ($f['geometry']['coordinates'][0] > $max[0]) {
            $max[0] = $f['geometry']['coordinates'][0];
        }
        if ($f['geometry']['coordinates'][1] > $max[1]) {
            $max[1] = $f['geometry']['coordinates'][1];
        }
    }

    if ($max[0] < $max[1]) {
        # need to switch data
        foreach ($json['features'] as $i => $f) {
            $x = $json['features'][$i]['geometry']['coordinates'][0];
            $json['features'][$i]['geometry']['coordinates'][0] = $json['features'][$i]['geometry']['coordinates'][1];
            $json['features'][$i]['geometry']['coordinates'][1] = $x;
        }
        $data = json_encode($json);
        return true;
    }
    return false;
}
