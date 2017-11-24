<?php

namespace Renogen\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
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
     * @Column(type="string", length=100)
     */
    public $title;

    /**
     * @Column(type="text", nullable=true)
     */
    public $description;

    /**
     * @Column(type="datetime")
     */
    public $execute_date;

    /**
     * @OneToMany(targetEntity="Item", mappedBy="deployment", indexBy="id", orphanRemoval=true)
     * @var ArrayCollection
     */
    public $items = null;

    /**
     * Validation rules
     * @var array
     */
    protected $validation_rules = array(
        'execute_date' => array('required' => 1, 'unique' => 'project'),
        'title' => array('trim' => 1, 'required' => 1, 'maxlen' => 100),
    );

    public function __construct(Project $project)
    {
        $this->project = $project;
        $this->items   = new ArrayCollection();
    }

    public function name()
    {
        return $this->datetimeString();
    }

    public function displayTitle()
    {
        return $this->datetimeString(true).' - '.$this->title;
    }

    public function datetimeString($pretty = false)
    {
        return $this->execute_date->format($pretty ? 'd/m/Y h:i A' : 'YmdHi');
    }

    public function isActive()
    {
        return (date_create()->setTime(0, 0, 0) < $this->execute_date);
    }

    /**
     *
     * @return ArrayCollection
     */
    public function getApprovedItems()
    {
        return $this->items->matching(Criteria::create()->where(
                    new Comparison('approved_date', '<>', null)));
    }

    /**
     *
     * @return ArrayCollection
     */
    public function getUnapprovedItems()
    {
        return $this->items->matching(Criteria::create()->where(
                    new Comparison('approved_date', '=', null)));
    }

    public function generateRunbooks()
    {
        $activities = array(
            -1 => array(),
            0 => array(),
            1 => array(),
        );
        foreach ($this->getApprovedItems() as $item) {
            foreach ($item->activities as $activity) {
                $tid   = sprintf("%03d:%s", $activity->template->priority, $activity->template->id);
                $array = &$activities[$activity->stage ?: 0];
                if (!isset($array[$tid])) {
                    $array[$tid] = array();
                }
                $array[$tid][] = $activity;
            }
        }

        $rungroups = array(
            -1 => array(),
            0 => array(),
            1 => array(),
        );
        $templates = Application::instance()->getActivityTemplateClass();
        foreach (array_keys($rungroups) as $stage) {
            ksort($activities[$stage]);
            foreach ($activities[$stage] as $acts) {
                $rungroups[$stage] = array_merge($rungroups[$stage], $templates[$acts[0]->template->class]
                        ->convertActivitiesToRunbookGroups($acts));
            }
        }

        return $rungroups;
    }
    /*
      public function isUsernameAllowed($username, $attribute)
      {
      return $this->project->isUsernameAllowed($username, $attribute);
      }
     */
}