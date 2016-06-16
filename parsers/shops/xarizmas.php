<?php
/**
 *
 * User: gaalferov
 * Date: 29.05.16
 * Time: 18:23
 */

require(__DIR__ . '/../../helpers/simple_html_dom.php');

$inParceUrl = "http://xarizmas.ru/online-shop/";
$companySite = "http://xarizmas.ru";
$company = $companyName = "xarizmas";

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
    createProducts($outXml, $companySite . $value['href'] . '?COUNTofPAGE=500', $value['id'], $companySite);
}
html_writeEndFile($outXml);

function parcing($url)
{

    $catID = 1;
    $categories = [];

    $html_cat = new simple_html_dom();
    $html_cat->load(html_getSaitData($url));

    $menus = $html_cat->find('.left-bar-menu', 0);
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

        ++$catID;

    }

    $html_cat->clear();
    return $categories;
}

function createProducts($outXml, $urlCatParce, $catId, $companySite)
{

    $html_prod = new simple_html_dom();
    $html_prod->load(html_getSaitData($urlCatParce));

    $prods_parcing = $html_prod->find('.catalog__items', 0);
    if ($prods_parcing) {
        $prods_parcing = $prods_parcing->children();
    } else {
        return false;
    }

    foreach ($prods_parcing as $key => $product) {

        $pid = $name = $aUrl = $price = $description = $image = null;

        $urlData = $product->find('.catalog-item__title a', 0);
        if ($urlData) {
            $aUrl = $companySite .  html_clear_var($urlData->href);
            $name = html_clear_var($urlData->plaintext);
        }

        if (empty($aUrl) || empty($name))
            continue;

        $html_prod_details = new simple_html_dom();
        $html_prod_details->load(html_getSaitData($aUrl));

        $idData = $html_prod_details->find('.catalog-content', 0);
        if ($idData) {
            $pid = html_clear_var(explode('_', $idData->getAttribute('id'))[1], 'int');
        }

        $imageData = $html_prod_details->find('.catalog-detail__slider');
        if ($imageData) {
            foreach ($imageData as $img) {
                $image[] = str_replace('//', 'http://', html_clear_var($img->href));
            }
        }

        $priceData = $html_prod_details->find('.catalog-item__price', 0);
        if ($priceData) {
            $price = html_clear_var($priceData->plaintext, 'double');
        }

        $descData = $html_prod_details->find('p', 4);
        if ($descData) {
            $description = html_clear_var($descData->plaintext);
        }

        if (empty($pid) || empty($image) || empty($price))
            continue;


        $sizeData = $html_prod_details->find('.catalog-detail__size-item');

        if (count($sizeData) > 1) {
            foreach ($sizeData as $sizeD) {
                $size = html_clear_var($sizeD->plaintext, 'int');
                $group_id = $pid . 'r' . $size;
                $_params['Размер'] = $size;
                $good = [
                    'id' => $group_id,
                    'group_id' => $pid,
                    'available' => true,
                    'url' => $aUrl,
                    'image' => $image,
                    'name' => $name,
                    'description' => $description,
                    'price' => $price,
                    'params' => $_params,
                ];
                html_writeProductInFile($outXml, $catId, $good);
            }
        } else {
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

    }

    $html_prod->clear();
    return true;
}