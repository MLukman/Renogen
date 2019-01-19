<?php

namespace Renogen\ActivityTemplate\Parameter;

use Renogen\ActivityTemplate\Parameter;
use Renogen\Base\Actionable;
use Renogen\Entity\ActivityFile;
use Symfony\Component\HttpFoundation\Request;

class MultiField extends Parameter
{
    public $allowed_types = ['freetext', 'password', 'dropdown', 'multiselect', 'multiline',
        'url', 'file'];
    public $default_type  = null;

    static public function create($templateLabel, $templateDescription,
                                  $templateRequired, $activityLabel,
                                  $activityDescription, $activityRequired,
                                  $allowed_types = null, $default_type = null)
    {
        $param = static::generateParameter('multifield', $templateLabel, $templateDescription, $templateRequired, $activityLabel, $activityDescription, $activityRequired);
        if ($allowed_types) {
            $param->allowed_types = $allowed_types;
        }
        $param->default_type = $default_type;
        return $param;
    }

    public function validateTemplateInput(array &$input, $key, array &$errors,
                                          $error_prefix = '')
    {
        $input[$key] = $this->templateFormToDatabase($input[$key]);
        $errkey      = ($error_prefix ? "$error_prefix.$key" : $key);

        if (empty($input[$key]) && $this->templateRequired) {
            $errors[$errkey] = array('Required');
        }

        $keys = array();
        foreach ($input[$key] as $i => $p) {
            foreach (array('id', 'title', 'type') as $f) {
                if (empty($p[$f])) {
                    $errors[$errkey.'.'.$i.'.'.$f] = array('Required');
                }
            }
            if (!empty($p['id'])) {
                if (isset($keys[$p['id']])) {
                    $errors[$errkey.'.'.$i.'.id'] = array('Must be unique');
                } else {
                    $keys[$p['id']] = 1;
                }
            }
        }

        return empty($errors);
    }

    public function validateActivityInput(array $template_parameters,
                                          array &$input, $key, array &$errors,
                                          $error_prefix = '')
    {
        $errkey = ($error_prefix ? "$error_prefix.$key" : $key);
        foreach ($template_parameters[$key] as $p) {
            if ($p['required'] && empty($input[$key][$p['id']])) {
                $errors[$errkey.'.'.$p['id']] = array('Required');
            } elseif ($p['type'] == 'url' && !filter_var($input[$key][$p['id']], FILTER_VALIDATE_URL, FILTER_FLAG_HOST_REQUIRED)) {
                $errors[$errkey.'.'.$p['id']] = array('Must be a valid URL');
            }
        }
        return empty($errors);
    }

    public function templateFormToDatabase($parameter)
    {
        $cfg = array();
        foreach ($parameter as $p) {
            if (empty($p['id']) && empty($p['title']) && empty($p['details']) && !isset($p['required'])) {
                continue;
            }
            $cfg[] = array_merge(array('required' => 0), $p);
        }
        return $cfg;
    }

    public function activityDatabaseToForm(array $template_parameters,
                                           array $parameters, $key,
                                           Actionable $activity = null)
    {
        $data = array();
        foreach ($template_parameters[$key] as $p) {
            if (!isset($parameters[$key]) || !isset($parameters[$key][$p['id']])) {
                continue;
            }
            switch ($p['type']) {
                case 'file':
                    if (($activity_file = $this->app['datastore']->queryOne($activity->fileClass, array(
                        "{$activity->actionableType}" => $activity,
                        'classifier' => $parameters[$key][$p['id']])))) {
                        /* @var $activity_file ActivityFile */
                        $data[$p['id']] = array(
                            'fileid' => $activity_file->id,
                            'filename' => $activity_file->filename,
                            'filesize' => $activity_file->filestore->filesize,
                            'mime_type' => $activity_file->filestore->mime_type,
                        );
                    }
                    break;
                default:
                    $data[$p['id']] = $parameters[$key][$p['id']];
            }
        }
        return $data;
    }

    public function handleActivityFiles(Request $request, Actionable $activity,
                                        array &$input, $key)
    {
        foreach ($activity->template->parameters[$key] as $p) {
            if ($p['type'] == 'file') {
                $pid = $p['id'];
                if (isset($activity->parameters[$key][$pid]) && $activity->files->containsKey($activity->parameters[$key][$pid])) {
                    $activity_file = $activity->files->get($activity->parameters[$key][$pid]);
                } else {
                    $activity_file = new ActivityFile($activity);
                }

                $post  = $request->request->get('parameters');
                $files = $request->files->get('parameters');
                if (isset($files[$key]) &&
                    isset($files[$key][$pid]) &&
                    ($file  = $files[$key][$pid])) {
                    $activity_file             = $this->app['datastore']->processFileUpload($file, $activity_file);
                    $activity_file->classifier = "$key.$pid";
                    $this->app['datastore']->manage($activity_file);
                } elseif (isset($post[$key]) &&
                    isset($post[$key][$pid.'_delete']) &&
                    $post[$key][$pid.'_delete']) {
                    $input[$key][$pid] = null;
                    $activity->files->removeElement($activity_file);
                    unset($input[$key][$pid.'_delete']);
                    continue;
                }
                $input[$key][$pid] = $activity_file->classifier;
            }
        }
    }

    public function displayActivityParameter(Actionable $activity, $key)
    {
        $isForRunbook = ($activity instanceof \Renogen\Entity\RunItem);
        $options      = array();
        $data         = $this->activityDatabaseToForm($activity->template->parameters, $activity->parameters, $key, $activity);
        foreach ($activity->template->parameters[$key] as $p) {
            if ($isForRunbook) {
                $d = $p['id'];
            } else {
                $d = $p['title'];
            }

            $options[$d] = null;
            if (isset($data[$p['id']])) {
                if ($p['type'] == 'file') {
                    $file = $this->app['datastore']->queryOne($activity->fileClass, array(
                        "{$activity->actionableType}" => $activity,
                        'classifier' => $key.'.'.$p['id'],
                    ));
                    if ($file) {
                        $options[$d] = '<a href="'.htmlentities($this->getDownloadLink($file)).'">'.htmlentities($file->filename).'</a>';
                    }
                } elseif ($p['type'] == 'password' && !$isForRunbook) {
                    $options[$d] = '******';
                } elseif ($p['type'] == 'url') {
                    $options[$d] = '<a href="'.htmlentities($data[$p['id']]).'" target="_blank">'.htmlentities($data[$p['id']]).'</a>';
                } else {
                    $options[$d] = $data[$p['id']];
                }
            }
        }
        return $options;
    }

    public function getTwigForTemplateForm()
    {
        return 'parameter/template_multifield.twig';
    }

    public function getTwigForActivityForm()
    {
        return 'parameter/activity_multifield.twig';
    }
}