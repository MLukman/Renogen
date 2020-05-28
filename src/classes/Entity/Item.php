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
use Doctrine\ORM\Mapping\PostLoad;
use Doctrine\ORM\Mapping\PostPersist;
use Doctrine\ORM\Mapping\PostRemove;
use Doctrine\ORM\Mapping\PostUpdate;
use Doctrine\ORM\Query\Expr\OrderBy;
use Renogen\App;
use Renogen\Base\Entity;

/**
 * @Entity @Table(name="items") @HasLifecycleCallbacks
 */
class Item extends Entity
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
     * @Column(type="string", length=40, nullable=true)
     */
    public $refnum;

    /**
     * @Column(type="string", length=255)
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
     * @OneToMany(targetEntity="Activity", mappedBy="item", indexBy="id", orphanRemoval=true, fetch="EXTRA_LAZY")
     * @OrderBy({"stage" = "asc", "priority" = "asc", "created_date" = "asc"})
     * @var ArrayCollection|Activity[]
     */
    public $activities = null;

    /**
     * @OneToMany(targetEntity="Attachment", mappedBy="item", indexBy="id", orphanRemoval=true, fetch="EXTRA_LAZY")
     * @OrderBy({"created_date" = "asc"})
     * @var ArrayCollection|Attachment[]
     */
    public $attachments = null;

    /**
     * @OneToMany(targetEntity="ItemComment", mappedBy="item", indexBy="id", orphanRemoval=true, cascade={"persist"}, fetch="EXTRA_LAZY")
     * @OrderBy({"created_date" = "asc"})
     * @var ArrayCollection|ItemComment[]
     */
    public $comments = null;

    /**
     * @OneToMany(targetEntity="ItemStatusLog", mappedBy="item", indexBy="id", orphanRemoval=true, cascade={"persist"}, fetch="EXTRA_LAZY")
     * @OrderBy({"created_date" = "asc"})
     * @var ArrayCollection|ItemStatusLog[]
     */
    public $status_logs = null;

    /**
     * @Column(type="string", length=100, nullable=true)
     */
    public $status = 'Documentation';

    /**
     * @Column(type="datetime", nullable=true)
     * @var \DateTime
     */
    public $approved_date;

    /**
     * @Column(type="json_array", nullable=true)
     * @var array
     */
    public $plugin_data = array();

    /**
     * Validation rules
     * @var array
     */
    protected $validation_rules = array(
        'refnum' => array('trim' => 1, 'maxlen' => 40),
        'title' => array('trim' => 1, 'required' => 1, 'truncate' => 255, 'unique' => 'deployment'),
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

    public function changeStatus($status, $remark = null)
    {
        $project         = $this->deployment->project;
        $old_status_real = $this->status();
        if ($status == 'Ready For Release' &&
            static::compareStatuses($project, $old_status_real, $status) > 0) {
            $this->approved_date = new \DateTime();
        }
        if (static::compareStatuses($project, $status, 'Go No Go') >= 0) {
            $this->approved_date = null;
        }

        $old_status   = $this->status;
        $this->storeOldValues(array('status'));
        $this->status = $status;
        $status_log   = new ItemStatusLog($this, $this->status);
        if (!empty($remark)) {
            $comment            = new ItemComment($this);
            $comment->event     = "$old_status > $this->status";
            $comment->text      = $remark;
            $this->comments->add($comment);
            $status_log->remark = $remark;
        }
        $this->status_logs->add($status_log);
        return static::compareStatuses($project, $old_status_real, $status);
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

    /**
     * @PostPersist
     */
    public function onInserted()
    {
        foreach ($this->deployment->project->plugins as $plugin) {
            $plugin->instance()->onItemStatusUpdated($this);
        }
    }

    /**
     * @PostLoad
     */
    public function onLoad()
    {
        $this->old_values['deployment'] = $this->deployment;
    }

    /**
     * @PostUpdate
     */
    public function onUpdated()
    {
        if ($this->old_values['deployment']->id != $this->deployment->id) {
            foreach ($this->deployment->project->plugins as $plugin) {
                $plugin->instance()->onItemMoved($this, $this->old_values['deployment']);
            }
        }
        if (isset($this->old_values['status']) && $this->status != $this->old_values['status']) {
            foreach ($this->deployment->project->plugins as $plugin) {
                $plugin->instance()->onItemStatusUpdated($this, $this->old_values['status']);
            }
        }
    }

    /**
     * @PostRemove
     */
    public function onDeleted()
    {
        foreach ($this->deployment->project->plugins as $plugin) {
            $plugin->instance()->onItemDeleted($this);
        }
    }

    public function getAllowedTransitions(User $user = null)
    {
        if (!$user) {
            $user = App::instance()->userEntity();
        }
        $transitions = array();
        foreach ($this->deployment->project->item_statuses as $status => $config) {
            $progress   = $this->compareCurrentStatusTo($status);
            $transition = array();
            if ($progress < 0) {
                // iterated status is behind current status
                if ($this->deployment->project->isUserNameAllowed($user->username, $config['role'])) {
                    $transition['Revert'] = array(
                        'status' => $status,
                        'remark' => true,
                        'type' => '',
                    );
                }
            } else if ($progress == 0) {
                // iterated status = current status
                if ($this->deployment->project->isUserNameAllowed($user->username, $config['role'])) {
                    $transition[$config['proceedaction']] = array(
                        'status' => $this->getNextStatus(),
                        'remark' => false,
                        'type' => 'primary',
                    );
                    if ($config['rejectaction']) {
                        $transition[$config['rejectaction']] = array(
                            'status' => $config['rejectaction'],
                            'remark' => true,
                            'type' => '',
                        );
                    }
                }
                if ($this->status == 'Ready For Release' &&
                    $this->activities->count() > 0) {
                    // Special condition: Ready For Release cannot be completed here
                    $transition = array();
                }
            }
            if (!empty($transition)) {
                $transitions[$status] = $transition;
            }
        }
        return $transitions;
    }
}