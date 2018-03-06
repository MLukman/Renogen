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
        $this->addParameter('folder', Parameter::MultiSelect('Folders', 'The choices of folders to sync', true, 'Folder(s)', 'The folder(s) to sync', true));
        $this->addParameter('revision', Parameter::FreeTextWithDefault('Default Revision','The default revision number', false, 'Revision', 'The revision ref number to sync (put \'LATEST\' to sync latest)', true));
        $this->addParameter('remark', Parameter::MultiLineText('Remark', 'Remark to be displayed in deployment runbook', false));
    }

    public function classTitle()
    {
        return 'Sync code using GitSync';
    }

    public function convertActivitiesToRunbookGroups(array $activities)
    {
        $templates              = array();
        $activities_by_template = array();

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
            $added = array();
            foreach ($activities as $activity) {
                if (!is_array($activity->parameters['folder'])) {
                    $activity->parameters['folder'] = array($activity->parameters['folder']);
                }
                foreach ($activity->parameters['folder'] as $folder) {
                    if (!isset($added[$folder])) {
                        $added[$folder] = array(
                            'folder' => $folder,
                            'revision' => array($activity->parameters['revision']),
                            'remark' => array(),
                        );
                    } else {
                        if (strtoupper($activity->parameters['revision']) != 'LATEST') {
                            $added[$folder]['revision'][] = $activity->parameters['revision'];
                        }
                    }
                    if (!empty($activity->parameters['remark'])) {
                        $added[$folder]['remark'][] = $activity->parameters['remark'];
                    }
                }
            }
            foreach ($added as $row) {
                $group->addRow($row);
            }
            $groups[] = $group;
        }

        return $groups;
    }
}