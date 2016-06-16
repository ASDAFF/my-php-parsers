<?php
/**
 * User: gaalferov
 * Date: 09.06.16
 * Time: 13:34
 */

require(__DIR__ . '/../../helpers/simple_html_dom.php');

$inParceUrl = "http://za-mechtoi.com/";
$companySite = "http://za-mechtoi.com";
$company = $companyName = "za-mechtoi";

$currencies = [];
$def_currency = 'RUR';

if ($currencies == null) {
    $currencies[0] = [
        'id' => $def_currency, 'rate' => 1
    ];
}

$outXml = OUTPUT_PATH . $company . '.' . 'xml';

$categories = parcing($inParceUrl);

html_writeStartFile($outXml, $categories, $companySite, $company, $companyName);
foreach ($categories as $value) {
    createProducts($outXml, $companySite . $value['href'] . '?on_page=100', $value['id'], $companySite);
}
html_writeEndFile($outXml);

function parcing($url)
{

    $catID = 1;
    $categories = [];

    $html_cat = new simple_html_dom();
    $html_cat->load(html_getSaitData($url));

    $menus = $html_cat->find('.sf-menu-phone2', 0);
    if ($menus) {
        $menus = $menus->children();
    } else {
        return false;
    }

    foreach ($menus as $menu) {

        $value = $menu->find('a', 0);

        $href = html_clear_var($value->href);
        $name = mb_strtolower($value->plaintext, 'UTF-8');

        if ($name == '' || $href == '') {
            continue;
        }

        $categories[] = [
            'id' => $catID,
            'parentId' => null,
            'name' => $name,
            'href' => $href
        ];

        ++$catID;

    }

    $html_cat->clear();
    return $categories;
}

function createProducts($outXml, $urlCatParce, $catId, $companySite)
{

    $html_prod = new simple_html_dom();
    $html_prod->load(html_getSaitData($urlCatParce));

    $prods_parcing = $html_prod->find('.products-grid .item');
    if (!$prods_parcing) {
        return false;
    }

    foreach ($prods_parcing as $key => $product) {

        $pid = $name = $aUrl = $price = $description = $image = null;

        $idData = $product->find('.actions .btn-cart', 0);
        if ($idData) {
            $pid = $idData->getAttribute('onclick');
            $pid = html_clear_var(str_replace(["return $.bootstrapAddIntoCart('/shop/cart/', ", ", 1)"], '', $pid), 'int');
        }

        $nameData = $product->find('.product-name a', 0);
        if ($nameData) {
            $name = html_clear_var($nameData->plaintext);
            if ($nameData->href) {
                $aUrl = $companySite . $nameData->href;
            }
        }

        if (empty($pid) || empty($name) || empty($aUrl))
            continue;

        $html_prod_intro = new simple_html_dom();
        $html_prod_intro->load(html_getSaitData($aUrl));

        $imageData = $html_prod_intro->find('#zoom', 0);
        if ($imageData) {
            $image = $companySite . html_clear_var($imageData->getAttribute('data-zoom-image'));
        }

        $descData = $html_prod_intro->find('.item-description', 0);
        if ($descData) {
            $description = html_clear_var($descData->plaintext);
        }

        $priceData = $html_prod_intro->find('.item-price', 0);
        if ($priceData) {
            $price = html_clear_var($priceData->plaintext, 'double');
        }

        $weightData = $html_prod_intro->find('.shop_property', 1);
        if ($weightData) {
            $description .= ' ' . html_clear_var($weightData->plaintext);
        }

        if (empty($image) || empty($price))
            continue;

        $good = [
            'id' => $pid,
            'group_id' => $pid,
            'available' => true,
            'url' => $aUrl,
            'image' => $image,
            'name' => $name,
            'description' => $description,
            'price' => $price,
        ];

        html_writeProductInFile($outXml, $catId, $good);
    }

    $html_prod->clear();

    return true;
}
