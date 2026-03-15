<?php

/**
 * Extraction des slides RevSlider de WordPress vers un CSV Thelia carousel
 * Table carousel: id, file, position, url, created_at, updated_at
 * Table carousel_i18n: id, locale, alt, title, description, chapo, postscriptum
 */

$wooDir      = __DIR__ . '/../wordpresswoocomerce/';
$electroDir  = __DIR__ . '/../electrohajkacem/';
$outDir      = __DIR__ . '/';
$imagesDir   = __DIR__ . '/images/';
$carouselCsv = $outDir . 'carousel.csv';
$i18nCsv     = $outDir . 'carousel_i18n.csv';

if (!is_dir($imagesDir)) {
    mkdir($imagesDir, 0755, true);
}

echo "=== Construction du carousel Thelia depuis RevSlider WooCommerce ===\n\n";

// 1. Lire les sliders
echo "Lecture des sliders RevSlider...\n";
$slidersFile = fopen($wooDir . 'wnew23p_revslider_sliders.csv', 'r');
$sliderHeader = fgetcsv($slidersFile, 0, ';');
$sliderHeader[0] = ltrim($sliderHeader[0], "\xEF\xBB\xBF");
$sliders = array();
while (($row = fgetcsv($slidersFile, 0, ';')) !== false) {
    if (count($row) >= 2) {
        $sliders[$row[0]] = $row[1];
        echo "  Slider #" . $row[0] . ": " . $row[1] . "\n";
    }
}
fclose($slidersFile);

// 2. Lire les slides ligne par ligne (fichier texte brut pour retrouver chaque ligne/slide)
echo "\nExtraction des slides depuis revslider_slides.csv...\n";

$rawContent = file($wooDir . 'wnew23p_revslider_slides.csv', FILE_IGNORE_NEW_LINES);
$slides = array();

// Sauter l'en-tête
for ($i = 1; $i < count($rawContent); $i++) {
    $line = $rawContent[$i];

    // Extraire les premiers champs (id;slider_id;slide_order) depuis le debut
    $firstFields = array();
    preg_match('/^(\d+);(\d+);(\d+);/', $line, $m);
    if (!isset($m[3])) continue;

    $slideId    = $m[1];
    $sliderId   = $m[2];
    $slideOrder = $m[3];

    // Extraire l'image du background depuis params (JSON encodé en double-quote CSV)
    // Pattern: ""image"":""https:\/\/...filename.ext""
    $imageUrl = '';
    $imageFile = '';
    if (preg_match('/""image"":""(https?:[^""]+\.(jpg|jpeg|png|webp))""/i', $line, $imgMatch)) {
        $imageUrl  = str_replace('\/', '/', $imgMatch[1]);
        $imageFile = basename(parse_url($imageUrl, PHP_URL_PATH));
    }

    if (empty($imageUrl)) {
        echo "  Slide #$slideId (Slider $sliderId): pas d'image de fond, ignoré\n";
        continue;
    }

    // Extraire alt depuis les layers
    $alt = '';
    if (preg_match('/alt=\\\\"([^\\\\"]+)\\\\"/u', $line, $altMatch)) {
        $alt = html_entity_decode($altMatch[1], ENT_QUOTES, 'UTF-8');
    }

    // Extraire title depuis les layers
    $title = '';
    if (preg_match('/title=\\\\"([^\\\\"]+)\\\\"/u', $line, $titleMatch)) {
        $title = html_entity_decode($titleMatch[1], ENT_QUOTES, 'UTF-8');
    }

    // Extraire URL du bouton (href)
    $url = '';
    if (preg_match('/href=\\\\"(https?:[^\\\\"]+)\\\\"/u', $line, $urlMatch)) {
        $url = str_replace('\/', '/', $urlMatch[1]);
        $url = str_replace('https://electrohadjkacem.com', '', $url);
        $url = str_replace('https://www.electrohadjkacem.com', '', $url);
    }

    $sliderName = isset($sliders[$sliderId]) ? $sliders[$sliderId] : "Slider $sliderId";

    echo "  Slide #$slideId (" . $sliderName . " - ordre:$slideOrder)\n";
    echo "    Image: $imageFile\n";
    if ($title) echo "    Titre: $title\n";
    if ($alt)   echo "    Alt: $alt\n";
    if ($url)   echo "    URL: $url\n";

    $slides[] = array(
        'id'         => $slideId,
        'slider_id'  => $sliderId,
        'slider_name' => $sliderName,
        'order'      => $slideOrder,
        'image_url'  => $imageUrl,
        'image_file' => $imageFile,
        'title'      => $title,
        'alt'        => $alt,
        'url'        => $url,
    );
}

echo "\n  → " . count($slides) . " slides avec image trouvés\n\n";

// 3. Construire index d'images disponibles dans electrohajkacem
echo "Construction de l'index des images disponibles...\n";
$availableImages = array(); // basename => full path

$uploadsDir = $electroDir . 'wp-content/uploads/';
if (is_dir($uploadsDir)) {
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($uploadsDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iter as $file) {
        if (!$file->isFile()) continue;
        $fname = $file->getFilename();
        // Exclure les thumbnails (WP génère NxN)
        if (preg_match('/-\d+x\d+\.(jpg|jpeg|png|webp)$/i', $fname)) continue;
        // Garder la première occurrence par nom
        $lowerName = strtolower($fname);
        if (!isset($availableImages[$lowerName])) {
            $availableImages[$lowerName] = $file->getPathname();
        }
    }
}
echo "  → " . count($availableImages) . " images disponibles\n\n";

// 4. Générer les CSVs
echo "Génération des CSVs Thelia...\n";

$foCarousel = fopen($carouselCsv, 'w');
$foI18n     = fopen($i18nCsv, 'w');

// BOM UTF-8
fwrite($foCarousel, "\xEF\xBB\xBF");
fwrite($foI18n, "\xEF\xBB\xBF");

fputcsv($foCarousel, array('id', 'file', 'position', 'url', 'created_at', 'updated_at'), ';');
fputcsv($foI18n, array('id', 'locale', 'alt', 'title', 'description', 'chapo', 'postscriptum'), ';');

$carouselId = 1;
$foundCount = 0;
$notFoundCount = 0;
$now = date('Y-m-d H:i:s');

foreach ($slides as $slide) {
    $imageFile  = $slide['image_file'];
    $finalFile  = $imageFile;
    $lowerFile  = strtolower($imageFile);

    // Chercher l'image
    if (isset($availableImages[$lowerFile])) {
        $srcPath = $availableImages[$lowerFile];
        $dstPath = $imagesDir . $imageFile;
        if (!file_exists($dstPath)) {
            copy($srcPath, $dstPath);
        }
        $foundCount++;
        echo "  ✓ Copiée: $imageFile\n";
    } else {
        // Chercher par similarité (sans extension)
        $baseName = strtolower(pathinfo($imageFile, PATHINFO_FILENAME));
        $found = false;
        foreach ($availableImages as $lname => $lpath) {
            $lb = strtolower(pathinfo($lname, PATHINFO_FILENAME));
            if ($lb === $baseName) {
                $srcPath = $lpath;
                $ext = pathinfo($lname, PATHINFO_EXTENSION);
                $finalFile = pathinfo($imageFile, PATHINFO_FILENAME) . '.' . $ext;
                $dstPath = $imagesDir . $finalFile;
                if (!file_exists($dstPath)) {
                    copy($srcPath, $dstPath);
                }
                $foundCount++;
                $found = true;
                echo "  ✓ Copiée (ext diff): $imageFile → $finalFile\n";
                break;
            }
        }
        if (!$found) {
            $notFoundCount++;
            echo "  ✗ Non trouvée: $imageFile (URL: " . $slide['image_url'] . ")\n";
        }
    }

    // Ligne carousel
    fputcsv($foCarousel, array(
        $carouselId,
        $finalFile,
        $carouselId,
        $slide['url'],
        $now,
        $now,
    ), ';');

    // Lignes i18n
    $altText = $slide['alt'] ? $slide['alt'] : $slide['title'];
    foreach (array('fr_FR', 'ar_AR') as $locale) {
        fputcsv($foI18n, array(
            $carouselId,
            $locale,
            $altText,
            $slide['title'],
            '',
            '',
            '',
        ), ';');
    }

    $carouselId++;
}

fclose($foCarousel);
fclose($foI18n);

echo "\n=== Résumé ===\n";
echo "  Total slides:            " . count($slides) . "\n";
echo "  Images copiées:          $foundCount\n";
echo "  Images non trouvées:     $notFoundCount\n";
echo "  Fichiers CSV créés:\n";
echo "    - $carouselCsv\n";
echo "    - $i18nCsv\n";
echo "  Images dans: $imagesDir\n";
