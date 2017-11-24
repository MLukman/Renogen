<?php

namespace Renogen\ActivityTemplate\Impl;

use Renogen\ActivityTemplate\BaseClass;
use Renogen\ActivityTemplate\Parameter;
use Renogen\Application;
use Renogen\Entity\Activity;
use Renogen\Runbook\Group;

class DeployFile extends BaseClass
{

    public function __construct(Application $app)
    {
        parent::__construct($app);
        $this->addParameter('instruction', Parameter::MultiLineText('Instruction', 'The instruction for deployment', true));
        $this->addParameter('file', Parameter\File::create('File', 'File to be deployed', true));
    }

    public function classTitle()
    {
        return 'Manually Deploy File';
    }

    public function convertActivitiesToRunbookGroups(array $activities)
    {
        $templates              = array();
        $activities_by_template = array();
        $added                  = array();

        foreach ($activities as $activity) {
            /* @var $activity Activity */
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
            $group->setTemplate('runbook/deployFile.twig');
            foreach ($activities as $activity) {
                $output    = $this->describeActivityAsArray($activity);
                $signature = json_encode($output);
                if (!isset($added[$signature])) {
                    $added[$signature] = true;
                    $group->addRow($output);
                }
            }
            $groups[] = $group;
        }

        return $groups;
    }
}