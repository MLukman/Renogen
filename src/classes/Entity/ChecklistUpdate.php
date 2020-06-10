<?php

namespace Renogen\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\JoinTable;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\ManyToOne;
use Renogen\Base\Entity;

/**
 * @Entity @Table(name="checklist_updates")
 */
class ChecklistUpdate extends Entity
{
    /**
     * @Id @Column(type="string") @GeneratedValue(strategy="UUID")
     */
    public $id;

    /**
     * @ManyToOne(targetEntity="Checklist")
     * @JoinColumn(name="checklist_id", referencedColumnName="id", onDelete="CASCADE")
     * @var Checklist
     */
    public $checklist;

    /**
     * @Column(type="string", length=150)
     */
    public $comment;

    /**
     * Validation rules
     * @var array
     */
    protected $validation_rules = array(
        'comment' => array('trim' => 1, 'required' => 1, 'maxlen' => 150),
    );

    public function __construct(Checklist $checklist)
    {
        $this->checklist = $checklist;
    }
}