<?php

namespace Renogen\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\ExpressionBuilder;
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
    public $status = 'Documentation';

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
    protected $_statuses;

    public function __construct(Deployment $deployment)
    {
        $this->deployment  = $deployment;
        $this->activities  = new ArrayCollection();
        $this->attachments = new ArrayCollection();
        $this->status_logs = new ArrayCollection();
        $this->status_logs->add(new ItemStatusLog($this, $this->status));
    }

    public function displayTitle()
    {
        return ($this->refnum ? $this->refnum.' - ' : '').$this->title;
    }

    public function status()
    {
        if (isset($this->deployment->project->item_statuses[$this->status])) {
            return $this->status;
        }
        return array_keys($this->deployment->project->item_statuses)[0];
    }

    public function statusIcon()
    {
        $status = $this->status;
        if (isset($this->deployment->project->item_statuses[$status])) {
            return $this->deployment->project->item_statuses[$status]['icon'];
        }
        return 'x';
    }

    /**
     *
     * @param type $status_to_compare
     * @return mixed 0 = same status, <0 = current status is before the provided status, >0 = current is ahead, FALSE = invalid status provided
     */
    public function compareCurrentStatusTo($status_to_compare)
    {
        return static::compareStatuses($this->deployment->project, $this->status(), $status_to_compare);
    }

    static public function compareStatuses(Project $project, $status1, $status2)
    {
        $_statuses      = array_keys($project->item_statuses);
        $compare_status = array_search($status1, $_statuses);
        if ($compare_status === FALSE) {
            return -1;
        }
        $against_status = array_search($status2, $_statuses);
        if ($against_status === FALSE) {
            return FALSE;
        }
        return $against_status - $compare_status;
    }

    public function getNextStatus()
    {
        $this->_statuses = array_keys($this->deployment->project->item_statuses);
        $compare_status  = array_search($this->status(), $this->_statuses);
        if ($compare_status === FALSE) {
            return $this->_statuses[0];
        } elseif ($compare_status < count($this->_statuses) - 1) {
            return $this->_statuses[$compare_status + 1];
        } else {
            return null;
        }
    }

    public function changeStatus($status)
    {
        $project    = $this->deployment->project;
        $old_status = $this->status();
        if ($status == 'Test Review' &&
            static::compareStatuses($project, $old_status, $status) > 0) {
            parent::submit();
        }
        if (static::compareStatuses($project, $status, 'Documentation') >= 0) {
            parent::unsubmit();
        }
        if ($status == 'Ready For Release' &&
            static::compareStatuses($project, $old_status, $status) > 0) {
            parent::approve();
        }
        if (static::compareStatuses($project, $status, 'Go No Go') >= 0) {
            parent::unapprove();
        }
        $this->status = $status;
        $this->status_logs->add(new ItemStatusLog($this, $this->status));
        return static::compareStatuses($project, $old_status, $status);
    }

    public function getStatusLog($status)
    {
        static $crit = null;
        if (!$crit) {
            $eb   = new ExpressionBuilder();
            $crit = new Criteria($eb->eq('status', $status));
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