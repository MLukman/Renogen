<?php

namespace Renogen\Plugin\Taiga;

use DateTime;
use DateTimeZone;
use Renogen\Entity\Deployment;
use Renogen\Entity\Item;
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
    protected $extract_refnum_patterns     = array(
        '([^\-\s]+)\s*-\s*(.*)' => 'REFNUM - Item title',
        '#([^\-\s]+)\s*\-*\s*(.*)' => '#REFNUM Item title',
        '\[([^\]\s]+)\]\s*\-*\s*(.*)' => '[REFNUM] Item title',
        '\(([^\)\s]+)\)\s*\-*\s*(.*)' => '(REFNUM) Item title',
    );
    protected $deployment_date_adjustments = array(
        '+0 day' => 'Same day',
        '+1 day' => 'Next day',
        '+2 day' => 'The day after next',
        'next monday' => 'The coming Monday',
        'next tuesday' => 'The coming Tuesday',
        'next wednesday' => 'The coming Wednesday',
        'next thursday' => 'The coming Thursday',
        'next friday' => 'The coming Friday',
    );

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
        switch ($payload['action']) {
            case 'create':
            case 'change':
                $parameters = new ParameterBag(array(
                    'title' => $payload['data']['name'],
                    'execute_date' => $this->makeDeploymentDate($pluginCore, $payload['data']['estimated_finish']),
                ));

                if (($nd = $this->findDeploymentWithTaigaId($project, $payload['data']['id']))) {
                    $nd->updated_by   = $this->taigaUser();
                    $nd->updated_date = new \DateTime();
                } else {
                    $nd                       = new Deployment($project);
                    $nd->created_by           = $this->taigaUser();
                    $nd->plugin_data['Taiga'] = array(
                        'id' => $payload['data']['id'],
                    );
                }

                if ($this->app['datastore']->prepareValidateEntity($nd, $parameters->keys(), $parameters)) {
                    $this->app['datastore']->commit($nd);
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
        $d_item = null;
        foreach ($project->deployments as $deployment) {
            foreach ($deployment->items as $item) {
                if (isset($item->plugin_data['Taiga']['id']) && $item->plugin_data['Taiga']['id']
                    == $payload['data']['id']) {
                    $d_item               = $item;
                    $d_item->updated_by   = $this->taigaUser();
                    $d_item->updated_date = new \DateTime();
                    break 2;
                }
            }
        }

        if ($d_item && ($payload['action'] == 'delete' || empty($payload['data']['milestone']))) {
            if ($pluginCore->getOptions('allow_delete_item') && (
                !$pluginCore->getOptions('delete_fresh_item_only') || $d_item->status
                == 'Documentation')) {
                $this->app['datastore']->deleteEntity($d_item);
                $this->app['datastore']->commit();
                return new JsonResponse(array(
                    'status' => 'success',
                    'message' => 'item deleted',
                ));
            } else {
                return new JsonResponse(array(
                    'status' => 'failed',
                    'message' => 'item deletion disabled',
                    ), 405);
            }
        }

        if (empty($payload['data']['milestone'])) {
            // do not process user story without milestone
            return new JsonResponse(array(
                'status' => 'failed',
                'message' => 'milestone not defined',
                ), 400);
        }
        if (!($d_deployment = $this->findDeploymentWithTaigaId($project, $payload['data']['milestone']['id']))) {
            // do not process the milestone was not integrated into Renogen
            return;
        }

        $parameters = new ParameterBag();
        if (!$d_item) {
            $d_item               = new Item($d_deployment);
            $d_item->created_by   = $this->taigaUser();
            $d_item->created_date = new \DateTime();
            $parameters->set('category', 'N/A');
            $parameters->set('modules', array('N/A'));
        } else {
            $d_item->deployment = $d_deployment;
        }

        $d_item->plugin_data['Taiga'] = array(
            'id' => $payload['data']['id'],
        );

        $title   = $payload['data']['subject'];
        $matches = null;
        if (($extract = $pluginCore->getOptions('extract_refnum_from_subject')) && preg_match("/^$extract$/", $title, $matches)) {
            $parameters->set('refnum', $matches[1]);
            $parameters->set('title', $matches[2]);
        } else {
            $parameters->set('title', $title);
            if (empty($d_item->refnum) && ($prefix = $pluginCore->getOptions('auto_refnum_from_id_prefix'))) {
                $id     = $payload['data']['id'];
                $lpad   = intval($pluginCore->getOptions('auto_refnum_from_id_ldap'));
                $refnum = (strlen($id) >= $lpad ? $id : str_repeat('0', $lpad - strlen($id)).$id);
                $parameters->set('refnum', $prefix.$refnum);
            }
        }

        if ($payload['data']['description']) {
            $parameters->set('description', $payload['data']['description']);
        }

        if ($this->app['datastore']->prepareValidateEntity($d_item, $parameters->keys(), $parameters)) {
            $this->app['datastore']->commit($d_item);
        }

        return new JsonResponse(array(
            'status' => empty($errors) ? 'success' : 'error',
            'errors' => $d_item->errors,
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
        if ($request->request->get('_action') == 'Save') {
            $options = $pluginCore->getOptions();
            foreach ($options as $k => $v) {
                $options[$k] = $request->request->get($k, $v);
            }
            $pluginCore->setOptions($options);
        }
        $this->savePlugin();
        return $this->render('configure', array(
                'extract_refnum_patterns' => $this->extract_refnum_patterns,
                'deployment_date_adjustments' => $this->deployment_date_adjustments,
        ));
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

    protected function makeDeploymentDate(PluginCore &$pluginCore, $string)
    {
        $execute_date = DateTime::createFromFormat('Y-m-d', $string, new DateTimeZone('UTC'));

        $adjust_date = $pluginCore->getOptions('deployment_date_adjust');
        if (intval($adjust_date)) {
            $execute_date->modify("+{$adjust_date} days");
        } else {
            $execute_date->modify($adjust_date);
        }

        $matches = null;
        if (preg_match("/^(\\d+):(\\d+) (\\w+)$/", $pluginCore->getOptions('deployment_time'), $matches)) {
            if ($matches[3] == 'PM' && $matches[1] < 12) {
                $matches[1] += 12;
            } elseif (($matches[3] == 'AM' && $matches[1] == 12)) {
                $matches[1] = 0;
            }
            $execute_date->setTime($matches[1], $matches[2], 0);
        } else {
            $execute_date->setTime(0, 0, 0);
        }

        return $execute_date;
    }

    protected function taigaUser()
    {
        return $this->app['datastore']->queryOne('\\Renogen\\Entity\\User', 'taiga');
    }
}