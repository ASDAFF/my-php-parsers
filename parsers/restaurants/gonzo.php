<?php
/**
 * User: gaalferov
 * Date: 08.06.16
 * Time: 12:46
 */


require(__DIR__ . '/../../helpers/simple_html_dom.php');

$inParceUrl = "http://www.gonzo.delivery/";
$companySite = "http://www.gonzo.delivery";
$company = $companyName = "gonzo";

$currencies = [];
$def_currency = 'RUR';

if ($currencies == null) {
    $currencies[0] = [
        'id' => $def_currency, 'rate' => 1
    ];
}

$outXml = OUTPUT_PATH . $company . '.' . 'xml';

$htmlData = new simple_html_dom();
$htmlData->load(html_getSaitData($inParceUrl));

if ($htmlData) {
    $categories = parcing($htmlData);

    html_writeStartFile($outXml, $categories, $companySite, $company, $companyName);
    foreach ($categories as $value) {
        createProducts($htmlData, $outXml, $value['name'], $value['id'], $companySite);
    }
    html_writeEndFile($outXml);
}

$htmlData->clear();

function parcing($htmlData)
{

    $categories = [];

    $menus = $htmlData->find('#product-type', 0);
    if ($menus) {
        $menus = $menus->children();
    } else {
        return false;
    }

    foreach ($menus as $menu) {

        $catID = $menu->getAttribute('type');
        $name = $menu->find('.title', 0)->plaintext;
        $name = mb_strtolower(trim($name), 'UTF-8');
        $name = str_replace('&', 'и', $name);


        if ($catID == '' || $name == '') {
            continue;
        }

        $categories[] = [
            'id' => $catID,
            'parentId' => null,
            'name' => $name,
            'href' => $catID
        ];

    }

    return $categories;
}

function createProducts($htmlData , $outXml, $catName, $catId, $companySite)
{

    $prods_parcing = $htmlData->find('.products', 0);
    if ($prods_parcing) {
        $prods_parcing = $prods_parcing->children();
    } else {
        return false;
    }

    foreach ($prods_parcing as $allProducts) {

        $typeName = mb_strtolower($allProducts->find('h4', 0)->plaintext, 'UTF-8');
        $typeName = str_replace('&', 'и', $typeName);
        if ($typeName != $catName) {
            continue;
        }

        $products = $allProducts->find('.items-list .item');

        if ($products) {
            foreach ($products as $product) {
                $pid = $name = $aUrl = $price = $description = $image = null;

                $idData = $product->getAttribute('product');
                if ($idData) {
                    $pid = html_clear_var($idData, 'int');
                }

                $nameData = $product->find('.title', 0);
                if ($nameData) {
                    $name = html_clear_var($nameData->plaintext);
                }

                $imageData = $product->find('.photo', 0);
                if ($imageData) {
                    $image = html_clear_var($companySite . $imageData->getAttribute('fullsize'));
                }

                $priceData = $product->find('.price', 0);
                if ($priceData) {
                    $price = html_clear_var($priceData->plaintext, 'double');
                }

                $weightData = $product->find('.hide-content .gram', 0);
                if ($weightData) {
                    $name = $name . ' ' . html_clear_var($weightData->plaintext);
                }

                $descData = $product->find('.hide-content .composition', 0);
                if ($descData) {
                    $description = html_clear_var($descData->plaintext);
                }

                if (empty($pid) || empty($name) || empty($image) || empty($price))
                    continue;

                $good = [
                    'id' => $pid,
                    'group_id' => $pid,
                    'available' => true,
                    'url' => $companySite,
                    'image' => $image,
                    'name' => $name,
                    'description' => $description,
                    'price' => $price,
                ];

                html_writeProductInFile($outXml, $catId, $good);
            }
        }
    }

    return true;
}