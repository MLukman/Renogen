<?php

namespace Renogen\ActivityTemplate\Impl;

use Renogen\ActivityTemplate\BaseClass;
use Renogen\ActivityTemplate\Parameter;
use Renogen\Application;
use Renogen\Base\RenoController;
use Renogen\Entity\Activity;
use Renogen\Runbook\Group;

class Rundeck extends BaseClass
{

    public function __construct(Application $app)
    {
        parent::__construct($app);
        $this->addParameter('url', Parameter::Config('Rundeck URL', 'The URL of the Rundeck portal', true));
        $this->addParameter('project', Parameter::Config('Project Name', 'The name of Rundeck project', true));
        $this->addParameter('group', Parameter::Config('Job Group', 'The name of Rundeck group', false));
        $this->addParameter('job', Parameter::Dropdown('List of Jobs', 'List of Rundeck jobs', true, '{jobDropdownLabel}', 'The name of Rundeck job', true));
        $this->addParameter('jobDropdownLabel', Parameter::Config('Job Dropdown Label', 'The label that will be displayed in activity create/edit form (it should describe the texts in the list of jobs above)', true));
        $this->addParameter('options', Parameter\MultiField::create('Job Options', 'Define job options to be entered when creating activities', false, 'Parameters', '', false));
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

        $optParam = $this->getParameter('options');
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
        $options  = array();
        $optParam = $this->getParameter('options');
        $data     = $optParam->activityDatabaseToForm($activity->template->parameters, $activity->parameters, 'options', $this->app);
        foreach ($activity->template->parameters['options'] as $p) {
            if ($useLabel) {
                $d = $p['title'];
            } else {
                $d = $p['id'];
            }

            if (is_array($data[$p['id']]) && isset($data[$p['id']]['fileid'])) {
                $options[$d] = '<a href="'.htmlentities($this->app->path('activity_file_download', RenoController::entityParams($activity)
                            + array('file' => $data[$p['id']]['fileid']))).'">'.htmlentities($data[$p['id']]['filename']).'</a>';
            } else {
                $options[$d] = $data[$p['id']];
            }
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
            $group = new Group($templates[$template_id]->title);
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
                        'remark' => $activity->parameters['remark'],
                    ));
                }
            }
            $groups[] = $group;
        }

        return $groups;
    }
}