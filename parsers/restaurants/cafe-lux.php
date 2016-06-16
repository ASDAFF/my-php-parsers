<?php
/**
 * User: gaalferov
 * Date: 06.06.16
 * Time: 15:14
 */

require(__DIR__ . '/../../helpers/simple_html_dom.php');

$inParceUrl = "http://nn.delivery-club.ru/widget/Ljuks_kafje/";
$companySite = "http://cafe-lux.ru";
$company = $companyName = "cafe-lux";

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
        createProducts($htmlData, $outXml, $value['href'], $value['id']);
    }
    html_writeEndFile($outXml);
}

$htmlData->clear();

function parcing(simple_html_dom $htmlData)
{

    $catID = 1;
    $categories = [];

    $menus = $htmlData->find('#category', 0);
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

    return $categories;
}

function createProducts($htmlData, $outXml, $urlCatParce, $catId)
{

    $prods_parcing = $htmlData->find('.dish_list', $catId - 1);
    if ($prods_parcing) {
        $prods_parcing = $prods_parcing->children();
    } else {
        return false;
    }

    foreach ($prods_parcing as $product) {

        $pid = $name = $aUrl = $price = $description = $image = $params = null;

        $idData = $product->find('input[name="product_id"]', 0);
        if ($idData) {
            $pid = html_clear_var($idData->value, 'int');
        }

        $nameData = $product->find('.product_title', 0);
        if ($nameData) {
            $name = html_clear_var($nameData->plaintext);
        }

        $priceData = $product->find('form strong span', 0);
        if ($priceData) {
            $price = html_clear_var($priceData->plaintext, 'double');
        }

        $imageData = $product->find('.main_img img', 0);
        if ($imageData) {
            $image = html_clear_var($imageData->src);
            if ($image == '/images/loading.gif') {
                $image = 'http://www.delivery-club.ru' . html_clear_var($imageData->getAttribute('data-load'));
            }
        }

        $descData = $product->find('.description', 0);
        if ($descData) {
            $description = html_clear_var($descData->plaintext);
        }

        if (empty($pid) || empty($name) || empty($price) || empty($image))
            continue;


        $good = [
            'id' => $pid,
            'group_id' => $pid,
            'available' => true,
            'url' => $urlCatParce,
            'image' => $image,
            'name' => $name,
            'description' => $description,
            'price' => $price,
        ];

        html_writeProductInFile($outXml, $catId, $good);
    }

    return true;
}


