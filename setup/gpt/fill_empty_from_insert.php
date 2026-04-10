<?php

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$csvDir = __DIR__ . '/csv';
$dsn = 'mysql:host=mariadb;port=3306;dbname=gpt_seed;charset=utf8';
$user = 'root';
$pass = 'toor';

$pdo = new PDO($dsn, $user, $pass, array(
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
));

$filled = 0;
$stillEmpty = 0;
$totalAddedRows = 0;

$files = scandir($csvDir);
foreach ($files as $file) {
    if (substr($file, -4) !== '.csv') {
        continue;
    }
    $path = $csvDir . '/' . $file;
    if (!is_file($path)) {
        continue;
    }

    $headers = read_header($path);
    if (!$headers) {
        continue;
    }

    if (!is_effectively_empty($path)) {
        continue;
    }

    $table = substr($file, 0, -4);
    $rows = fetch_rows($pdo, $table);
    if (!$rows) {
        $stillEmpty++;
        continue;
    }

    $h = fopen($path, 'wb');
    fputcsv($h, $headers, ';');
    $added = 0;
    foreach ($rows as $r) {
        $line = array();
        foreach ($headers as $c) {
            $v = array_key_exists($c, $r) ? $r[$c] : '';
            if ($v === null) $v = '';
            $line[] = $v;
        }
        fputcsv($h, $line, ';');
        $added++;
    }
    fclose($h);
    $filled++;
    $totalAddedRows += $added;
    echo $table . ': +' . $added . PHP_EOL;
}

echo 'filled_files=' . $filled . PHP_EOL;
echo 'added_rows=' . $totalAddedRows . PHP_EOL;
echo 'remaining_empty=' . $stillEmpty . PHP_EOL;

function read_header($path)
{
    $h = fopen($path, 'rb');
    if (!$h) return array();
    $head = fgetcsv($h, 0, ';');
    fclose($h);
    if ($head === false) return array();
    $out = array();
    foreach ($head as $v) {
        $out[] = trim((string)$v, "\xEF\xBB\xBF \t\n\r\0\x0B");
    }
    return $out;
}

function is_effectively_empty($path)
{
    $h = fopen($path, 'rb');
    if (!$h) return false;
    fgetcsv($h, 0, ';');
    while (($row = fgetcsv($h, 0, ';')) !== false) {
        $blank = true;
        foreach ($row as $v) {
            if (trim((string)$v) !== '') {
                $blank = false;
                break;
            }
        }
        if (!$blank) {
            fclose($h);
            return false;
        }
    }
    fclose($h);
    return true;
}

function fetch_rows($pdo, $table)
{
    $sql = 'SELECT * FROM `' . str_replace('`', '``', $table) . '`';
    $st = $pdo->query($sql);
    return $st->fetchAll();
}
