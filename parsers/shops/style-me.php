<?php
/**
 * User: gaalferov
 * Date: 02.06.16
 * Time: 13:40
 */


require(__DIR__ . '/../../helpers/simple_html_dom.php');

$inParceUrl = "http://style-me.ru/category/platya/";
$companySite = "http://style-me.ru";
$company = $companyName = "style-me";

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
    createProducts($outXml, $companySite . $value['href'], $value['id'], $companySite, true);
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

    $menus = $html_cat->find('#sidebar-nav', 0);
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

function checkpagination($outXml, $urlCatParce, $catId, $companySite)
{
    for ($i = 2; ; $i++) {
        $res = createProducts($outXml, $urlCatParce . '?page='.$i, $catId, $companySite, false);
        if (!$res)
            break;
    }
}

function createProducts($outXml, $urlCatParce, $catId, $companySite, $pagination = false)
{

    $html_prod = new simple_html_dom();
    $html_prod->load(html_getSaitData($urlCatParce));

    $prods_parcing = $html_prod->find('.product-items', 0);
    if ($prods_parcing) {
        $prods_parcing = $prods_parcing->children();
    } else {
        return false;
    }

    foreach ($prods_parcing as $product) {

        $pid = $name = $aUrl = $price = $description = $image = $params = null;

        $idData = $product->find('.addtocart', 0);
        if ($idData) {
            $pid = html_clear_var($idData->find('input[name=product_id]', 0)->value, 'int');
            $price = html_clear_var($idData->find('meta[itemprop=price]', 0)->content, 'double');
        }

        $nameData = $product->find('.product-img', 0);
        if ($nameData) {
            $name = html_clear_var($nameData->title);
            if ($nameData->href) {
                $aUrl = $companySite . $nameData->href;
            }
        }

        if (empty($pid) || empty($name) || empty($aUrl) || empty($price))
            continue;

        $html_prod_intro = new simple_html_dom();
        $html_prod_intro->load(html_getSaitData($aUrl));

        $imageData = $html_prod_intro->find('#product-gallery', 0);
        if ($imageData) {
            $imageData = $imageData->children();
            foreach ($imageData as $_img) {
                if ($_img->find('a', 0)->href) {
                    $image[] = html_clear_var($companySite . $_img->find('a', 0)->href);
                }
            }
        }

        $descData = $html_prod_intro->find('#product-description', 0);
        if ($descData) {
            $description = $descData->plaintext;
        }

        $paramsData = $html_prod_intro->find('#product-features tr');
        if ($paramsData) {
            foreach ($paramsData as $key => $param) {
                $_param = $param->find('td');
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

    if ($pagination)
        checkpagination($outXml, $urlCatParce, $catId, $companySite);

    return true;
}

