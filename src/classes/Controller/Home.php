<?php

namespace Renogen\Controller;

use Renogen\Base\Controller;
use Symfony\Component\HttpFoundation\Request;

class Home extends Controller
{

    public function index(Request $request)
    {
        $projects = $this->app['datastore']->queryMany('\Renogen\Entity\Project', array(), array(
            'created_date' => 'ASC'));
        if (count($projects) == 0 && $this->app['securilex']->isGranted('ROLE_ADMIN')) {
            return $this->app->redirect('project_create');
        }
        return $this->render('home', array(
                'projects' => $projects,
        ));
    }
}