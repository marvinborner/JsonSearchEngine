<?php
/**
 * User: Marvin Borner
 * Date: 16/09/2018
 * Time: 21:53
 */

class WebRequest
{
    public function getContent($url)
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');
        curl_setopt($curl, CURLOPT_ENCODING, '');
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
}