<?php

namespace Renogen\Entity;

use Renogen\Base\Entity;

/**
 * @Entity @Table(name="attachments")
 */
class Attachment extends Entity
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
     * @Column(type="string")
     */
    public $filename;

    /**
     * @Column(type="integer")
     */
    public $filesize = 0;

    /**
     * @Column(type="string", nullable=true)
     */
    public $mime_type;

    /**
     * @Column(type="text")
     */
    public $description;

    /**
     * Validation rules
     * @var array
     */
    protected $validation_rules = array(
        'filename' => array('truncate' => 255),
        'description' => array('trim' => 1, 'required' => 1),
    );

    public function __construct(Item $item)
    {
        $this->item = $item;
    }
}