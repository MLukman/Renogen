<?php

namespace Renogen\ActivityTemplate\Parameter;

use Parsedown;
use Renogen\ActivityTemplate\Parameter;
use Renogen\Base\Actionable;
use Renogen\Entity\Template;

class Markdown extends Parameter
{
    public $for_template = false;
    public $for_activity = false;

    protected function __construct($type)
    {
        parent::__construct($type);
        Parsedown::instance()->setSafeMode(true);
    }

    static public function create($activityLabel, $activityDescription,
                                  $activityRequired)
    {
        $param               = static::generateParameterSimpler('markdown', $activityLabel, $activityDescription, $activityRequired);
        $param->for_activity = true;
        return $param;
    }

    static public function createForTemplateOnly($templateLabel,
                                                 $templateDescription,
                                                 $templateRequired,
                                                 $activityLabel = null)
    {
        $param               = static::generateParameter('markdown', $templateLabel, $templateDescription, $templateRequired, $activityLabel
                    ?: $templateLabel, null, null);
        $param->for_template = true;
        return $param;
    }

    static public function createWithDefault($templateLabel,
                                             $templateDescription,
                                             $templateRequired, $activityLabel,
                                             $activityDescription,
                                             $activityRequired)
    {
        $param               = static::generateParameter('markdown', $templateLabel, $templateDescription, $templateRequired, $activityLabel, $activityDescription, $activityRequired);
        $param->for_template = true;
        $param->for_activity = true;
        return $param;
    }

    public function displayActivityParameter(Actionable $activity, $key)
    {
        $param = (isset($activity->parameters[$key]) ? $activity->parameters[$key]
                : null);

        return Parsedown::instance()->parse($param);
    }

    public function displayTemplateParameter(Template $template, $key)
    {
        $param = (isset($template->parameters[$key]) ? $template->parameters[$key]
                : null);

        return Parsedown::instance()->parse($param);
    }

    public function getTwigForTemplateForm()
    {
        return 'parameter/template_markdown.twig';
    }

    public function getTwigForActivityForm()
    {
        return 'parameter/activity_markdown.twig';
    }
}