<?php

namespace Renogen\Entity;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Renogen\Base\Entity;
use Renogen\Plugin\BaseCore;

/**
 * @Entity @Table(name="plugins")
 */
class Plugin extends Entity
{
    /**
     * @Id @ManyToOne(targetEntity="Project")
     * @JoinColumn(name="project_id", referencedColumnName="id", onDelete="CASCADE")
     * @var Project
     */
    public $project;

    /**
     * @Id
     * @Column(type="string", length=100)
     */
    public $name;

    /**
     * @Column(type="string", length=255)
     */
    public $class;

    /**
     *
     * @var BaseCore
     */
    protected $instance;

    /**
     * @Column(type="json_array", nullable=true)
     */
    public $options;

    public function __construct(Project $project, BaseCore $pluginInstance)
    {
        $this->project = $project;
        $this->setInstance($pluginInstance);
    }

    /**
     *
     * @return BaseCore
     */
    public function instance()
    {
        if (!$this->instance) {
            $cls            = $this->class;
            $this->instance = new $cls($this->options);
        }
        return $this->instance;
    }

    public function setInstance(BaseCore $pluginInstance)
    {
        $this->instance = $pluginInstance;
        $this->class    = get_class($this->instance);
        $this->name     = $this->instance->getName();
        $this->options  = $this->instance->getOptions();
    }
}