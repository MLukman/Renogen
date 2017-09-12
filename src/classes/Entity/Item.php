<?php

namespace Renogen\Entity;

use Doctrine\Common\Collections\ArrayCollection;
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
     * @OneToMany(targetEntity="Activity", mappedBy="item", indexBy="id")
     * @OrderBy({"priority" = "asc", "created_date" = "asc"})
     * @var ArrayCollection
     */
    public $activities = null;

    /**
     * @OneToMany(targetEntity="Attachment", mappedBy="item", indexBy="id")
     * @OrderBy({"created_date" = "asc"})
     * @var ArrayCollection
     */
    public $attachments = null;

    /**
     * Validation rules
     * @var array
     */
    protected $validation_rules = array(
        'refnum' => array('trim' => 1, 'maxlen' => 16),
        'title' => array('trim' => 1, 'required' => 1, 'maxlen' => 100),
        'category' => array('required' => 1),
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