<?php

namespace Renogen\ActivityTemplate\Impl;

use Renogen\ActivityTemplate\BaseClass;
use Renogen\ActivityTemplate\Parameter;
use Renogen\Entity\Activity;

class Rundeck extends BaseClass
{

    public function __construct(\Renogen\Application $app)
    {
        parent::__construct($app);
        $this->addParameter('url', Parameter::Config('Rundeck URL', 'The URL of the Rundeck portal', true));
        $this->addParameter('project', Parameter::Config('Project Name', 'The name of Rundeck project', true));
        $this->addParameter('group', Parameter::Config('Job Group', 'The name of Rundeck group', false));
        $this->addParameter('job', Parameter::Dropdown('List of Jobs', 'List of Rundeck jobs', true, '{jobDropdownLabel}', 'The name of Rundeck job', true));
        $this->addParameter('jobDropdownLabel', Parameter::Config('Job Dropdown Label', 'The label that will be displayed in activity create/edit form (it should describe the texts in the list of jobs above)', true));
        $this->addParameter('options', Parameter::MultiFreeText('Job Options', 'Define job options to be entered when creating activities', false, 'Parameters', '', false));
        $this->addParameter('remark', Parameter::MultiLineText('Remark', 'Remark to be displayed in deployment runbook', false));
    }

    public function describeActivityAsArray(Activity $activity)
    {
        $jobLabel = $activity->template->parameters['jobDropdownLabel'];
        $jobValue = $activity->parameters['job'];
        $jobName  = (isset($activity->template->parameters['job'][$jobValue]) ?
            $activity->template->parameters['job'][$jobValue] : $jobValue);
        $describe = array(
            "$jobLabel" => $jobName,
        );

        $optParam = $this->getParameter('options', true);
        $options  = $this->getOptions($activity, true);
        $optLabel = $optParam->activityLabel($activity->template->parameters);
        if (!empty($options)) {
            $describe[$optLabel] = $options;
        }

        $remark = $activity->parameters['remark'];
        if (!empty($remark)) {
            $describe['Remark'] = $remark;
        }

        return $describe;
    }

    protected function getOptions(Activity $activity, $useLabel = false)
    {
        $options = array();
        foreach ($activity->template->parameters['options'] as $key => $label) {
            if (empty($key)) {
                continue;
            }
            if ($useLabel) {
                if (substr($label, -1) == '*') {
                    $label = substr($label, 0, -1);
                }
                $d = $label;
            } else {
                $d = $key;
            }
            $options[$d] = (!isset($activity->parameters['options'][$key]) ? null
                    : $activity->parameters['options'][$key]);
        }
        return $options;
    }

    public function classTitle()
    {
        return 'Execute RunDeck Job';
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
            $group = new \Renogen\Runbook\Group($templates[$template_id]->title);
            $group->setInstruction("Login to Rundeck @ ".$templates[$template_id]->parameters['url']." and run the following jobs:");
            $group->setTemplate('runbook/rundeck.twig');
            foreach ($activities as $activity) {
                $signature = json_encode($this->describeActivityAsArray($activity));
                if (!isset($added[$signature])) {
                    $added[$signature] = true;
                    $group->addRow(array(
                        'project' => $templates[$template_id]->parameters['project'],
                        'group' => $templates[$template_id]->parameters['group'],
                        'job' => $activity->parameters['job'],
                        'options' => $this->getOptions($activity),
                    ));
                }
            }
            $groups[] = $group;
        }

        return $groups;
    }
}