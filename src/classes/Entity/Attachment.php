<?php

namespace Renogen\Entity;

use Renogen\Base\FileEntity;

/**
 * @Entity @Table(name="attachments")
 */
class Attachment extends FileEntity
{
    /**
     * @ManyToOne(targetEntity="Item")
     * @JoinColumn(name="item_id", referencedColumnName="id", onDelete="CASCADE")
     * @var Item
     */
    public $item;

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

    public function getFolder()
    {
        return $this->item->deployment->project->getAttachmentFolder();
    }
}