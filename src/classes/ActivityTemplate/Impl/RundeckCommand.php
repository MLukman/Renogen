<?php

namespace Renogen\ActivityTemplate\Impl;

use Renogen\ActivityTemplate\BaseClass;
use Renogen\ActivityTemplate\Parameter;
use Renogen\App;
use Renogen\Base\Actionable;
use Renogen\Runbook\Group;

class RundeckCommand extends BaseClass
{

    public function __construct(App $app)
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
        return '[RunDeck] Execute a single command';
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
            $group->setInstruction("Login to Rundeck @ ".$templates[$template_id]->parameters['url'].", go to 'Commands' screen and execute the following command sets:");
            $group->setTemplate('runbook/RundeckCommand.twig');
            foreach ($activities as $activity) {
                $group->addRow($activity, array(
                    'command' => $activity->parameters['command'],
                    'nodes' => $activity->parameters['nodes'],
                    'remark' => $activity->parameters['remark'],
                ));
            }
            $groups[] = $group;
        }

        return $groups;
    }
}