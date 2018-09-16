<?php
/**
 * User: Marvin Borner
 * Date: 16/09/2018
 * Time: 21:51
 */

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

        print "\t\e[92mFound data: " . $urlInfo['title'] . "\n";

        return $urlInfo;
    }

    public static function getLinks($path): array
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
        global $currentlyCrawled;

        $newUrl = ltrim($url); // trim whitespaces

        // normally only for links/href
        if (filter_var($newUrl, FILTER_VALIDATE_URL) === false || (strpos($newUrl, 'http') !== 0)) {
            if (strpos($newUrl, 'www') === 0) {
                $newUrl = 'http://' . $newUrl; // fixes eg. "www.example.com" by adding http:// at beginning
            } else if (strpos($newUrl, 'javascript:') === 0) {
                $newUrl = ''; // fixes javascript void links
            } else if (strpos($newUrl, '../') === 0) {
                $parsedUrl = parse_url($currentlyCrawled);
                $backCount = substr_count($parsedUrl['path'], '../'); // TODO: Better back counter (../../foo/../bar isn't parsed correctly)
                $newUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . dirname($parsedUrl['path'] ?? '', $backCount) . $newUrl; // fixes eg. "../sub_dir" by going back and adding new path
            } else if (strpos($newUrl, '/') === 0) {
                $parsedUrl = parse_url($currentlyCrawled);
                $newUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . $newUrl; // fixes eg. "/sub_dir" by removing path and adding new path
            } else {
                $newUrl = $currentlyCrawled . $newUrl; // fixes eg. "sub_dir" by adding currently crawled url at beginning
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
}