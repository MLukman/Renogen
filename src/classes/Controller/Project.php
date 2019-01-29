<?php

namespace Renogen\Controller;

use Renogen\Base\RenoController;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class Project extends RenoController
{
    const entityFields = array('name', 'title', 'description');

    public function create(Request $request)
    {
        $this->addCreateCrumb('Create project', $this->app->path('project_create'));
        return $this->edit_or_create($request->request);
    }

    public function view(Request $request, $project)
    {
        if (!($project instanceof \Renogen\Entity\Project)) {
            $name    = $project;
            if (!($project = $this->app['datastore']->queryOne('\Renogen\Entity\Project', array(
                'name' => $name)))) {
                return $this->errorPage('Project not found', "There is not such project with name '$name'");
            }
        }
        $this->checkAccess(array('view', 'execute', 'entry', 'review', 'approval'), $project);
        $this->addEntityCrumb($project);
        return $this->render('project_view', array(
                'project' => $project
        ));
    }

    public function past(Request $request, $project)
    {
        if (!($project instanceof \Renogen\Entity\Project)) {
            $name    = $project;
            if (!($project = $this->app['datastore']->queryOne('\Renogen\Entity\Project', array(
                'name' => $name)))) {
                return $this->errorPage('Project not found', "There is not such project with name '$name'");
            }
        }
        $this->checkAccess(array('view', 'execute', 'entry', 'review', 'approval'), $project);
        $this->addEntityCrumb($project);
        $this->addCrumb('Past deployments', $this->app->entity_path('project_past', $project), 'clock');
        return $this->render('project_past', array(
                'project' => $project
        ));
    }

    public function edit(Request $request, $project)
    {
        if (!($project instanceof \Renogen\Entity\Project)) {
            $name    = $project;
            if (!($project = $this->app['datastore']->queryOne('\Renogen\Entity\Project', array(
                'name' => $name)))) {
                return $this->errorPage('Project not found', "There is not such project with name '$name'");
            }
        }
        if (!$this->app['securilex']->isGranted('ROLE_ADMIN') && !$this->app['securilex']->isGranted('approval', $project)) {
            throw new AccessDeniedException();
        }
        $this->addEntityCrumb($project);
        $this->addEditCrumb($this->app->entity_path('project_edit', $project));
        return $this->edit_or_create($request->request, $project);
    }

    protected function edit_or_create(ParameterBag $post,
                                      \Renogen\Entity\Project $project = null)
    {
        $context = array();
        if ($post->count() > 0) {
            if ($project && $post->get('_action') == 'Delete') {
                $this->app['datastore']->deleteEntity($project);
                $this->app['datastore']->commit();
                $this->app->addFlashMessage("Project '$project->title' has been deleted");
                return $this->app->params_redirect('home');
            }
            if (!$project) {
                $project     = new \Renogen\Entity\Project();
                $nuser       = new \Renogen\Entity\UserProject($project, $this->app->userEntity());
                $nuser->role = 'approval';
                $project->userProjects->add($nuser);
            }
            $project->categories = (($categories          = trim($post->get('categories')))
                    ? explode("\n", str_replace("\r\n", "\n", $categories)) : null);
            $project->modules    = (($modules             = trim($post->get('modules')))
                    ? explode("\n", str_replace("\r\n", "\n", $modules)) : null);
            $project->private    = $post->get('private', false);
            if ($this->app['datastore']->prepareValidateEntity($project, static::entityFields, $post)) {
                $this->app['datastore']->commit($project);
                $this->app->addFlashMessage("Project '$project->title' has been successfully saved");
                return $this->app->entity_redirect('project_view', $project);
            } else {
                $context['errors'] = $project->errors;
            }
        } else if (!$project) {
            $project = new \Renogen\Entity\Project();
        }

        $context['project'] = $project;
        return $this->render('project_form', $context);
    }
}