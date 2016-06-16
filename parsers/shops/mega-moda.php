<?php
/**
 * User: gaalferov
 * Date: 29.02.16
 * Time: 12:00
 */
require(__DIR__ . '/../../helpers/simple_html_dom.php');

header('Content-type: text/html; charset=utf-8');
set_time_limit(0);
date_default_timezone_set('Europe/Moscow');

$inParceUrl = "http://www.mega-moda.ru/tiu.xml";
$companySite = "http://www.mega-moda.ru";
$company = $companyName = "mega-moda";
$outXml = OUTPUT_PATH . $company . '.xml';

saveFile($outXml, $inParceUrl);


function saveFile($outXml, $inParceUrl)
{
    @unlink($outXml);
    $fd = fopen($outXml, "a");
    fwrite($fd, httpRequest($inParceUrl));
    fclose($fd);
}

function httpRequest($url)
{
    $useragent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/44.0.2403.89 Safari/537.36';
    $timeout = 0;

    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_ENCODING, "");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
    curl_setopt($ch, CURLOPT_REFERER, $url);

    $content = curl_exec($ch);

    if (curl_errno($ch)) {
        echo 'error:' . curl_error($ch);
        $res = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        var_dump($res);
        exit('');
    }

    curl_close($ch);

    $content = str_replace('<!DOCTYPE yml_catalog SYSTEM "shops.dtd">', '', $content);

    return $content;
}