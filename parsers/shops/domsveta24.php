<?php
/**
 * User: gaalferov
 * Date: 22.05.16
 * Time: 10:00
 */

require(__DIR__ . '/../../helpers/simple_html_dom.php');

$inParceUrl = "http://domsveta24.ru/";
$companySite = "http://domsveta24.ru";
$company = $companyName = "domsveta24";

$currencies = $products = [];
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
    createProducts($outXml, $value['href'] . '?limit=3000', $value['id']);
}
html_writeEndFile($outXml);

//Удаляем пустые категорие, дублирующиеся товары, выставляем корректно id категории
$unique = html_restoreXML($outXml);
if ($unique) {
    html_writeStartFile($outXml, $unique['categories'], $companySite, $company, $companyName);
    foreach ($unique['offers'] as $offer) {
        html_writeProductInFile($outXml, $offer['categoryId'], $offer);
    }
    html_writeEndFile($outXml);
}

function parcing($url)
{

    $catID = 1;
    $categories = [];

    $html_cat = new simple_html_dom();
    $html_cat->load(html_getSaitData($url));

    $menus = $html_cat->find('.supermenu-left .haskids');
    if (!$menus) {
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

function createProducts($outXml, $urlCatParce, $catId)
{

    $html_prod = new simple_html_dom();
    $html_prod->load(html_getSaitData($urlCatParce));

    $prods_parcing = $html_prod->find('.product-list', 0);
    if ($prods_parcing) {
        $prods_parcing = $prods_parcing->children();
    } else {
        return false;
    }

    foreach ($prods_parcing as $product) {

        $pid = $name = $aUrl = $price = $description = $image = $params = null;

        $idData = $product->find('.cart .button', 0);
        if ($idData) {
            $pid = str_replace(["addToCart('", "');"], '', $idData->getAttribute('onclick'));
            $pid = html_clear_var($pid, 'int');
        }


        if (!array_key_exists($pid, $GLOBALS['products'])) {
            $GLOBALS['products'][$pid] = $pid;
        } else {
            continue;
        }

        $nameData = $product->find('.name a', 0);
        if ($nameData) {
            $name = html_clear_var($nameData->plaintext);
            if ($nameData->href) {
                $aUrl = $nameData->href;
            }
        }

        $priceData = $product->find('.price', 0);
        if ($priceData) {
            $price = html_clear_var($priceData->plaintext, 'double');
        }

        if (empty($pid) || empty($name) || empty($aUrl) || empty($price))
            continue;

        $imageData = $product->find('.imagejail', 0);
        if ($imageData) {
            $image = html_clear_var($imageData->getAttribute('data-src'));
            $image = str_replace('160x160', '500x500', $image);
        }

        $descData = $product->find('.description', 0);
        if ($descData) {
            $description = html_clear_var($descData->plaintext);
        }

        /*

        $html_prod_intro = new simple_html_dom();
        $html_prod_intro->load(html_getSaitData($aUrl));

        $imageData = $html_prod_intro->find('.product-info .image img', 0);
        if ($imageData) {
            $image = html_clear_var($imageData->src);
        }
        $descData = $html_prod_intro->find('.descriproin_large table tr');
        if ($descData) {
            foreach ($descData as $key => $desc) {
                $_param = $desc->find('td');
                if ($_param) {
                    $_pname = html_clear_var(addslashes(strip_tags($_param[0]->plaintext)));
                    $_pvalue = html_clear_var(addslashes(strip_tags($_param[1]->plaintext)));
                    if (($_pname && $_pvalue) && (mb_strlen($_pname) < 40)) {
                        $params[$_pname] = $_pvalue;
                    }
                }

                // ограничение на кол-во параметров у товара
                if ($key >= 4) {
                    break;
                }
            }
        }

        */

        if (empty($image))
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
            'params' => $params,
        ];

        html_writeProductInFile($outXml, $catId, $good);
    }

    $html_prod->clear();

    return true;
}


