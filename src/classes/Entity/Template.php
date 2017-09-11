<?php

namespace Renogen\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Renogen\Base\Entity;

/**
 * @Entity @Table(name="templates")
 */
class Template extends Entity
{
    /**
     * @Id @Column(type="string") @GeneratedValue(strategy="UUID")
     */
    public $id;

    /**
     * @ManyToOne(targetEntity="Project")
     * @JoinColumn(name="project_id", referencedColumnName="id", onDelete="CASCADE")
     * @var Project
     */
    public $project;

    /**
     * @Column(type="string", length=100)
     */
    public $title;

    /**
     * @Column(type="text", nullable=true)
     */
    public $description;

    /**
     * @Column(type="string", length=100)
     */
    public $class;

    /**
     * @Column(type="integer")
     */
    public $priority = 1;

    /**
     * @Column(type="json_array", nullable=true)
     */
    public $parameters;

    /**
     * @OneToMany(targetEntity="Activity", mappedBy="template", indexBy="id")
     * @var ArrayCollection
     */
    public $activities = null;

    /**
     * Validation rules
     * @var array
     */
    protected $validation_rules = array(
        'class' => array('required' => 1),
        'title' => array('trim' => 1, 'required' => 1, 'unique' => 'project', 'maxlen' => 100),
    );

    public function __construct(Project $project)
    {
        $this->project    = $project;
        $this->activities = new ArrayCollection();
    }
}