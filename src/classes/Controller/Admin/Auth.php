<?php

namespace Renogen\Controller\Admin;

use Renogen\Base\RenoController;
use Renogen\Entity\AuthDriver;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

class Auth extends RenoController
{

    public function index(Request $request)
    {
        $this->addCrumb('Authentication', $this->app->path('admin_auth'), 'lock');
        return $this->render('admin_auth_list', array('drivers' => $this->app['datastore']->queryMany('\Renogen\Entity\AuthDriver')));
    }

    public function create(Request $request)
    {
        $this->addCrumb('Authentication', $this->app->path('admin_auth'), 'lock');
        $this->addCreateCrumb('Add new authentication', $this->app->path('admin_auth_add'));
        return $this->edit_or_create(new AuthDriver(), $request->request);
    }

    public function edit(Request $request, $driver)
    {
        if ($driver == 'password') {
            $this->app->addFlashMessage("Authentication 'password' cannot be edited");
            return $this->app->params_redirect('admin_auth');
        }
        $this->addCrumb('Authentication', $this->app->path('admin_auth'), 'lock');
        $this->addEditCrumb($this->app->path('admin_auth_edit', array('driver' => $driver)));
        $auth = $this->app['datastore']->queryOne('\Renogen\Entity\AuthDriver', $driver);
        return $this->edit_or_create($auth, $request->request);
    }

    protected function edit_or_create(AuthDriver $auth, ParameterBag $post)
    {
        $errors = array();
        $ds = $this->app['datastore'];
        if ($post->count() > 0) {
            if ($post->get('_action') == 'Delete') {
                $ds->deleteEntity($auth);
                $ds->commit();
                $this->app->addFlashMessage("Authentication '$auth->name' has been deleted");
                return $this->app->params_redirect('admin_auth');
            }
            if (!$post->has('parameters')) {
                $post->set('parameters', array());
            }
            if (!$ds->prepareValidateEntity($auth, array('name', 'class', 'parameters'), $post)) {
                $errors = $auth->errors;
            }
            if (class_exists($auth->class) &&
                ($p_errors = call_user_func(array($auth->class, 'checkParams'), $auth->parameters))) {
                $errors['parameters'] = $p_errors;
            }
            if (empty($errors)) {
                $ds->commit($auth);
                return $this->app->params_redirect('admin_auth');
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