<?php

namespace Renogen\Plugin\Taiga;

class Core extends \Renogen\Plugin\PluginCore
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

    public function onDeploymentCreated(\Renogen\Entity\Deployment $deployment)
    {

    }

    public function onDeploymentDateChanged(\Renogen\Entity\Deployment $deployment,
                                            \DateTime $old_date)
    {

    }

    public function onItemStatusUpdated(\Renogen\Entity\Item $item,
                                        $old_status = null)
    {
        
    }

    public function onItemMoved(\Renogen\Entity\Item $item,
                                \Renogen\Entity\Deployment $old_deployment)
    {
        
    }

    public function onItemDeleted(\Renogen\Entity\Item $item)
    {

    }
}