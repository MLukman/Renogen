<?php

namespace Renogen\ActivityTemplate\Parameter;

use Renogen\ActivityTemplate\Parameter;
use Renogen\Base\Actionable;
use Renogen\Entity\ActivityFile;
use Renogen\Entity\RunItem;
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
        if (isset($input[$key])) {
            $input[$key] = $this->templateFormToDatabase($input[$key]);
        }
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
                                           array $parameters, $key,
                                           Actionable $activity = null)
    {
        if ($activity && isset($parameters[$key]) && ($activity_file = $this->app['datastore']->queryOne($activity->fileClass, array(
            "{$activity->actionableType}" => $activity,
            'classifier' => $parameters[$key])))) {
            /* @var $activity_file ActivityFile */
            return array(
                'fileid' => $activity_file->id,
                'filename' => $activity_file->filename,
                'filesize' => $activity_file->filestore->filesize,
                'mime_type' => $activity_file->filestore->mime_type,
            );
        }
        return null;
    }

    public function displayActivityParameter(Actionable $activity, $key)
    {
        if ($activity instanceof RunItem) {
            $class  = '\Renogen\Entity\RunItemFile';
            $parent = 'runitem';
        } else {
            $class  = '\Renogen\Entity\ActivityFile';
            $parent = 'activity';
        }
        if (isset($activity->parameters[$key]) && ($activity_file = $this->app['datastore']->queryOne($class, array(
            "$parent" => $activity,
            'classifier' => $activity->parameters[$key])))) {
            /* @var $activity_file ActivityFile */
            return $activity_file->getHtmlLink();
        }
        return null;
    }

    public function handleActivityFiles(Request $request, Actionable $activity,
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
            $activity_file             = $this->app['datastore']->processFileUpload($file, $activity_file);
            $activity_file->classifier = $key;
            $this->app['datastore']->manage($activity_file);
        }
        $input[$key] = $activity_file->classifier;
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