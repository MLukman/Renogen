<?php

namespace Renogen\Plugin\Taiga;

class Core extends \Renogen\Plugin\PluginCore
{

    static public function getIcon()
    {
        return 'help';
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