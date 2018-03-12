<?php
include_once __DIR__.'/../vendor/autoload.php';
$app = new Renogen\Application();

$ds = $app['datastore'];
foreach ($ds->queryMany('\Renogen\Entity\Activity') as $act) {
    $act->calculateSignature();
    $ds->commit($act);
}
