<?php

namespace Renogen\Base;

use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\RedirectResponse;

abstract class Controller
{
    /**
     *
     * @var string
     */
    public $title = null;

    /**
     * @var \Renogen\Application
     */
    protected $app = null;

    /**
     * Base context
     * @var array
     */
    protected $basectx = array(
        'extra_js' => array(),
        'extra_css' => array(),
    );

    public function __construct(\Renogen\Application $app)
    {
        $this->app                   = $app;
        $this->app['controller']     = $this;
        $this->basectx['controller'] = $this;
        $this->basectx['crumbs']     = array();
        if (empty($this->title)) {
            $reflect     = new \ReflectionClass($this);
            $this->title = $reflect->getShortName();
        }
    }

    public function render($view, $context = array())
    {
        $this->title .= ' :: '.$this->app->title();
        return $this->app['twig']->render("$view.twig", array_merge($this->basectx, $context));
    }

    public function addJS($file, $tag = null)
    {
        $this->basectx['extra_js'][$tag ?: $file] = $this->relativizeFile($file);
    }

    public function addCSS($file, $tag = null)
    {
        $this->basectx['extra_css'][$tag ?: $file] = $this->relativizeFile($file);
    }

    public function addCrumb($text, $url, $icon = null)
    {
        $this->basectx['crumbs'][] = array(
            'text' => $text,
            'url' => $url,
            'icon' => $icon,
        );
    }

    protected function relativizeFile($file)
    {
        if (substr($file, 0, 7) !== "http://" && substr($file, 0, 1) !== "/") {
            $file = $this->request->getBaseUrl().'/'.$file;
        }
        return $file;
    }

    public function queryOne($entity, $id_or_criteria)
    {
        if (empty($id_or_criteria)) {
            return null;
        }

        $repo = $this->em->getRepository($entity);
        return (is_array($id_or_criteria) ?
            $repo->findOneBy($id_or_criteria) :
            $repo->find($id_or_criteria));
    }

    public function queryMany($entity, Array $criteria = array(),
                              Array $sort = array())
    {
        $repo = $this->em->getRepository($entity);
        return $repo->findBy($criteria, $sort);
    }

    public function redirect($path, Array $params = array())
    {
        return new RedirectResponse($this->app->path($path, $params));
    }

    public function errorPage($title, $message)
    {
        return $this->render('error', array(
                'error' => array(
                    'title' => $title,
                    'message' => $message,
                )
        ));
    }

    public function __get($name)
    {
        return $this->app[$name];
    }
}