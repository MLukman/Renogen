<?php

namespace Renogen\Entity;

use DateInterval;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\OrderBy;
use Renogen\Base\Entity;

/**
 * @Entity @Table(name="projects")
 */
class Project extends Entity
{
    /**
     * @Id @Column(type="string") @GeneratedValue(strategy="UUID")
     */
    public $id;

    /**
     * @Column(type="datetime")
     */
    public $created_date;

    /**
     * @Column(type="string", length=30)
     */
    public $name;

    /**
     * @Column(type="string", length=100)
     */
    public $title;

    /**
     * @Column(type="string", length=30, options={"default":"cube"})
     */
    public $icon = 'cube';

    /**
     * @Column(type="text", nullable=true)
     */
    public $description;

    /**
     * @Column(type="json_array", nullable=true)
     */
    public $modules = array();

    /**
     * @Column(type="json_array", nullable=true)
     */
    public $categories = array(
        'Bug Fix',
        'Enhancement',
        'New Feature',
    );

    /**
     * @Column(type="boolean", options={"default":"0"})
     */
    public $private = false;

    /**
     * @Column(type="json_array", nullable=true)
     */
    public $checklist_templates;

    /**
     * @Column(type="boolean", options={"default":"0"})
     */
    public $archived = false;

    /**
     * @OneToMany(targetEntity="Deployment", mappedBy="project", indexBy="id", orphanRemoval=true, fetch="EXTRA_LAZY")
     * @var ArrayCollection|Deployment[]
     */
    public $deployments = null;

    /**
     * @OneToMany(targetEntity="UserProject", mappedBy="project", indexBy="username", orphanRemoval=true, cascade={"persist"})
     * @var ArrayCollection|UserProject[]
     */
    public $userProjects = null;

    /**
     * @OneToMany(targetEntity="Template", mappedBy="project", indexBy="id", orphanRemoval=true)
     * @OrderBy({"priority" = "asc", "created_date" = "asc"})
     * @var ArrayCollection|Template[]
     */
    public $templates = null;

    /**
     * @OneToMany(targetEntity="Plugin", mappedBy="project", indexBy="name", orphanRemoval=true, cascade={"persist"})
     * @var ArrayCollection|Plugin[]
     */
    public $plugins = null;

    /**
     * Validation rules
     * @var array
     */
    protected $validation_rules = array(
        'name' => array('required' => 1, 'unique' => true, 'maxlen' => 30,
            'preg_match' => array('/^[0-9a-zA-Z][0-9a-zA-Z_-]*$/', 'Project name must start with an alphanumerical character'),
            'invalidvalues' => array('login', 'admin', 'archived')),
        'title' => array('required' => 1, 'unique' => true, 'maxlen' => 100),
        'categories' => array('required' => 1),
        'modules' => array('required' => 1),
        'icon' => array('trim' => 1, 'maxlen' => 30),
    );
    protected $validation_default = array('trim' => 1);

    const ITEM_STATUS_INIT = 'Documentation';
    const ITEM_STATUS_REVIEW = 'Test Review';
    const ITEM_STATUS_APPROVAL = 'Go No Go';
    const ITEM_STATUS_READY = 'Ready For Release';
    const ITEM_STATUS_COMPLETED = 'Completed';
    const ITEM_STATUS_REJECTED = 'Rejected';
    const ITEM_STATUS_FAILED = 'Failed';

    public $item_statuses = array(
        self::ITEM_STATUS_INIT => array(
            'icon' => 'edit',
            'stepicon' => 'edit',
            'proceedaction' => 'Submit For Review',
            'rejectaction' => false,
            'role' => ['entry', 'approval'],
        ),
        self::ITEM_STATUS_REVIEW => array(
            'icon' => 'clipboard check',
            'stepicon' => 'clipboard check',
            'proceedaction' => 'Verified',
            'rejectaction' => 'Rejected',
            'role' => ['review', 'approval'],
        ),
        self::ITEM_STATUS_APPROVAL => array(
            'icon' => 'thumbs up',
            'stepicon' => 'thumbs up',
            'proceedaction' => 'Approved',
            'rejectaction' => 'Rejected',
            'role' => 'approval',
        ),
        self::ITEM_STATUS_READY => array(
            'icon' => 'cloud upload',
            'stepicon' => 'cloud upload',
            'proceedaction' => 'Completed',
            'rejectaction' => 'Failed',
            'role' => ['execute', 'approval'],
        ),
        self::ITEM_STATUS_COMPLETED => array(
            'icon' => 'flag checkered',
            'stepicon' => 'flag checkered',
            'proceedaction' => false,
            'rejectaction' => false,
            'role' => null,
        )
    );

    public function __construct()
    {
        $this->created_date = new \DateTime();
        $this->deployments = new ArrayCollection();
        $this->templates = new ArrayCollection();
        $this->plugins = new ArrayCollection();
        $this->userProjects = new ArrayCollection();
    }

    /**
     * Get upcoming deployments
     * @return Deployment[]
     */
    public function upcoming()
    {
        return $this->cached('upcoming', function() {
                return $this->deployments->matching(
                        Criteria::create()
                            ->where(new Comparison('execute_date', '>=', date_create()->setTime(0, 0, 0)))
                            ->orderBy(array('execute_date' => 'ASC')));
            });
    }

    /**
     * Get past deployments
     * @return Deployment[]
     */
    public function past()
    {
        return $this->cached('past', function() {
                return $this->deployments->matching(
                        Criteria::create()
                            ->where(new Comparison('execute_date', '<', date_create()->setTime(0, 0, 0)))
                            ->orderBy(array('execute_date' => 'DESC')));
            });
    }

    public function getDeploymentsByDateString($datestring,
                                               $include_future = false)
    {
        $criteria = Criteria::create();
        switch (strlen($datestring)) {
            case 12:
                $criteria->where(Criteria::expr()->eq('execute_date', DateTime::createFromFormat('!YmdHi', $datestring)));
                $matching = $this->deployments->matching($criteria);
                if ($matching->count() == 0) {
                    return $this->getDeploymentsByDateString(substr($datestring, 0, 8), true);
                }
                return $matching;

            case 8:
                $criteria->andWhere(new Comparison('execute_date', '>=', DateTime::createFromFormat('!Ymd', $datestring)))
                    ->orderBy(array('execute_date' => 'ASC'));
                if (!$include_future) {
                    $criteria->andWhere(new Comparison('execute_date', '<', DateTime::createFromFormat('!Ymd', $datestring)->add(new DateInterval("P1D"))));
                }
                return $this->deployments->matching($criteria);

            default:
                return null;
        }
    }

    public function getUserAccess($username)
    {
        if ($username instanceof User) {
            $username = $username->username;
        }
        return ($this->userProjects->containsKey($username) ?
            $this->userProjects->get($username)->role : null);
    }

    public function isUsernameAllowed($username, $attr = 'view')
    {
        $this->allowedRoles = array();
        if (method_exists($this, '__load')) {
            $this->__load();
        }
        if (!$this->userProjects->containsKey($username)) {
            return false;
        } elseif ($attr == 'any') {
            return true;
        }
        if (!is_array($attr)) {
            $attr = array($attr);
        }
        $role = $this->userProjects->get($username)->role;
        foreach ($attr as $a) {
            if ($role == $a) {
                return true;
            }
        }
        return false;
    }

    public function enabled_templates()
    {
        if (!isset($this->_enabled_templates)) {
            $this->_enabled_templates = $this->templates->matching(Criteria::create()->where(Criteria::expr()->eq("disabled", false)));
        }
        return $this->_enabled_templates;
    }

    public function usersWithRole($role)
    {
        return array_map(function($a) {
            return $a->user;
        }, $this->userProjects->matching(Criteria::create()->where(Criteria::expr()->eq('role', 'approval')))->toArray());
    }

    /**
     * @PrePersist
     * @PreUpdate
     */
    public function defaultIcon()
    {
        if (empty($this->icon)) {
            $this->icon = 'cube';
        }
    }
}