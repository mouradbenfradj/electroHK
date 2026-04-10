<?php

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$setup = dirname(__DIR__);
$wooDir = $setup . '/wordpresswoocomerce';
$csvDir = __DIR__ . '/csv';
$now = date('Y-m-d H:i:s');

list($catHeader, $catRows) = load_csv_assoc($csvDir . '/category.csv');
list($pcHeader, $pcRows) = load_csv_assoc($csvDir . '/product_category.csv');
list($prodHeader, $prodRows) = load_csv_assoc($csvDir . '/product.csv');

if (!$catHeader || !$pcHeader || !$prodHeader) {
    fwrite(STDERR, "Missing required GPT CSV files.\n");
    exit(1);
}

$productIdSet = array();
foreach ($prodRows as $r) {
    $pid = trim((string)getv($r, 'id', ''));
    if ($pid !== '') $productIdSet[$pid] = true;
}

// Build product_cat map from Woo term_taxonomy
$ttMap = array(); // tt_id => [term_id,parent]
csv_each_assoc($wooDir . '/wnew23p_term_taxonomy.csv', function ($r) use (&$ttMap) {
    if ((string)getv($r, 'taxonomy', '') !== 'product_cat') return;
    $ttId = trim((string)getv($r, 'term_taxonomy_id', ''));
    $termId = trim((string)getv($r, 'term_id', ''));
    $parent = trim((string)getv($r, 'parent', '0'));
    if ($ttId === '' || $termId === '') return;
    $ttMap[$ttId] = array('term_id' => $termId, 'parent' => $parent === '' ? '0' : $parent);
});

// Rebuild category.csv from product_cat terms
$newCategories = array();
foreach ($ttMap as $ttId => $v) {
    $termId = $v['term_id'];
    $newCategories[$termId] = array(
        'id' => $termId,
        'parent' => $v['parent'],
        'visible' => '1',
        'position' => $termId,
        'default_template_id' => '',
        'created_at' => $now,
        'updated_at' => $now,
        'version' => '1',
        'version_created_at' => $now,
        'version_created_by' => 'gpt-rebuild'
    );
}
ksort($newCategories, SORT_NUMERIC);
save_csv_assoc($csvDir . '/category.csv', $catHeader, array_values($newCategories));

// Rebuild product_category.csv from Woo relationships for real products in gpt/product.csv
$pairs = array(); // product_id => [category_ids]
csv_each_assoc($wooDir . '/wnew23p_term_relationships.csv', function ($r) use (&$pairs, $ttMap, $productIdSet) {
    $objectId = trim((string)getv($r, 'object_id', ''));
    $ttId = trim((string)getv($r, 'term_taxonomy_id', ''));
    if ($objectId === '' || $ttId === '') return;
    if (!isset($productIdSet[$objectId])) return;
    if (!isset($ttMap[$ttId])) return;
    $catId = $ttMap[$ttId]['term_id'];
    if (!isset($pairs[$objectId])) $pairs[$objectId] = array();
    $pairs[$objectId][$catId] = true;
});

$newPcRows = array();
ksort($pairs, SORT_NUMERIC);
foreach ($pairs as $productId => $cats) {
    $catIds = array_keys($cats);
    sort($catIds, SORT_NUMERIC);
    $pos = 1;
    foreach ($catIds as $idx => $catId) {
        $newPcRows[] = array(
            'product_id' => $productId,
            'category_id' => $catId,
            'default_category' => $idx === 0 ? '1' : '0',
            'position' => (string)$pos++,
            'created_at' => $now,
            'updated_at' => $now
        );
    }
}

save_csv_assoc($csvDir . '/product_category.csv', $pcHeader, $newPcRows);

echo "Rebuilt category.csv rows=" . count($newCategories) . PHP_EOL;
echo "Rebuilt product_category.csv rows=" . count($newPcRows) . PHP_EOL;

function csv_each_assoc($file, $callback)
{
    if (!is_file($file)) return;
    $h = fopen($file, 'rb');
    if (!$h) return;
    $header = fgetcsv($h, 0, ';');
    if ($header === false) {
        fclose($h);
        return;
    }
    $header = array_map('trim_bom', $header);
    while (($row = fgetcsv($h, 0, ';')) !== false) {
        if (count($row) < count($header)) $row = array_pad($row, count($header), '');
        if (count($row) > count($header)) $row = array_slice($row, 0, count($header));
        $assoc = array_combine($header, $row);
        if ($assoc !== false) call_user_func($callback, $assoc);
    }
    fclose($h);
}

function load_csv_assoc($file)
{
    if (!is_file($file)) return array(array(), array());
    $h = fopen($file, 'rb');
    if (!$h) return array(array(), array());
    $header = fgetcsv($h, 0, ';');
    if ($header === false) {
        fclose($h);
        return array(array(), array());
    }
    $header = array_map('trim_bom', $header);
    $rows = array();
    while (($row = fgetcsv($h, 0, ';')) !== false) {
        if (count($row) < count($header)) $row = array_pad($row, count($header), '');
        if (count($row) > count($header)) $row = array_slice($row, 0, count($header));
        $assoc = array_combine($header, $row);
        if ($assoc !== false) $rows[] = $assoc;
    }
    fclose($h);
    return array($header, $rows);
}

function save_csv_assoc($file, $header, $rows)
{
    $h = fopen($file, 'wb');
    fputcsv($h, $header, ';');
    foreach ($rows as $r) {
        $line = array();
        foreach ($header as $c) {
            $line[] = getv($r, $c, '');
        }
        fputcsv($h, $line, ';');
    }
    fclose($h);
}

function getv($arr, $key, $default)
{
    return (is_array($arr) && array_key_exists($key, $arr)) ? $arr[$key] : $default;
}

function trim_bom($v)
{
    return trim((string)$v, "\xEF\xBB\xBF \t\n\r\0\x0B");
}
