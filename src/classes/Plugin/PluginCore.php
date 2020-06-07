<?php

namespace Renogen\Plugin;

use Renogen\App;
use Renogen\Entity\Deployment;
use Renogen\Entity\Item;

abstract class PluginCore
{
    protected $options = array();
    protected $app;

    public function __construct(array $options)
    {
        $this->app = App::instance();
        $this->setOptions($options);
    }

    abstract static function getIcon();

    abstract static function getTitle();

    abstract public function onDeploymentCreated(Deployment $deployment);

    abstract public function onDeploymentDateChanged(Deployment $deployment,
                                                     \DateTime $old_date);

    abstract public function onItemStatusUpdated(Item $item, $old_status = null);

    abstract public function onItemMoved(Item $item, Deployment $old_deployment);

    abstract public function onItemDeleted(Item $item);

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