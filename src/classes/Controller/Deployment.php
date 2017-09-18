<?php

namespace Renogen\Controller;

use Doctrine\ORM\NoResultException;
use Renogen\Base\RenoController;
use Renogen\Entity\Deployment as DeploymentEntity;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

class Deployment extends RenoController
{
    const entityFields = array('name', 'title', 'execute_date', 'description');

    public function create(Request $request, $project)
    {
        try {
            $project_obj = $this->fetchProject($project);
            $this->checkAccess('approval', $project_obj);
            $this->addEntityCrumb($project_obj);
            $this->addCreateCrumb('Create deployment', $this->app->path('deployment_create', $this->entityParams($project_obj)));
            return $this->edit_or_create(new DeploymentEntity($project_obj), $request->request);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    public function view(Request $request, $project, $deployment)
    {
        try {
            $deployment_obj = $this->fetchDeployment($project, $deployment);
            $this->addEntityCrumb($deployment_obj);
            return $this->render('deployment_view', array(
                    'deployment' => $deployment_obj,
            ));
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    public function edit(Request $request, $project, $deployment)
    {
        try {
            $deployment_obj = $this->fetchDeployment($project, $deployment);
            $this->checkAccess('approval', $deployment_obj);
            $this->addEntityCrumb($deployment_obj);
            $this->addEditCrumb($this->app->path('deployment_edit', $this->entityParams($deployment_obj)));
            return $this->edit_or_create($deployment_obj, $request->request, array(
                    'deployment' => $deployment_obj));
        } catch (NoResultException $ex) {
            return $this->errorPage('Deployment not found', $ex->getMessage());
        }
    }

    protected function edit_or_create(DeploymentEntity $deployment,
                                      ParameterBag $post,
                                      array $context = array())
    {
        if ($post->count() > 0) {
            if ($post->get('_action') == 'Delete') {
                $deployment->delete($this->app['em']);
                $this->app['em']->flush();
                $this->app->addFlashMessage("Deployment '$deployment->title' has been deleted");
                return $this->redirect('project_view', array(
                        'project' => $deployment->project->name,
                ));
            }
            $context['deployment'] = $deployment;
            if ($this->saveEntity($deployment, static::entityFields, $post)) {
                $this->app->addFlashMessage("Deployment '$deployment->title' has been successfully saved");
                return $this->redirect('deployment_view', $this->entityParams($deployment));
            } else {
                $context['errors'] = $deployment->errors;
            }
        }
        $this->addCSS("ui/semantic2/library/calendar.css");
        $this->addJS("ui/semantic2/library/calendar.js");
        return $this->render('deployment_form', $context);
    }
}