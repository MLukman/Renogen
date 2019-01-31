<?php

namespace Renogen\ActivityTemplate;

use Renogen\App;
use Renogen\Base\Actionable;
use Renogen\Entity\FileLink;
use Renogen\Entity\RunItemFile;
use Symfony\Component\HttpFoundation\Request;

class Parameter
{
    public $type;
    public $templateLabel;
    public $templateDescription;
    public $templateRequired;
    public $activityLabel;
    public $activityDescription;
    public $activityRequired;
    protected $app;

    protected function __construct($type)
    {
        $this->type = $type;
    }

    public function setApplication(App $app)
    {
        $this->app = $app;
    }

    public function getType()
    {
        return $this->type;
    }

    /**
     * Config is for a parameter that needs be entered
     * during template creation but not during activity creation
     * @param type $templateLabel
     * @param type $templateDescription
     * @param type $templateRequired
     * @return \static
     */
    static public function Config($templateLabel, $templateDescription,
                                  $templateRequired, $activityLabel = null,
                                  $activityDescription = null)
    {
        $param                      = new static('config');
        $param->templateLabel       = $templateLabel;
        $param->templateDescription = $templateDescription;
        $param->templateRequired    = (bool) $templateRequired;
        $param->activityLabel       = $activityLabel;
        $param->activityDescription = $activityDescription;
        return $param;
    }

    /**
     * MultiLineConfig is for a parameter that needs be entered
     * during template creation but not during activity creation
     * @param type $templateLabel
     * @param type $templateDescription
     * @param type $templateRequired
     * @return \static
     */
    static public function MultiLineConfig($templateLabel, $templateDescription,
                                           $templateRequired,
                                           $activityLabel = null,
                                           $activityDescription = null)
    {
        $param                      = new static('multilineconfig');
        $param->templateLabel       = $templateLabel;
        $param->templateDescription = $templateDescription;
        $param->templateRequired    = (bool) $templateRequired;
        $param->activityLabel       = $activityLabel;
        $param->activityDescription = $activityDescription;
        return $param;
    }

    static public function FreeText($activityLabel, $activityDescription,
                                    $activityRequired)
    {
        return static::generateParameterSimpler('freetext', $activityLabel, $activityDescription, $activityRequired);
    }

    static public function FreeTextWithDefault($templateLabel,
                                               $templateDescription,
                                               $templateRequired,
                                               $activityLabel,
                                               $activityDescription,
                                               $activityRequired)
    {
        return static::generateParameter('freetext', $templateLabel, $templateDescription, $templateRequired, $activityLabel, $activityDescription, $activityRequired);
    }
    /*
      static public function RegexText($templateLabel, $templateDescription,
      $templateRequired, $activityLabel,
      $activityDescription, $activityRequired)
      {
      return static::generateParameter('regextext', $templateLabel, $templateDescription, $templateRequired, $activityLabel, $activityDescription, $activityRequired);
      } */

    static public function MultiLineText($activityLabel, $activityDescription,
                                         $activityRequired)
    {
        return static::generateParameterSimpler('multilinetext', $activityLabel, $activityDescription, $activityRequired);
    }

    static public function MultiLineTextWithDefault($templateLabel,
                                                    $templateDescription,
                                                    $templateRequired,
                                                    $activityLabel,
                                                    $activityDescription,
                                                    $activityRequired)
    {
        return static::generateParameter('multilinetext', $templateLabel, $templateDescription, $templateRequired, $activityLabel, $activityDescription, $activityRequired);
    }

    static public function MultiFreeText($templateLabel, $templateDescription,
                                         $templateRequired, $activityLabel,
                                         $activityDescription, $activityRequired)
    {
        return static::generateParameter('multifreetext', $templateLabel, $templateDescription, $templateRequired, $activityLabel, $activityDescription, $activityRequired);
    }

    static public function Dropdown($templateLabel, $templateDescription,
                                    $templateRequired, $activityLabel,
                                    $activityDescription, $activityRequired)
    {
        return static::generateParameter('dropdown', $templateLabel, $templateDescription, $templateRequired, $activityLabel, $activityDescription, $activityRequired);
    }

    static public function MultiSelect($templateLabel, $templateDescription,
                                       $templateRequired, $activityLabel,
                                       $activityDescription, $activityRequired)
    {
        return static::generateParameter('multiselect', $templateLabel, $templateDescription, $templateRequired, $activityLabel, $activityDescription, $activityRequired);
    }

    static protected function generateParameter($type, $templateLabel,
                                                $templateDescription,
                                                $templateRequired,
                                                $activityLabel,
                                                $activityDescription,
                                                $activityRequired)
    {
        $param                      = new static($type);
        $param->templateLabel       = $templateLabel;
        $param->templateDescription = $templateDescription;
        $param->templateRequired    = (bool) $templateRequired;
        $param->activityLabel       = $activityLabel;
        $param->activityDescription = $activityDescription;
        $param->activityRequired    = (bool) $activityRequired;
        return $param;
    }

    static protected function generateParameterSimpler($type, $activityLabel,
                                                       $activityDescription,
                                                       $activityRequired)
    {
        $param                      = new static($type);
        $param->activityLabel       = $activityLabel;
        $param->activityDescription = $activityDescription;
        $param->activityRequired    = (bool) $activityRequired;
        return $param;
    }

    static protected function linesToCleanArray($text)
    {
        $lines = array();
        foreach (explode("\n", $text) as $t) {
            $t = trim($t);
            if (!empty($t)) {
                $lines[] = $t;
            }
        }
        return $lines;
    }

    public function validateTemplateInput(array &$input, $key, array &$errors,
                                          $error_prefix = '')
    {
        if (empty($this->templateLabel)) {
            return true;
        }

        $input[$key] = $this->templateFormToDatabase($input[$key]);

        $errkey = ($error_prefix ? "$error_prefix.$key" : $key);
        if (empty($input[$key]) && $this->templateRequired) {
            $errors[$errkey] = array('Required');
        }

        return empty($errors);
    }

    public function validateActivityInput(array $template_parameters,
                                          array &$input, $key, array &$errors,
                                          $error_prefix = '')
    {
        $errkey = ($error_prefix ? "$error_prefix.$key" : $key);
        switch ($this->type) {
            case 'multifreetext':
                foreach ($template_parameters[$key] as $id => $label) {
                    if (substr($label, -1) == '*' && empty($input[$key][$id])) {
                        $errors[$errkey.'.'.$id] = array('Required');
                    }
                }
                break;
            case 'dropdown':
            case 'multiselect':
                if (empty($input[$key])) {
                    if (count($template_parameters[$key]) == 1) {
                        $input[$key] = array_values($template_parameters)[0];
                    } else {
                        $errors[$errkey] = array('Required');
                        return false;
                    }
                }
                break;

            default:
                if (empty($input[$key]) && $this->activityRequired) {
                    $errors[$errkey] = array('Required');
                    return false;
                }
        }
        return empty($errors);
    }

    public function activityLabel(array $map)
    {
        $label = $this->activityLabel;
        foreach ($map as $key => $value) {
            if (is_string($value)) {
                $label = str_replace('{'.$key.'}', $value, $label);
            }
        }
        return $label;
    }

    public function activityRequireInputs($templateParameter)
    {
        switch ($this->type) {
            case 'dropdown':
            case 'multiselect':
            case 'multifreetext':
                return count($templateParameter) > 0;
            default:
                return !empty($this->activityLabel);
        }
    }

    public function templateFormToDatabase($parameter)
    {
        switch ($this->type) {
            case 'dropdown':
            case 'multiselect':
                $cfg    = array();
                $values = static::linesToCleanArray($parameter['values']);
                $texts  = static::linesToCleanArray($parameter['texts']);
                $size   = count($values);
                for ($i = 0; $i < $size; $i++) {
                    if (empty($values[$i])) {
                        continue;
                    }
                    $text = trim(isset($texts[$i]) && !empty(trim($texts[$i])) ?
                        $texts[$i] : $values[$i]);

                    $cfg[$values[$i]] = $text;
                }
                return $cfg;

            case 'multifreetext':
                $cfg    = array();
                $keys   = static::linesToCleanArray($parameter['keys']);
                $labels = static::linesToCleanArray($parameter['labels']);
                $size   = min(count($keys), count($labels));
                for ($i = 0; $i < $size; $i++) {
                    if (empty($keys[$i]) || empty($labels[$i])) {
                        continue;
                    }
                    $cfg[$keys[$i]] = $labels[$i];
                }
                return $cfg;

            default:
                return $parameter;
        }
    }

    public function templateDatabaseToForm($parameter)
    {
        switch ($this->type) {
            case 'dropdown':
            case 'multiselect':
                return array(
                    'values' => implode("\n", array_keys($parameter ?: array())),
                    'texts' => implode("\n", array_values($parameter ?: array())),
                );

            case 'multifreetext':
                return array(
                    'keys' => implode("\n", array_keys($parameter ?: array())),
                    'labels' => implode("\n", array_values($parameter ?: array())),
                );

            default:
                return $parameter;
        }
    }

    public function activityDatabaseToForm(array $template_parameters,
                                           array $parameters, $key,
                                           Actionable $activity = null)
    {
        return (isset($parameters[$key]) ? $parameters[$key] : null);
    }

    public function displayActivityParameter(Actionable $activity, $key)
    {
        return (isset($activity->parameters[$key]) ? $activity->parameters[$key]
                : null);
    }

    public function handleActivityFiles(Request $request, Actionable $activity,
                                        array &$input, $key)
    {
        // nothing to do
    }

    public function getDownloadLink(FileLink $filelink)
    {
        return $this->app->entity_path($filelink instanceof RunItemFile ?
                'runitem_file_download' : 'activity_file_download', $filelink);
    }

    public function getTwigForTemplateForm()
    {
        return 'parameter/template.twig';
    }

    public function getTwigForActivityForm()
    {
        return 'parameter/activity.twig';
    }
}