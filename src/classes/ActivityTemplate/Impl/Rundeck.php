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
    }

    public function describeActivity(Activity $activity)
    {
        $twig = new \Twig\Environment(new \Twig\Loader\ArrayLoader(array(
            'description' => '<table class="ui very compact table"><tr><td class="ui collapsing">{{jobDropdownLabel}}</td><td>{{jobName}}</tr>'
            .'{% if options %}<tr><td class="ui collapsing top aligned">{{optLabel}}</td><td><table class="ui very compact table">'
            .'{% for key,option in options %}<tr><td class="ui collapsing top aligned">{{key}}</td><td>{{option|default("null")}}</td></tr>{% endfor %}'
            .'</table></td></tr>{% endif %}</table>',
        )));

        $f          = array_search($activity->parameters['job'], explode("\n", $activity->template->parameters['job']['values']));
        $texts      = explode("\n", $activity->template->parameters['job']['texts']);
        $optParam   = $this->getParameter('options');
        $keysLabels = array_combine(explode("\n", $activity->template->parameters['options']['keys']), explode("\n", $activity->template->parameters['options']['labels']));
        $options    = array();
        foreach ($keysLabels as $key => $label) {
            if (substr($label, -1) == '*') {
                $label = substr($label, 0, -1);
            }
            $options[$label] = (isset($activity->parameters['options'][$key]) ? $activity->parameters['options'][$key]
                    : null);
        }

        return $twig->render('description', array(
                'jobDropdownLabel' => $activity->template->parameters['jobDropdownLabel'],
                'jobName' => ($f !== FALSE ? $texts[$f] : $activity->parameters['job']),
                'optLabel' => $optParam->activityLabel($activity->template->parameters),
                'options' => $options,
        ));
    }

    public function classTitle()
    {
        return 'Execute RunDeck Job';
    }
}