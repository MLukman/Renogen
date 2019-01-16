<?php

namespace Renogen\ActivityTemplate\Impl;

use Renogen\ActivityTemplate\BaseClass;
use Renogen\ActivityTemplate\Parameter;
use Renogen\Application;
use Renogen\Base\Actionable;
use Renogen\Runbook\Group;

class Database extends BaseClass
{

    public function __construct(Application $app)
    {
        parent::__construct($app);
        $this->addParameter('dbname', Parameter::Config('Database name', 'The well-known name the database is known as', true));
        $this->addParameter('login', Parameter::Dropdown('Logins', 'The choices of logins to the database', true, 'Login As', 'The database login the DBA needs to log in as into the database', true));
        $this->addParameter('sql', Parameter::MultiLineText('SQL', 'The SQL script', true));
    }

    public function classTitle()
    {
        return 'Execute database SQL script';
    }

    public function describeActivityAsArray(Actionable $activity)
    {
        return array(
            "Login" => $activity->parameters['login'],
            "SQL" => '<pre>'.htmlentities($activity->parameters['sql']).'</pre>',
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
            $group->setInstruction("Log into database '".$templates[$template_id]->parameters['dbname']."' using login(s) specified below and execute the respective SQL script(s):");
            $group->setTemplate('runbook/Database.twig');
            foreach ($activities as $activity) {
                $group->addRow($activity, array(
                    'login' => $activity->parameters['login'],
                    'sql' => $activity->parameters['sql'],
                ));
            }
            $groups[] = $group;
        }

        return $groups;
    }
}