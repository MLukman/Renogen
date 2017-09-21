<?php

namespace Renogen\ActivityTemplate\Parameter;

use Renogen\ActivityTemplate\Parameter;
use Renogen\Application;
use Renogen\Entity\ActivityFile;
use Symfony\Component\HttpFoundation\Request;

class MultiField extends Parameter
{

    static public function create($templateLabel, $templateDescription,
                                  $templateRequired, $activityLabel,
                                  $activityDescription, $activityRequired)
    {
        return static::generateParameter('multifield', $templateLabel, $templateDescription, $templateRequired, $activityLabel, $activityDescription, $activityRequired);
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
                                           Application $app)
    {
        $data = array();
        foreach ($template_parameters[$key] as $p) {
            if (!isset($parameters[$key]) || !isset($parameters[$key][$p['id']])) {
                continue;
            }
            switch ($p['type']) {
                case 'file':
                    if (($activity_file = $app['em']->getRepository('\Renogen\Entity\ActivityFile')->findOneBy(array(
                        'stored_filename' => $parameters[$key][$p['id']])))) {
                        /* @var $activity_file ActivityFile */
                        $data[$p['id']] = array(
                            'fileid' => $activity_file->id,
                            'filename' => $activity_file->filename,
                            'filesize' => $activity_file->filesize,
                            'mime_type' => $activity_file->mime_type,
                            'filepath' => $activity_file->getFilesystemPath(),
                        );
                    }
                    break;
                default:
                    $data[$p['id']] = $parameters[$key][$p['id']];
            }
        }
        return $data;
    }

    public function handleActivityFiles(Request $request,
                                        \Renogen\Entity\Activity $activity,
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