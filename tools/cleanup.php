<?php

use Renogen\App;

include_once __DIR__.'/../vendor/autoload.php';
const STATUSLOG = '\Renogen\Entity\ItemStatusLog';
$app = new App();

$ds = $app['datastore'];
foreach ($ds->queryMany('\Renogen\Entity\FileStore') as $file) {
    if ($file->links->count() == 0) {
        $ds->deleteEntity($file);
    }
}

$ds->commit($item);
