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
 * @Entity @Table(name="checklists")
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
     * @Column(type="string", length=250)
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
     * @OneToMany(targetEntity="ChecklistUpdate", mappedBy="checklist", indexBy="id", orphanRemoval=true, cascade={"persist"}, fetch="EXTRA_LAZY")
     * @OrderBy({"created_date" = "asc"})
     * @var ArrayCollection|ChecklistUpdate[]
     */
    public $updates;

    /**
     * Validation rules
     * @var array
     */
    protected $validation_rules = array(
        'title' => array('trim' => 1, 'required' => 1, 'truncate' => 250, 'unique' => 'deployment'),
        'start_datetime' => array('required' => 1),
        'pics' => array('required' => 1),
    );

    public function __construct(Deployment $deployment)
    {
        $this->deployment = $deployment;
        $this->pics = new ArrayCollection();
        $this->updates = new ArrayCollection();
    }

    public function isPending()
    {
        return $this->status == 'Not Started' || $this->status == 'In Progress';
    }

    public function isUsernameAllowed($username, $attribute)
    {
        switch ($attribute) {
            case 'edit':
                if ($this->created_by->username == $username) {
                    return true;
                }
                foreach ($this->pics as $user) {
                    if ($user->username == $username) {
                        return true;
                    }
                }
                break;

            case 'delete':
                if ($this->created_by->username == $username) {
                    return true;
                }
                break;
        }

        return $this->deployment->isUsernameAllowed($username, 'approval');
    }
}