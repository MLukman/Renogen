<?php

namespace Renogen\ActivityTemplate\Impl;

use Renogen\ActivityTemplate\BaseClass;
use Renogen\ActivityTemplate\Parameter;
use Renogen\Application;
use Renogen\Entity\Activity;
use Renogen\Runbook\Group;

class PortalS6 extends BaseClass
{

    public function __construct(Application $app)
    {
        parent::__construct($app);
        $this->addParameter('url', Parameter::Config('Git URL', 'The URL of the Git repository', true));
        $this->addParameter('branch', Parameter::MultiSelect('Branches', 'The choices of Git branches', true, 'Branch(s)', 'The branch(s) to deploy', true));
        $this->addParameter('modules', Parameter::MultiSelect('Modules', 'The choices of modules', true, 'Modules', 'The module(s) to deploy', true));
        $this->addParameter('remark', Parameter::MultiLineText('Remark', 'Remark to be displayed in deployment runbook', false));
    }

    public function classTitle()
    {
        return 'Package portal S6 module(s)';
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
            $group->setInstruction("Use S6ModulesPackager to merge the following modules to the specified branch @ ".$templates[$template_id]->parameters['url'].":");
            $group->setTemplate('runbook/portalS6.twig');
            $added = array();
            foreach ($activities as $activity) {
                if (!is_array($activity->parameters['branch'])) {
                    $activity->parameters['branch'] = array($activity->parameters['branch']);
                }
                foreach ($activity->parameters['branch'] as $branch) {
                    if (!isset($added[$branch])) {
                        $added[$branch] = array(
                            'branch' => $branch,
                            'modules' => $activity->parameters['modules'],
                            'remark' => array(),
                        );
                    } else {
                        $added[$branch]['modules']  = array_unique(array_merge($added[$branch]['modules'], $activity->parameters['modules']));
                    }
                    if (!empty($activity->parameters['remark'])) {
                        $added[$branch]['remark'][] = $activity->parameters['remark'];
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