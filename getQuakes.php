<?php
$appDir = dirname(__FILE__);
require_once $appDir . '/config.php';
header('Content-type: application/json');

// for options request, return CORS headers
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    }
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
        header('Access-Control-Allow-Headers: ' . $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']);
    }
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    exit(0);
}

// connect to database
$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (mysqli_connect_errno()) {
    die("Failed to connect to MySQL: " . mysqli_connect_error());
}

$WHERE = "";
if (isset($_GET['source'])) {
    $WHERE = "      AND source = '" . $db->real_escape_string($_GET['source']) . "'";
}

$INTERVAL = 35;
if (isset($_GET['interval'])) {
    $INTERVAL = intval($db->real_escape_string(($_GET['interval'])));
}
if ($INTERVAL < 1 || $INTERVAL > 365) {
    $INTERVAL = 35;
}

$query = <<<SQL
    WITH mags AS (SELECT quake_id, GROUP_CONCAT('"', magnitudes.type, '":', magnitudes.value) AS magnitudes
                  FROM magnitudes
                  GROUP BY quake_id)
    SELECT q.quake_id,
           source,
           source_id,
           time,
           FROM_UNIXTIME(ROUND(time)) AS time_utc,
           latitude,
           longitude,
           depth,
           magnitude_ml,
           location,
           region,
           comment,
           url,
           author,
           IF (m.magnitudes IS NOT NULL, CONCAT('{', m.magnitudes, '}'), null) AS magnitudes
    FROM quakes q
        LEFT JOIN mags m ON q.quake_id = m.quake_id
    WHERE time > UNIX_TIMESTAMP(SUBDATE(NOW(), INTERVAL $INTERVAL DAY))
    $WHERE
    ORDER BY time DESC;
SQL;

$result = $db->query($query);
if (!$result) {
    print json_encode([
        'success' => false,
        'error' => 'Failed to retrieve quakes: ' . $db->error
    ]);
    die();
}

$features = [];
while ($row = $result->fetch_assoc()) {
    $features[] = [
        'type' => 'Feature',
        'geometry' => [
            'type' => 'Point',
            'coordinates' => [
                floatval($row['latitude']),
                floatval($row['longitude']),
                floatval($row['depth'])
            ]
        ],
        'properties' => [
            'quake_id' => intval($row['quake_id']),
            'source' => $row['source'],
            'source_id' => $row['source_id'],
            'time' => floatval($row['time']),
            'time_utc' => $row['time_utc'],
            'magnitude_ml' => floatval($row['magnitude_ml']),
            'location' => $row['location'],
            'region' => $row['region'],
            'comment' => $row['comment'],
            'url' => $row['url'],
            'author' => $row['author'],
            'magnitudes' => json_decode($row['magnitudes']),
        ],
    ];
}

$data = [
    'type' => 'FeatureCollection',
    'features' => $features
];

print json_encode($data, JSON_PRETTY_PRINT);
