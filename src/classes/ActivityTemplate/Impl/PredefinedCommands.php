<?php

namespace Renogen\ActivityTemplate\Impl;

use Renogen\ActivityTemplate\BaseClass;
use Renogen\ActivityTemplate\Parameter;
use Renogen\App;
use Renogen\Base\Actionable;
use Renogen\Runbook\Group;

class PredefinedCommands extends BaseClass
{

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->addParameter('nodes', Parameter::MultiSelect('Nodes', 'The list of nodes', true, 'Nodes', 'The list of nodes the file will be deployed at', true));
        $this->addParameter('runas', Parameter::FreeTextWithDefault('Default run as user', 'The commands will be run as this user', true, 'Run as user', 'The commands will be run as this user', true));
        $this->addParameter('commands', Parameter::MultiLineConfig('Commands', 'The commands; any configurable parts can be specified using {config_id} where config_id refers to the configuration values below', true));
        $this->addParameter('configuration', Parameter\MultiField::create('Command Configuration', 'Define job options to be entered when creating activities', false, 'Parameters', '', false, array(
                'freetext', 'password', 'dropdown', 'formatted'), 'freetext'));
    }

    public function classTitle()
    {
        return 'Execute predefined commands via command line interface (e.g. SSH)';
    }

    public function describeActivityAsArray(Actionable $activity)
    {
        return array(
            "Nodes" => $this->getParameter('nodes')->displayActivityParameter($activity, 'nodes'),
            "Run as" => $this->getParameter('runas')->displayActivityParameter($activity, 'runas'),
            "Commands" => '<pre>'.htmlentities($this->prepareCommands($activity)).'</pre>',
        );
    }

    public function prepareCommands(Actionable $activity)
    {
        $commands = $activity->template->parameters['commands'];
        foreach ($activity->template->parameters['configuration'] as $cfg) {
            if ($cfg['type'] != 'password' || $activity->actionableType == 'runitem') {
                $k        = $cfg['id'];
                $v        = $activity->parameters['configuration'][$k];
                $commands = str_replace("{{$k}}", $v, $commands);
            }
        }
        return $commands;
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
            $group->setInstruction("Execute the following commands on the respective nodes:");
            $group->setTemplate('runbook/PredefinedCommands.twig');
            foreach ($activities as $activity) {
                $group->addRow($activity, array(
                    "Nodes" => $activity->parameters['nodes'],
                    "Run as" => $activity->parameters['runas'],
                    "Commands" => $this->prepareCommands($activity),
                ));
            }
            $groups[] = $group;
        }

        return $groups;
    }
}