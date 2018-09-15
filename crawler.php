<?php
/**
 * User: Marvin Borner
 * Date: 14/09/2018
 * Time: 23:48
 */

error_reporting(E_ERROR | E_PARSE);

include 'mysql_conf.inc';

$currentUrl = $argv[1];

while (true) {
    crawl($currentUrl);
}

function crawl($url)
{
    global $currentUrl;

    if (!alreadyCrawled(cleanUrl($url))) {
        $requestResponse = getContent($url);
        if (preg_match('/2\d\d/', $requestResponse[1])) {
            print 'Download Size: ' . $requestResponse[2];

            $htmlPath = createPathFromHtml($requestResponse[0]);
            $urlInfo = getUrlInfo($htmlPath);
            $allLinks = getLinks($htmlPath);

            writeToQueue($allLinks);
            saveData($urlInfo);
        }
    }

    $currentUrl = getFirstFromQueue(); // set new
    removeFromQueue($currentUrl);
}


function getContent($url)
{
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_USERAGENT, 'Googlebot/2.1 (+http://www.google.com/bot.html)');
    curl_setopt($curl, CURLOPT_TIMEOUT, 5);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_BINARYTRANSFER, true);
    $content = curl_exec($curl);
    $responseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $downloadSize = curl_getinfo($curl, CURLINFO_SIZE_DOWNLOAD) / 1000 . "KB\n";
    if (preg_match('~Location: (.*)~i', $content, $match)) {
        $updatedUrl = trim($match[1]); // update url on 301/302
    }
    curl_close($curl);

    return [$content, $responseCode, $downloadSize, $updatedUrl ?? ''];
}

function getUrlInfo($path)
{
    $urlInfo = [];

    $urlInfo['title'] = strip_tags($path->query('//title')[0]->textContent);
    foreach ($path->query('//html') as $language) {
        $urlInfo['language'] = strip_tags($language->getAttribute('lang'));
    }
    foreach ($path->query('/html/head/meta[@name="description"]') as $description) {
        $urlInfo['description'] = strip_tags($description->getAttribute('content'));
    }

    // Fix empty information
    if (!isset($urlInfo['description'])) {
        $urlInfo['description'] = '';
        foreach ($path->query('//p') as $text) {
            if (strlen($urlInfo['description']) < 350) {
                $urlInfo['description'] .= $text->textContent . ' ';
            }
        }
    }

    if (empty($urlInfo['title'])) {
        $urlInfo['title'] = '';
        if (strlen($urlInfo['title']) < 350) {
            $urlInfo['title'] .= $path->query('//h1')[0]->textContent . ' ';
        }
    }

    return $urlInfo;
}

function getLinks($path)
{
    $allLinks = [];

    foreach ($path->query('//a') as $ink) {
        $href = cleanUrl($ink->getAttribute('href'));
        $allLinks[] = $href;
    }

    return array_unique($allLinks);
}

function cleanUrl($url)
{
    global $currentUrl;

    $url = ltrim($url);

    if (!(strpos($url, 'http') === 0)) {
        if (strpos($url, 'www') === 0) {
            $url = 'http://' . $url;
        } else if (strpos($url, '/') === 0) {
            $url = $currentUrl . $url;
        } else {
            $url = $currentUrl . $url;
        }
    }

    // if it's pure domain without slash (prevents duplicate domains because of slash)
    if (preg_match('/\w+\.\w{2,3}$/', $url)) {
        $url .= '/';
    }

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
    $checkStmt = $conn->query('SELECT url FROM queue LIMIT 1');

    return $checkStmt->fetchAll(PDO::FETCH_ASSOC)[0]['url'];
}

function writeToQueue($urls)
{
    $conn = initDbConnection();

    foreach ($urls as $url) {
        $hash = md5($url);

        print "\t\e[96mChecking if url already has been crawled " . $url . "\n";
        $checkStmt = $conn->prepare('SELECT null FROM url_data where hash = :hash');
        $checkStmt->execute(['hash' => $hash]);
        if ($checkStmt->rowCount() === 0) {
            $stmt = $conn->prepare('INSERT IGNORE INTO queue (url, hash) VALUES (:url, :hash)');
            $stmt->execute([':url' => $url, 'hash' => $hash]);
            if ($stmt->rowCount() > 0) {
                print "\t\e[92mQueueing url " . $url . "\n";
            } else {
                print "\t\e[91mUrl already queued " . $url . "\n";
            }
        } else {
            print "\t\e[91mUrl already crawled " . $url . "\n";
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

    print "\e[96mFinished previous url - crawling: " . $currentUrl . "\n";

    $title = $urlInfo['title'] ?? '';
    $description = $urlInfo['description'] ?? '';
    $language = $urlInfo['language'] ?? 'en';
    $hash = md5($currentUrl);

    try {
        $conn = initDbConnection();
        $stmt = $conn->prepare('INSERT IGNORE INTO url_data (url, title, description, lang, hash) VALUES (:url, :title, :description, :lang, :hash)');
        $stmt->execute([':url' => $currentUrl, ':title' => $title, ':description' => $description, ':lang' => $language, ':hash' => $hash]);
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