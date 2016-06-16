<?php
/**
 * User: gaalferov
 * Date: 07.02.16
 * Time: 10:00
 */
header('Content-type: text/html; charset=utf-8');
require(__DIR__ . '/../../helpers/PHPExcel/PHPExcel.php');
require(__DIR__ . '/../../helpers/simple_html_dom.php');

set_time_limit(0);
date_default_timezone_set('Europe/Moscow');

$company = "chernovar";
$companyName = "Черновар";
$companySite = "http://xn--80adi6afke3c.xn--p1ai";

$currencies = [];
$def_currency = 'RUR';

if ($currencies == null) {
    $currencies[0] = [
        'id' => $def_currency, 'rate' => 1
    ];
}

$inFile = __DIR__ . '/../restaurants_data/chernovar.xlsx';
$outXml = OUTPUT_PATH . $company . '.' . 'xml';

$data = getData($inFile);
if ($data) {
    @unlink($outXml);
    writeStartFile($outXml, $data['categories'], $companySite, $company, $companyName);
    foreach ($data['offers'] as $offer) {
        writeProductInFile($outXml, $offer['categoryId'], $offer);
    }
    writeEndFile($outXml);
}


function getData($filename)
{
    $inputFileType = PHPExcel_IOFactory::identify($filename);
    $objReader = PHPExcel_IOFactory::createReader($inputFileType);
    $objPHPExcel = $objReader->load($filename);
    $objPHPExcel->setActiveSheetIndex(0);
    $currentList = $objPHPExcel->getActiveSheet();
    $datas = $currentList->toArray();

    $categories = $offers = [];
    $cat_id = $prod_id = $topId = 0;
    $bottle_name = $bottle_desc = '';
    $subcategories_name = ['ручная работа', 'свинина', 'говядина', 'баранина', 'птица', 'рыба', 'гарниры'];

    foreach ($datas as $data) {
        //category
        if ($data[0]) {
            $cat_id++;
            $cname = mb_strtolower((string)$data[0], 'UTF-8');
            if (in_array($cname, $subcategories_name)) {
                $cparentId = $topId;
            } else {
                $topId = $cat_id;
                $cparentId = 0;
            }
            $categories[$cat_id] = [
                "name" => $cname,
                "id" => $cat_id,
                "parentId" => $cparentId,
                "products_count" => 0
            ];
        }
        //product
        if ($data[1]) {
            $prod_id++;

            $oname = clear_var($data[1]);
            $oprice = clear_var($data[2], 'double');
            $odescription = clear_var($data[3]);
            $oimage = clear_var($data[4]);

            if ($cname == 'разливные пенные напитки') {
                if (in_array($oname, ['0,33 л.', '0,5 л.', '1 л.'])) {
                    $oname = $bottle_name . ' ' . clear_var($data[1]);
                    $odescription = $bottle_desc . ' ' . clear_var($data[3]);
                } else {
                    $bottle_name = $oname;
                    $bottle_desc = $odescription;
                }
            }

            if (empty($oname) || empty($oprice)) {
                continue;
            }

            $offers[] = [
                'id' => $prod_id,
                'available' => true,
                "categoryId" => $cat_id,
                'url' => $GLOBALS['companySite'],
                'image' => $oimage,
                'name' => $oname,
                'description' => $odescription,
                'price' => $oprice,
            ];
            $categories[$cat_id]['products_count']++;
        }
    }

    return ['categories' => $categories, 'offers' => $offers];
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
                (($category['parentId']) ? " parentId=\"" . $category['parentId'] . "\"" : "") . ">" .
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
        $txt .= "        <picture>" . $product['image'] . "</picture>\n";
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


function clear_var($var, $type = 'string')
{
    if ($type == 'int') $var = (int)$var;
    else if ($type == 'double') $var = (double)$var;
    else $var = (string)$var;

    $var = preg_replace('|[\s]+|s', ' ', $var);
    $var = str_replace('&quot;', '', $var);
    $var = str_replace("&amp;", '&', $var);
    $var = trim($var);
    return $var;
}