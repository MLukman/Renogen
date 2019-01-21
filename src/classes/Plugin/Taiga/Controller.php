<?php

namespace Renogen\Plugin\Taiga;

use DateInterval;
use DateTime;
use DateTimeZone;
use Renogen\Entity\Deployment;
use Renogen\Entity\Project;
use Renogen\Entity\User;
use Renogen\Plugin\PluginController;
use Renogen\Plugin\PluginCore;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use function random_bytes;

class Controller extends PluginController
{

    public function handleAction(Request $request, Project $project,
                                 PluginCore &$pluginCore, $action)
    {
        switch ($action) {
            case 'webhook':
                $payload = json_decode($request->getContent(), true);
                switch ($payload['type']) {
                    case 'milestone':
                        return $this->handleWebhookDeployment($project, $pluginCore, $payload);
                    case 'userstory':
                        return $this->handleWebhookItem($project, $pluginCore, $payload);
                }
                return new JsonResponse(array(
                    'status' => 'success',
                ));
        }
    }

    protected function handleWebhookDeployment(Project $project,
                                               PluginCore &$pluginCore, $payload)
    {
        $errors = null;
        $fields = array('title', 'execute_date');
        switch ($payload['action']) {
            case 'create':
                $nd                       = new Deployment($project);
                $nd->title                = $payload['data']['name'];
                $nd->execute_date         = $this->makeDeploymentDate($payload['data']['estimated_finish']);
                $nd->plugin_data['Taiga'] = array(
                    'id' => $payload['data']['id'],
                );
                $nd->created_by           = $this->taigaUser();
                if ($nd->validate($this->app['em'])) {
                    $this->app['datastore']->commit($nd);
                } else {
                    $errors = $nd->errors;
                }
                break;

            case 'change':
                if (!($deployment = $this->findDeploymentWithTaigaId($project, $payload['data']['id']))) {
                    break;
                }
                $parameters = new ParameterBag(array('title' => $payload['data']['name']));
                if (isset($payload['change']['diff']['estimated_date'])) {
                    $parameters->set('execute_date', $this->makeDeploymentDate($payload['data']['estimated_finish']));
                }
                if ($this->app['datastore']->prepareValidateEntity($deployment, $fields, $parameters)) {
                    $deployment->updated_by   = $this->app['datastore']->queryOne('\\Renogen\\Entity\\User', 'taiga');
                    $deployment->updated_date = new \DateTime();
                    $this->app['datastore']->commit($deployment);
                } else {
                    $errors = $nd->errors;
                }
                break;
        }
        return new JsonResponse(array(
            'status' => empty($errors) ? 'success' : 'error',
            'errors' => $errors,
        ));
    }

    protected function handleWebhookItem(Project $project,
                                         PluginCore &$pluginCore, $payload)
    {
        if (empty($payload['data']['milestone'])) {
            // do not process user story without milestone
            return;
        }
        if (!($d_deployment = $this->findDeploymentWithTaigaId($project, $payload['data']['milestone']['id']))) {
            // do not process the milestone was not integrated into Renogen
            return;
        }
        $d_item = null;
        foreach ($project->deployments as $deployment) {
            foreach ($deployment->items as $item) {
                if (isset($item->plugin_data['Taiga']['id']) && $item->plugin_data['Taiga']['id']
                    == $payload['data']['id']) {
                    $d_item = $item;
                    break 2;
                }
            }
        }

        $errors     = null;
        $parameters = new ParameterBag(array(
            'title' => $payload['data']['subject'],
        ));

        if (!$d_item) {
            $d_item               = new \Renogen\Entity\Item($d_deployment);
            $d_item->created_by   = $this->taigaUser();
            $d_item->created_date = new \DateTime();
            $parameters->set('category', 'N/A');
            $parameters->set('modules', array('N/A'));
        } else {
            $d_item->deployment   = $d_deployment;
            $d_item->updated_by   = $this->taigaUser();
            $d_item->updated_date = new \DateTime();
        }

        if ($payload['action'] == 'delete') {
            $this->app['datastore']->deleteEntity($d_item);
            $this->app['datastore']->commit();
        } else {
            $d_item->plugin_data['Taiga'] = array(
                'id' => $payload['data']['id'],
            );
            if ($this->app['datastore']->prepareValidateEntity($d_item, $parameters->keys(), $parameters)) {
                $this->app['datastore']->commit($d_item);
            } else {
                $errors = $d_item->errors;
            }
        }
        return new JsonResponse(array(
            'status' => empty($errors) ? 'success' : 'error',
            'errors' => $errors,
        ));
    }

    public function handleConfigure(Request $request, Project $project,
                                    PluginCore &$pluginCore)
    {
        if (!$this->app['datastore']->queryOne('\\Renogen\\Entity\\User', 'taiga')) {
            $taiga            = new User();
            $taiga->username  = 'taiga';
            $taiga->shortname = 'Taiga';
            $taiga->roles     = array('ROLE_NONE');
            $taiga->auth      = 'password';
            $taiga->password  = md5(random_bytes(100));
            $this->app['datastore']->commit($taiga);
        }
        $this->savePlugin();
        return $this->render('configure');
    }

    public static function availableActions()
    {
        return array(
            'webhook' => array(
                'public' => true,
            ),
        );
    }

    protected function findDeploymentWithTaigaId(Project $project, $id)
    {
        foreach ($project->deployments as $deployment) {
            if (isset($deployment->plugin_data['Taiga']['id']) && $deployment->plugin_data['Taiga']['id']
                == $id) {
                return $deployment;
            }
        }
        return null;
    }

    protected function makeDeploymentDate($string)
    {
        $execute_date = DateTime::createFromFormat('Y-m-d', $string, new DateTimeZone('UTC'));
        $execute_date->setTime(0, 0, 0);
        $execute_date->add(new DateInterval('P1D'));
        return $execute_date;
    }

    protected function taigaUser()
    {
        return $this->app['datastore']->queryOne('\\Renogen\\Entity\\User', 'taiga');
    }
}