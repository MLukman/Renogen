<?php

namespace Renogen\Controller;

use Renogen\Base\RenoController;
use Renogen\Entity\User;
use Renogen\Entity\UserProject;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

class Admin extends RenoController
{

    public function index(Request $request)
    {

    }

    public function users(Request $request)
    {
        $this->addCrumb('Users', $this->app->path('admin_users'), 'users');
        return $this->render('admin_user_list', array('users' => $this->queryMany('\Renogen\Entity\User')));
    }

    public function user_create(Request $request)
    {
        $this->addCrumb('Users', $this->app->path('admin_users'), 'users');
        $this->addCreateCrumb('Add user', $this->app->path('admin_user_add'));
        return $this->edit_or_create(new User(), $request->request);
    }

    public function user_edit(Request $request, $username)
    {
        $user = $this->queryOne('\Renogen\Entity\User', $username);
        $this->addCrumb('Users', $this->app->path('admin_users'), 'users');
        $this->addEditCrumb($this->app->path('admin_user_edit', array('username' => $username)));
        return $this->edit_or_create($user, $request->request);
    }

    protected function edit_or_create(User $user, ParameterBag $post)
    {
        $errors = array();
        if ($post->count() > 0) {
            if ($post->get('_action') == 'Delete') {
                $has_other_admin = false;
                $deployment->delete($this->app['em']);
                $this->app['em']->flush();
                $this->app->addFlashMessage("Deployment '$deployment->title' has been deleted");
                return $this->redirect('project_view', array(
                        'project' => $deployment->project->name,
                ));
            }
            if (!$post->has('roles')) {
                $post->set('roles', array());
            }
            if ($this->saveEntity($user, array('auth', 'username', 'shortname', 'roles'), $post)) {
                foreach ($post->get('project_role', array()) as $project_name => $role) {
                    try {
                        $project      = $this->fetchProject($project_name);
                        $project_role = $project->userProjects->containsKey($user->username)
                                ? $project->userProjects->get($user->username) : null;
                        if ($role == 'none') {
                            if ($project_role) {
                                $project_role->delete($this->app['em']);
                            }
                        } else {
                            if (!$project_role) {
                                $project_role = new UserProject($project, $user);
                                $this->app['em']->persist($project_role);
                            }
                            $project_role->role = $role;
                        }
                        if ($project_role) {
                            $this->app['em']->flush($project_role);
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }
                return $this->redirect('admin_users');
            } else {
                $errors = $user->errors;
            }
        }

        return $this->render('admin_user_form', array(
                'user' => $user,
                'auths' => $this->queryMany('\Renogen\Entity\AuthDriver'),
                'projects' => $this->queryMany('\Renogen\Entity\Project'),
                'errors' => $errors,
        ));
    }
}