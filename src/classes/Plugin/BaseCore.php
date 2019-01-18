<?php

namespace Renogen\Plugin;

abstract class BaseCore
{
    protected $options = array();
    protected $app;

    public function __construct(array $options)
    {
        $this->options = $options;
        $this->app     = \Renogen\Application::instance();
    }

    abstract function getPluginTitle();

    abstract function getIcon();

    abstract public function onDeploymentCreated(\Renogen\Entity\Deployment $deployment);

    abstract public function onDeploymentDateChanged(\Renogen\Entity\Deployment $deployment,
                                                     \DateTime $old_date);

    abstract public function onItemStatusUpdated(\Renogen\Entity\Item $item,
                                                 $old_status = null);

    abstract public function onItemDeleted(\Renogen\Entity\Item $item);

    public function getOptions()
    {
        return $this->options;
    }

    public function setOptions(array $options)
    {
        $this->options = $options;
    }

    public function getName()
    {
        $reflection = new \ReflectionClass($this);
        return basename(dirname($reflection->getFileName()));
    }

    protected function getTemplateFileBasePath()
    {
        return '@plugin/'.$this->getName().'/';
    }
}