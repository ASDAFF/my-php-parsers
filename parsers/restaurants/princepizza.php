<?php
/**
 * User: gaalferov
 * Date: 03.02.16
 * Time: 10:00
 */

require(__DIR__ . '/../../helpers/simple_html_dom.php');

$inParceUrl = ["http://princepizza.ru"];
$companySite = "http://princepizza.ru";
$company = "princepizza";
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
    @unlink($outXml);

    $categories = parcing($inParceUrl);

    writeStartFile($outXml, $categories, $companySite, $company, $companyName);

    foreach ($categories as $value) {
        createProducts($outXml, $companySite . $value['href'] . 'all', $value['id'], $companySite);
    }

    writeEndFile($outXml);
}

function parcing($url)
{

    if (!is_array($url)) {
        $url[0] = $url;
    }

    if (is_array($url)) {
        $catID = 1;
        $categories = [];

        foreach ($url as $valueUrl) {

            $result = getSaitData($valueUrl);
            $html_cat = new simple_html_dom();
            $html_cat->load($result);

            $menu = $html_cat->find('ul#lmenu', 0);
            if ($menu) {
                $menu = $menu->children();
            }

            foreach ($menu as $value) {

                $a = $value->find('a', 0);
                $href = $a->href;
                $href = trim($href);
                $href = str_replace("&amp;", '&', $href);
                $name = $a->plaintext;
                $name = str_replace("NEW!", '', $name);
                $name = trim($name);
                $name = mb_strtolower($name, 'UTF-8');

                if ($name == '') {
                    continue;
                }

                $categories[] = [
                    'id' => $catID,
                    'parentId' => null,
                    'name' => $name,
                    'href' => $href,
                ];

                ++$catID;
            }
        }
    }
    $html_cat->clear();
    return $categories;

}

function createProducts($outXml, $urlCatParce, $catId, $baseUrl)
{

    $result = getSaitData($urlCatParce);
    $html_prod = new simple_html_dom();
    $html_prod->load($result);

    $prods_parcing = $html_prod->find('.product_brief_block');
    if (!$prods_parcing) {
        return;
    }

    foreach ($prods_parcing as $product) {

        $id = null;
        $idData = $product->find('input[name=productID]', 0);
        if ($idData) {
            $id = $idData->value;
            $id = trim($id);
        }

        if (empty($id)) {
            continue;
        }

        $name = null;
        $nameData = $product->find('div.prdbrief_name a', 0);
        if ($nameData) {
            $name = $nameData->plaintext;
            $name = str_ireplace('&quot;', '', $name);
            $name = trim($name);
            $name = mb_strtolower($name, 'UTF-8');
        }

        if (empty($name)) {
            continue;
        }

        $aUrl = null;
        if ($nameData) {
            $aUrl = $nameData->href;
            $aUrl = str_replace("&amp;", '&', $aUrl);
            $aUrl = trim($baseUrl.$aUrl);
        }

        $priceData = $product->find('input[class=product_price]', 0);
        if ($priceData) {
            $price = (double)$priceData->value;
        }

        if (empty($price)) {
            continue;
        }

        $description = null;
        $descriptionData = $product->find('div.prdbrief_brief_description', 0);
        if ($descriptionData) {
            $description = $descriptionData->plaintext;
            $description = preg_replace('|[\s]+|s', ' ', $description);
            $description = str_replace("NEW", '', $description);
            $description = trim(strip_tags($description));
        }

        $result = getSaitData($aUrl);
        $html_article = new simple_html_dom();
        $html_article->load($result);

        $image = null;
        $imgData = $html_article->find('div.cpt_product_images a', 1);
        if ($imgData) {
            $image = $imgData->href;
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
            'price' => $price,
        ];

        writeProductInFile($outXml, $catId, $good);
    }
    $html_prod->clear();
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