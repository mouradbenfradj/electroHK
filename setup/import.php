<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/
use Thelia\Model\ContentFolderQuery;
use Thelia\Model\ProductAssociatedContent;

if (php_sapi_name() != 'cli') {
    throw new \Exception('this script can only be launched with cli sapi');
}

$bootstrapToggle = false;
$bootstraped = false;

// Autoload bootstrap

foreach ($argv as $arg) {
    if ($arg === '-b') {
        $bootstrapToggle = true;

        continue;
    }

    if ($bootstrapToggle) {
        require __DIR__ . DIRECTORY_SEPARATOR . $arg;

        $bootstraped = true;
    }
}

if (!$bootstraped) {
    if (isset($bootstrapFile)) {
        require $bootstrapFile;
    } elseif (is_file($file = __DIR__ . '/../core/vendor/autoload.php')) {
        require $file;
    } elseif (is_file($file = __DIR__ . '/../../bootstrap.php')) {
        // Here we are on a thelia/thelia-project
        require $file;
    } else {
        echo "No autoload file found. Please use the -b argument to include yours";
        exit(1);
    }
}

$thelia = new Thelia\Core\Thelia("dev", true);
$thelia->boot();

// Load the translator
$thelia->getContainer()->get("thelia.translator");

$faker = Faker\Factory::create();
// Intialize URL management
$url = new Thelia\Tools\URL();
$con = \Propel\Runtime\Propel::getConnection(
    Thelia\Model\Map\ProductTableMap::DATABASE_NAME
);
$con->beginTransaction();

try {
    $stmt = $con->prepare("SET foreign_key_checks = 0");
    $stmt->execute();
    clearTables($con);
    $stmt = $con->prepare("SET foreign_key_checks = 1");
    $stmt->execute();

    $material = createMaterials($con);

    $color = createColors($con);
    $brands = createBrands($faker, $con);

    $folders = createFolders($faker, $con);
    $contents = createContents($faker, $folders, $con);

    $categories = createCategories($faker, $con);

    echo "creating templates\n";
    $template = new \Thelia\Model\Template();
    $template
        ->setLocale('fr_FR')
            ->setName('template de démo')
        ->setLocale('en_US')
            ->setName('demo template')
        ->save($con);

    $at = new Thelia\Model\AttributeTemplate();

    $at
        ->setTemplate($template)
        ->setAttribute($color)
        ->save($con);

    $ft = new Thelia\Model\FeatureTemplate();

    $ft
        ->setTemplate($template)
        ->setFeature($material)
        ->save($con);
    echo "end creating templates\n";

    createProduct($faker, $categories, $brands, $contents, $template, $color, $material, $con);

    createCustomer($faker, $con);

    // set some config key
    createConfig($faker, $folders, $contents, $con);


    $con->commit();
} catch (Exception $e) {
    echo "error : ".$e->getMessage()."\n";
    $con->rollBack();
}

function createProduct($faker, $categories, $brands, $contents, $template, $attribute, $feature, $con)
{
    echo "start creating products\n";
    $fileSystem = new \Symfony\Component\Filesystem\Filesystem();
    if (($handle = fopen(THELIA_SETUP_DIRECTORY . 'import/products.csv', "r")) !== FALSE) {
        $row=0;
        while (($data = fgetcsv($handle, 100000, ";")) !== FALSE) {
            $row++;
            if($row == 1) continue;
            $product = new \Thelia\Model\Product();

            $product
                ->setRef($data[0])
                ->setVisible(1)
                ->setTaxRuleId(1)
                ->setTemplate($template)
            ;

            $productCategories = explode(';', $data[15]);
            foreach ($productCategories as $productCategory) {
                $productCategory = trim($productCategory);
                if (array_key_exists($productCategory, $categories)) {
                    $product->addCategory($categories[$productCategory]);
                }
            }

            $brand = $data[11];
            if (array_key_exists($brand, $brands)) {
                $product->setBrand($brands[$brand]);
            }

            $ref = trim($data[0]);
            $titleEn = !empty(trim($data[1])) ? trim($data[1]) : $ref;
            $titleFr = !empty(trim($data[1])) ? trim($data[1]) : $ref;
            $product
                ->setLocale('fr_FR')
                    ->setTitle($titleFr)
                    ->setChapo(!empty(trim($data[3])) ? trim($data[3]) : '')
                    ->setDescription(!empty(trim($data[5])) ? trim($data[5]) : '')
                    ->setPostscriptum(!empty(trim($data[7])) ? trim($data[7]) : '')
                ->setLocale('en_US')
                    ->setTitle($titleEn)
                    ->setChapo(!empty(trim($data[2])) ? trim($data[2]) : '')
                    ->setDescription(!empty(trim($data[4])) ? trim($data[4]) : '')
                    ->setPostscriptum(!empty(trim($data[6])) ? trim($data[6]) : '')
            ->save($con);

            $firstProductCategory = $product->getProductCategories()->getFirst();
            if ($firstProductCategory !== null) {
                $firstProductCategory->setDefaultCategory(true)->save($con);
            }

            // Set the position
            $product->setPosition($product->getNextPosition())->save($con);

            $images = explode(';', $data[10]);

            foreach ($images as $image) {
                $image = trim($image);
                if(empty($image)) continue;
                $productImage = new \Thelia\Model\ProductImage();
                $productImage
                    ->setProduct($product)
                    ->setFile($image)
                    ->save($con);
                if (file_exists(THELIA_SETUP_DIRECTORY . 'import/images/'.$image)) {
                    $fileSystem->copy(THELIA_SETUP_DIRECTORY . 'import/images/'.$image, THELIA_LOCAL_DIR . 'media/images/product/'.$image, true);
                }
            }

            $pses = explode(";", $data[12]);
            $pseCreated = false;

            foreach ($pses as $pse) {
                if(empty(trim($pse))) continue;
                $stock = new \Thelia\Model\ProductSaleElements();
                $stock->setProduct($product);
                $stock->setRef($product->getId() . '_' . uniqid('', true));
                $stock->setQuantity($faker->numberBetween(1,50));
                if (!empty($data[9])) {
                    $stock->setPromo(1);
                } else {
                    $stock->setPromo(0);
                }
                $stock->setNewness($faker->numberBetween(0,1));
                $stock->setWeight($faker->randomFloat(2, 1,30));
                $stock->save($con);

                $productPrice = new \Thelia\Model\ProductPrice();
                $productPrice->setProductSaleElements($stock);
                $productPrice->setCurrencyId(1);
                $productPrice->setPrice($data[8]);
                $productPrice->setPromoPrice(!empty($data[9]) ? $data[9] : 0);
                $productPrice->save($con);

                $attributeAv = \Thelia\Model\AttributeAvI18nQuery::create()
                    ->filterByLocale('en_US')
                    ->filterByTitle(trim($pse))
                    ->findOne($con);

                if ($attributeAv !== null) {
                    $attributeCombination = new \Thelia\Model\AttributeCombination();
                    $attributeCombination
                        ->setAttributeId($attribute->getId())
                        ->setAttributeAvId($attributeAv->getId())
                        ->setProductSaleElements($stock)
                        ->save($con);
                }
                $pseCreated = true;
            }

            if (!$pseCreated) {
                $stock = new \Thelia\Model\ProductSaleElements();
                $stock->setProduct($product);
                $stock->setRef($product->getId() . '_' . uniqid('', true));
                $stock->setQuantity($faker->numberBetween(1,50));
                $stock->setPromo(0);
                $stock->setNewness(0);
                $stock->setWeight($faker->randomFloat(2, 1,30));
                $stock->save($con);

                $productPrice = new \Thelia\Model\ProductPrice();
                $productPrice->setProductSaleElements($stock);
                $productPrice->setCurrencyId(1);
                $productPrice->setPrice(!empty($data[8]) ? $data[8] : 0);
                $productPrice->setPromoPrice(0);
                $productPrice->save($con);
            }

            $productSaleElements = $product->getProductSaleElementss()->getFirst();
            if ($productSaleElements !== null) {
                $productSaleElements->setIsDefault(1)->save($con);
            }

            // associated content
            $associatedContents = explode(";", $data[14]);
            foreach ($associatedContents as $associatedContent) {
                $associatedContent = trim($associatedContent);
                if (empty($associatedContent)) continue;
                $assocContent = new ProductAssociatedContent();
                if ( ! array_key_exists($associatedContent, $contents)){
                    continue;
                }

                $assocContent
                    ->setProduct($product)
                    ->setContent($contents[$associatedContent])
                    ->save($con)
                ;
            }

            // feature
            $features = explode(";", $data[13]);

            foreach ($features as $aFeature) {
                $aFeature = trim($aFeature);
                if (empty($aFeature)) continue;
                $featurAv = \Thelia\Model\FeatureAvI18nQuery::create()
                    ->filterByLocale('en_US')
                    ->filterByTitle($aFeature)
                    ->findOne($con);

                if ($featurAv === null) continue;

                $featureProduct = new Thelia\Model\FeatureProduct();
                $featureProduct->setProduct($product)
                    ->setFeatureId($feature->getId())
                    ->setFeatureAvId($featurAv->getId())
                    ->save($con)
                ;
            }
        }
    }
    echo "end creating products\n";
}

function createConfig($faker, $folders, $contents, $con){

    // Store
    \Thelia\Model\ConfigQuery::write("store_name", "Thelia");
    \Thelia\Model\ConfigQuery::write("store_description", "E-commerce solution based on Symfony 2");
    \Thelia\Model\ConfigQuery::write("store_email", "Thelia");
    \Thelia\Model\ConfigQuery::write("store_address1", "5 rue Rochon");
    \Thelia\Model\ConfigQuery::write("store_city", "Clermont-Ferrrand");
    \Thelia\Model\ConfigQuery::write("store_phone", "+(33)444053102");
    \Thelia\Model\ConfigQuery::write("store_email", "contact@thelia.net");
    // Contents
    if (array_key_exists('Information', $folders)) {
        \Thelia\Model\ConfigQuery::write("information_folder_id", $folders['Information']->getId());
    }
    $tcKey = null;
    foreach (array_keys($contents) as $k) {
        if (stripos($k, 'condition') !== false || stripos($k, 'terms') !== false || stripos($k, 'condition') !== false) {
            $tcKey = $k;
            break;
        }
    }
    if ($tcKey !== null) {
        \Thelia\Model\ConfigQuery::write("terms_conditions_content_id", $contents[$tcKey]->getId());
    }
}

function createCustomer($faker, $con){

    echo "Creating customer\n";

    //customer
    $customer = new Thelia\Model\Customer();
    $customer->createOrUpdate(
        1,
        "thelia",
        "thelia",
        "5 rue rochon",
        "",
        "",
        "0102030405",
        "0601020304",
        "63000",
        "Clermont-Ferrand",
        64,
        "test@thelia.net",
        "thelia"
    );
    for ($j = 0; $j <= 2; $j++) {
        $address = new Thelia\Model\Address();
        $address->setLabel($faker->text(20))
            ->setTitleId(rand(1,3))
            ->setFirstname($faker->firstname)
            ->setLastname($faker->lastname)
            ->setAddress1($faker->streetAddress)
            ->setAddress2($faker->streetAddress)
            ->setAddress3($faker->streetAddress)
            ->setCellphone($faker->phoneNumber)
            ->setPhone($faker->phoneNumber)
            ->setZipcode($faker->postcode)
            ->setCity($faker->city)
            ->setCountryId(64)
            ->setCustomer($customer)
            ->save($con)
        ;
    }

    echo "End creating customer\n";
}

function createMaterials($con)
{
    echo "start creating materials feature\n";

    $feature = null;
    $features = array();

    if (($handle = fopen(THELIA_SETUP_DIRECTORY . 'import/materials.csv', "r")) !== FALSE) {
        $row=0;
        $feature = new \Thelia\Model\Feature();
        $feature
            ->setPosition(1)
            ->setLocale('fr_FR')
                ->setTitle('Matière')
            ->setLocale('en_US')
                ->setTitle('Material');

        while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
            $row++;
            $featureAv = new \Thelia\Model\FeatureAv();
            $featureAv
                ->setPosition($row)
                ->setLocale('fr_FR')
                    ->setTitle($data[0])
                ->setLocale('en_US')
                    ->setTitle($data[1]);
            //$featureAv->setFeature($feature);

            $feature->addFeatureAv($featureAv);
        }

        $feature->save($con);

        fclose($handle);
    }
    echo "materials feature created successfully\n";

    return $feature;
}


function createBrands($faker, $con)
{
    echo "start creating brands\n";

    $fileSystem = new \Symfony\Component\Filesystem\Filesystem();

    $brands = array();
    if (($handle = fopen(THELIA_SETUP_DIRECTORY . 'import/brand.csv', "r")) !== FALSE) {
        $row=0;
        while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
            $row++;
            if ($row == 1) continue;

            $brand = new \Thelia\Model\Brand();

            $brand
                ->setVisible(1)
                ->setPosition($row-1)
                ->setLocale('fr_FR')
                    ->setTitle(trim($data[0]))
                    ->setChapo($faker->text(20))
                    ->setDescription($faker->text(100))
                ->setLocale('en_US')
                    ->setTitle(trim($data[0]))
                    ->setChapo($faker->text(20))
                    ->setDescription($faker->text(100))
                ->save($con);

            $brands[trim($data[0])] = $brand;

            $images = explode(';', $data[1]);
            $logoId = null;
            foreach ($images as $image) {
                $image = trim($image);
                if(empty($image)) continue;
                $brandImage = new \Thelia\Model\BrandImage();
                $brandImage
                    ->setBrandId($brand->getId())
                    ->setFile($image)
                    ->save($con);
                if ($logoId === null) {
                    $logoId = $brandImage->getId();
                }
                $fileSystem->copy(THELIA_SETUP_DIRECTORY . 'import/images/'.$image, THELIA_LOCAL_DIR . 'media/images/brand/'.$image, true);
            }

            if ($logoId !== null){
                $brand->setLogoImageId($logoId);
                $brand->save($con);
            }

        }
        fclose($handle);
    }
    echo "brands created successfully\n";

    return $brands;
}


function createCategories($faker, $con)
{
    echo "start creating categories\n";
    $categories = array();
    $categoryByPath = array();

    if (($handle = fopen(THELIA_SETUP_DIRECTORY . 'import/categories.csv', "r")) !== FALSE) {
        $row = 0;
        $position = 0;
        while (($data = fgetcsv($handle, 100000, ";")) !== FALSE) {
            $row++;
            if ($row == 1) continue;

            $fullPathFr = trim($data[0]);
            if (empty($fullPathFr)) continue;

            $parts = explode('/', $fullPathFr);
            $parentId = 0;
            $currentPath = '';

            foreach ($parts as $i => $part) {
                $part = trim($part);
                if (empty($part)) continue;

                $currentPath = ($currentPath === '') ? $part : $currentPath . '/' . $part;
                $isLeaf = ($i === count($parts) - 1);

                if (isset($categoryByPath[$currentPath])) {
                    $parentId = $categoryByPath[$currentPath]->getId();
                    continue;
                }

                $position++;

                if ($isLeaf) {
                    $titleFr = $part;
                    $titleEn = !empty(trim($data[1])) ? trim($data[1]) : $part;
                    $chapoFr = !empty(trim($data[2])) ? trim($data[2]) : $faker->text(20);
                    $chapoEn = !empty(trim($data[3])) ? trim($data[3]) : $faker->text(20);
                    $descFr  = !empty(trim($data[4])) ? trim($data[4]) : $faker->text(100);
                    $descEn  = !empty(trim($data[5])) ? trim($data[5]) : $faker->text(100);
                } else {
                    $titleFr = $part;
                    $titleEn = $part;
                    $chapoFr = $faker->text(20);
                    $chapoEn = $faker->text(20);
                    $descFr  = $faker->text(100);
                    $descEn  = $faker->text(100);
                }

                $category = new \Thelia\Model\Category();
                $category
                    ->setVisible(1)
                    ->setPosition($position)
                    ->setParent($parentId)
                    ->setLocale('fr_FR')
                        ->setTitle($titleFr)
                        ->setChapo($chapoFr)
                        ->setDescription($descFr)
                    ->setLocale('en_US')
                        ->setTitle($titleEn)
                        ->setChapo($chapoEn)
                        ->setDescription($descEn)
                    ->save($con);

                $categoryByPath[$currentPath] = $category;
                $categories[$currentPath] = $category;

                $parentId = $category->getId();
            }
        }
        fclose($handle);
    }
    echo "categories created successfully\n";

    return $categories;
}

function createFolders($faker, $con)
{
    echo "start creating folders\n";

    $fileSystem = new \Symfony\Component\Filesystem\Filesystem();

    $folders = array();
    if (($handle = fopen(THELIA_SETUP_DIRECTORY . 'import/folders.csv', "r")) !== FALSE) {
        $row=0;
        while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
            $row++;
            if ($row == 1) continue;

            $folder = new \Thelia\Model\Folder();

            $folder
                ->setVisible(1)
                ->setPosition($row-1)
                ->setLocale('fr_FR')
                    ->setTitle(trim($data[0]))
                    ->setChapo($faker->text(20))
                    ->setDescription($faker->text(100))
                ->setLocale('en_US')
                    ->setTitle(trim($data[1]))
                    ->setChapo($faker->text(20))
                    ->setDescription($faker->text(100))
                ->save($con);

            $folders[trim($data[1])] = $folder;

            $images = explode(';', $data[6]);
            foreach ($images as $image) {
                $image = trim($image);
                if(empty($image)) continue;
                $folderImage = new \Thelia\Model\FolderImage();
                $folderImage
                    ->setFolderId($folder->getId())
                    ->setFile($image)
                    ->save($con);
                $fileSystem->copy(THELIA_SETUP_DIRECTORY . 'import/images/'.$image, THELIA_LOCAL_DIR . 'media/images/folder/'.$image, true);
            }
        }
        fclose($handle);
    }
    echo "Folders created successfully\n";

    return $folders;
}


function createContents($faker, $folders, $con)
{
    echo "start creating contents\n";

    $fileSystem = new \Symfony\Component\Filesystem\Filesystem();

    $contents = array();
    if (($handle = fopen(THELIA_SETUP_DIRECTORY . 'import/contents.csv', "r")) !== FALSE) {
        $row=0;
        while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
            $row++;
            if ($row == 1) continue;

            $content = new \Thelia\Model\Content();

            $content
                ->setVisible(1)
                ->setPosition($row-1)
                ->setLocale('fr_FR')
                    ->setTitle(trim($data[0]))
                    ->setChapo($faker->text(20))
                    ->setDescription($faker->text(200))
                ->setLocale('en_US')
                    ->setTitle(trim($data[1]))
                    ->setChapo($faker->text(20))
                    ->setDescription($faker->text(200));

            // folder
            $contentFolders = explode(';', $data[7]);
            $defaultFolder = null;
            foreach ($contentFolders as $contentFolder) {
                $contentFolder = trim($contentFolder);
                if (array_key_exists($contentFolder, $folders)) {
                    $content->addFolder($folders[$contentFolder]);
                    if (null === $defaultFolder) {
                        $defaultFolder = $folders[$contentFolder]->getId();
                    }
                }
            }

            $content
                ->getContentFolders()
                ->getFirst()
                ->setDefaultFolder(true)
                ->save($con)
            ;

            $content->save($con);

            $images = explode(';', $data[6]);
            foreach ($images as $image) {
                $image = trim($image);
                if(empty($image)) continue;
                $contentImage = new \Thelia\Model\ContentImage();
                $contentImage
                    ->setContentId($content->getId())
                    ->setFile($image)
                    ->save($con);
                $fileSystem->copy(THELIA_SETUP_DIRECTORY . 'import/images/'.$image, THELIA_LOCAL_DIR . 'media/images/content/'.$image, true);
            }

            $contents[trim($data[1])] = $content;
        }
        fclose($handle);
    }
    echo "Contents created successfully\n";

    return $contents;
}


function createColors($con)
{
    echo "start creating colors attributes\n";
    if (($handle = fopen(THELIA_SETUP_DIRECTORY . 'import/colors.csv', "r")) !== FALSE) {
        $row=0;
        $attribute = new \Thelia\Model\Attribute();
        $attribute
            ->setPosition(1)
            ->setLocale('fr_FR')
                ->setTitle('Couleur')
            ->setLocale('en_US')
                ->setTitle('Colors');

        while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
            $row++;
            $attributeAv = new \Thelia\Model\AttributeAv();
            $attributeAv
                ->setPosition($row)
                ->setLocale('fr_FR')
                    ->setTitle($data[0])
                ->setLocale('en_US')
                    ->setTitle($data[1]);

            $attribute->addAttributeAv($attributeAv);
        }
        $attribute->save($con);
        fclose($handle);
    }
    echo "colors attributes created with success\n";

    return $attribute;
}

function clearTables($con)
{
    echo "Clearing tables\n";

    $tables = [
        'product_associated_content',
        'category_associated_content',
        'feature_product',
        'attribute_combination',
        'feature_av_i18n',
        'feature_av',
        'feature_i18n',
        'feature',
        'attribute_av_i18n',
        'attribute_av',
        'attribute_i18n',
        'attribute',
        'brand_image',
        'brand_i18n',
        'brand',
        'product_price',
        'product_sale_elements',
        'product_image',
        'product_i18n',
        'product_category',
        'product',
        'category_i18n',
        'category',
        'content_folder',
        'content_i18n',
        'content',
        'folder_i18n',
        'folder',
        'accessory',
        'customer',
        'order_address',
        'sale_product',
        'sale_i18n',
        'sale',
        'rewriting_url',
    ];

    foreach ($tables as $table) {
        try {
            $stmt = $con->prepare("DELETE FROM `{$table}`");
            $stmt->execute();
        } catch (\Exception $e) {
            // ignore if table doesn't exist or is already empty
        }
    }

    echo "Tables cleared with success\n";

}
