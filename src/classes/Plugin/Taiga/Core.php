<?php

namespace Renogen\Plugin\Taiga;

use Renogen\Entity\Deployment;
use Renogen\Entity\Item;
use Renogen\Plugin\PluginCore;

class Core extends PluginCore
{
    protected $options = array(
        'allow_delete_item' => false,
        'delete_fresh_item_only' => false,
        'extract_refnum_from_subject' => null,
        'auto_refnum_from_id_prefix' => null,
        'auto_refnum_from_id_lpad' => 1,
        'deployment_date_adjust' => '+0 day',
        'deployment_time' => '12:00 AM',
    );

    static public function getIcon()
    {
        return 'shipping fast';
    }

    static public function getTitle()
    {
        return 'Integration with Taiga';
    }

    public function onDeploymentCreated(Deployment $deployment)
    {

    }

    public function onDeploymentDateChanged(Deployment $deployment,
                                            \DateTime $old_date)
    {

    }

    public function onDeploymentDeleted(Deployment $deployment)
    {

    }

    public function onItemStatusUpdated(Item $item, $old_status = null)
    {
        
    }

    public function onItemMoved(Item $item, Deployment $old_deployment)
    {
        
    }

    public function onItemDeleted(Item $item)
    {
        
    }
}