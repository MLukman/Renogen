<?php

namespace Renogen\Base;

use Renogen\App;

abstract class Controller
{
    /**
     *
     * @var string
     */
    public $title = null;

    /**
     * @var App
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

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->app['controller'] = $this;
        $this->basectx['controller'] = $this;
        $this->basectx['errors'] = array();
        $this->basectx['crumbs'] = array();
        if (empty($this->title)) {
            $reflect = new \ReflectionClass($this);
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

    public function addCrumb($text, $url, $icon = null, $hide_on_mobile = false)
    {
        $this->basectx['crumbs'][] = array(
            'text' => $text,
            'url' => $url,
            'icon' => $icon,
            'hide_on_mobile' => $hide_on_mobile,
        );
    }

    protected function relativizeFile($file)
    {
        if (substr($file, 0, 7) !== "http://" && substr($file, 0, 1) !== "/") {
            $file = $this->request->getBaseUrl().'/'.$file;
        }
        return $file;
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