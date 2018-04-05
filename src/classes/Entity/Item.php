<?php

namespace Renogen\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Query\Expr\OrderBy;
use Renogen\Base\ApproveableEntity;
use Securilex\Authorization\SecuredAccessInterface;
use Securilex\Authorization\SecuredAccessTrait;

/**
 * @Entity @Table(name="items")
 */
class Item extends ApproveableEntity implements SecuredAccessInterface
{

    use SecuredAccessTrait;
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
     * @Column(type="string", length=16, nullable=true)
     */
    public $refnum;

    /**
     * @Column(type="string", length=100)
     */
    public $title;

    /**
     * @Column(type="string", length=100, nullable=true)
     */
    public $category;

    /**
     * @Column(type="json_array", nullable=true)
     */
    public $modules = array();

    /**
     * @Column(type="text", nullable=true)
     */
    public $description;

    /**
     * @OneToMany(targetEntity="Activity", mappedBy="item", indexBy="id", orphanRemoval=true)
     * @OrderBy({"stage" = "asc", "priority" = "asc", "created_date" = "asc"})
     * @var ArrayCollection
     */
    public $activities = null;

    /**
     * @OneToMany(targetEntity="Attachment", mappedBy="item", indexBy="id", orphanRemoval=true)
     * @OrderBy({"created_date" = "asc"})
     * @var ArrayCollection
     */
    public $attachments = null;

    /**
     * @OneToMany(targetEntity="ItemComment", mappedBy="item", indexBy="id", orphanRemoval=true, cascade={"persist"})
     * @OrderBy({"created_date" = "asc"})
     * @var ArrayCollection
     */
    public $comments = null;

    /**
     * @OneToMany(targetEntity="ItemStatusLog", mappedBy="item", indexBy="id", orphanRemoval=true, cascade={"persist"})
     * @OrderBy({"created_date" = "asc"})
     * @var ArrayCollection
     */
    public $status_logs = null;

    /**
     * @Column(type="string", length=100, nullable=true)
     */
    public $status = 'Unsubmitted';

    /**
     * Validation rules
     * @var array
     */
    protected $validation_rules = array(
        'refnum' => array('trim' => 1, 'maxlen' => 16),
        'title' => array('trim' => 1, 'required' => 1, 'maxlen' => 100, 'unique' => 'deployment'),
        'category' => array('required' => 1),
        'modules' => array('required' => 1),
    );

    const STATUSES = array(
        'Unsubmitted',
        'Rejected',
        'Pending Review',
        'Pending Approval',
        'Approved',
        'Ready',
        'Completed',
    );

    public function __construct(Deployment $deployment)
    {
        $this->deployment  = $deployment;
        $this->activities  = new ArrayCollection();
        $this->attachments = new ArrayCollection();
        $this->status_logs = new ArrayCollection();
        $this->status_logs->add(new \Renogen\Entity\ItemStatusLog($this, $this->status));
    }

    public function displayTitle()
    {
        return ($this->refnum ? $this->refnum.' - ' : '').$this->title;
    }

    public function status()
    {
        return $this->status;
    }

    public function statusIcon()
    {
        switch ($this->status()) {
            case 'Approved':
            case 'Ready':
            case 'Completed':
                return 'check';
            case 'Rejected':
                return 'x';
            case 'Pending Review':
            case 'Pending Approval':
                return 'help';
            default:
                return 'warning';
        }
    }

    /**
     *
     * @param type $status_to_compare
     * @return mixed 0 = same status, <0 = current status is before the provided status, >0 = current is ahead, FALSE = invalid status provided
     */
    public function compareCurrentStatusTo($status_to_compare)
    {
        $compare_status = array_search($status_to_compare, static::STATUSES);
        if ($compare_status === FALSE) {
            return FALSE;
        }
        $against_status = array_search($this->status, static::STATUSES);
        return $against_status - $compare_status;
    }

    public function submit(User $user = null)
    {
        parent::submit($user);
        $this->changeStatus('Pending Approval');
    }

    public function approve(User $user = null)
    {
        parent::approve($user);
        $this->changeStatus('Approved');
    }

    public function unapprove()
    {
        parent::unapprove();
        $this->changeStatus('Pending Approval');
    }

    public function reject(User $user = null)
    {
        parent::reject($user);
        $this->changeStatus('Rejected');
    }

    public function changeStatus($status)
    {
        $this->status = $status;
        $this->status_logs->add(new \Renogen\Entity\ItemStatusLog($this, $this->status));
    }

    public function getStatusLog($status)
    {
        static $crit = null;
        if (!$crit) {
            $eb   = new \Doctrine\Common\Collections\ExpressionBuilder();
            $crit = new \Doctrine\Common\Collections\Criteria($eb->eq('status', $status));
        }
        return $this->status_logs->matching($crit)->last();
    }

    public function getStatusLogBefore(ItemStatusLog $status)
    {
        $found = false;
        foreach (array_reverse($this->status_logs->toArray()) as $log) {
            if ($found && $log->created_date < $status->created_date) {
                return $log;
            } else if ($log === $status) {
                $found = true;
            }
        }
    }

    public function isUsernameAllowed($username, $attribute)
    {
        $allowed = false;

        switch ($attribute) {
            case 'delete':
            case 'move':
                $allowed   = ($this->created_by->username == $username);
                $attribute = 'approval';
                break;
        }

        return $allowed || $this->deployment->isUsernameAllowed($username, $attribute);
    }
}