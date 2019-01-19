<?php

namespace Renogen\ActivityTemplate\Impl;

use Renogen\ActivityTemplate\BaseClass;
use Renogen\ActivityTemplate\Parameter;
use Renogen\Application;
use Renogen\Base\Actionable;
use Renogen\Runbook\Group;

class RundeckJob extends BaseClass
{

    public function __construct(Application $app)
    {
        parent::__construct($app);
        $this->addParameter('url', Parameter::Config('Rundeck URL', 'The URL of the Rundeck portal', true));
        $this->addParameter('project', Parameter::Config('Project Name', 'The name of Rundeck project', true));
        $this->addParameter('group', Parameter::Config('Job Group', 'The name of Rundeck group', false));
        $this->addParameter('job', Parameter::Dropdown('List of Jobs', 'List of Rundeck jobs', true, '{jobDropdownLabel}', 'The name of Rundeck job', true));
        $this->addParameter('jobDropdownLabel', Parameter::Config('Job Dropdown Label', 'The label that will be displayed in activity create/edit form (it should describe the texts in the list of jobs above)', true));
        $this->addParameter('options', Parameter\MultiField::create('Job Options', 'Define job options to be entered when creating activities', false, 'Parameters', '', false, null, 'freetext'));
        $this->addParameter('remark', Parameter::MultiLineText('Remark', 'Remark to be displayed in deployment runbook', false));
    }

    public function describeActivityAsArray(Actionable $activity)
    {
        $jobLabel = $activity->template->parameters['jobDropdownLabel'];
        $jobValue = $activity->parameters['job'];
        $jobName  = (isset($activity->template->parameters['job'][$jobValue]) ?
            $activity->template->parameters['job'][$jobValue] : $jobValue);
        $describe = array(
            "$jobLabel" => $jobName,
        );

        $optParam = $this->getParameter('options');
        $options  = $optParam->displayActivityParameter($activity, 'options');
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

    protected function getOptions(Actionable $activity, $useLabel = false)
    {
        $options  = array();
        $optParam = $this->getParameter('options');
        $data     = $optParam->activityDatabaseToForm($activity->template->parameters, $activity->parameters, 'options', $activity);
        foreach ($activity->template->parameters['options'] as $p) {
            if ($useLabel) {
                $d = $p['title'];
            } else {
                $d = $p['id'];
            }

            $options[$d] = null;
            if (isset($data[$p['id']])) {
                if ($p['type'] == 'file') {
                    $file = $this->app['datastore']->queryOne($activity->fileClass, array(
                        "{$activity->actionableType}" => $activity,
                        'classifier' => 'options.'.$p['id'],
                    ));
                    if ($file) {
                        $options[$d] = '<a href="'.htmlentities($this->getDownloadLink($file)).'">'.htmlentities($file->filename).'</a>';
                    }
                } elseif ($useLabel && $p['type'] == 'password') {
                    $options[$d] = '******';
                } else {
                    $options[$d] = $data[$p['id']];
                }
            }
        }
        return $options;
    }

    public function classTitle()
    {
        return '[RunDeck] Execute job';
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
            $group->setInstruction("Login to Rundeck @ ".$templates[$template_id]->parameters['url']." and run the following jobs:");
            $group->setTemplate('runbook/RundeckJob.twig');
            foreach ($activities as $activity) {
                $signature = json_encode($this->describeActivityAsArray($activity));
                if (!isset($added[$signature])) {
                    $added[$signature] = true;
                    $group->addRow($activity, array(
                        'project' => $templates[$template_id]->parameters['project'],
                        'group' => $templates[$template_id]->parameters['group'],
                        'job' => $activity->parameters['job'],
                        'options' => $this->getParameter('options')->displayActivityParameter($activity, 'options'),
                        'remark' => $activity->parameters['remark'],
                    ));
                }
            }
            $groups[] = $group;
        }

        return $groups;
    }
}