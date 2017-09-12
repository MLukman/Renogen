<?php

namespace Renogen\Controller;

use Renogen\Base\RenoController;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

class Project extends RenoController
{
    const entityFields = array('name', 'title', 'description');

    public function create(Request $request)
    {
        $this->addCreateCrumb('Create project', $this->app->path('project_create'));
        return $this->edit_or_create(new \Renogen\Entity\Project(), $request->request);
    }

    public function view(Request $request, $project)
    {
        if (!($project instanceof \Renogen\Entity\Project)) {
            $name    = $project;
            if (!($project = $this->queryOne('\Renogen\Entity\Project', array('name' => $name)))) {
                return $this->errorPage('Project not found', "There is not such project with name '$name'");
            }
        }
        $this->addEntityCrumb($project);
        return $this->render('project_view', array(
                'project' => $project
        ));
    }

    public function edit(Request $request, $project)
    {
        if (!($project instanceof \Renogen\Entity\Project)) {
            $name    = $project;
            if (!($project = $this->queryOne('\Renogen\Entity\Project', array('name' => $name)))) {
                return $this->errorPage('Project not found', "There is not such project with name '$name'");
            }
        }
        $this->addEntityCrumb($project);
        $this->addEditCrumb($this->app->path('project_edit', $this->entityParams($project)));
        return $this->edit_or_create($project, $request->request, array('project' => $project));
    }

    protected function edit_or_create(\Renogen\Entity\Project $project,
                                      ParameterBag $post,
                                      array $context = array())
    {
        if ($post->count() > 0) {
            if ($post->get('_action') == 'Delete') {
                $this->app['em']->remove($project);
                $this->app['em']->flush();
                $this->app->addFlashMessage("Project '$project->title' has been deleted");
                return $this->redirect('home');
            }
            $context['project']  = $project;
            $categories          = trim($post->get('categories'));
            $project->categories = ($categories ?
                explode("\n", str_replace("\r\n", "\n", $categories)) : null);
            if ($this->saveEntity($project, static::entityFields, $post)) {
                $this->app->addFlashMessage("Project '$project->title' has been successfully saved");
                return $this->redirect('project_view', $this->entityParams($project));
            } else {
                $context['errors'] = $project->errors;
            }
        }
        return $this->render('project_form', $context);
    }
}