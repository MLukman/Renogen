<?php

namespace Renogen\Entity;

use Renogen\Base\Entity;

/**
 * @Entity @Table(name="item_comments")
 */
class ItemComment extends Entity
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
     * @Column(type="text")
     */
    public $text;

    /**
     * Validation rules
     * @var array
     */
    protected $validation_rules = array(
        'text' => array('trim' => 1, 'required' => 1),
    );

    public function __construct(Item $item)
    {
        $this->item = $item;
    }
}