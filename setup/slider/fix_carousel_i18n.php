<?php
/**
 * Corriger le CSV carousel_i18n avec les titres/alts des slides RevSlider
 */

$wooFile     = '/var/www/html/setup/wordpresswoocomerce/wnew23p_revslider_slides.csv';
$outI18n     = '/var/www/html/setup/slider/carousel_i18n.csv';
$outCarousel = '/var/www/html/setup/slider/carousel.csv';

$bslash = chr(0x5C); // backslash
$dquote = chr(0x22); // double quote
$bsdq   = $bslash . $dquote . $dquote; // \" followed by "

$lines = file($wooFile, FILE_IGNORE_NEW_LINES);
$slides = array();

for ($i = 1; $i < count($lines); $i++) {
    $line = $lines[$i];
    if (!preg_match('/^(\d+);(\d+);(\d+);/', $line, $m)) continue;
    if (!preg_match('/""image"":""(https?:[^""]+\.(jpg|jpeg|png|webp))""/i', $line)) continue;

    $slideId = $m[1];

    // ---------- Extraction alt (layers button) ----------
    $alt = '';
    $pos = strpos($line, 'alt=' . $bsdq);
    if ($pos !== false) {
        $start = $pos + strlen('alt=' . $bsdq);
        $end   = strpos($line, $bsdq, $start);
        if ($end !== false) {
            $alt = substr($line, $start, $end - $start);
            // Décoder \uXXXX
            $alt = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function($mm) {
                return mb_convert_encoding(pack('H*', $mm[1]), 'UTF-8', 'UTF-16BE');
            }, $alt);
        }
    }

    // ---------- Extraction title (layers button) ----------
    $title = '';
    $pos = strpos($line, 'title=' . $bsdq);
    if ($pos !== false) {
        $start = $pos + strlen('title=' . $bsdq);
        $end   = strpos($line, $bsdq, $start);
        if ($end !== false) {
            $title = substr($line, $start, $end - $start);
            $title = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function($mm) {
                return mb_convert_encoding(pack('H*', $mm[1]), 'UTF-8', 'UTF-16BE');
            }, $title);
        }
    }

    // ---------- Extraction texte DuoTone (text layers) ----------
    if (empty($title)) {
        // Le texte des layers DuoTone est dans ""text"":""CONTENT""
        if (preg_match('/""text"":""([^""<]+)/', $line, $tm)) {
            $raw = $tm[1];
            // Nettoyer les HTML
            $raw = preg_replace('/<[^>]+>/', '', $raw);
            $raw = str_replace(array('\\n', '<BR\/>', '<br\/>', '<br>', '<BR>'), ' ', $raw);
            $raw = strip_tags($raw);
            $title = trim($raw);
        }
    }

    // Si alt vide, utiliser title
    if (empty($alt)) $alt = $title;

    // ---------- Extraction URL href ----------
    $url = '';
    $pos = strpos($line, 'href=' . $bsdq);
    if ($pos !== false) {
        $start = $pos + strlen('href=' . $bsdq);
        $end   = strpos($line, $bsdq, $start);
        if ($end !== false) {
            $url = substr($line, $start, $end - $start);
            $url = str_replace('\/', '/', $url);
            $url = str_replace('https://electrohadjkacem.com', '', $url);
            $url = str_replace('https://www.electrohadjkacem.com', '', $url);
        }
    }

    $slides[] = array(
        'id'    => $slideId,
        'alt'   => $alt,
        'title' => $title,
        'url'   => $url,
    );
    echo "Slide #$slideId  title='" . substr($title, 0, 50) . "'  url='$url'\n";
}

echo "\n" . count($slides) . " slides\n\n";

// -------- Mettre à jour carousel_i18n.csv --------
$fh = fopen($outI18n, 'w');
fwrite($fh, "\xEF\xBB\xBF");
fputcsv($fh, array('id', 'locale', 'alt', 'title', 'description', 'chapo', 'postscriptum'), ';');
$cid = 1;
foreach ($slides as $s) {
    foreach (array('fr_FR', 'ar_AR') as $locale) {
        fputcsv($fh, array($cid, $locale, $s['alt'], $s['title'], '', '', ''), ';');
    }
    $cid++;
}
fclose($fh);

// -------- Mettre à jour les URLs dans carousel.csv --------
$urlMap = array();
$cid2 = 1;
foreach ($slides as $s) {
    $urlMap[$cid2++] = $s['url'];
}

$clines = file($outCarousel, FILE_IGNORE_NEW_LINES);
$fo = fopen($outCarousel, 'w');
fwrite($fo, "\xEF\xBB\xBF");
foreach ($clines as $idx => $ln) {
    $ln = ltrim($ln, "\xEF\xBB\xBF");
    $fields = str_getcsv($ln, ';');
    if ($idx === 0) {
        fputcsv($fo, $fields, ';');
        continue;
    }
    $rid = isset($fields[0]) ? (int)$fields[0] : 0;
    if ($rid > 0 && isset($urlMap[$rid]) && $urlMap[$rid]) {
        $fields[3] = $urlMap[$rid];
    }
    fputcsv($fo, $fields, ';');
}
fclose($fo);

echo "Fichiers carousel.csv et carousel_i18n.csv mis à jour!\n";
