<?php

namespace Renogen\Controller;

use Exception;
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
        return $this->render('admin_user_list', array('users' => $this->app['datastore']->queryMany('\Renogen\Entity\User')));
    }

    public function user_create(Request $request)
    {
        $this->addCrumb('Users', $this->app->path('admin_users'), 'users');
        $this->addCreateCrumb('Add user', $this->app->path('admin_user_add'));
        return $this->edit_or_create_user(new User(), $request->request);
    }

    public function user_edit(Request $request, $username)
    {
        $user = $this->app['datastore']->queryOne('\Renogen\Entity\User', $username);
        $this->addCrumb('Users', $this->app->path('admin_users'), 'users');
        $this->addEditCrumb($this->app->path('admin_user_edit', array('username' => $username)));
        return $this->edit_or_create_user($user, $request->request);
    }

    protected function edit_or_create_user(User $user, ParameterBag $post)
    {
        $errors = array();
        if ($post->count() > 0) {
            if ($post->get('_action') == 'Delete') {
                $this->app['datastore']->deleteEntity($user);
                $this->app['datastore']->commit();
                $this->app->addFlashMessage("User '$user->username' has been deleted");
                return $this->redirect('admin_users');
            }
            if (!$post->has('roles')) {
                $post->set('roles', array());
            }
            if ($this->app['datastore']->prepareValidateEntity($user, array('auth',
                    'username', 'shortname', 'roles'), $post)) {
                $this->app['datastore']->commit($user);
                foreach ($post->get('project_role', array()) as $project_name => $role) {
                    try {
                        $project      = $this->app['datastore']->fetchProject($project_name);
                        $project_role = $project->userProjects->containsKey($user->username)
                                ? $project->userProjects->get($user->username) : null;
                        if ($role == 'none') {
                            if ($project_role) {
                                $this->app['datastore']->deleteEntity($project_role);
                            }
                        } else {
                            if (!$project_role) {
                                $project_role = new UserProject($project, $user);
                            }
                            $project_role->role = $role;
                            $this->app['datastore']->commit($project_role);
                        }
                    } catch (Exception $e) {
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
                'auths' => $this->app['datastore']->queryMany('\Renogen\Entity\AuthDriver'),
                'projects' => $this->app['datastore']->queryMany('\Renogen\Entity\Project'),
                'errors' => $errors,
        ));
    }

    public function auth(Request $request)
    {
        $this->addCrumb('Authentication', $this->app->path('admin_auth'), 'lock');
        return $this->render('admin_auth_list', array('drivers' => $this->app['datastore']->queryMany('\Renogen\Entity\AuthDriver')));
    }

    public function auth_edit(Request $request, $driver)
    {
        $this->addCrumb('Authentication', $this->app->path('admin_auth'), 'lock');
        $this->addEditCrumb($this->app->path('admin_auth_edit', array('driver' => $driver)));
        $auth = $this->app['datastore']->queryOne('\Renogen\Entity\AuthDriver', $driver);
        return $this->edit_or_create_auth($auth, $request->request);
    }

    protected function edit_or_create_auth(\Renogen\Entity\AuthDriver $auth,
                                           ParameterBag $post)
    {
        $errors = array();
        if ($post->count() > 0) {
            if (!$post->has('parameters')) {
                $post->set('parameters', array());
            }
            if ($this->app['datastore']->prepareValidateEntity($auth, array('name',
                    'class', 'parameters'), $post)) {
                if (class_exists($auth->class)) {
                    $p_errors = call_user_func(array($auth->class, 'checkParams'), $auth->parameters);
                    if (empty($p_errors)) {
                        $this->app['datastore']->commit($auth);
                        return $this->redirect('admin_auth');
                    }
                    $errors['parameters'] = $p_errors;
                }
            }
        }
        return $this->render('admin_auth_form', array(
                'auth' => $auth,
                'classes' => $this->app->getAuthClassNames(),
                'paramConfigs' => ($auth->class ? call_user_func(array($auth->class,
                    'getParamConfigs')) : null),
                'errors' => $errors,
        ));
    }
}