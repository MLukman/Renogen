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
            $this->checkAccess(array('approval', 'ROLE_ADMIN'), $project_obj);
            $this->addEntityCrumb($project_obj);
            $this->addCrumb('Plugins', $this->app->entity_path('plugin_index', $project_obj), 'plug');

            $plugins = array();
            foreach (glob(ROOTDIR.'/src/classes/Plugin/*', GLOB_ONLYDIR) as $plugin) {
                $plugin           = basename($plugin);
                $pclass           = "\\Renogen\\Plugin\\$plugin\\Core";
                $plugins[$pclass] = array(
                    'name' => $plugin,
                    'title' => $pclass::getTitle(),
                    'icon' => $pclass::getIcon(),
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
}