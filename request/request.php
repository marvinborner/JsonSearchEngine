<?php
include '../database/mysqlConf.inc';
include '../database/Database.php';

print_r(Database::getUrlData($_GET['search'] ?? ''));