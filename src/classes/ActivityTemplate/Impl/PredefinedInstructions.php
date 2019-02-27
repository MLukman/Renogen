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
        $param_instructions = Parameter::MultiLineConfig('Instructions', 'The instructions to be performed before/during/after deployment; any variations can be configurable during activity creations by adding them to the additional details below. You can use markdown syntax to format this instructions text.', true, 'Instructions');
        $param_instructions->markdown = true;
        $this->addParameter('instructions', $param_instructions);
        $this->addParameter('details', Parameter\MultiField::create('Details', 'Define configurable activity details to be entered when creating activities', false, 'Details', '', false));
    }

    public function classTitle()
    {
        return 'Perform predefined instructions with additional configurations';
    }

    public function describeActivityAsArray(Actionable $activity)
    {
        return array(
            "Instructions" => $this->getParameter('instructions')->displayTemplateParameter($activity->template, 'instructions'),
            "Details" => $this->getParameter('details')->displayActivityParameter($activity, 'details'),
        );
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