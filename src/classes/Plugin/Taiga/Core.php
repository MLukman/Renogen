<?php

namespace Renogen\Plugin\Taiga;

class Core extends \Renogen\Plugin\PluginCore
{
    protected $options = array(
        'allow_delete_item' => false,
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

    public function onItemDeleted(\Renogen\Entity\Item $item)
    {

    }

    public function onItemStatusUpdated(\Renogen\Entity\Item $item,
                                        $old_status = null)
    {

    }
}