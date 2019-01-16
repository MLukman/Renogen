<?php

namespace Renogen\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Renogen\Base\Actionable;

/**
 * @Entity @Table(name="activities")
 */
class Activity extends Actionable
{
    /**
     * @ManyToOne(targetEntity="Item")
     * @JoinColumn(name="item_id", referencedColumnName="id", onDelete="CASCADE")
     * @var Item
     */
    public $item;

    /**
     * @OneToMany(targetEntity="ActivityFile", mappedBy="activity", indexBy="classifier", orphanRemoval=true, cascade={"persist", "remove"})
     * @var ArrayCollection
     */
    public $files = null;

    /**
     * @ManyToOne(targetEntity="RunItem")
     * @JoinColumn(name="runitem_id", referencedColumnName="id", onDelete="SET NULL")
     * @var RunItem
     */
    public $runitem;

    /**
     * Validation rules
     * @var array
     */
    protected $validation_rules = array(
        'template' => array('required' => 1),
    );
    public $fileClass           = '\Renogen\Entity\ActivityFile';
    public $actionableType      = 'activity';

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
}