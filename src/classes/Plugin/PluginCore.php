<?php

namespace Renogen\Plugin;

abstract class PluginCore
{
    protected $options = array();
    protected $app;

    public function __construct(array $options)
    {
        $this->app = \Renogen\App::instance();
        $this->setOptions($options);
    }

    abstract static function getIcon();

    abstract static function getTitle();

    abstract public function onDeploymentCreated(\Renogen\Entity\Deployment $deployment);

    abstract public function onDeploymentDateChanged(\Renogen\Entity\Deployment $deployment,
                                                     \DateTime $old_date);

    abstract public function onItemStatusUpdated(\Renogen\Entity\Item $item,
                                                 $old_status = null);

    abstract public function onItemMoved(\Renogen\Entity\Item $item,
                                         \Renogen\Entity\Deployment $old_deployment);

    abstract public function onItemDeleted(\Renogen\Entity\Item $item);

    public function getOptions($key = null)
    {
        if ($key) {
            return isset($this->options[$key]) ? $this->options[$key] : null;
        }
        return $this->options;
    }

    public function setOptions(array $options)
    {
        $this->options = array_merge($this->options, $options);
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