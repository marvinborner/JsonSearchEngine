<?php
header('Content-type: text/plain; charset=utf-8');

/**
 * User: Marvin Borner
 * Date: 16/09/2018
 * Time: 21:53
 */
class WebRequest
{
    private static $userAgent = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

    public static function getContent($url)
    {
        if (self::checkRobotsTxt($url)) {
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_USERAGENT, self::$userAgent);
            curl_setopt($curl, CURLOPT_ENCODING, '');
            curl_setopt($curl, CURLOPT_TIMEOUT, 5);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_BINARYTRANSFER, true);
            $content = curl_exec($curl);
            $responseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $downloadSize = curl_getinfo($curl, CURLINFO_SIZE_DOWNLOAD) / 1000 . "KB\n";
            $updatedUrl = curl_getinfo($curl, CURLINFO_EFFECTIVE_URL); // update on 301/302
            curl_close($curl);

            return [$content, $responseCode, $downloadSize, $updatedUrl];
        }

        return false;
    }

    public static function checkRobotsTxt($url): bool
    {
        $userAgent = self::$userAgent;
        $parsed = parse_url($url);
        $agents = array(preg_quote('*', NULL));
        if ($userAgent) {
            $agents[] = preg_quote($userAgent, NULL);
        }
        $agents = implode('|', $agents);
        $robotsTxt = @file("http://{$parsed['host']}/robots.txt");
        if (empty($robotsTxt)) {
            return true;
        }
        $rules = array();
        $ruleApplies = false;
        foreach ($robotsTxt as $line) {
            if (!$line = trim($line)) {
                continue;
            }
            if (preg_match('/^\s*User-agent: (.*)/i', $line, $match)) {
                $ruleApplies = preg_match("/($agents)/i", $match[1]);
            }
            if ($ruleApplies && preg_match('/^\s*Disallow:(.*)/i', $line, $regs)) {
                if (!$regs[1]) {
                    return true;
                }
                $rules[] = preg_quote(trim($regs[1]), '/');
            }
        }
        foreach ($rules as $rule) {
            if (preg_match("/^$rule/", $parsed['path'])) {
                return false;
            }
        }
        return true;
    }
}