<?php

namespace Renogen\ActivityTemplate\Impl;

use Renogen\ActivityTemplate\BaseClass;
use Renogen\ActivityTemplate\Parameter;
use Renogen\Application;
use Renogen\Entity\Activity;
use Renogen\Runbook\Group;

class RunCommand extends BaseClass
{

    public function __construct(Application $app)
    {
        parent::__construct($app);
        $this->addParameter('url', Parameter::Config('Rundeck URL', 'The URL of the Rundeck portal', true));
        $this->addParameter('project', Parameter::Config('Project Name', 'The name of Rundeck project', true));
        $this->addParameter('command', Parameter::FreeText('Command', 'Command to be executed', true));
        $this->addParameter('nodes', Parameter::MultiSelect('Nodes', 'The list of nodes as registered in RunDeck', true, 'Nodes', 'The list of nodes the command will be executed on', true));
        $this->addParameter('remark', Parameter::MultiLineText('Remark', 'Remark to be displayed in deployment runbook', false));
    }

    public function classTitle()
    {
        return 'Execute ad-hoc command via RunDeck';
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
            $group->setInstruction("Login to Rundeck @ ".$templates[$template_id]->parameters['url'].", go to 'Commands' screen and execute the following command sets:");
            $group->setTemplate('runbook/runCommand.twig');
            foreach ($activities as $activity) {
                $signature = json_encode($this->describeActivityAsArray($activity));
                if (!isset($added[$signature])) {
                    $added[$signature] = true;
                    $group->addRow(array(
                        'command' => $activity->parameters['command'],
                        'nodes' => $activity->parameters['nodes'],
                        'remark' => $activity->parameters['remark'],
                    ));
                }
            }
            $groups[] = $group;
        }

        return $groups;
    }
}