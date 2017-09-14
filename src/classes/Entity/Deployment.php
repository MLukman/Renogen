<?php

namespace Renogen\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\ORM\EntityManager;
use Renogen\Application;
use Renogen\Base\Entity;

/**
 * @Entity @Table(name="deployments")
 */
class Deployment extends Entity
{
    /**
     * @Id @Column(type="string") @GeneratedValue(strategy="UUID")
     */
    public $id;

    /**
     * @ManyToOne(targetEntity="Project")
     * @JoinColumn(name="project_id", referencedColumnName="id", onDelete="CASCADE")
     * @var Project
     */
    public $project;

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
     * @Column(type="date", nullable=true)
     */
    public $execute_date;

    /**
     * @OneToMany(targetEntity="Item", mappedBy="deployment", indexBy="id")
     * @var ArrayCollection
     */
    public $items = null;

    /**
     * Validation rules
     * @var array
     */
    protected $validation_rules = array(
        'name' => array('trim' => 1, 'required' => 1, 'unique' => 'project', 'maxlen' => 16,
            'preg_match' => '/^[0-9a-zA-Z_]+$/', 'invalidvalues' => array('templates')),
        'title' => array('trim' => 1, 'required' => 1, 'unique' => 'project', 'maxlen' => 100),
        'execute_date' => array('required' => 1),
    );

    public function __construct(Project $project)
    {
        $this->project = $project;
        $this->items   = new ArrayCollection();
    }

    public function displayTitle()
    {
        return $this->execute_date->format('d/m/Y').' - '.$this->title;
    }

    public function generateRunbooks()
    {
        $activities = array(
            -1 => array(),
            0 => array(),
            1 => array(),
        );
        foreach ($this->items->matching(Criteria::create()->where(
                new Comparison('approved_date', '<>', null))) as $item) {
            foreach ($item->activities as $activity) {
                $templateClass = $activity->template->class;
                $array         = &$activities[$activity->stage ?: 0];
                if (!isset($array[$templateClass])) {
                    $array[$templateClass] = array();
                }
                $array[$templateClass][] = $activity;
            }
        }

        $rungroups = array(
            -1 => array(),
            0 => array(),
            1 => array(),
        );
        $templates = Application::instance()->getActivityTemplateClass();
        foreach (array_keys($rungroups) as $stage) {
            foreach ($activities[$stage] as $templateClass => $acts) {
                $rungroups[$stage] = array_merge($rungroups[$stage], $templates[$templateClass]
                        ->convertActivitiesToRunbookGroups($acts));
            }
        }

        return $rungroups;
    }

    public function delete(EntityManager $em)
    {
        foreach ($this->items as $item) {
            $item->delete($em);
        }
        parent::delete($em);
    }
}