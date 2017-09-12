<?php

namespace Renogen\ActivityTemplate;

class Parameter
{
    public $type;
    public $templateLabel;
    public $templateDescription;
    public $templateRequired;
    public $activityLabel;
    public $activityDescription;
    public $activityRequired;

    protected function __construct($type)
    {
        $this->type = $type;
    }

    public function getType()
    {
        return $this->type;
    }

    /**
     * ConfigType is for a parameter that needs be entered
     * during template creation but not during activity creation
     * @param type $templateLabel
     * @param type $templateDescription
     * @param type $templateRequired
     * @return \static
     */
    static public function Config($templateLabel, $templateDescription,
                                  $templateRequired)
    {
        $param                      = new static('config');
        $param->templateLabel       = $templateLabel;
        $param->templateDescription = $templateDescription;
        $param->templateRequired    = (bool) $templateRequired;
        return $param;
    }

    static public function FreeText($activityLabel, $activityDescription,
                                    $activityRequired)
    {
        $param                      = new static('freetext');
        $param->activityLabel       = $activityLabel;
        $param->activityDescription = $activityDescription;
        $param->activityRequired    = (bool) $activityRequired;
        return $param;
    }

    static public function RegexText($templateLabel, $templateDescription,
                                     $templateRequired, $activityLabel,
                                     $activityDescription, $activityRequired)
    {
        return static::generateParameter('regextext', $templateLabel, $templateDescription, $templateRequired, $activityLabel, $activityDescription, $activityRequired);
    }

    static public function MultiLineText($activityLabel, $activityDescription,
                                         $activityRequired)
    {
        $param                      = new static('multilinetext');
        $param->activityLabel       = $activityLabel;
        $param->activityDescription = $activityDescription;
        $param->activityRequired    = (bool) $activityRequired;
        return $param;
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

    static public function File($templateLabel, $templateDescription,
                                $templateRequired, $activityLabel,
                                $activityDescription, $activityRequired)
    {
        return static::generateParameter('file', $templateLabel, $templateDescription, $templateRequired, $activityLabel, $activityDescription, $activityRequired);
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

    public function cleanupTemplateInput(array &$input, $key)
    {
        switch ($this->type) {
            case 'dropdown':
            case 'multiselect':
                $values                = static::linesToCleanArray($input[$key]['values']);
                $texts                 = static::linesToCleanArray($input[$key]['texts']);
                $size                  = min(count($values), count($texts));
                $input[$key]['values'] = implode("\n", array_slice($values, 0, $size));
                $input[$key]['texts']  = implode("\n", array_slice($texts, 0, $size));
                break;

            case 'multifreetext':
                $keys                  = static::linesToCleanArray($input[$key]['keys']);
                $labels                = static::linesToCleanArray($input[$key]['labels']);
                $size                  = min(count($keys), count($labels));
                $input[$key]['keys']   = implode("\n", array_slice($keys, 0, $size));
                $input[$key]['labels'] = implode("\n", array_slice($labels, 0, $size));
                break;

            default:
            // nothing to do
        }
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
        $this->cleanupTemplateInput($input, $key);

        $errkey = ($error_prefix ? "$error_prefix.$key" : $key);
        switch ($this->type) {
            case 'dropdown':
            case 'multiselect':
                if ($this->templateRequired &&
                    (empty($input[$key]['values']) || empty($input[$key]['texts']))) {
                    $errors[$errkey] = array('Required');
                    return false;
                }
                break;

            default:
                if (empty($input[$key]) && $this->templateRequired) {
                    $errors[$errkey] = array('Required');
                    return false;
                }
        }

        return true;
    }

    public function validateActivityInput(array $template_parameters,
                                          array &$input, $key, array &$errors,
                                          $error_prefix = '')
    {
        $errkey = ($error_prefix ? "$error_prefix.$key" : $key);
        switch ($this->type) {
            case 'multifreetext':
                if (!isset($template_parameters[$key]['keys'])) {
                    return true;
                }
                $keys   = explode("\n", $template_parameters[$key]['keys']);
                $labels = explode("\n", $template_parameters[$key]['labels']);
                for ($i = 0; $i < count($keys); $i++) {
                    if (substr($labels[$i], -1) == '*' && empty($input[$key][$keys[$i]])) {
                        $errors[$errkey.'.'.$keys[$i]] = array('Required');
                    }
                }
                break;

            default:
                if (empty($input[$key]) && $this->activityRequired) {
                    $errors[$errkey] = array('Required');
                    return false;
                }
        }
        return true;
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
}