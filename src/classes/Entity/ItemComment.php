<?php

namespace Renogen\Entity;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
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
     * @Column(type="datetime", nullable=true)
     */
    public $deleted_date = null;

    /**
     * @Column(type="text", nullable=true)
     */
    public $event;

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