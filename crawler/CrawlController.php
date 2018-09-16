<?php
/**
 * User: Marvin Borner
 * Date: 14/09/2018
 * Time: 23:48
 */

include 'mysql_conf.inc';

class CrawlController
{
    public function __construct()
    {
        set_time_limit(3600000);
        error_reporting(E_ERROR | E_PARSE);

        $currentlyCrawled = $argv[1] ?? '';

        while (true) {
            crawl($currentlyCrawled);
        }
    }

    public function crawl($url)
    {
        global $currentlyCrawled;

        if (Database::alreadyCrawled(Algorithms::cleanUrl($url))) {
            print "\t\e[91mUrl already crawled " . $url . "\n";

            Database::removeFromQueue($currentlyCrawled);
            $currentlyCrawled = $this->getFromQueue('DESC');
        } else {
            $requestResponse = getContent($url);
            $currentlyCrawled = $requestResponse[3];
            if (preg_match('/2\d\d/', $requestResponse[1])) { // success
                print 'Download Size: ' . $requestResponse[2];

                $htmlPath = Algorithms::createPathFromHtml($requestResponse[0]);
                $urlInfo = Algorithms::getUrlInfo($htmlPath);
                $allLinks = Algorithms::getLinks($htmlPath);

                Database::writeToQueue($allLinks);
                $this->saveData($urlInfo, $currentlyCrawled);

                Database::removeFromQueue($currentlyCrawled);
                $currentlyCrawled = Database::getFromQueue('DESC'); // set new from start
            } else {
                print "\t\e[91mError " . $requestResponse[1] . ' ' . $currentlyCrawled . "\n";

                Database::urlHasError($currentlyCrawled); // prevents re-crawling of error url
                Database::removeFromQueue($currentlyCrawled);
                $currentlyCrawled = Database::getFromQueue('ASC'); // set new from end
            }
        }
    }

    public function saveData($urlInfo, $url)
    {
        if ($url !== '') {
            print "\e[96mFinished previous url - crawling: " . $url . "\n";

            $title = $urlInfo['title'] ?? '';
            $description = $urlInfo['description'] ?? '';
            $language = $urlInfo['language'] ?? 'en';
            $hash = md5($url);
            $data = [$title, $description, $language, $hash];

            Database::saveUrlData($data);
        }
    }


}