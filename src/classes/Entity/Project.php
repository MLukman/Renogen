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
    public $categories = array();

    /**
     * @Column(type="boolean", options={"default":"0"})
     */
    public $private = false;

    /**
     * @OneToMany(targetEntity="Deployment", mappedBy="project", indexBy="id", orphanRemoval=true)
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
    protected $validation_rules   = array(
        'name' => array('required' => 1, 'unique' => true, 'maxlen' => 30,
            'preg_match' => '/^[0-9a-zA-Z_-]+$/',
            'invalidvalues' => array('login', 'admin')),
        'title' => array('required' => 1, 'unique' => true, 'maxlen' => 100),
        'categories' => array('required' => 1),
        'modules' => array('required' => 1),
    );
    protected $validation_default = array('trim' => 1);
    public $item_statuses         = array(
        'Documentation' => array(
            'icon' => 'warning',
            'proceedaction' => 'Submit For Review',
            'rejectaction' => false,
            'role' => ['entry', 'approval'],
        ),
        'Test Review' => array(
            'icon' => 'help',
            'proceedaction' => 'Verified',
            'rejectaction' => 'Rejected',
            'role' => ['review', 'approval'],
        ),
        'Go No Go' => array(
            'icon' => 'help',
            'proceedaction' => 'Approved',
            'rejectaction' => 'Rejected',
            'role' => 'approval',
        ),
        'Ready For Release' => array(
            'icon' => 'check',
            'proceedaction' => 'Completed',
            'rejectaction' => 'Failed',
            'role' => ['execute', 'approval'],
        ),
        'Completed' => array(
            'icon' => 'check',
            'proceedaction' => false,
            'rejectaction' => false,
            'role' => null,
        )
    );

    public function __construct()
    {
        $this->created_date = new \DateTime();
        $this->deployments  = new ArrayCollection();
        $this->templates    = new ArrayCollection();
        $this->plugins      = new ArrayCollection();
        $this->userProjects = new ArrayCollection();
    }

    public function upcoming()
    {
        return $this->cached('upcoming', function() {
                return $this->deployments->matching(
                        Criteria::create()
                            ->where(new Comparison('execute_date', '>=', date_create()->setTime(0, 0, 0)))
                            ->orderBy(array('execute_date' => 'ASC')));
            });
    }

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
}