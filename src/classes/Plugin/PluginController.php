<?php

namespace Renogen\Plugin;

use ReflectionClass;
use Renogen\Base\RenoController;
use Renogen\Entity\Project;
use Renogen\Exception\NoResultException;
use Symfony\Component\HttpFoundation\Request;

abstract class PluginController extends RenoController
{
    protected $project;
    protected $pluginCore;

    abstract function handleConfigure(Request $request, Project $project,
                                      PluginCore &$pluginCore);

    public function getName()
    {
        $reflection = new ReflectionClass($this);
        $coreClass  = $reflection->getNamespaceName().'\\Core';
        return $coreClass::getName();
    }

    public function getTitle()
    {
        $reflection = new ReflectionClass($this);
        $coreClass  = $reflection->getNamespaceName().'\\Core';
        return $coreClass::getTitle();
    }

    public function configure(Request $request, $project)
    {
        $pname = $this->getName();
        try {
            if (!$this->fetchPluginCore($project)) {
                $pclass           = "\\Renogen\\Plugin\\$pname\\Core";
                $this->pluginCore = new $pclass(array());
            }

            $this->addEntityCrumb($this->project);
            $this->addCrumb($this->getName(), $this->app->path("plugin_{$pname}_configure", array(
                    'project' => $project)), $this->pluginCore->getIcon());

            return $this->handleConfigure($request, $this->project, $this->pluginCore);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    static function availableActions()
    {
        return array();
    }

    public function action(Request $request, $project, $action)
    {
        $pname = $this->getName();
        if (!in_array($action, array_keys(static::availableActions()))) {
            throw new NoResultException("Plugin '$pname' does not support action '$action'");
        }
        if (!$this->fetchPluginCore($project)) {
            throw new NoResultException("Project '$project' does not have plugin named '$pname'");
        }
        return $this->handleAction($request, $this->project, $this->pluginCore, $action);
    }

    abstract function handleAction(Request $request, Project $project,
                                   PluginCore &$pluginCore, $action);

    protected function fetchPluginCore($project)
    {
        $pname         = $this->getName();
        $this->project = $this->app['datastore']->fetchProject($project);
        $plugin        = $this->app['datastore']->queryOne('\\Renogen\\Entity\\Plugin', array(
            'project' => $this->project,
            'name' => $pname,
        ));
        if ($plugin) {
            $this->pluginCore = $plugin->instance();
            return $this->pluginCore;
        } else {
            return null;
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
                    'title' => $this->pluginCore->getTitle(),
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