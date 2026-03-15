<?php
/**
 * Script pour convertir les données WordPress/WooCommerce vers des CSV compatibles Thelia
 */

class WordPressToTheliaConverter {
    private $sqlFile;
    private $posts = [];
    private $terms = [];
    private $termTaxonomy = [];
    private $postmeta = [];
    private $termRelationships = [];
    
    public function __construct($sqlFile) {
        $this->sqlFile = $sqlFile;
    }
    
    /**
     * Extrait les données d'une table SQL
     */
    private function parseSqlTable($tableName) {
        echo "Extraction de la table: $tableName\n";
        
        if (!file_exists($this->sqlFile)) {
            echo "Fichier SQL non trouvé: " . $this->sqlFile . "\n";
            return [];
        }
        
        $content = file_get_contents($this->sqlFile);
        
        // Pattern pour trouver les INSERT dans une table spécifique
        $pattern = "/INSERT INTO `$tableName`.*?VALUES\s*(.+?);/si";
        
        if (!preg_match($pattern, $content, $matches)) {
            echo "Aucune donnée trouvée pour la table $tableName\n";
            return [];
        }
        
        $valuesStr = $matches[1];
        $rows = [];
        
        // Parser les valeurs multi-lignes
        $currentRow = '';
        $parenthesesCount = 0;
        $inQuotes = false;
        $i = 0;
        $len = strlen($valuesStr);
        
        while ($i < $len) {
            $char = $valuesStr[$i];
            
            if ($char === "'" && ($i === 0 || $valuesStr[$i - 1] !== '\\')) {
                $inQuotes = !$inQuotes;
                $currentRow .= $char;
                $i++;
            } elseif (!$inQuotes && $char === '(') {
                $parenthesesCount++;
                $currentRow .= $char;
                $i++;
            } elseif (!$inQuotes && $char === ')') {
                $parenthesesCount--;
                $currentRow .= $char;
                $i++;
                
                if ($parenthesesCount === 0) {
                    // Fin d'une ligne
                    if (preg_match('/^\((.*)\)$/', trim($currentRow), $rowMatch)) {
                        $row = $this->parseInsertValues($rowMatch[1]);
                        if (!empty($row)) {
                            $rows[] = $row;
                        }
                    }
                    $currentRow = '';
                    
                    // Sauter les virgules et espaces
                    while ($i < $len && ($valuesStr[$i] === ',' || $valuesStr[$i] === ' ' || $valuesStr[$i] === "\n")) {
                        $i++;
                    }
                }
            } else {
                $currentRow .= $char;
                $i++;
            }
        }
        
        echo "Table $tableName: " . count($rows) . " enregistrements trouvés\n";
        return $rows;
    }
    
    /**
     * Parse une ligne de valeurs INSERT SQL
     */
    private function parseInsertValues($valuesStr) {
        $values = [];
        $current = '';
        $inQuotes = false;
        $i = 0;
        $len = strlen($valuesStr);
        
        while ($i < $len) {
            $char = $valuesStr[$i];
            
            if ($char === '\\' && $i + 1 < $len) {
                // Caractère échappé
                $current .= $valuesStr[$i + 1];
                $i += 2;
            } elseif ($char === "'" && ($i === 0 || $valuesStr[$i - 1] !== '\\')) {
                // Début/fin de chaîne
                $inQuotes = !$inQuotes;
                $current .= $char;
                $i++;
            } elseif ($char === ',' && !$inQuotes) {
                // Séparateur de valeurs
                $values[] = $this->cleanValue($current);
                $current = '';
                $i++;
            } else {
                $current .= $char;
                $i++;
            }
        }
        
        // Ajouter la dernière valeur
        if (!empty($current)) {
            $values[] = $this->cleanValue($current);
        }
        
        return $values;
    }
    
    /**
     * Nettoie une valeur extraite
     */
    private function cleanValue($value) {
        $value = trim($value);
        
        if ($value === 'NULL') {
            return '';
        }
        
        if (preg_match("/^'(.*)'$/", $value, $matches)) {
            $value = $matches[1];
            // Gérer les échappements
            $value = str_replace("\\'", "'", $value);
            $value = str_replace("\\\"", "\"", $value);
            $value = str_replace("\\\\", "\\", $value);
        }
        
        return $value;
    }
    
    /**
     * Extrait toutes les données des tables WordPress
     */
    public function extractAllData() {
        echo "Début de l'extraction des données WordPress...\n";
        
        $this->posts = $this->parseSqlTable('wp_posts');
        $this->terms = $this->parseSqlTable('wp_terms');
        $this->termTaxonomy = $this->parseSqlTable('wp_term_taxonomy');
        $this->postmeta = $this->parseSqlTable('wp_postmeta');
        $this->termRelationships = $this->parseSqlTable('wp_term_relationships');
        
        echo "Extraction terminée!\n";
    }
    
    /**
     * Récupère les metas d'un post
     */
    private function getPostMetas($postId) {
        $metas = array();
        foreach ($this->postmeta as $meta) {
            if (isset($meta[1]) && $meta[1] == $postId) {
                $key = isset($meta[2]) ? $meta[2] : '';
                $value = isset($meta[3]) ? $meta[3] : '';
                $metas[$key] = $value;
            }
        }
        return $metas;
    }
    
    /**
     * Récupère le nom d'un terme
     */
    private function getTermName($termId) {
        foreach ($this->terms as $term) {
            if (isset($term[0]) && $term[0] == $termId) {
                return isset($term[1]) ? $term[1] : '';
            }
        }
        return '';
    }
    
    /**
     * Récupère les catégories d'un produit
     */
    private function getProductCategories($postId) {
        $categories = array();
        
        foreach ($this->termRelationships as $rel) {
            if (isset($rel[0]) && $rel[0] == $postId) {
                $termTaxonomyId = isset($rel[1]) ? $rel[1] : '';
                
                foreach ($this->termTaxonomy as $tt) {
                    if (isset($tt[0]) && $tt[0] == $termTaxonomyId && (isset($tt[3]) ? $tt[3] : '') === 'category') {
                        $termId = isset($tt[1]) ? $tt[1] : '';
                        $categoryName = $this->getTermName($termId);
                        if ($categoryName) {
                            $categories[] = $categoryName;
                        }
                    }
                }
            }
        }
        
        return $categories;
    }
    
    /**
     * Génère le CSV des catégories
     */
    public function generateCategoriesCsv() {
        echo "Génération du CSV des catégories...\n";
        
        $categories = array();
        $processedTerms = array();
        
        foreach ($this->termTaxonomy as $tt) {
            if (isset($tt[3]) && $tt[3] === 'category') {
                $termId = isset($tt[1]) ? $tt[1] : '';
                
                if (!in_array($termId, $processedTerms)) {
                    $processedTerms[] = $termId;
                    
                    $categoryName = $this->getTermName($termId);
                    $description = '';
                    
                    // Récupérer la description
                    foreach ($this->terms as $term) {
                        if (isset($term[0]) && $term[0] == $termId) {
                            $description = isset($term[3]) ? $term[3] : '';
                            break;
                        }
                    }
                    
                    $categories[] = array(
                        'CATEGORIES FR' => $categoryName,
                        'CATEGORIES UK' => $categoryName,
                        'CHAPO FR' => mb_substr($description, 0, 200, 'UTF-8'),
                        'CHAPO UK' => mb_substr($description, 0, 200, 'UTF-8'),
                        'DESCRIPTIF FR' => $description,
                        'DESCRIPTIF UK' => $description,
                        'PHOTO' => ''
                    );
                }
            }
        }
        
        $this->writeCsv('setup/csvfromsql/categories.csv', $categories);
        echo "Categories CSV généré: " . count($categories) . " catégories\n";
    }
    
    /**
     * Génère le CSV des produits
     */
    public function generateProductsCsv() {
        echo "Génération du CSV des produits...\n";
        
        $products = array();
        
        foreach ($this->posts as $post) {
            if (isset($post[12]) && $post[12] === 'product') {
                $postId = isset($post[0]) ? $post[0] : '';
                $postTitle = isset($post[5]) ? $post[5] : '';
                $postContent = isset($post[11]) ? $post[11] : '';
                $postExcerpt = isset($post[9]) ? $post[9] : '';
                
                $metas = $this->getPostMetas($postId);
                
                // Informations de base
                $sku = array_key_exists('_sku', $metas) ? $metas['_sku'] : 'WP_' . $postId;
                $price = array_key_exists('_price', $metas) ? $metas['_price'] : '0';
                $regularPrice = array_key_exists('_regular_price', $metas) ? $metas['_regular_price'] : $price;
                $salePrice = array_key_exists('_sale_price', $metas) ? $metas['_sale_price'] : '';
                
                // Catégories
                $categories = $this->getProductCategories($postId);
                $categoryPath = !empty($categories) ? implode(' > ', $categories) : 'NON CLASSÉ';
                
                // Marque (attribut)
                $brand = '';
                foreach ($metas as $key => $value) {
                    if (strpos(strtolower($key), 'attribute') !== false && 
                        (strpos(strtolower($key), 'brand') !== false || strpos(strtolower($key), 'marque') !== false)) {
                        $brand = $value;
                        break;
                    }
                }
                
                // Image
                $image = '';
                $thumbnailId = array_key_exists('_thumbnail_id', $metas) ? $metas['_thumbnail_id'] : '';
                if ($thumbnailId) {
                    $thumbnailMetas = $this->getPostMetas($thumbnailId);
                    $imageFile = array_key_exists('_wp_attached_file', $thumbnailMetas) ? $thumbnailMetas['_wp_attached_file'] : '';
                    $image = basename($imageFile);
                }
                
                $products[] = array(
                    'REF' => $sku,
                    'TITRE UK' => $postTitle,
                    'CHAPO UK' => mb_substr($postExcerpt, 0, 200, 'UTF-8'),
                    'CHAPO FR' => mb_substr($postExcerpt, 0, 200, 'UTF-8'),
                    'DESCRIPTIF UK' => $postContent,
                    'DESCRIPTIF FR' => $postContent,
                    'POSTSCRIPTUM UK' => '',
                    'POSTSCRIPTUM FR' => '',
                    'PRIX' => $regularPrice,
                    'PRIX2' => $salePrice,
                    'PHOTO' => $image,
                    'BRAND' => $brand,
                    'COULEUR UK' => '',
                    'MATERIAL UK' => '',
                    'CONTENT UK' => '',
                    'CATEGORIE' => $categoryPath
                );
            }
        }
        
        $this->writeCsv('setup/csvfromsql/products.csv', $products);
        echo "Products CSV généré: " . count($products) . " produits\n";
    }
    
    /**
     * Génère le CSV des marques
     */
    public function generateBrandsCsv() {
        echo "Génération du CSV des marques...\n";
        
        $brands = array();
        
        foreach ($this->posts as $post) {
            if (isset($post[12]) && $post[12] === 'product') {
                $postId = isset($post[0]) ? $post[0] : '';
                $metas = $this->getPostMetas($postId);
                
                foreach ($metas as $key => $value) {
                    if (strpos(strtolower($key), 'attribute') !== false && 
                        (strpos(strtolower($key), 'brand') !== false || strpos(strtolower($key), 'marque') !== false)) {
                        if (!empty($value) && !in_array($value, $brands)) {
                            $brands[] = $value;
                        }
                    }
                }
            }
        }
        
        sort($brands);
        $brandsData = array();
        foreach ($brands as $brand) {
            $brandsData[] = array('brand' => $brand);
        }
        
        $this->writeCsv('setup/csvfromsql/brands.csv', $brandsData);
        echo "Brands CSV généré: " . count($brandsData) . " marques\n";
    }
    
    /**
     * Génère le CSV des couleurs
     */
    public function generateColorsCsv() {
        echo "Génération du CSV des couleurs...\n";
        
        $colors = array();
        
        foreach ($this->posts as $post) {
            if (isset($post[12]) && $post[12] === 'product') {
                $postId = isset($post[0]) ? $post[0] : '';
                $metas = $this->getPostMetas($postId);
                
                foreach ($metas as $key => $value) {
                    if (strpos(strtolower($key), 'attribute') !== false && 
                        (strpos(strtolower($key), 'color') !== false || strpos(strtolower($key), 'couleur') !== false)) {
                        if (!empty($value) && !in_array($value, $colors)) {
                            $colors[] = $value;
                        }
                    }
                }
            }
        }
        
        sort($colors);
        $colorsData = array();
        foreach ($colors as $color) {
            $colorsData[] = array('color' => $color);
        }
        
        $this->writeCsv('setup/csvfromsql/colors.csv', $colorsData);
        echo "Colors CSV généré: " . count($colorsData) . " couleurs\n";
    }
    
    /**
     * Génère le CSV des matériaux
     */
    public function generateMaterialsCsv() {
        echo "Génération du CSV des matériaux...\n";
        
        $materials = array();
        
        foreach ($this->posts as $post) {
            if (isset($post[12]) && $post[12] === 'product') {
                $postId = isset($post[0]) ? $post[0] : '';
                $metas = $this->getPostMetas($postId);
                
                foreach ($metas as $key => $value) {
                    if (strpos(strtolower($key), 'attribute') !== false && 
                        (strpos(strtolower($key), 'material') !== false || 
                         strpos(strtolower($key), 'matiere') !== false || 
                         strpos(strtolower($key), 'matériau') !== false)) {
                        if (!empty($value) && !in_array($value, $materials)) {
                            $materials[] = $value;
                        }
                    }
                }
            }
        }
        
        sort($materials);
        $materialsData = array();
        foreach ($materials as $material) {
            $materialsData[] = array('material' => $material);
        }
        
        $this->writeCsv('setup/csvfromsql/materials.csv', $materialsData);
        echo "Materials CSV généré: " . count($materialsData) . " matériaux\n";
    }
    
    /**
     * Écrit un fichier CSV
     */
    private function writeCsv($filename, $data) {
        if (empty($data)) {
            echo "Aucune donnée à écrire pour $filename\n";
            return;
        }
        
        $file = fopen($filename, 'w');
        if (!$file) {
            echo "Impossible d'ouvrir le fichier $filename\n";
            return;
        }
        
        // Entête
        $headers = array_keys($data[0]);
        fputcsv($file, $headers, ';');
        
        // Données
        foreach ($data as $row) {
            fputcsv($file, $row, ';');
        }
        
        fclose($file);
    }
    
    /**
     * Lance la conversion complète
     */
    public function convertAll() {
        echo "Début de la conversion WordPress vers Thelia...\n";
        echo "Fichier SQL: " . $this->sqlFile . "\n\n";
        
        // Extraire toutes les données
        $this->extractAllData();
        
        echo "\nGénération des fichiers CSV...\n";
        
        // Générer tous les fichiers CSV
        $this->generateCategoriesCsv();
        $this->generateProductsCsv();
        $this->generateBrandsCsv();
        $this->generateColorsCsv();
        $this->generateMaterialsCsv();
        
        echo "\nConversion terminée! Fichiers CSV générés dans setup/csvfromsql/\n";
    }
}

// Exécution du script
$converter = new WordPressToTheliaConverter('setup/uvpxgnzzfh.sql');
$converter->convertAll();
?>
