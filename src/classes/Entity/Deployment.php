<?php

namespace Renogen\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Renogen\Base\Entity;

/**
 * @Entity @Table(name="deployments")
 */
class Deployment extends Entity
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
     * @Column(type="string", length=16)
     */
    public $name;

    /**
     * @Column(type="string", length=100)
     */
    public $title;

    /**
     * @Column(type="date", nullable=true)
     */
    public $execute_date;

    /**
     * @OneToMany(targetEntity="Item", mappedBy="deployment", indexBy="id")
     * @var ArrayCollection
     */
    public $items = null;

    /**
     * Validation rules
     * @var array
     */
    protected $validation_rules = array(
        'name' => array('trim' => 1, 'required' => 1, 'unique' => 'project', 'maxlen' => 16,
            'preg_match' => '/^[0-9a-zA-Z_]+$/'),
        'title' => array('trim' => 1, 'required' => 1, 'unique' => 'project', 'maxlen' => 100),
        'execute_date' => array('required' => 1),
    );

    public function __construct(Project $project)
    {
        $this->project = $project;
        $this->items   = new ArrayCollection();
    }

    public function displayTitle()
    {
        return $this->execute_date->format('d/m/Y').' - '.$this->title;
    }
}