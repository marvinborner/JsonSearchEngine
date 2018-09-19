<?php
header('Content-type: text/plain; charset=utf-8');

/**
 * User: Marvin Borner
 * Date: 16/09/2018
 * Time: 21:51
 */

require_once 'CrawlController.php';

class Algorithms
{
    public static function getUrlInfo($path): array
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
                if (mb_strlen($urlInfo['description']) < 350) {
                    $urlInfo['description'] .= $text->textContent . ' ';
                }
            }
        }
        if (empty($urlInfo['title'])) {
            $urlInfo['title'] = '';
            if (mb_strlen($urlInfo['title']) < 350) {
                $urlInfo['title'] .= $path->query('//h1')[0]->textContent . ' ';
            }
        }

        print "\t\e[92mFound data: " . $urlInfo['title'] . "\n";

        return $urlInfo;
    }

    public static function getLinks($path): array
    {
        $allLinks = [];

        foreach ($path->query('//a') as $link) {
            $linkHref = $link->getAttribute('href');
            $href = self::cleanUrl($linkHref);
            $allLinks[] = $href;
        }

        return array_unique($allLinks);
    }

    public static function createPathFromHtml($content): \DOMXPath
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($content);
        libxml_use_internal_errors(false);
        return new DOMXPath($dom);
    }

    public static function cleanUrl($url): string
    {
        $newUrl = self::fixEncoding(ltrim($url)); // trim whitespaces

        // normally only for links/href
        if (filter_var($newUrl, FILTER_VALIDATE_URL) === false || mb_strpos($newUrl, 'http') !== 0) {
            if (mb_strpos($newUrl, 'www') === 0) {
                $newUrl = 'http://' . $newUrl; // fixes eg. "www.example.com" by adding http:// at beginning
            } else if (mb_strpos($newUrl, 'javascript:') === 0 || mb_strpos($newUrl, 'mailto') === 0) {
                $newUrl = CrawlController::$currentlyCrawled; // fixes javascript void links
            } else if (mb_strpos($newUrl, '../') === 0) {
                $parsedUrl = parse_url(CrawlController::$currentlyCrawled);
                $backCount = mb_substr_count($parsedUrl['path'], '../'); // TODO: Better back counter (../../foo/../bar isn't parsed correctly)
                if ($backCount >= 1) {
                    $newUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . dirname($parsedUrl['path'] ?? '', $backCount) . $newUrl; // fixes eg. "../sub_dir" by going back and adding new path
                }
            } else if (mb_strpos($newUrl, '/') === 0) {
                $parsedUrl = parse_url(CrawlController::$currentlyCrawled);
                $newUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . $newUrl; // fixes eg. "/sub_dir" by removing path and adding new path
            } else {
                $newUrl = '/' . CrawlController::$currentlyCrawled . $newUrl; // fixes eg. "sub_dir" by adding currently crawled url at beginning
            }
        }

        // if it's pure domain without slash (prevents duplicate domains because of slash)
        if (preg_match('/\w+\.\w{2,3}$/', $newUrl)) {
            $newUrl .= '/';
        }

        // strip some things
        $newUrl = preg_replace('/([^:])(\/{2,})/', '$1/', $newUrl); // double slashes
        $newUrl = self::mb_strtok($newUrl, '?'); // parameters
        $newUrl = self::mb_strtok($newUrl, '#'); // hash fragments

        if (mb_strpos($newUrl, '/') === 0) {
            $newUrl = mb_substr($newUrl, 1); // remove first slash from domain, which could have been added
        }

        if ($url !== $newUrl) {
            print "\t\e[92mChanged " . $url . ' to ' . $newUrl . "\n";
        }

        return $newUrl;
    }

    private static function fixEncoding($text): string
    {
        return iconv(mb_detect_encoding($text, mb_detect_order(), true), 'UTF-8', $text);
    }

    private static function mb_strtok($str, $delimiters)
    {
        $pos = 0;
        $string = $str;

        $token = '';

        while ($pos < mb_strlen($string)) {
            $char = mb_substr($string, $pos, 1);
            $pos++;
            if (mb_strpos($delimiters, $char) === FALSE) {
                $token .= $char;
            } else if ($token !== '') {
                return $token;
            }
        }

        if ($token !== '') {
            return $token;
        }

        return false;
    }
}