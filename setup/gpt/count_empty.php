<?php
$d = __DIR__ . '/csv';
$it = scandir($d);
$empty = array();
foreach ($it as $f) {
    if (substr($f, -4) !== '.csv') {
        continue;
    }
    $h = fopen($d . '/' . $f, 'r');
    if (!$h) {
        continue;
    }
    fgetcsv($h, 0, ';');
    $n = 0;
    while (($r = fgetcsv($h, 0, ';')) !== false) {
        $blank = true;
        foreach ($r as $v) {
            if (trim((string)$v) !== '') {
                $blank = false;
                break;
            }
        }
        if (!$blank) {
            $n++;
        }
    }
    fclose($h);
    if ($n === 0) {
        $empty[] = $f;
    }
}
echo "empty=" . count($empty) . PHP_EOL;
foreach ($empty as $e) {
    echo $e . PHP_EOL;
}
