<?php
include 'mysql_conf.inc';

print_r(getContent($argv[1]));

function getContent($query)
{
    $conn = initDbConnection();
    $checkStmt = $conn->prepare('SELECT url, title, description, lang FROM url_data WHERE title LIKE :query OR description LIKE :query');
    $checkStmt->execute([':query' => '%' . $query . '%']);
    return $checkStmt->fetchAll(PDO::FETCH_ASSOC);
}

function initDbConnection()
{
    global $servername, $dbname, $username, $password;
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->exec('SET CHARACTER SET utf8');
    return $conn;
}