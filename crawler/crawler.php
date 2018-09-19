<?php
header('Content-type: text/plain; charset=utf-8');
require_once 'CrawlController.php';

CrawlController::start($argv[1]);