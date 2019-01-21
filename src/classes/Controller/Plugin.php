<?php

namespace Renogen\Controller;

use Renogen\Base\RenoController;
use Renogen\Exception\NoResultException;
use Symfony\Component\HttpFoundation\Request;

class Plugin extends RenoController
{

    public function index(Request $request, $project)
    {
        try {
            $project_obj = $this->app['datastore']->fetchProject($project);
            $this->addEntityCrumb($project_obj);
            $this->addCrumb('Plugins', $this->app->entity_path('plugin_index', $project_obj), 'plug');

            $plugins = array();
            foreach (glob(ROOTDIR.'/src/classes/Plugin/*', GLOB_ONLYDIR) as $plugin) {
                $plugin           = basename($plugin);
                $pclass           = "\\Renogen\\Plugin\\$plugin\\Core";
                $plugins[$pclass] = array(
                    'name' => $plugin,
                    'title' => $pclass::getTitle(),
                );
            }
            return $this->render('plugin_index', array(
                    'project' => $project_obj,
                    'pluginClasses' => $plugins,
            ));
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    public function view(Request $request, $project, $plugin)
    {
        try {
            $project_obj = $this->app['datastore']->fetchProject($project);
            $plugin_obj  = $this->app['datastore']->queryOne('\Renogen\Entity\Plugin', array(
                'project' => $project,
                'name' => $plugin,
            ));

            $context = array(
                'project' => $project_obj,
                'plugin' => array(
                    'name' => null,
                    'title' => null,
                ),
            );

            $plugin_instance = null;
            if ($plugin_obj) {
                $plugin_instance = $plugin_obj->instance();
            }

            if (empty($context['plugin']['name'])) {
                throw new NoResultException("No such plugin '$plugin'");
            }

            $this->addEntityCrumb($project_obj);
            $this->addCrumb('Plugins', $this->app->entity_path('plugin_index', $project_obj), 'plug');
            $this->addCrumb($context['plugin']['title'], $this->app->entity_path('plugin_view', $project_obj, array(
                    'plugin' => $context['plugin']['name'])), 'plug');
            return $this->render('plugin_view', $context);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }
}