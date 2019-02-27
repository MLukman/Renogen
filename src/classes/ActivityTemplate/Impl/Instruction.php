<?php

namespace Renogen\ActivityTemplate\Impl;

use Renogen\ActivityTemplate\BaseClass;
use Renogen\ActivityTemplate\Parameter;
use Renogen\App;
use Renogen\Base\Actionable;
use Renogen\Runbook\Group;

class Instruction extends BaseClass
{

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->addParameter('instruction', Parameter\Markdown::createWithDefault('Default Instruction', 'Optional default instruction to be prepopulated when creating new activity', false, 'Instruction', 'The instruction for deployment. Markdown format is supported.', true));
        $this->addParameter('nodes', Parameter::MultiSelect('Nodes', 'The list of nodes', true, 'Nodes', 'The list of nodes the file will be deployed at', true));
    }

    public function classTitle()
    {
        return 'Execute as per instruction';
    }

    public function convertActivitiesToRunbookGroups(array $activities)
    {
        $templates              = array();
        $activities_by_template = array();
        $added                  = array();

        foreach ($activities as $activity) {
            /* @var $activity Actionable */
            if (!isset($activities_by_template[$activity->template->id])) {
                $templates[$activity->template->id]              = $activity->template;
                $activities_by_template[$activity->template->id] = array();
            }
            $activities_by_template[$activity->template->id][] = $activity;
        }

        $groups = array();
        foreach ($activities_by_template as $template_id => $activities) {
            $group = new Group($templates[$template_id]->title);
            if (!empty($templates[$template_id]->description)) {
                $group->setInstruction($templates[$template_id]->description);
            }
            $group->setTemplate('runbook/Instruction.twig');
            foreach ($activities as $activity) {
                $output    = $this->describeActivityAsArray($activity);
                $signature = json_encode($output);
                $group->addRow($activity, $output);
            }
            $groups[] = $group;
        }

        return $groups;
    }
}