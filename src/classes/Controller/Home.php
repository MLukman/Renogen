<?php

namespace Renogen\Controller;

use Renogen\Base\Controller;
use Symfony\Component\HttpFoundation\Request;

class Home extends Controller
{

    public function index(Request $request)
    {
        $projects = $this->app['datastore']->queryMany('\Renogen\Entity\Project', array(
            'archived' => false), array(
            'title' => 'ASC'));
        if (count($projects) == 0 && $this->app['securilex']->isGranted('ROLE_ADMIN')) {
            return $this->app->params_redirect('project_create');
        }
        return $this->render('home', array(
                'projects' => $projects,
        ));
    }

    public function archived(Request $request)
    {
        $this->addCrumb('Archived Projects', $this->app->path('archived'), 'archive');
        $projects = $this->app['datastore']->queryMany('\Renogen\Entity\Project', array(
            'archived' => true), array(
            'title' => 'ASC'));
        return $this->render('archived', array(
                'projects' => $projects,
        ));
    }
}