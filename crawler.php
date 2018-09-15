<?php
/**
 * User: Marvin Borner
 * Date: 14/09/2018
 * Time: 23:48
 */

include "mysql_conf.inc";

$currentUrl = $argv[1];
crawlLoop();

function crawlLoop()
{
    global $currentUrl;

    $content = getContent($currentUrl);
    $htmlPath = createPathFromHtml($content);
    $urlInfo = getUrlInfo($htmlPath);
    $allLinks = getLinks($htmlPath);

    writeToQueue($allLinks);
    saveData($urlInfo);
}


function getContent($url)
{
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_BINARYTRANSFER, true);
    $content = curl_exec($curl);
    print "Download Size: " . curl_getinfo($curl, CURLINFO_SIZE_DOWNLOAD) / 1000 . "KB\n";
    curl_close($curl);

    return $content;
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
    global $currentUrl;
    $allLinks = [];

    foreach ($path->query("//a") as $ink) {
        $href = ltrim($ink->getAttribute("href"));

        if (!(substr($href, 0, 4) === "http")) {
            if (substr($href, 0, 3) === "www") $href = "http://" . $href;
            else if (substr($href, 0, 1) === "/") $href = $currentUrl . $href;
            else $href = $currentUrl . $href;
        }

        // if it's pure domain without slash (prevents duplicate domains because of slash)
        if (preg_match('/\w+\.\w{2,3}$/', $href)) $href = $href . "/";

        array_push($allLinks, $href);
    }

    return array_unique($allLinks);
}

function createPathFromHtml($content)
{
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($content);
    libxml_use_internal_errors(false);
    return new DOMXPath($dom);
}

function writeToQueue($urls)
{
    $conn = initDbConnection();

    foreach ($urls as $url) {
        $hash = md5($url);

        $checkStmt = $conn->prepare('SELECT hash FROM url_data where hash = :hash');
        $checkStmt->execute(['hash' => $hash]);
        if ($checkStmt->rowCount() === 0) {
            $stmt = $conn->prepare('INSERT IGNORE INTO queue (url, hash) VALUES (:url, :hash)');
            $stmt->execute([':url' => $url, 'hash' => $hash]);
        }
    }
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

function initDbConnection()
{
    global $servername, $dbname, $username, $password;
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $conn;
}