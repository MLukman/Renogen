<?php
include_once __DIR__.'/../vendor/autoload.php';

$app = new Renogen\Application();
$app->initializeOrRefreshDatabaseSchemas();
