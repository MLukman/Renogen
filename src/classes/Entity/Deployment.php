<?php

namespace Renogen\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Index;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\OrderBy;
use Doctrine\ORM\Mapping\PostPersist;
use Doctrine\ORM\Mapping\PostUpdate;
use Doctrine\ORM\Mapping\Table;
use Renogen\App;
use Renogen\Base\Entity;

/**
 * @Entity
 * @Table(name="deployments", indexes={@Index(name="execute_date_idx", columns={"execute_date"})})
 * @HasLifecycleCallbacks
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
     * @var DateTime
     */
    public $execute_date;

    /**
     * @OneToMany(targetEntity="Item", mappedBy="deployment", indexBy="id", orphanRemoval=true, fetch="EXTRA_LAZY")
     * @var ArrayCollection|Item[]
     */
    public $items = null;

    /**
     * @OneToMany(targetEntity="RunItem", mappedBy="deployment", indexBy="id", orphanRemoval=true, fetch="EXTRA_LAZY")
     * @OrderBy({"created_date" = "ASC"})
     * @var ArrayCollection|RunItem[]
     */
    public $runitems = null;

    /**
     * @OneToMany(targetEntity="Checklist", mappedBy="deployment", indexBy="id", orphanRemoval=true, fetch="EXTRA_LAZY")
     * @OrderBy({"start_datetime" = "ASC", "end_datetime" = "ASC"})
     * @var ArrayCollection|Checklist[]
     */
    public $checklists = null;

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
        'execute_date' => array('required' => 1, 'unique' => 'project'),
        'title' => array('trim' => 1, 'required' => 1, 'truncate' => 100),
    );

    public function __construct(Project $project)
    {
        $this->project = $project;
        $this->items = new ArrayCollection();
        $this->runitems = new ArrayCollection();
        $this->checklists = new ArrayCollection();
    }

    public function name()
    {
        return $this->datetimeString();
    }

    public function displayTitle()
    {
        return $this->datetimeString(true).' - '.$this->title;
    }

    public function datetimeString($pretty = false, DateTime $ddate = null)
    {
        if (!$ddate) {
            $ddate = $this->execute_date;
        }
        if ($ddate->format('Hi') == '0000') {
            return $ddate->format($pretty ? 'd/m/Y' : 'Ymd');
        } else {
            return $ddate->format($pretty ? 'd/m/Y h:i A' : 'YmdHi');
        }
    }

    public function isActive()
    {
        return ($this->execute_date >= date_create()->setTime(0, 0, 0)) && !$this->project->archived;
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

    /**
     *
     * @return ArrayCollection
     */
    public function getItemsWithStatus($status)
    {
        return $this->items->matching(Criteria::create()->where(
                    new Comparison('status', '=', $status)));
    }

    public function generateRunbooks()
    {
        $activities = array(
            -1 => array(),
            0 => array(),
            1 => array(),
        );
        foreach ($this->runitems as $runitem) {
            $tid = sprintf("%03d:%s", $runitem->template->priority, $runitem->template->id);
            $array = &$activities[$runitem->stage ?: 0];
            if (!isset($array[$tid])) {
                $array[$tid] = array();
            }
            $array[$tid][] = $runitem;
        }

        $rungroups = array(
            -1 => array(),
            0 => array(),
            1 => array(),
        );
        $templates = App::instance()->getActivityTemplateClass();
        foreach (array_keys($rungroups) as $stage) {
            ksort($activities[$stage]);
            foreach ($activities[$stage] as $acts) {
                $rungroups[$stage] = array_merge($rungroups[$stage], $templates[$acts[0]->template->class]
                        ->convertActivitiesToRunbookGroups($acts));
            }
        }

        return $rungroups;
    }

    public function isUsernameAllowed($username, $attribute)
    {
        return $this->project->isUsernameAllowed($username, $attribute);
    }

    public function getChecklistTemplates()
    {
        if (empty($this->project->checklist_templates)) {
            return array();
        }
        $checklists = array_map(function($c) {
            return $c->title;
        }, $this->checklists->toArray());
        return array_values(array_filter($this->project->checklist_templates, function($a) use ($checklists) {
                return !in_array($a, $checklists);
            }));
    }

    /**
     * @PostPersist
     */
    public function onInserted()
    {
        foreach ($this->project->plugins as $plugin) {
            /** @var Plugin $plugin */
            $plugin->instance()->onDeploymentCreated($this);
        }
    }

    /**
     * @PostUpdate
     */
    public function onUpdated()
    {
        if (isset($this->old_values['execute_date']) &&
            $this->datetimeString(false, $this->execute_date) != $this->datetimeString(false, $this->old_values['execute_date'])) {
            foreach ($this->project->plugins as $plugin) {
                /** @var Plugin $plugin */
                $plugin->instance()->onDeploymentDateChanged($this, $this->old_values['execute_date']);
            }
        }
    }
}