<?php

namespace Renogen\ActivityTemplate\Impl;

use Renogen\ActivityTemplate\BaseClass;
use Renogen\ActivityTemplate\Parameter;
use Renogen\App;
use Renogen\Base\Actionable;
use Renogen\Runbook\Group;

class PredefinedInstructions extends BaseClass
{

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->addParameter('instructions', Parameter\Markdown::createForTemplateOnly('Instructions', 'The instructions to be performed before/during/after deployment; any variations can be configurable during activity creations by adding them to the additional details below. You can use markdown syntax to format this instructions text.', true));
        $this->addParameter('details', Parameter\MultiField::create('Details', 'Define configurable activity details to be entered when creating activities', false, 'Details', '', false));
        $this->addParameter('nodes', Parameter::MultiSelect('Nodes', 'The list of nodes', false, 'Nodes', 'The list of nodes the file will be deployed at', true));
    }

    public function classTitle()
    {
        return 'Perform predefined instructions with additional configurations';
    }

    public function prepareInstructions(Actionable $activity)
    {
        $instr = $activity->template->parameters['instructions'];
        foreach ($activity->template->parameters['details'] as $cfg) {
            $k = $cfg['id'];
            if (strpos($instr, "{{$k}}") === false ||
                ($cfg['type'] == 'password' && $activity->actionableType != 'runitem')) {
                continue;
            }
            $v     = $activity->parameters['details'][$k];
            $instr = str_replace("{{$k}}", $v, $instr);
        }
        return $instr;
    }

    public function instructionsContainVariables(Actionable $activity)
    {
        foreach ($activity->template->parameters['details'] as $cfg) {
            if (false !== strpos($activity->template->parameters['instructions'], "{{$cfg['id']}}")) {
                return true;
            }
        }
        return false;
    }

    public function describeActivityAsArray(Actionable $activity)
    {
        $instr  = $activity->template->parameters['instructions'];
        $params = $this->getParameter('details')->activityDatabaseToForm($activity->template->parameters, $activity->parameters, 'details', $activity);
        foreach ($activity->template->parameters['details'] as $cfg) {
            $k = $cfg['id'];
            if (strpos($instr, "{{$k}}") === false ||
                ($cfg['type'] == 'password' && $activity->actionableType != 'runitem')) {
                continue;
            }
            if ($cfg['type'] == 'file') {
                $v = $params[$k]['filename'];
            } else {
                $v = $params[$k];
            }
            if (!empty($v)) {
                $instr = str_replace("{{$k}}", $v, $instr);
                unset($activity->parameters['details'][$k]);
            }
        }
        $activity->parameters['instructions'] = $instr;

        $describe = array(
            "Nodes" => $this->getParameter('nodes')->displayActivityParameter($activity, 'nodes'),
            "Instructions" => $this->getParameter('instructions')->displayActivityParameter($activity, 'instructions'),
        );
        if (($details  = $this->getParameter('details')->displayActivityParameter($activity, 'details'))) {
            $describe["Details"] = $details;
        }
        return $describe;
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
            $group->setTemplate('runbook/PredefinedInstructions.twig');
            foreach ($activities as $activity) {
                $group->addRow($activity, $this->describeActivityAsArray($activity));
            }
            $groups[] = $group;
        }

        return $groups;
    }
}