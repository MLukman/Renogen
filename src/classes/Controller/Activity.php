<?php

namespace Renogen\Controller;

use Renogen\Base\RenoController;
use Renogen\Exception\NoResultException;
use Symfony\Component\HttpFoundation\Request;

class Activity extends RenoController
{
    const entityFields = array('title', 'stage', 'parameters');
    const editAccess = array('entry', 'review', 'approval');

    public function create(Request $request, $project, $deployment, $item)
    {
        try {
            $project_obj = $this->app['datastore']->fetchProject($project);
            $item_obj = $this->app['datastore']->fetchItem($project_obj, $deployment, $item);
            $this->checkAccess(static::editAccess, $item_obj);
            $this->addEntityCrumb($item_obj);
            $this->addCreateCrumb('Add activity', $this->app->entity_path('activity_create', $item_obj));
            $activity_obj = new \Renogen\Entity\Activity($item_obj);
            $activity_obj->template = $project_obj->templates->get($request->request->get('template'));
            if ($activity_obj->template && empty($activity_obj->title)) {
                $activity_obj->title = $activity_obj->template->title;
            }
            return $this->edit_or_create($activity_obj, $request);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    public function edit(Request $request, $project, $deployment, $item,
                         $activity)
    {
        try {
            $activity_obj = $this->app['datastore']->fetchActivity($project, $deployment, $item, $activity);
            $this->checkAccess(static::editAccess, $activity_obj);
            $this->addEntityCrumb($activity_obj);
            return $this->edit_or_create($activity_obj, $request);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    protected function edit_or_create(\Renogen\Entity\Activity $activity,
                                      Request $request)
    {
        $post = $request->request;
        $context = array();
        if ($post->count() > 0) {
            switch ($post->get('_action')) {
                case 'Delete':
                    $this->app['datastore']->deleteEntity($activity);
                    $this->app['datastore']->commit();
                    $this->app->addFlashMessage("Activity has been deleted");
                    return $this->app->entity_redirect('item_view', $activity->item);

                case 'Next':
                    $this->app['datastore']->prepareValidateEntity($activity, static::entityFields, $post);
                    $context['errors'] = $activity->errors;
                    break;

                default:
                    $errors = array();
                    if ($activity->template) {
                        $activity->priority = $activity->template->priority;
                        if (($templateClass = $activity->template->templateClass())) {
                            $parameters = $post->get('parameters', array());
                            foreach ($templateClass->getParameters() as $param => $parameter) {
                                $parameter->handleActivityFiles($request, $activity, $parameters, $param);
                                $parameter->validateActivityInput($activity->template->parameters, $parameters, $param, $errors, 'parameters');
                            }
                            $post->set('parameters', $parameters);
                        }
                    }

                    if ($this->app['datastore']->prepareValidateEntity($activity, static::entityFields, $post)
                        && empty($errors)) {
                        $activity->calculateSignature();
                        $activity->runitem = null;
                        $this->app['datastore']->manage($activity);
                        $this->app['datastore']->commit();
                        $this->app->addFlashMessage("Activity has been successfully saved");
                        return $this->app->entity_redirect('item_view', $activity->item);
                    } else {
                        $context['errors'] = $errors + $activity->errors;
                    }
            }
        }
        $context['activity'] = $activity;
        return $this->render('activity_form', $context);
    }

    public function download_file(Request $request, $project, $deployment,
                                  $item, $activity, $file)
    {
        try {
            if (!($activity_file = $this->app['datastore']->queryOne('\Renogen\Entity\ActivityFile', $file))) {
                throw new NoResultException("No such activity file with id '$file'");
            }
            return $activity_file->returnDownload();
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }
}