<?php

namespace Renogen\Controller;

class Home extends \Renogen\Base\Controller
{

    public function index(\Symfony\Component\HttpFoundation\Request $request)
    {
        return $this->render('home', array(
                'projects' => $this->queryMany('\Renogen\Entity\Project'),
        ));
    }
}