<?php

namespace Renogen\Entity;

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
    public $stage = 0;

    /**
     * @Column(type="integer", nullable=true)
     */
    public $priority;

    /**
     * @Column(type="json_array", nullable=true)
     */
    public $parameters;

    /**
     * Validation rules
     * @var array
     */
    protected $validation_rules = array(
        'template' => array('required' => 1),
    );

    public function __construct(Item $item)
    {
        $this->item = $item;
    }

    public function displayTitle()
    {
        return 'Activity: '.$this->template->title;
    }
}