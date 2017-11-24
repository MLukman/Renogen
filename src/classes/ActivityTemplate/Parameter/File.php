<?php

namespace Renogen\ActivityTemplate\Parameter;

use Renogen\ActivityTemplate\Parameter;
use Renogen\Application;
use Renogen\Entity\ActivityFile;
use Symfony\Component\HttpFoundation\Request;

class File extends Parameter
{

    static public function create($activityLabel, $activityDescription,
                                  $activityRequired)
    {
        return static::generateParameterSimpler('file', $activityLabel, $activityDescription, $activityRequired);
    }

    public function validateTemplateInput(array &$input, $key, array &$errors,
                                          $error_prefix = '')
    {
        $input[$key] = $this->templateFormToDatabase($input[$key]);
        $errkey      = ($error_prefix ? "$error_prefix.$key" : $key);

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
        if (empty($input[$key]) && $this->activityRequired) {
            $errors[$errkey] = array('Required');
        }
        return empty($errors);
    }

    public function templateFormToDatabase($parameter)
    {
        return $parameter;
    }

    public function activityDatabaseToForm(array $template_parameters,
                                           array $parameters, $key)
    {
        if (isset($parameters[$key]) && ($activity_file = $this->app['datastore']->queryOne('\Renogen\Entity\ActivityFile', array(
            'stored_filename' => $parameters[$key])))) {
            /* @var $activity_file ActivityFile */
            return array(
                'fileid' => $activity_file->id,
                'filename' => $activity_file->filename,
                'filesize' => $activity_file->filesize,
                'mime_type' => $activity_file->mime_type,
                'filepath' => $activity_file->getFilesystemPath(),
            );
        }
        return null;
    }

    public function displayActivityParameter(\Renogen\Entity\Activity $activity,
                                             $key)
    {
        if (isset($activity->parameters[$key]) && ($activity_file = $this->app['datastore']->queryOne('\Renogen\Entity\ActivityFile', array(
            'stored_filename' => $activity->parameters[$key])))) {
            /* @var $activity_file ActivityFile */
            return $activity_file->getHtmlLink();
        }
        return null;
    }

    public function handleActivityFiles(Request $request,
                                        \Renogen\Entity\Activity $activity,
                                        array &$input, $key)
    {
        if (isset($activity->parameters[$key]) && $activity->files->containsKey($activity->parameters[$key])) {
            $activity_file = $activity->files->get($activity->parameters[$key]);
        } else {
            $activity_file = new ActivityFile($activity);
        }

        $files = $request->files->get('parameters');
        if (isset($files[$key]) &&
            ($file  = $files[$key])) {
            $activity_file->processUploadedFile($file);
            if (!$activity_file->id) {
                $activity->files->add($activity_file);
            }
        }
        $input[$key] = $activity_file->stored_filename;
    }

    public function getTwigForTemplateForm()
    {
        return 'parameter/template_file.twig';
    }

    public function getTwigForActivityForm()
    {
        return 'parameter/activity_file.twig';
    }
}