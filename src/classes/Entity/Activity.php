<?php

namespace Renogen\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Renogen\Base\ApproveableEntity;

/**
 * @Entity @Table(name="activities")
 */
class Activity extends ApproveableEntity
{
    /**
     * @Id @Column(type="string") @GeneratedValue(strategy="UUID")
     */
    public $id;

    /**
     * @ManyToOne(targetEntity="Item")
     * @JoinColumn(name="item_id", referencedColumnName="id", onDelete="CASCADE")
     * @var Item
     */
    public $item;

    /**
     * @ManyToOne(targetEntity="Template")
     * @JoinColumn(name="template_id", referencedColumnName="id", onDelete="CASCADE")
     * @var Template
     */
    public $template;

    /**
     * @Column(type="integer", nullable=true)
     */
    public $stage;

    /**
     * @Column(type="integer", nullable=true)
     */
    public $priority;

    /**
     * @Column(type="json_array", nullable=true)
     */
    public $parameters;

    /**
     * @OneToMany(targetEntity="ActivityFile", mappedBy="activity", indexBy="stored_filename", orphanRemoval=true, cascade={"persist", "remove"})
     * @var ArrayCollection
     */
    public $files = null;

    /**
     * @Column(type="string", length=100, nullable=true)
     */
    public $status;

    /**
     * @Column(type="string", length=100, nullable=true)
     */
    public $signature;

    /**
     * Validation rules
     * @var array
     */
    protected $validation_rules = array(
        'template' => array('required' => 1),
    );

    public function __construct(Item $item)
    {
        $this->item  = $item;
        $this->files = new ArrayCollection();
    }

    public function displayTitle()
    {
        return 'Activity: '.$this->template->title;
    }

    public function isUsernameAllowed($username, $attribute)
    {
        return parent::isUsernameAllowed($username, $attribute) ||
            $this->item->isUsernameAllowed($username, $attribute);
    }

    /**
     * @PrePersist
     * @PreUpdate
     */
    public function calculateSignature()
    {
        $this->signature = sha1($this->template->id.'|'.$this->stage.'|'.json_encode($this->parameters));
    }
}