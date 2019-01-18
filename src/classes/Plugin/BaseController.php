<?php

namespace Renogen\Plugin;

use ReflectionClass;
use Renogen\Base\RenoController;
use Renogen\Entity\Project;
use Renogen\Exception\NoResultException;
use Symfony\Component\HttpFoundation\Request;

abstract class BaseController extends RenoController
{
    protected $project;
    protected $pluginCore;

    abstract function handleConfigure(Request $request, Project $project,
                                      BaseCore &$pluginCore);

    public function getName()
    {
        $reflection = new ReflectionClass($this);
        return basename(dirname($reflection->getFileName()));
    }

    public function configure(Request $request, $project)
    {
        $pname = $this->getName();
        try {
            $this->project = $this->app['datastore']->fetchProject($project);
            $this->addEntityCrumb($this->project);
            $plugin        = $this->app['datastore']->queryOne('\Renogen\Entity\Plugin', array(
                'project' => $this->project,
                'name' => $this->getName(),
            ));

            if ($plugin) {
                $this->pluginCore = $plugin->instance();
            } else {
                $pclass           = "\\Renogen\\Plugin\\$pname\\Core";
                $this->pluginCore = new $pclass(array());
            }

            $this->addCrumb($this->getTitle(), $this->app->path("plugin_{$pname}_configure", array(
                    'project' => $project)), $this->pluginCore->getIcon());

            return $this->handleConfigure($request, $this->project, $this->pluginCore);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    public function render($view, $context = array())
    {
        $pname = $this->getName();
        return parent::render("@plugin/$pname/$view", array_merge_recursive(array(
                'project' => $this->project,
                'core' => $this->pluginCore,
                'plugin' => array(
                    'name' => $pname,
                    'icon' => $this->pluginCore->getIcon(),
                    'title' => $this->pluginCore->getPluginTitle(),
                )), $context));
    }

    protected function savePlugin()
    {
        $plugin          = $this->app['datastore']->queryOne('\Renogen\Entity\Plugin', array(
                'project' => $this->project,
                'name' => $this->getName(),
            )) ?: new \Renogen\Entity\Plugin($this->project, $this->pluginCore);
        $plugin->options = $this->pluginCore->getOptions();
        $this->app['datastore']->commit($plugin);
    }

    protected function deletePlugin()
    {
        $plugin = $this->app['datastore']->queryOne('\Renogen\Entity\Plugin', array(
            'project' => $this->project,
            'name' => $this->getName(),
        ));
        if ($plugin) {
            $this->app['datastore']->deleteEntity($plugin);
            $this->app['datastore']->commit();
        }
    }
}