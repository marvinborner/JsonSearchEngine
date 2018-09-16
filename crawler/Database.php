<?php
/**
 * User: Marvin Borner
 * Date: 16/09/2018
 * Time: 21:34
 */

class Database
{
    public static function getFromQueue($sort): string
    {
        print "\t\e[96mStarting at " . ($sort === 'DESC' ? 'bottom' : 'top') . " of queue\n";
        $conn = self::initDbConnection();
        $checkStmt = $conn->query('SELECT url FROM queue ORDER BY id ' . $sort . ' LIMIT 1');

        return $checkStmt->fetchAll(PDO::FETCH_ASSOC)[0]['url'];
    }

    private static function initDbConnection(): PDO
    {
        global $servername, $dbname, $username, $password;
        $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    }

    public static function insertIntoQueue($url): bool
    {
        if (!self::alreadyCrawled($url)) {
            $conn = self::initDbConnection();
            $hash = md5($url);
            $stmt = $conn->prepare('INSERT IGNORE INTO queue (url, hash) VALUES (:url, :hash)');
            $stmt->execute([':url' => $url, 'hash' => $hash]);
            return $stmt->rowCount() > 0;
        }
    }

    public static function alreadyCrawled($url): bool
    {
        $hash = md5($url);
        $conn = self::initDbConnection();
        $checkStmt = $conn->prepare('(SELECT null FROM url_data WHERE hash = :hash) UNION (SELECT null FROM error_url WHERE hash = :hash)');
        $checkStmt->execute([':hash' => $hash]);
        return $checkStmt->rowCount() !== 0; // return true if already crawled
    }

    public static function removeFromQueue($url): void
    {
        $hash = md5($url);
        $conn = self::initDbConnection();
        $checkStmt = $conn->prepare('DELETE FROM queue WHERE hash = :hash');
        $checkStmt->execute([':hash' => $hash]);
    }

    public static function urlHasError($url): void
    {
        $hash = md5($url);
        $conn = self::initDbConnection();
        $checkStmt = $conn->prepare('INSERT IGNORE INTO error_url (url, hash) VALUES (:url, :hash)');
        $checkStmt->execute([':url' => $url, 'hash' => $hash]);
    }

    public static function saveUrlData($data): void
    {
        $conn = self::initDbConnection();
        $stmt = $conn->prepare('INSERT IGNORE INTO url_data (url, title, description, lang, hash) VALUES (:url, :title, :description, :lang, :hash)');
        $stmt->execute([':url' => $data[0], ':title' => $data[1], ':description' => $data[2], ':lang' => $data[3], ':hash' => $data[4]]);
    }
}