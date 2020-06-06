<?php

namespace Renogen\Controller;

use Renogen\Base\RenoController;
use Renogen\Entity\UserProject;
use Renogen\Exception\NoResultException;
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
        try {
            $project = $this->app['datastore']->fetchProject($project);
            $this->checkAccess('any', $project);
            $this->addEntityCrumb($project);
            return $this->render('project_view', array(
                    'project' => $project
            ));
        } catch (NoResultException $ex) {
            return $this->errorPage('Project not found', $ex->getMessage());
        }
    }

    public function past(Request $request, $project)
    {
        try {
            $project = $this->app['datastore']->fetchProject($project);
            $this->checkAccess(array('view', 'execute', 'entry', 'review', 'approval'), $project);
            $this->addEntityCrumb($project);
            $this->addCrumb('Past deployments', $this->app->entity_path('project_past', $project), 'clock');
            return $this->render('project_past', array(
                    'project' => $project
            ));
        } catch (NoResultException $ex) {
            return $this->errorPage('Project not found', $ex->getMessage());
        }
    }

    public function users(Request $request, $project)
    {
        try {
            $project = $this->app['datastore']->fetchProject($project);
            $this->checkAccess(array('approval', 'ROLE_ADMIN'), $project);

            if ($request->request->get('_action')) {
                foreach ($request->request->get('role', array()) as $username => $role) {
                    try {
                        $project_role = $project->userProjects->containsKey($username)
                                ? $project->userProjects->get($username) : null;
                        if ($role) {
                            if (!$project_role) {
                                $project_role = new UserProject($project, $this->app['datastore']->fetchUser($username));
                                $this->app['datastore']->manage($project_role);
                            }
                            $project_role->role = $role;
                        } else {
                            if ($project_role) {
                                $this->app['datastore']->deleteEntity($project_role);
                            }
                        }
                    } catch (Exception $e) {
                        continue;
                    }
                }
                $this->app['datastore']->commit();
                return $this->app->entity_redirect('project_users', $project);
            }

            $this->addEntityCrumb($project);
            $this->addCrumb('Users', $this->app->entity_path('project_users', $project), 'users');
            return $this->render('project_users', array(
                    'project' => $project,
                    'users' => $this->app['datastore']->queryMany('\Renogen\Entity\User'),
            ));
        } catch (NoResultException $ex) {
            return $this->errorPage('Project not found', $ex->getMessage());
        }
    }

    public function edit(Request $request, $project)
    {
        try {
            $project = $this->app['datastore']->fetchProject($project);
            if (!$this->app['securilex']->isGranted('ROLE_ADMIN') && !$this->app['securilex']->isGranted('approval', $project)) {
                throw new AccessDeniedException();
            }
            $this->addEntityCrumb($project);
            $this->addEditCrumb($this->app->entity_path('project_edit', $project));
            return $this->edit_or_create($request->request, $project);
        } catch (NoResultException $ex) {
            return $this->errorPage('Project not found', $ex->getMessage());
        }
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
            if ($project && $post->get('_action') == 'Archive') {
                $project->archived = true;
                $this->app['datastore']->commit($project);
                $this->app->addFlashMessage("Project '$project->title' has been archived");
                return $this->app->redirect($this->app->path('archived'));
            }
            if ($project && $post->get('_action') == 'Unarchive') {
                $project->archived = false;
                $this->app['datastore']->commit($project);
                $this->app->addFlashMessage("Project '$project->title' has been unarchived");
                return $this->app->entity_redirect('project_view', $project);
            }
            if (!$project) {
                $project = new \Renogen\Entity\Project();
                $nuser = new UserProject($project, $this->app->userEntity());
                $nuser->role = 'approval';
                $project->userProjects->add($nuser);
            }

            $multiline2array = function($multiline) {
                return empty($multiline) ? null : explode("\n", str_replace("\r\n", "\n", $multiline));
            };
            $project->categories = $multiline2array(trim($post->get('categories')));
            $project->modules = $multiline2array(trim($post->get('modules')));
            $project->checklist_templates = $multiline2array(trim($post->get('checklist_templates')));
            $project->private = $post->get('private', false);
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