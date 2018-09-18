<?php
/**
 * User: Marvin Borner
 * Date: 14/09/2018
 * Time: 23:48
 */

require_once 'mysql_conf.inc';
require_once 'WebRequest.php';
require_once 'Database.php';
require_once 'Algorithms.php';

class CrawlController
{
    private static $currentlyCrawled;

    public static function start($url = '')
    {
        set_time_limit(3600000);
        error_reporting(E_ERROR | E_PARSE);

        while (true) {
            self::$currentlyCrawled = $url;
            self::crawl(self::$currentlyCrawled);
        }
    }

    private static function crawl($url)
    {
        if (Database::alreadyCrawled(Algorithms::cleanUrl($url))) {
            Database::removeFromQueue(self::$currentlyCrawled);
            self::$currentlyCrawled = Database::getFromQueue('DESC');
        } else {
            $requestResponse = WebRequest::getContent($url);
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