<?php

namespace Renogen\ActivityTemplate\Impl;

use Renogen\ActivityTemplate\BaseClass;
use Renogen\ActivityTemplate\Parameter;
use Renogen\Application;
use Renogen\Entity\Activity;
use Renogen\Runbook\Group;

class GitSync extends BaseClass
{

    public function __construct(Application $app)
    {
        parent::__construct($app);
        $this->addParameter('url', Parameter::Config('GitSync URL', 'The URL of the GitSync', true));
        $this->addParameter('folder', Parameter::Dropdown('Folders', 'The choices of folders to sync', true, 'Folder', 'The folder to sync', true));
        $this->addParameter('revision', Parameter::FreeText('Revision', 'The revision ref number to sync (put \'LATEST\' to sync latest)', true));
        $this->addParameter('remark', Parameter::MultiLineText('Remark', 'Remark to be displayed in deployment runbook', false));
    }

    public function classTitle()
    {
        return 'Sync code using GitSync';
    }

    public function describeActivityAsArray(Activity $activity)
    {
        return array(
            "Folder" => $activity->parameters['folder'],
            "Revision" => $activity->parameters['revision'],
        );
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
            $group->setInstruction("Use GitSync to sync code @ ".$templates[$template_id]->parameters['url'].":");
            $group->setTemplate('runbook/gitsync.twig');
            foreach ($activities as $activity) {
                $signature = json_encode($this->describeActivityAsArray($activity));
                if (!isset($added[$signature])) {
                    $added[$signature] = true;
                    $group->addRow(array(
                        'folder' => $activity->parameters['folder'],
                        'revision' => $activity->parameters['revision'],
                        'remark' => $activity->parameters['remark'],
                    ));
                }
            }
            $groups[] = $group;
        }

        return $groups;
    }
}