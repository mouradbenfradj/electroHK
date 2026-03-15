<?php

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
$importDir = $root . '/import';
$gptCsvDir = __DIR__ . '/csv';
$now = date('Y-m-d H:i:s');

$materials = read_csv_rows($importDir . '/materials.csv', false);
$colors = read_csv_rows($importDir . '/colors.csv', false);

ensure_template_seed($gptCsvDir, $now);
ensure_feature_seed($gptCsvDir, $materials, $now);
ensure_attribute_seed($gptCsvDir, $colors, $now);
ensure_template_links($gptCsvDir, $now);
patch_products_template_id($gptCsvDir);

echo "Patch from setup/import completed.\n";

function ensure_template_seed($dir, $now)
{
    list($h, $rows) = load_csv($dir . '/template.csv');
    if (!$h) return;
    if (!has_data_rows($rows)) {
        $rows = array(array(
            'id' => 1,
            'created_at' => $now,
            'updated_at' => $now
        ));
        save_csv($dir . '/template.csv', $h, $rows);
    }

    list($h2, $rows2) = load_csv($dir . '/template_i18n.csv');
    if (!$h2) return;
    if (!has_data_rows($rows2)) {
        $rows2 = array(
            array('id' => 1, 'locale' => 'fr_FR', 'name' => 'Template par défaut'),
            array('id' => 1, 'locale' => 'en_US', 'name' => 'Default template')
        );
        save_csv($dir . '/template_i18n.csv', $h2, $rows2);
    }
}

function ensure_feature_seed($dir, $materials, $now)
{
    list($h, $rows) = load_csv($dir . '/feature.csv');
    if (!$h) return;
    if (!has_data_rows($rows)) {
        $rows = array(array('id' => 1, 'visible' => 1, 'position' => 1, 'created_at' => $now, 'updated_at' => $now));
        save_csv($dir . '/feature.csv', $h, $rows);
    }

    list($h2, $rows2) = load_csv($dir . '/feature_i18n.csv');
    if ($h2 && !has_data_rows($rows2)) {
        $rows2 = array(
            array('id' => 1, 'locale' => 'fr_FR', 'title' => 'Matière', 'description' => '', 'chapo' => '', 'postscriptum' => ''),
            array('id' => 1, 'locale' => 'en_US', 'title' => 'Material', 'description' => '', 'chapo' => '', 'postscriptum' => '')
        );
        save_csv($dir . '/feature_i18n.csv', $h2, $rows2);
    }

    list($h3, $rows3) = load_csv($dir . '/feature_av.csv');
    if ($h3 && !has_data_rows($rows3)) {
        $id = 1;
        $out = array();
        foreach ($materials as $mat) {
            $t = trim((string)val($mat, 0, ''));
            if ($t === '') continue;
            $out[] = array('id' => $id, 'feature_id' => 1, 'position' => $id, 'created_at' => $now, 'updated_at' => $now);
            $id++;
        }
        save_csv($dir . '/feature_av.csv', $h3, $out);
    }

    list($h4, $rows4) = load_csv($dir . '/feature_av_i18n.csv');
    if ($h4 && !has_data_rows($rows4)) {
        $id = 1;
        $out = array();
        foreach ($materials as $mat) {
            $fr = trim((string)val($mat, 0, ''));
            $en = trim((string)val($mat, 1, $fr));
            if ($fr === '') continue;
            $out[] = array('id' => $id, 'locale' => 'fr_FR', 'title' => $fr, 'description' => '', 'chapo' => '', 'postscriptum' => '');
            $out[] = array('id' => $id, 'locale' => 'en_US', 'title' => $en, 'description' => '', 'chapo' => '', 'postscriptum' => '');
            $id++;
        }
        save_csv($dir . '/feature_av_i18n.csv', $h4, $out);
    }
}

function ensure_attribute_seed($dir, $colors, $now)
{
    list($h, $rows) = load_csv($dir . '/attribute.csv');
    if (!$h) return;
    if (!has_data_rows($rows)) {
        $rows = array(array('id' => 1, 'position' => 1, 'created_at' => $now, 'updated_at' => $now));
        save_csv($dir . '/attribute.csv', $h, $rows);
    }

    list($h2, $rows2) = load_csv($dir . '/attribute_i18n.csv');
    if ($h2 && !has_data_rows($rows2)) {
        $rows2 = array(
            array('id' => 1, 'locale' => 'fr_FR', 'title' => 'Couleur', 'description' => '', 'chapo' => '', 'postscriptum' => ''),
            array('id' => 1, 'locale' => 'en_US', 'title' => 'Colors', 'description' => '', 'chapo' => '', 'postscriptum' => '')
        );
        save_csv($dir . '/attribute_i18n.csv', $h2, $rows2);
    }

    list($h3, $rows3) = load_csv($dir . '/attribute_av.csv');
    if ($h3 && !has_data_rows($rows3)) {
        $id = 1;
        $out = array();
        foreach ($colors as $c) {
            $t = trim((string)val($c, 0, ''));
            if ($t === '') continue;
            $out[] = array('id' => $id, 'attribute_id' => 1, 'position' => $id, 'created_at' => $now, 'updated_at' => $now);
            $id++;
        }
        save_csv($dir . '/attribute_av.csv', $h3, $out);
    }

    list($h4, $rows4) = load_csv($dir . '/attribute_av_i18n.csv');
    if ($h4 && !has_data_rows($rows4)) {
        $id = 1;
        $out = array();
        foreach ($colors as $c) {
            $fr = trim((string)val($c, 0, ''));
            $en = trim((string)val($c, 1, $fr));
            if ($fr === '') continue;
            $out[] = array('id' => $id, 'locale' => 'fr_FR', 'title' => $fr, 'description' => '', 'chapo' => '', 'postscriptum' => '');
            $out[] = array('id' => $id, 'locale' => 'en_US', 'title' => $en, 'description' => '', 'chapo' => '', 'postscriptum' => '');
            $id++;
        }
        save_csv($dir . '/attribute_av_i18n.csv', $h4, $out);
    }
}

function ensure_template_links($dir, $now)
{
    list($h, $rows) = load_csv($dir . '/feature_template.csv');
    if ($h && !has_data_rows($rows)) {
        $rows = array(array('id' => 1, 'feature_id' => 1, 'template_id' => 1, 'position' => 1, 'created_at' => $now, 'updated_at' => $now));
        save_csv($dir . '/feature_template.csv', $h, $rows);
    }

    list($h2, $rows2) = load_csv($dir . '/attribute_template.csv');
    if ($h2 && !has_data_rows($rows2)) {
        $rows2 = array(array('id' => 1, 'attribute_id' => 1, 'template_id' => 1, 'position' => 1, 'created_at' => $now, 'updated_at' => $now));
        save_csv($dir . '/attribute_template.csv', $h2, $rows2);
    }
}

function patch_products_template_id($dir)
{
    $path = $dir . '/product.csv';
    list($h, $rows) = load_csv($path);
    if (!$h || !has_data_rows($rows)) return;
    $out = array();
    foreach ($rows as $r) {
        if (trim((string)getv($r, 'template_id', '')) === '') {
            $r['template_id'] = '1';
        }
        $out[] = $r;
    }
    save_csv($path, $h, $out);
}

function read_csv_rows($path, $hasHeader)
{
    $rows = array();
    if (!is_file($path)) return $rows;
    $h = fopen($path, 'rb');
    if (!$h) return $rows;
    if ($hasHeader) fgetcsv($h, 0, ';');
    while (($r = fgetcsv($h, 0, ';')) !== false) $rows[] = $r;
    fclose($h);
    return $rows;
}

function load_csv($path)
{
    if (!is_file($path)) return array(array(), array());
    $h = fopen($path, 'rb');
    if (!$h) return array(array(), array());
    $header = fgetcsv($h, 0, ';');
    if ($header === false) {
        fclose($h);
        return array(array(), array());
    }
    $header = array_map('trim_bom', $header);
    $rows = array();
    while (($r = fgetcsv($h, 0, ';')) !== false) {
        if (count($r) < count($header)) $r = array_pad($r, count($header), '');
        if (count($r) > count($header)) $r = array_slice($r, 0, count($header));
        $rows[] = array_combine($header, $r);
    }
    fclose($h);
    return array($header, $rows);
}

function has_data_rows($rows)
{
    foreach ($rows as $r) {
        foreach ($r as $v) {
            if (trim((string)$v) !== '') return true;
        }
    }
    return false;
}

function save_csv($path, $header, $rows)
{
    $h = fopen($path, 'wb');
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

function getv($a, $k, $d)
{
    return (is_array($a) && array_key_exists($k, $a)) ? $a[$k] : $d;
}

function trim_bom($v)
{
    return trim((string)$v, "\xEF\xBB\xBF \t\n\r\0\x0B");
}

function val($a, $i, $d)
{
    return (is_array($a) && array_key_exists($i, $a)) ? $a[$i] : $d;
}
