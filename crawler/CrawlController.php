<?php
header('Content-type: text/plain; charset=utf-8');
/**
 * User: Marvin Borner
 * Date: 14/09/2018
 * Time: 23:48
 */

require_once '../database/mysqlConf.inc';
require_once '../database/Database.php';
require_once 'WebRequest.php';
require_once 'Algorithms.php';

class CrawlController
{
    public static $currentlyCrawled;

    public static function start($url = '')
    {
        set_time_limit(3600000);

        self::$currentlyCrawled = $url;

        while (true) {
            self::crawl(Algorithms::cleanUrl(self::$currentlyCrawled));
        }
    }

    private static function crawl($url)
    {
        if ($url !== '' && Database::alreadyCrawled($url)) {
            Database::removeFromQueue(self::$currentlyCrawled);
            self::$currentlyCrawled = Database::getFromQueue('DESC');
        } else {
            $requestResponse = WebRequest::getContent($url);
            if ($requestResponse) {
                self::$currentlyCrawled = $requestResponse[3];
                if (preg_match('/2\d\d/', $requestResponse[1])) { // success
                    print 'Download Size: ' . $requestResponse[2];

                    $htmlPath = Algorithms::createPathFromHtml($requestResponse[0]);

                    $urlInfo = Algorithms::getUrlInfo($htmlPath);
                    Database::saveUrlData(self::$currentlyCrawled, $urlInfo);

                    $allLinks = Algorithms::getLinks($htmlPath);
                    Database::insertIntoQueue($allLinks);

                    Database::removeFromQueue(self::$currentlyCrawled);
                    self::$currentlyCrawled = Database::getFromQueue('DESC'); // set new from start
                    print "\e[96mFinished previous url - crawling: " . self::$currentlyCrawled . "\n";
                } else {
                    print "\t\e[91mError " . $requestResponse[1] . ' ' . self::$currentlyCrawled . "\n";

                    Database::urlHasError(self::$currentlyCrawled); // prevents re-crawling of error url
                    Database::removeFromQueue(self::$currentlyCrawled);
                    self::$currentlyCrawled = Database::getFromQueue('ASC'); // set new from end
                    print "\e[91mFinished previous url with error - crawling: " . self::$currentlyCrawled . "\n";
                }
            }
        }
    }
}