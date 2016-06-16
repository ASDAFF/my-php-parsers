<?php
/**
 * User: gaalferov
 * Date: 02.02.16
 * Time: 14:23
 */

require(__DIR__ . '/../../helpers/simple_html_dom.php');
header('Content-type: text/html; charset=utf-8');
date_default_timezone_set('Europe/Moscow');

$inParceUrl = ["http://big-fish.su/"];
$companySite = "http://big-fish.su";
$company = "big-fish";
$companyName = null;
if ($companyName == null) {
    $companyName = $company;
}

$currencies = [];
$def_currency = 'RUR';

if ($currencies == null) {
    $currencies[0] = [
        'id' => $def_currency, 'rate' => 1
    ];
}

$prod_ids = [];

index($inParceUrl, $companySite, $company, $companyName);

function index($inParceUrl, $companySite, $company, $companyName)
{

    global $argv;
    if ($argv[1] != 'loc') {
        $outPath = OUTPUT_PATH;
    } else {
        $outPath = '';
    }

    $outXml = $outPath . $company . '.' . 'xml';
    $categories = parcing($inParceUrl);

    @unlink($outXml);

    writeStartFile($outXml, $categories, $companySite, $company, $companyName);

    foreach ($categories as $value) {
        createProducts($outXml, $companySite . $value['href'], $value['id'], $companySite);
    }

    writeEndFile($outXml);
}

function parcing($url)
{

    if (!is_array($url)) {
        $url [0] = $url;
    }

    if (is_array($url)) {
        $catID = 1;
        $categories = [];

        $doubleCnt = 0;
        foreach ($url as $valueUrl) {
            /*
            *раскомментируем если нужно изменить коджировку
            *
            **/
            //$html_cat->load(iconv('WINDOWS-1251', 'UTF-8', file_get_contents($url)));
            $result = getSaitData($valueUrl);
            $html_cat = new simple_html_dom();
            $html_cat->load($result);

            $menu = $html_cat->find('div#Menu ul', 0);
            if ($menu) {

                $menu = $menu->children();
            }

            foreach ($menu as $value) {

                $a = $value->find('a', 0);
                $href = $a->href;
                $href = trim($href);
                $href = str_replace("&amp;", '&', $href);
                $name = $a->plaintext;
                $name = trim($name);
                $name = str_replace("&nbsp;", '', $name);

                $name = mb_strtolower($name, 'UTF-8');

                /**
                 * /*устанавливаем если нужно пропустить пункты меню
                 */

                $otMenu = ['блог'];
                $resultOutMenu = outMenu($name, $otMenu);
                if ($resultOutMenu !== false) {
                    continue;
                }

                if ($name == '') {
                    continue;
                }

                $categories[] = [
                    'id' => $catID,
                    'parentId' => null,
                    'name' => $name,
                    'href' => $href,
                    //'products' => $products,
                ];
                ++$catID;
            }
            ++$doubleCnt;
            $html_cat->clear();
        }
    }

    return $categories;

}

function createProducts($outXml, $urlCatParce, $catId, $baseUrl)
{

    /*
    *раскомментируем если нужно изменить коджировку
    *
    **/
    //$html_prod->load(iconv('WINDOWS-1251', 'UTF-8', file_get_contents($urlCatParce)));

    //$urlCatParce = 'http://www.pizzakazan.ru/rolli';
    $result = getSaitData($urlCatParce);
    $html_prod = new simple_html_dom();
    $html_prod->load($result);

    $prods_pagination_parcing = $html_prod->find('div.pagination', 0);
    //var_dump($prods_pagination_parcing->innertext);exit;
    $paginationArray = [];
    if ($prods_pagination_parcing) {
        $pagers = $prods_pagination_parcing->find('a');
        if ($pagers) {
            $paginationI = 0;
            //$lastIndex = count($pagers) - 1;
            foreach ($pagers as $paginationProd) {
                //if ($paginationI != 0) {
                $paginationArray [$paginationI] = $paginationProd->href;
                //}
                $paginationI++;
            }
            unset($paginationArray [(count($paginationArray) - 1)]);
            unset($paginationArray [(count($paginationArray) - 1)]);
            //var_dump($paginationArray);exit;
        }
    }
    $pagenationCnt = count($paginationArray) + 1;

    $prods_parcing = $html_prod->find('div#Content', 0);

    if ($prods_parcing) {
        $prods_parcing = $prods_parcing->find('div.Products');
        if (!$prods_parcing) return;
    } else {
        return;
    }

    $iProd = 0;
    $flagPagination = -1;

    do {

        if ($flagPagination != (-1)) {

            $result = getSaitData($paginationArray[$flagPagination]);
            $html_prod = new simple_html_dom();
            $html_prod->load($result);


            $prods_parcing = $html_prod->find('div#Content', 0);
            //var_dump($prods_parcing->innertext); exit;
            if ($prods_parcing) {
                $prods_parcing = $prods_parcing->find('div.Products');
                if (!$prods_parcing) return;
            } else {
                return;
            }
        }

        foreach ($prods_parcing as $html_products) {

            $items_parcing = $html_products->find('ul.Items', 0);

            if ($items_parcing == null) {
                continue;
            } else {
                $items_parcing = $items_parcing->children();
            }

            foreach ($items_parcing as $product) {

                $id = null;
                $idData = $product->find('input[name=my-item-id]', 0);
                if ($idData) {
                    $id = $idData->value;
                    $id = trim($id);
                }

                if (empty($id)) {
                    continue;
                }

                $name = null;
                $nameData =  $product->find('input[name=my-item-name]', 0);
                if ($nameData) {
                    $name = $nameData->value;
                    $name = trim($name);
                    $name = mb_strtolower($name, 'UTF-8');
                }

                if (empty($name)) {
                    continue;
                }

                $aUrl = trim($urlCatParce);

                $priceData =  $product->find('input[name=my-item-price]', 0);
                if ($priceData) {
                    $price = $priceData->value;
                    $price = trim($price);
                    $price = (double)$price;
                }

                if (empty($price)) {
                    continue;
                }

                $description = null;
                $descriptionData = $product->find('p', 0);
                if ($descriptionData) {
                    $description = $descriptionData->plaintext;
                    $description = trim(strip_tags($description));
                }

                $pictures = [];

                $image = null;

                $imgData = $product->find('img', 0);
                if ($imgData) {
                    $image = $imgData->src;
                    $image = trim($image);
                    $pictures[0] = $baseUrl.$image;
                }

                if (empty($image)) {
                    continue;
                }

                $good = [
                    'id' => $id,
                    'available' => true,
                    'url' => $aUrl,
                    'pictures' => $pictures,
                    'name' => $name,
                    'description' => $description,
                    'param' => [],
                    'price' => $price,
                ];

                $iProd++;

                if (!array_key_exists((string)$id, $GLOBALS['prod_ids'])) {
                    writeProductInFile($outXml, $catId, $good);
                }

                $GLOBALS['prod_ids'][(string)$id] = $name;
            }
        }
        $flagPagination++;
        $pagenationCnt--;
        $html_prod->clear();

    } while ($pagenationCnt > 0);

}

function getSaitData($url)
{

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_VERBOSE, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0');
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

function outMenu($name, $otMenu)
{
    return array_search($name, $otMenu);
}


function writeStartFile($outXml, $categories, $companySite, $company, $companyName)
{

    $fd = fopen($outXml, "a");

    $txt = "";
    $txt .= "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    $txt .= "<yml_catalog date=\"" . date("Y-m-d H:i") . "\">\n";
    $txt .= "  <shop>\n";
    $txt .= "    <name>" . $companyName . "</name>\n";
    $txt .= "    <company>" . $company . "</company>\n";
    $txt .= "    <url>" . $companySite . "</url>\n";
    $txt .= "    <currencies>\n";
    foreach ($GLOBALS['currencies'] as $currency) {
        $txt .= "      <currency id=\"" . $currency['id'] . "\" rate=\"" . $currency['rate'] . "\"/>\n";
    }
    $txt .= "    </currencies>\n";
    $txt .= "    <categories>\n";
    foreach ($categories as $category) {
        if (!empty($category)) {
            $txt .= "      <category id='" . $category['id'] . "'" .
                (isset($category['parentId']) ? " parentId=\"" . $category['parentId'] . "\"" : "") . ">" .
                $category['name'] . "</category>\n";
        }
    }
    $txt .= "    </categories>\n";
    $txt .= "    <offers>\n";

    fwrite($fd, $txt);
    fclose($fd);
}

function writeProductInFile($outXml, $catId, $product)
{
    $txt = '';

    $fd = fopen($outXml, "a");

    if ($product) {
        $available = (isset($product['available'])) ? 'true' : 'false';
        $txt .= "      <offer id=\"" . $product['id'] . "\" available=\"" . $available . "\">\n";
        $txt .= "        <url><![CDATA[" . $product['url'] . "]]></url>\n";
        $txt .= "        <currencyId>RUR</currencyId>\n";
        $txt .= "        <categoryId>" . $catId . "</categoryId>\n";

        if ($product['pictures']) {
            foreach ($product['pictures'] as $picture) {
                $txt .= "        <picture>" . $picture . "</picture>\n";
            }
        }

        $txt .= "        <name><![CDATA[" . $product['name'] . "]]></name>\n";
        $txt .= "        <vendor/>\n";
        $txt .= "        <description><![CDATA[" . $product['description'] . "]]></description>\n";

        $txt .= "        <price><![CDATA[" . $product['price'] . "]]></price>\n";
        $txt .= "      </offer>\n";
    }

    fwrite($fd, $txt);
    fclose($fd);
}


function writeEndFile($outXml)
{
    $txt = '';

    $fd = fopen($outXml, "a");

    $txt .= "    </offers>\n";
    $txt .= "  </shop>\n";
    $txt .= "</yml_catalog>\n";
    fwrite($fd, $txt);
    fclose($fd);
}