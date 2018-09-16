<?php
/**
 * User: Marvin Borner
 * Date: 14/09/2018
 * Time: 23:48
 */

set_time_limit(3600000);
error_reporting(E_ERROR | E_PARSE);

include 'mysql_conf.inc';

$currentUrl = $argv[1] ?? '';

while (true) {
    crawl($currentUrl);
}

function crawl($url)
{
    global $currentUrl;

    if (alreadyCrawled(cleanUrl($url))) {
        print "\t\e[91mUrl already crawled " . $url . "\n";

        removeFromQueue($currentUrl);
        $currentUrl = getFromQueue('DESC');
    } else {
        $requestResponse = getContent($url);
        $currentUrl = $requestResponse[3];
        if (preg_match('/2\d\d/', $requestResponse[1])) { // success
            print 'Download Size: ' . $requestResponse[2];

            $htmlPath = createPathFromHtml($requestResponse[0]);
            $urlInfo = getUrlInfo($htmlPath);
            $allLinks = getLinks($htmlPath);

            writeToQueue($allLinks);
            saveData($urlInfo, $currentUrl);

            removeFromQueue($currentUrl);
            $currentUrl = getFromQueue('DESC'); // set new from start
        } else {
            print "\t\e[91mError " . $requestResponse[1] . ' ' . $currentUrl . "\n";

            urlHasError($currentUrl); // prevents re-crawling of error url
            removeFromQueue($currentUrl);
            $currentUrl = getFromQueue('ASC'); // set new from end
        }
    }
}


function getContent($url)
{
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');
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

    return [$content, $responseCode, $downloadSize, $updatedUrl ?? $url];
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

    foreach ($path->query('//a') as $link) {
        $linkHref = $link->getAttribute('href');
        if ($linkHref !== 'javascript:void(0)') {
            $href = cleanUrl($linkHref);
            $allLinks[] = $href;
        }
    }

    return array_unique($allLinks);
}

function cleanUrl($url)
{
    global $currentUrl;

    $newUrl = ltrim($url); // trim whitespaces

    // normally only for links/href
    if (filter_var($newUrl, FILTER_VALIDATE_URL) === false || (strpos($newUrl, 'http') !== 0)) {
        if (strpos($newUrl, 'www') === 0) {
            $newUrl = 'http://' . $newUrl; // fixes eg. "www.example.com" by adding http:// at beginning
        } else if ($newUrl === 'javascript:void') {
            $newUrl = '';
        } else if (strpos($url, '/') === 0) {
            $parsedUrl = parse_url($currentUrl);
            $newUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . $newUrl; // fixes eg. "/sub_dir" by removing path and adding new path
        } else {
            $newUrl = $currentUrl . $newUrl; // fixes eg. "sub_dir" by adding currently crawled url at beginning
        }
    }

    // if it's pure domain without slash (prevents duplicate domains because of slash)
    if (preg_match('/\w+\.\w{2,3}$/', $newUrl)) {
        $newUrl .= '/';
    }

    // strip some things
    $newUrl = preg_replace('/([^:])(\/{2,})/', '$1/', $newUrl); // double slashes
    $newUrl = strtok($newUrl, '?'); // parameters
    $newUrl = strtok($newUrl, '#'); // hash fragments

    if ($url !== $newUrl) {
        print "\t\e[92mChanged " . $url . ' to ' . $newUrl . "\n";
    }

    return $newUrl;
}

function createPathFromHtml($content)
{
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($content);
    libxml_use_internal_errors(false);
    return new DOMXPath($dom);
}

function getFromQueue($sort)
{
    print "\t\e[96mStarting at " . ($sort === 'DESC' ? 'bottom' : 'top') . " of queue\n";
    $conn = initDbConnection();
    $checkStmt = $conn->query('SELECT url FROM queue ORDER BY id ' . $sort . ' LIMIT 1');

    return $checkStmt->fetchAll(PDO::FETCH_ASSOC)[0]['url'];
}

function writeToQueue($urls)
{
    $conn = initDbConnection();

    foreach ($urls as $url) {
        if ($url !== '') {
            $hash = md5($url);

            print "\t\e[96mChecking if url already has been crawled " . $url . "\n";
            $checkStmt = $conn->prepare('SELECT null FROM url_data WHERE hash = :hash');
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
}

function removeFromQueue($url)
{
    $hash = md5($url);

    $conn = initDbConnection();
    $checkStmt = $conn->prepare('DELETE FROM queue WHERE hash = :hash');
    $checkStmt->execute([':hash' => $hash]);
}

function urlHasError($url)
{
    $hash = md5($url);

    $conn = initDbConnection();
    $checkStmt = $conn->prepare('INSERT IGNORE INTO error_url (url, hash) VALUES (:url, :hash)');
    $checkStmt->execute([':url' => $url, 'hash' => $hash]);
}

function saveData($urlInfo, $url)
{
    if ($url !== '') {
        print "\e[96mFinished previous url - crawling: " . $url . "\n";

        $title = mb_convert_encoding($urlInfo['title'] ?? '', 'Windows-1252', 'UTF-8');
        $description = mb_convert_encoding($urlInfo['description'] ?? '', 'Windows-1252', 'UTF-8');
        $language = $urlInfo['language'] ?? 'en';
        $hash = md5($url);

        try {
            $conn = initDbConnection();
            $stmt = $conn->prepare('INSERT IGNORE INTO url_data (url, title, description, lang, hash) VALUES (:url, :title, :description, :lang, :hash)');
            $stmt->execute([':url' => $url, ':title' => $title, ':description' => $description, ':lang' => $language, ':hash' => $hash]);
        } catch (PDOException $e) {
            print $e->getMessage();
        }
    }
}

function alreadyCrawled($url)
{
    $hash = md5($url);
    $conn = initDbConnection();
    $checkStmt = $conn->prepare('(SELECT null FROM url_data WHERE hash = :hash) UNION (SELECT null FROM error_url WHERE hash = :hash)');
    $checkStmt->execute([':hash' => $hash]);
    return $checkStmt->rowCount() !== 0; // return true if already crawled
}

function initDbConnection()
{
    global $servername, $dbname, $username, $password;
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $conn;
}