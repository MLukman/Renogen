<?php

namespace Renogen\Controller;

use Renogen\Base\RenoController;
use Renogen\Entity\Deployment as DeploymentEntity;
use Renogen\Entity\Item;
use Renogen\Exception\NoResultException;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

class Deployment extends RenoController
{
    const entityFields = array('execute_date', 'title', 'description');

    public function create(Request $request, $project)
    {
        try {
            $project_obj = $this->app['datastore']->fetchProject($project);
            $this->checkAccess('approval', $project_obj);
            $this->addEntityCrumb($project_obj);
            $this->addCreateCrumb('Create deployment', $this->app->entity_path('deployment_create', $project_obj));
            return $this->edit_or_create(new DeploymentEntity($project_obj), $request->request);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    public function view(Request $request, $project, $deployment)
    {
        try {
            $deployment_obj = $this->app['datastore']->fetchDeployment($project, $deployment);
            if (is_string($deployment) && $deployment != $deployment_obj->datetimeString()) {
                return $this->app->entity_redirect('deployment_view', $deployment_obj);
            }
            $this->addEntityCrumb($deployment_obj);
            return $this->render('deployment_view', array(
                    'deployment' => $deployment_obj,
                    'project' => $deployment_obj->project,
            ));
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    public function edit(Request $request, $project, $deployment)
    {
        try {
            $deployment_obj = $this->app['datastore']->fetchDeployment($project, $deployment);
            if (is_string($deployment) && $deployment != $deployment_obj->datetimeString()) {
                return $this->app->entity_redirect('deployment_edit', $deployment_obj);
            }
            $this->checkAccess('approval', $deployment_obj);
            $this->addEntityCrumb($deployment_obj);
            $this->addEditCrumb($this->app->entity_path('deployment_edit', $deployment_obj));
            return $this->edit_or_create($deployment_obj, $request->request);
        } catch (NoResultException $ex) {
            return $this->errorPage('Deployment not found', $ex->getMessage());
        }
    }

    protected function edit_or_create(DeploymentEntity $deployment,
                                      ParameterBag $post)
    {
        $context = array();
        if ($post->count() > 0) {
            if ($post->get('_action') == 'Delete') {
                if ($deployment->items->count() == 0) {
                    $this->app['datastore']->deleteEntity($deployment);
                    $this->app['datastore']->commit();
                    $this->app->addFlashMessage("Deployment '$deployment->title' has been deleted");
                    return $this->app->entity_redirect('project_view', $deployment->project);
                } else {
                    $this->app->addFlashMessage("Deployment '$deployment->title' cannot be deleted because it contains item(s).\nMove or delete the item(s) first.", "Invalid action", "error");
                    return $this->app->entity_redirect('deployment_edit', $deployment);
                }
            }

            $old_date = $deployment->execute_date;
            if ($this->app['datastore']->prepareValidateEntity($deployment, static::entityFields, $post)) {
                $is_new = ($deployment->id == null);
                $this->app['datastore']->commit($deployment);
                $this->app->addFlashMessage("Deployment '$deployment->title' has been successfully saved");
                return $this->app->entity_redirect('deployment_view', $deployment);
            } else {
                $context['errors'] = $deployment->errors;
            }
        }
        $context['deployment'] = $deployment;
        $context['project']    = $deployment->project;
        return $this->render('deployment_form', $context);
    }

    public function release_note(Request $request, $project, $deployment)
    {
        $deployment_obj = $this->app['datastore']->fetchDeployment($project, $deployment);
        if (is_string($deployment) && $deployment != $deployment_obj->datetimeString()) {
            return $this->app->entity_redirect('release_note', $deployment_obj);
        }
        $this->addEntityCrumb($deployment_obj);
        $this->addCrumb('Release Note', $this->app->entity_path('release_note', $deployment_obj), 'ordered list');
        $context = array(
            'deployment' => $deployment_obj,
            'project' => $deployment_obj->project,
            'items' => array(),
        );

        foreach ($deployment_obj->project->categories as $category) {
            $context['items'][$category] = array();
        }

        foreach ($deployment_obj->items as $item) {
            /* @var $item Item  */
            $context['items'][$item->category][] = $item;
        }

        return $this->render('release_note', $context);
    }
}