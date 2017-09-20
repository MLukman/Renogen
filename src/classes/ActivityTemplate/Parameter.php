<?php

namespace Renogen\ActivityTemplate;

use Renogen\Entity\ActivityFile;
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

    static public function MultiField($templateLabel, $templateDescription,
                                      $templateRequired, $activityLabel,
                                      $activityDescription, $activityRequired)
    {
        return static::generateParameter('multifield', $templateLabel, $templateDescription, $templateRequired, $activityLabel, $activityDescription, $activityRequired);
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

            case 'multifield':
                $toremove = array();
                foreach ($input[$key] as $i => $p) {
                    if (empty($p['id']) && empty($p['title']) && empty($p['details'])
                        && !isset($p['required'])) {
                        $toremove[] = $i;
                    }
                }
                foreach (array_reverse($toremove) as $i) {
                    unset($input[$key][$i]);
                }
                break;

            default:
                if (empty($input[$key]) && $this->templateRequired) {
                    $errors[$errkey] = array('Required');
                    return false;
                }
        }

        $input[$key] = $this->templateFormToDatabase($input[$key]);
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
            case 'dropdown':
            case 'multiselect':
                if (empty($input[$key])) {
                    $values = explode("\n", $template_parameters[$key]['values']);
                    if (count($values) == 1) {
                        $input[$key] = $values[0];
                    } else {
                        $errors[$errkey] = array('Required');
                        return false;
                    }
                }
                break;

            case 'multifield':
                foreach ($template_parameters[$key] as $p) {
                    if ($p['required'] && empty($input[$key][$p['id']])) {
                        $errors[$errkey.'.'.$p['id']] = array('Required');
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
                $values = explode("\n", $parameter['values']);
                $texts  = explode("\n", $parameter['texts']);
                for ($i = 0; $i < min(count($values), count($texts)); $i++) {
                    if (empty($values[$i]) || empty($texts[$i])) {
                        continue;
                    }
                    $cfg[$values[$i]] = $texts[$i];
                }
                return $cfg;

            case 'multifreetext':
                $cfg    = array();
                $keys   = explode("\n", $parameter['keys']);
                $labels = explode("\n", $parameter['labels']);
                for ($i = 0; $i < min(count($keys), count($labels)); $i++) {
                    if (empty($keys[$i]) || empty($labels[$i])) {
                        continue;
                    }
                    $cfg[$keys[$i]] = $labels[$i];
                }
                return $cfg;

            case 'multifield':
                $cfg = array();
                foreach ($parameter as $p) {
                    if (empty($p['id']) && empty($p['title']) && empty($p['details'])
                        && !isset($p['required'])) {
                        continue;
                    }
                    $cfg[] = array_merge(array('required' => 0), $p);
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
                                           \Renogen\Application $app)
    {
        switch ($this->type) {
            case 'multifield':
                $data = array();
                foreach ($template_parameters[$key] as $p) {
                    switch ($p['type']) {
                        case 'file':
                            if (($activity_file = $app['em']->getRepository('\Renogen\Entity\ActivityFile')->findOneBy(array(
                                'stored_filename' => $parameters[$key])))) {
                                /* @var $activity_file ActivityFile */
                                $data[$p['id']] = array(
                                    'fileid' => $activity_file->id,
                                    'filename' => $activity_file->filename,
                                    'filesize' => $activity_file->filesize,
                                    'filepath' => $activity_file->getFilesystemPath(),
                                );
                            }
                            break;
                        default:
                            $data[$p['id']] = $parameters[$key][$p['id']];
                    }
                }
                return $data;
            default:
                return $parameters[$key];
        }
    }

    public function handleActivityFiles(Request $request,
                                        \Renogen\Entity\Activity $activity,
                                        array &$input, $key)
    {
        switch ($this->type) {
            case 'multifield':
                foreach ($activity->template->parameters[$key] as $p) {
                    if ($p['type'] == 'file') {
                        $pid = $p['id'];
                        if (isset($activity->parameters[$key][$pid]) && $activity->files->containsKey($activity->parameters[$key][$pid])) {
                            $activity_file = $activity->files->get($activity->parameters[$key][$pid]);
                        } else {
                            $activity_file = new ActivityFile($activity);
                        }

                        $files = $request->files->get('parameters');
                        if (isset($files[$key]) &&
                            isset($files[$key][$pid]) &&
                            ($file  = $files[$key][$pid])) {
                            $activity_file->processUploadedFile($file);
                            if (!$activity_file->id) {
                                $activity->files->add($activity_file);
                            }
                        }
                        $input[$key][$pid] = $activity_file->stored_filename;
                    }
                }
                break;
        }
    }
}