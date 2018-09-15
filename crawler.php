<?php
/**
 * User: Marvin Borner
 * Date: 14/09/2018
 * Time: 23:48
 */

error_reporting(E_ERROR | E_PARSE);

include "mysql_conf.inc";

$currentUrl = $argv[1];

while (true) {
    crawl($currentUrl);
}

function crawl($url)
{
    global $currentUrl;

    if (!alreadyCrawled(cleanUrl($url))) {
        $requestResponse = getContent($url);
        if ($requestResponse[1] != 404) {
            print "Download Size: " . $requestResponse[2];

            $htmlPath = createPathFromHtml($requestResponse[0]);
            $urlInfo = getUrlInfo($htmlPath);
            $allLinks = getLinks($htmlPath);

            writeToQueue($allLinks);
            saveData($urlInfo);
        }
    }

    $currentUrl = getFirstFromQueue(); // set new
    removeFromQueue($currentUrl);

    return;
}


function getContent($url)
{
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_USERAGENT, "Googlebot/2.1 (+http://www.google.com/bot.html)");
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_BINARYTRANSFER, true);
    $content = curl_exec($curl);
    $responseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $downloadSize = curl_getinfo($curl, CURLINFO_SIZE_DOWNLOAD) / 1000 . "KB\n";
    curl_close($curl);

    return [$content, $responseCode, $downloadSize];
}

function getUrlInfo($path)
{
    $urlInfo = [];

    foreach ($path->query("//html") as $html) $urlInfo["language"] = $html->getAttribute("lang");
    foreach ($path->query("//meta") as $meta) $urlInfo[$meta->getAttribute("name")] = $meta->getAttribute("content");
    foreach ($path->query("//link") as $link) $urlInfo[$link->getAttribute("rel")] = $link->getAttribute("href");
    $urlInfo["title"] = $path->query("//title")[0]->textContent;

    return $urlInfo;
}

function getLinks($path)
{
    $allLinks = [];

    foreach ($path->query("//a") as $ink) {
        $href = cleanUrl($ink->getAttribute("href"));
        array_push($allLinks, $href);
    }

    return array_unique($allLinks);
}

function cleanUrl($url)
{
    global $currentUrl;

    $url = ltrim($url);

    if (!(substr($url, 0, 4) === "http")) {
        if (substr($url, 0, 3) === "www") $url = "http://" . $url;
        else if (substr($url, 0, 1) === "/") $url = $currentUrl . $url;
        else $url = $currentUrl . $url;
    }

    // if it's pure domain without slash (prevents duplicate domains because of slash)
    if (preg_match('/\w+\.\w{2,3}$/', $url)) $url = $url . "/";

    // strip some things
    $url = preg_replace('/([^:])(\/{2,})/', '$1/', $url); // double slashes
    $url = strtok($url, '?'); // parameters
    $url = strtok($url, '#'); // hash fragments

    return $url;
}

function createPathFromHtml($content)
{
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($content);
    libxml_use_internal_errors(false);
    return new DOMXPath($dom);
}

function getFirstFromQueue()
{
    $conn = initDbConnection();
    $checkStmt = $conn->prepare('SELECT url FROM queue LIMIT 1');
    $checkStmt->execute();

    return $checkStmt->fetchAll(PDO::FETCH_ASSOC)[0]["url"];
}

function writeToQueue($urls)
{
    $conn = initDbConnection();

    foreach ($urls as $url) {
        $hash = md5($url);

        $checkStmt = $conn->prepare('SELECT null FROM url_data where hash = :hash');
        $checkStmt->execute(['hash' => $hash]);
        if ($checkStmt->rowCount() === 0) {
            $stmt = $conn->prepare('INSERT IGNORE INTO queue (url, hash) VALUES (:url, :hash)');
            $stmt->execute([':url' => $url, 'hash' => $hash]);
        }
    }
}

function removeFromQueue($url)
{
    $hash = md5($url);

    $conn = initDbConnection();
    $checkStmt = $conn->prepare('DELETE FROM queue where hash = :hash');
    $checkStmt->execute(['hash' => $hash]);
}

function saveData($urlInfo)
{
    global $currentUrl;

    print $currentUrl . "\n";

    $title = isset($urlInfo["title"]) ? $urlInfo["title"] : "";
    $description = isset($urlInfo["description"]) ? $urlInfo["description"] : "";
    $icon = isset($urlInfo["icon"]) ? $urlInfo["icon"] : "";
    $language = isset($urlInfo["language"]) ? $urlInfo["language"] : "en";
    $hash = md5($currentUrl);

    try {
        $conn = initDbConnection();
        $stmt = $conn->prepare('INSERT IGNORE INTO url_data (url, title, description, icon, lang, hash) VALUES (:url, :title, :description, :icon, :lang, :hash)');
        $stmt->execute([':url' => $currentUrl, ':title' => $title, ':description' => $description, ':icon' => $icon, ':lang' => $language, ':hash' => $hash]);
    } catch (PDOException $e) {
        print $e->getMessage();
    }
}

function alreadyCrawled($url)
{
    $hash = md5($url);
    $conn = initDbConnection();
    $checkStmt = $conn->prepare('SELECT null FROM url_data where hash = :hash');
    $checkStmt->execute(['hash' => $hash]);
    return $checkStmt->rowCount() !== 0; // return true if already crawled
}

function initDbConnection()
{
    global $servername, $dbname, $username, $password;
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $conn;
}