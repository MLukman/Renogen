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
 * @Entity @Table(name="checklists") @HasLifecycleCallbacks
 */
class Checklist extends Entity
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
     * @Column(type="string", length=255)
     */
    public $title;

    /**
     * @Column(type="datetime")
     * @var \DateTime
     */
    public $start_datetime;

    /**
     * @Column(type="datetime", nullable=true)
     * @var \DateTime
     */
    public $end_datetime;

    /**
     * @Column(type="string", length=30)
     */
    public $status = 'Not Started';

    /**
     * @ManyToMany(targetEntity="User")
     * @JoinTable(
     *  name="checklist_pics",
     *  joinColumns={
     *      @JoinColumn(name="checklist_id", referencedColumnName="id")
     *  },
     *  inverseJoinColumns={
     *      @JoinColumn(name="pic_username", referencedColumnName="username")
     *  }
     * )
     * @var ArrayCollection|User[]
     */
    public $pics;

    /**
     * Validation rules
     * @var array
     */
    protected $validation_rules = array(
        'title' => array('trim' => 1, 'required' => 1, 'truncate' => 255, 'unique' => 'deployment'),
        'start_datetime' => array('required' => 1),
        'pics' => array('required' => 1),
    );

    public function __construct(Deployment $deployment)
    {
        $this->deployment = $deployment;
        $this->pics = new ArrayCollection();
    }

    public function isPending()
    {
        return $this->status == 'Not Started' || $this->status == 'In Progress';
    }

    public function isUsernameAllowed($username, $attribute)
    {
        $allowed = false;

        switch ($attribute) {
            case 'delete':
                $allowed = ($this->created_by->username == $username);
                $attribute = 'approval';
                break;
        }

        return $allowed || $this->deployment->isUsernameAllowed($username, $attribute);
    }
}