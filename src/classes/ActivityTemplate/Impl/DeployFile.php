<?php

namespace Renogen\ActivityTemplate\Impl;

use Renogen\ActivityTemplate\BaseClass;
use Renogen\ActivityTemplate\Parameter;
use Renogen\App;
use Renogen\Base\Actionable;
use Renogen\Runbook\Group;

class DeployFile extends BaseClass
{

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->addParameter('instruction', Parameter::MultiLineText('Instruction', 'The instruction for deployment', true));
        $this->addParameter('file', Parameter\File::create('File', 'File to be deployed', true));
        $this->addParameter('nodes', Parameter::MultiSelect('Nodes', 'The list of nodes', true, 'Nodes', 'The list of nodes the file will be deployed at', true));
    }

    public function classTitle()
    {
        return 'Manually deploy file';
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
            $group->setInstruction("Manually deploy file as per instruction:");
            $group->setTemplate('runbook/DeployFile.twig');
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