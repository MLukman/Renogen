<?php

namespace Renogen\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;
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
     * @Column(type="string", length=16)
     */
    public $name;

    /**
     * @Column(type="string", length=100)
     */
    public $title;

    /**
     * @Column(type="string", length=4000, nullable=true)
     */
    public $description;

    /**
     * @OneToMany(targetEntity="Deployment", mappedBy="project", indexBy="name")
     * @var ArrayCollection
     */
    public $deployments = null;

    /**
     * Validation rules
     * @var array
     */
    protected $validation_rules = array(
        'name' => array('trim' => 1, 'required' => 1, 'unique' => true, 'maxlen' => 16,
            'preg_match' => '/^[0-9a-zA-Z_]+$/'),
        'title' => array('trim' => 1, 'required' => 1, 'unique' => true, 'maxlen' => 100),
        'description' => array('trim' => 1, 'truncate' => 4000),
    );

    public function __construct()
    {
        $this->deployments = new ArrayCollection();
    }

    public function upcoming()
    {
        return $this->cached('upcoming', function() {
                return $this->deployments->matching(
                        Criteria::create()
                            ->where(new Comparison('execute_date', '>=', (new \DateTime())->setTime(0, 0, 0)))
                            ->orderBy(array('execute_date' => 'ASC')));
            });
    }

    public function past()
    {
        return $this->cached('past', function() {
                return $this->deployments->matching(
                        Criteria::create()
                            ->where(new Comparison('execute_date', '<', (new \DateTime())->setTime(0, 0, 0)))
                            ->orderBy(array('execute_date' => 'DESC')));
            });
    }
}