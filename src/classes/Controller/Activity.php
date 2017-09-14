<?php

namespace Renogen\Controller;

use Doctrine\ORM\NoResultException;
use Renogen\Base\RenoController;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

class Activity extends RenoController
{
    const entityFields = array('stage', 'parameters');

    public function create(Request $request, $project, $deployment, $item)
    {
        try {
            $project_obj            = $this->fetchProject($project);
            $item_obj               = $this->fetchItem($project_obj, $deployment, $item);
            $this->addEntityCrumb($item_obj);
            $this->addCreateCrumb('Add activity', $this->app->path('activity_create', $this->entityParams($item_obj)));
            $activity_obj           = new \Renogen\Entity\Activity($item_obj);
            $activity_obj->template = $project_obj->templates->get($request->request->get('template'));
            return $this->edit_or_create($activity_obj, $request->request);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    public function edit(Request $request, $project, $deployment, $item,
                         $activity)
    {
        try {
            $activity_obj = $this->fetchActivity($project, $deployment, $item, $activity);
            $this->addEntityCrumb($activity_obj);
            return $this->edit_or_create($activity_obj, $request->request, array(
                    'activity' => $activity_obj));
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    protected function edit_or_create(\Renogen\Entity\Activity $activity,
                                      ParameterBag $post,
                                      array $context = array())
    {
        if ($post->count() > 0) {
            switch ($post->get('_action')) {
                case 'Delete':
                    $activity->delete($this->app['em']);
                    $this->app['em']->flush();
                    $this->app->addFlashMessage("Activity has been deleted");
                    return $this->redirect('item_view', $this->entityParams($activity->item));

                case 'Next':
                    $this->prepareValidateEntity($activity, static::entityFields, $post);
                    $context['errors'] = $activity->errors;
                    break;

                default:
                    $errors = array();
                    if ($activity->template) {
                        $activity->priority = $activity->template->priority;
                        if (($templateClass      = $activity->template->templateClass())) {
                            $parameters = $post->get('parameters', array());
                            foreach ($templateClass->getParameters() as $param => $parameter) {
                                $parameter->validateActivityInput($activity->template->parameters, $parameters, $param, $errors, 'parameters');
                            }
                            $post->set('parameters', $parameters);
                        }
                    }

                    if ($this->prepareValidateEntity($activity, static::entityFields, $post)
                        && empty($errors)) {
                        $this->saveEntity($activity, static::entityFields, $post);
                        $this->app->addFlashMessage("Activity has been successfully saved");
                        return $this->redirect('item_view', $this->entityParams($activity->item));
                    } else {
                        $context['errors'] = $errors + $activity->errors;
                    }
            }
        }
        $context['activity'] = $activity;
        return $this->render('activity_form', $context);
    }
}