<?php
/**
 * User: gaalferov
 * Date: 08.05.16
 * Time: 13:00
 */


require(__DIR__ . '/../../helpers/simple_html_dom.php');

$inParceUrl = "http://pirogeria.ru/";
$companySite = "http://pirogeria.ru";
$company = $companyName = "pirogeria";

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
    createProducts($outXml, $companySite . $value['href'], $value['id'], $companySite);
}
html_writeEndFile($outXml);


function parcing($url)
{

    $catID = 1;
    $categories = [];

    $result = html_getSaitData($url);
    $html_cat = new simple_html_dom();
    $html_cat->load($result);

    $menus = $html_cat->find('.navmain-lvl-2', 0);
    if ($menus) {
        $menus = $menus->children();
    } else {
        return false;
    }

    foreach ($menus as $menu) {

        $value = $menu->find('a', 0);

        $href = html_clear_var($value->href);
        $name = $value->plaintext;
        $name = mb_strtolower(html_clear_var($name), 'UTF-8');

        if ($name == '' || $href == '') {
            continue;
        }

        $categories[] = [
            'id' => $catID,
            'parentId' => null,
            'name' => $name,
            'href' => $href
        ];


        $details_menus = $menu->find('ul', 0);
        if ($details_menus) {
            $details_menus = $details_menus->children();
            $detailCatId = $catID;
            foreach ($details_menus as $detail_menu) {
                $value = $detail_menu->find('a', 0);

                $href = html_clear_var($value->href);
                $name = $value->plaintext;
                $name = mb_strtolower(html_clear_var($name), 'UTF-8');

                if ($name == '' || $href == '') {
                    continue;
                }

                $categories[] = [
                    'id' => ++$detailCatId,
                    'parentId' => $catID,
                    'name' => $name,
                    'href' => $href
                ];
            }

            $catID = $detailCatId;
        }

        ++$catID;

    }

    $html_cat->clear();
    return $categories;
}

function createProducts($outXml, $urlCatParce, $catId, $companySite)
{

    $html_prod = new simple_html_dom();
    $html_prod->load(html_getSaitData($urlCatParce));

    $prods_parcing = $html_prod->find('.catalog-list', 0);
    if ($prods_parcing) {
        $prods_parcing = $prods_parcing->children();
    } else {
        return false;
    }

    foreach ($prods_parcing as $product) {

        $pid = $name = $aUrl = $price = $description = $image = $jsonData = $diametr = $weight = null;

        $idData = $product->find('.count input[name="quantity"]', 0);
        if ($idData) {
            $pid = $idData->getAttribute('data-id');
        }

        $nameData = $product->find('.catalog-item__title', 0);
        if ($nameData) {
            $name = html_clear_var($nameData->plaintext);
            $aUrl = $companySite . $nameData->href;
        }

        if (empty($pid) || empty($name) || empty($aUrl))
            continue;

        $html_prod_desc = new simple_html_dom();
        $html_prod_desc->load(html_getSaitData($aUrl));

        $descData = $html_prod_desc->find('.descr', 0);
        if ($descData) {
            $description = html_clear_var($descData->plaintext);
        }

        $imgData = $html_prod_desc->find('.card__img a', 0);
        if ($imgData) {
            $image = $companySite . $imgData->href;
        }

        if (empty($image))
            continue;

        $vars = $product->find('.choose_item .item-content');
        if (count($vars) > 1) {
            foreach ($vars as $key => $var) {
                $diametr = $weight = '';

                $diametr = $var->find('.diametr', 0);
                if ($diametr) {
                    $diametr = html_clear_var($diametr->plaintext);
                }

                $weight = $var->find('.weight', 0);
                if ($weight) {
                    $weight = html_clear_var($weight->plaintext);
                }

                $_name = html_clear_var($name . ' ' . $diametr . ' ' . $weight);

                $_pid = $pid . 'r' . html_clear_var($diametr, 'int');

                $priceData = $product->find('.price', $key);
                if ($priceData) {
                    $price = html_clear_var(str_replace('q', '', $priceData->plaintext), 'double');
                }

                if (empty($price) || empty($pid) || empty($name) || empty($aUrl) || empty($image) || empty($image)) {
                    continue;
                }

                $good = [
                    'id' => $_pid,
                    'group_id' => $pid,
                    'available' => true,
                    'url' => $aUrl,
                    'image' => $image,
                    'name' => $_name,
                    'description' => $description,
                    'price' => $price,
                ];

                html_writeProductInFile($outXml, $catId, $good);
            }
            continue;
        } else {

            $diametr = $vars[0]->find('.diametr', 0);
            if ($diametr) {
                $diametr = html_clear_var($diametr->plaintext);
            }

            $name = html_clear_var($name . ' ' . $diametr);
        }

        $priceData = $product->find('.price', 0);
        if ($priceData) {
            $price = html_clear_var(str_replace('q', '', $priceData->plaintext), 'double');
        }

        if (empty($price) || empty($pid) || empty($name)) {
            continue;
        }

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