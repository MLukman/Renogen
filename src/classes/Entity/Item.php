<?php

namespace Renogen\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Renogen\Base\ApproveableEntity;

/**
 * @Entity @Table(name="items")
 */
class Item extends ApproveableEntity
{
    /**
     * @Id @Column(type="string") @GeneratedValue(strategy="UUID")
     */
    public $id;

    /**
     * @ManyToOne(targetEntity="Deployment")
     * @JoinColumn(name="deployment_id", referencedColumnName="id", onDelete="CASCADE")
     * @var Deployment
     */
    public $deployment;

    /**
     * @Column(type="string", length=16, nullable=true)
     */
    public $refnum;

    /**
     * @Column(type="string", length=100)
     */
    public $title;

    /**
     * @Column(type="string", length=100, nullable=true)
     */
    public $category;

    /**
     * @Column(type="json_array", nullable=true)
     */
    public $modules = array();

    /**
     * @Column(type="text", nullable=true)
     */
    public $description;

    /**
     * @OneToMany(targetEntity="Activity", mappedBy="item", indexBy="id", orphanRemoval=true)
     * @OrderBy({"stage" = "asc", "priority" = "asc", "created_date" = "asc"})
     * @var ArrayCollection
     */
    public $activities = null;

    /**
     * @OneToMany(targetEntity="Attachment", mappedBy="item", indexBy="id", orphanRemoval=true)
     * @OrderBy({"created_date" = "asc"})
     * @var ArrayCollection
     */
    public $attachments = null;

    /**
     * @OneToMany(targetEntity="ItemComment", mappedBy="item", indexBy="id", orphanRemoval=true)
     * @OrderBy({"created_date" = "asc"})
     * @var ArrayCollection
     */
    public $comments = null;

    /**
     * Validation rules
     * @var array
     */
    protected $validation_rules = array(
        'refnum' => array('trim' => 1, 'maxlen' => 16),
        'title' => array('trim' => 1, 'required' => 1, 'maxlen' => 100, 'unique' => 'deployment'),
        'category' => array('required' => 1),
        'modules' => array('required' => 1),
    );

    public function __construct(Deployment $deployment)
    {
        $this->deployment  = $deployment;
        $this->activities  = new ArrayCollection();
        $this->attachments = new ArrayCollection();
    }

    public function displayTitle()
    {
        return ($this->refnum ? $this->refnum.' - ' : '').$this->title;
    }

    public function status()
    {
        if ($this->approved_date) {
            return 'Approved';
        } elseif ($this->submitted_date) {
            return 'Pending Approval';
        } else {
            return 'Unsubmitted';
        }
    }

    public function statusIcon()
    {
        if ($this->approved_date) {
            return 'check';
        } elseif ($this->submitted_date) {
            return 'help';
        } else {
            return 'warning';
        }
    }
}