<?php
$appDir = dirname(__FILE__);
require_once $appDir . '/config.php';

$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (mysqli_connect_errno()) {
    die("Failed to connect to MySQL: " . mysqli_connect_error());
}

$sources = [];
$res = $db->query("SELECT DISTINCT source FROM quakes ORDER BY source");
while ($row = $res->fetch_row()) {
    $sources[] = $row[0];
}
$res->free();

$results = [];

foreach ($sources as $source) {
    $stmt = $db->prepare(<<<SQL
        SELECT q.quake_id, q.source_id, q.time, q.latitude, q.longitude,
               q.depth, q.magnitude_ml, q.location, q.region, q.comment, q.url, q.author,
               GROUP_CONCAT(m.type, ':', m.value ORDER BY m.type SEPARATOR ', ') AS magnitudes
        FROM quakes q
        LEFT JOIN magnitudes m ON m.quake_id = q.quake_id
        WHERE q.source = ?
        GROUP BY q.quake_id
        ORDER BY q.time DESC
        LIMIT 5
    SQL);
    $stmt->bind_param("s", $source);
    $stmt->execute();
    $res = $stmt->get_result();
    $results[$source] = $res->fetch_all(MYSQLI_ASSOC);
    $res->free();
    $stmt->close();
}

$db->close();

function formatTime(float $ts): string {
    return gmdate('Y-m-d H:i:s', (int)$ts) . ' UTC';
}

function esc(mixed $v): string {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Latest Earthquakes</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 2em;
            background: #f5f5f5;
            color: #222;
        }
        h1 {
            margin-bottom: 0.25em;
        }
        h2 {
            margin-top: 1.5em;
            margin-bottom: 0.5em;
            border-bottom: 2px solid #555;
            padding-bottom: 0.25em;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            background: #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.15);
            margin-bottom: 2em;
        }
        th {
            background: #333;
            color: #fff;
            padding: 8px 10px;
            text-align: left;
            white-space: nowrap;
        }
        td {
            padding: 7px 10px;
            border-bottom: 1px solid #ddd;
            vertical-align: top;
        }
        tr:last-child td {
            border-bottom: none;
        }
        tr:hover td {
            background: #f0f4ff;
        }
        a {
            color: #0055cc;
        }
        .no-data {
            color: #888;
            font-style: italic;
            padding: 1em 0;
        }
        .mag {
            font-weight: bold;
        }
    </style>
</head>
<body>

<h1>Latest Earthquakes</h1>
<p>5 most recent entries per data source. Times in UTC.</p>

<?php foreach ($sources as $source): ?>
    <h2><?= esc($source) ?></h2>
    <?php if (empty($results[$source])): ?>
        <p class="no-data">No data available.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>#</th>
                <th>Time (UTC)</th>
                <th>Magnitude</th>
                <th>All Magnitudes</th>
                <th>Depth (km)</th>
                <th>Latitude</th>
                <th>Longitude</th>
                <th>Location</th>
                <th>Region</th>
                <th>Comment / Title</th>
                <th>Author</th>
                <th>URL</th>
                <th>Source ID</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($results[$source] as $i => $row): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= esc(formatTime((float)$row['time'])) ?></td>
                    <td class="mag"><?= $row['magnitude_ml'] !== null ? esc($row['magnitude_ml']) . ' ML' : '–' ?></td>
                    <td><?= esc($row['magnitudes'] ?? '') ?: '–' ?></td>
                    <td><?= $row['depth'] !== null ? esc($row['depth']) : '–' ?></td>
                    <td><?= esc($row['latitude']) ?></td>
                    <td><?= esc($row['longitude']) ?></td>
                    <td><?= esc($row['location']) ?: '–' ?></td>
                    <td><?= esc($row['region']) ?: '–' ?></td>
                    <td><?= esc($row['comment']) ?: '–' ?></td>
                    <td><?= esc($row['author']) ?: '–' ?></td>
                    <td>
                        <?php if (!empty($row['url'])): ?>
                            <a href="<?= esc($row['url']) ?>" target="_blank" rel="noopener">Link</a>
                        <?php else: ?>
                            –
                        <?php endif; ?>
                    </td>
                    <td><?= esc($row['source_id']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
<?php endforeach; ?>

</body>
</html>

