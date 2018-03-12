<?php

use Renogen\Entity\ItemStatusLog;

include_once __DIR__.'/../vendor/autoload.php';
const STATUSLOG = '\Renogen\Entity\ItemStatusLog';
$app = new Renogen\Application();

$ds = $app['datastore'];
foreach ($ds->queryMany('\Renogen\Entity\Item') as $item) {
    if (0 == count($ds->queryMany(STATUSLOG, array(
                'status' => 'Unsubmitted',
                'created_date' => $item->created_date)))) {
        $log          = new ItemStatusLog($item, 'Unsubmitted', $item->created_by, $item->created_date);
        $item->status = 'Unsubmitted';
        $ds->commit($log);
    }

    if ($item->submitted_date && 0 == count($ds->queryMany(STATUSLOG, array(
                'status' => 'Pending Approval',
                'created_date' => $item->submitted_date)))) {
        $log          = new ItemStatusLog($item, 'Pending Approval', $item->submitted_by, $item->submitted_date);
        $item->status = 'Pending Approval';
        $ds->commit($log);
    }

    if ($item->approved_date && 0 == count($ds->queryMany(STATUSLOG, array(
                'status' => 'Approved',
                'created_date' => $item->approved_date)))) {
        $log          = new ItemStatusLog($item, 'Approved', $item->approved_by, $item->approved_date);
        $item->status = 'Approved';
        $ds->commit($log);
    }
    if ($item->rejected_date && 0 == count($ds->queryMany(STATUSLOG, array(
                'status' => 'Rejected',
                'created_date' => $item->approved_date)))) {
        $log          = new ItemStatusLog($item, 'Rejected', $item->rejected_by, $item->rejected_date);
        $item->status = 'Rejected';
        $ds->commit($log);
    }

    $ds->commit($item);
}
