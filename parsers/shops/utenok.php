<?php
/**
 * User: gaalferov
 * Date: 30.04.16
 * Time: 11:00
 */

require(__DIR__ . '/../../helpers/simple_html_dom.php');

$inParceUrl = "http://utenok.ru/catalog";
$companySite = "http://utenok.ru";
$company = $companyName = "utenok";

$currencies = [];
$def_currency = 'RUR';

if ($currencies == null) {
    $currencies[0] = [
        'id' => $def_currency, 'rate' => 1
    ];
}

$outXml = OUTPUT_PATH . $company . '.' . 'xml';

$categories = [];
$categories = parcing($inParceUrl);

html_writeStartFile($outXml, $categories, $companySite, $company, $companyName);
foreach ($categories as $value) {
    createProducts($outXml, $value['href'], $value['id'], $companySite);
}
html_writeEndFile($outXml);




function parcing($url, $parentId = null)
{
    global $categories;

    $catID = 1;

    $html_cat = new simple_html_dom();
    $html_cat->load(html_getSaitData($url));

    $menus = $html_cat->find('.product-categories-item-slim a');
    if (!$menus) {
        return false;
    }

    foreach ($menus as $menu) {

        $href = html_clear_var($menu->href);
        $name = $menu->plaintext;
        $name = mb_strtolower(html_clear_var($name), 'UTF-8');

        if (empty($name) || empty($href)) {
            continue;
        }

        $categories[] = [
            'id' => $catID,
            'parentId' => $parentId,
            'name' => $name,
            'href' => $href
        ];

        if (!$parentId) {
            parcing($href, $catID);
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

    $prods_parcing = $html_prod->find('.products-view-block');
    if (!$prods_parcing) {
        return false;
    }

    foreach ($prods_parcing as $product) {

        $pid = $name = $aUrl = $price = $description = $image = null;

        $idData = $product->find('.products-view-buttons a', 0);
        if ($idData) {
            $pid = html_clear_var($idData->getAttribute('data-product-id'), 'int');
            $aUrl = html_clear_var($idData->href);
        }

        $nameData = $product->find('.products-view-name-default a', 0);
        if ($nameData) {
            $name = mb_strtolower(html_clear_var($nameData->plaintext), 'UTF-8');
        }

        if (empty($pid) || empty($name) || empty($aUrl))
            continue;

        $prods_parcing_intro = new simple_html_dom();
        $prods_parcing_intro->load(html_getSaitData($aUrl));

        $priceData = $prods_parcing_intro->find('.price-current', 0);
        if ($priceData) {
            $price = str_replace('руб.', '', $priceData->plaintext);
            $price = html_clear_var($price, 'double');
        }

        $descData = $prods_parcing_intro->find('.details-tabs-deacription', 0);
        if ($descData) {
            $description = mb_convert_encoding(html_clear_var($descData->plaintext), 'UTF-8');
        }

        $imageGeneral = $prods_parcing_intro->find('.gallery-picture-link', 0);
        $imageAdditional = $prods_parcing_intro->find('.details-carousel-item');
        if ($imageGeneral) {
            $image[] = $imageGeneral->href;
            if ($imageAdditional) {
                foreach ($imageAdditional as $_image) {
                    $_image = $_image->getAttribute('data-parameters');
                    if ($_image) {
                        $_json_image = json_decode(str_replace("'", '"', $_image), true);

                        if (array_search($_json_image['originalPath'], $image) === false)
                            $image[] = $_json_image['originalPath'];
                    }
                }
            }
        }

        if (empty($image) || empty($price))
            continue;

        $sizeData = $prods_parcing_intro->find('.sizes-viewer-list div', 1);
        if ($sizeData) {
            $sizes = $sizeData->getAttribute('data-sizes');
            if ($sizes) {
                $_json_sizes = json_decode(str_replace("::", '', html_entity_decode($sizes)));
                foreach ($_json_sizes as $_sizes) {
                    $_size_id = (int) explode(' ', $_sizes->SizeName)[0];
                    $_pid = $pid . 'r' . $_size_id;
                    $_params['Размер'] = $_size_id;
                    $_description = $description . ' Размер: ' . $_sizes->SizeName;
                    $good = [
                        'id' => $_pid,
                        'group_id' => $pid,
                        'available' => true,
                        'url' => $aUrl,
                        'image' => $image,
                        'name' => $name,
                        'description' => '',
                        'price' => $price,
                        'params' => $_params,
                    ];
                    html_writeProductInFile($outXml, $catId, $good);
                }
            }
        } else {
            $good = [
                'id' => $pid,
                'available' => true,
                'url' => $aUrl,
                'image' => $image,
                'name' => $name,
                'description' => '',
                'price' => $price,
            ];
            html_writeProductInFile($outXml, $catId, $good);
        }

        $prods_parcing_intro->clear();
    }

    $html_prod->clear();

    return true;
}