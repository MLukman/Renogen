<?php

namespace Renogen\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\ORM\EntityManager;
use Renogen\Base\Entity;
use Securilex\Authorization\SecuredAccessInterface;
use Securilex\Authorization\SecuredAccessTrait;

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
     * @Column(type="string", length=16)
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
     * @OneToMany(targetEntity="Deployment", mappedBy="project", indexBy="name", orphanRemoval=true)
     * @var ArrayCollection
     */
    public $deployments = null;

    /**
     * @OneToMany(targetEntity="UserProject", mappedBy="project", indexBy="username", orphanRemoval=true)
     * @var ArrayCollection
     */
    public $userProjects = null;

    /**
     * @OneToMany(targetEntity="Template", mappedBy="project", indexBy="id", orphanRemoval=true)
     * @OrderBy({"priority" = "asc", "created_date" = "asc"})
     * @var ArrayCollection
     */
    public $templates = null;

    /**
     * Validation rules
     * @var array
     */
    protected $validation_rules   = array(
        'name' => array('required' => 1, 'unique' => true, 'maxlen' => 16,
            'preg_match' => '/^[0-9a-zA-Z_]+$/',
            'invalidvalues' => array('login', 'admin')),
        'title' => array('required' => 1, 'unique' => true, 'maxlen' => 100),
        'categories' => array('required' => 1),
        'modules' => array('required' => 1),
    );
    protected $validation_default = array('trim' => 1);

    public function __construct()
    {
        $this->deployments  = new ArrayCollection();
        $this->templates    = new ArrayCollection();
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
                $criteria->where(Criteria::expr()->in('execute_date', array(\DateTime::createFromFormat('!YmdHi', $datestring))));
                $matching = $this->deployments->matching($criteria);
                if ($matching->count() == 0) {
                    return $this->getDeploymentsByDateString(substr($datestring, 0, 8), true);
                }
                return $matching;

            case 8:
                $criteria->andWhere(new Comparison('execute_date', '>=', \DateTime::createFromFormat('!Ymd', $datestring)))
                    ->orderBy(array('execute_date' => 'ASC'));
                if (!$include_future) {
                    $criteria->andWhere(new Comparison('execute_date', '<', \DateTime::createFromFormat('!Ymd', $datestring)->add(new \DateInterval("P1D"))));
                }
                return $this->deployments->matching($criteria);

            default:
                return null;
        }
    }

    public function isUsernameAllowed($username, $attr = 'view')
    {
        $this->allowedRoles = array();
        if (method_exists($this, '__load')) {
            $this->__load();
        }
        return ($this->userProjects->containsKey($username) &&
            ($this->userProjects->get($username)->role == $attr || $attr == 'any'));
    }

    public function getAttachmentFolder()
    {
        return ROOTDIR.'/data/attachments/'.$this->name.'/';
    }

    public function delete(EntityManager $em)
    {
        if (file_exists($this->getAttachmentFolder())) {
            rmdir($this->getAttachmentFolder());
        }
        parent::delete($em);
    }
}