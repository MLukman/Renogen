<?php

namespace Renogen\Controller;

use Renogen\Base\RenoController;
use Renogen\Exception\NoResultException;
use Symfony\Component\HttpFoundation\Request;

class Runbook extends RenoController
{

    public function view(Request $request, $project, $deployment)
    {
        try {
            $deployment_obj = $this->app['datastore']->fetchDeployment($project, $deployment);
            if (is_string($deployment) && $deployment != $deployment_obj->datetimeString()) {
                return $this->app->entity_redirect('runbook_view', $deployment_obj);
            }
            $this->checkAccess(array('approval', 'review', 'execute'), $deployment_obj->project);
            $this->addEntityCrumb($deployment_obj);
            $this->addCrumb('Run Book', $this->app->entity_path('runbook_view', $deployment_obj), 'checkmark box');
            return $this->render('runbook_view', array(
                    'deployment' => $deployment_obj,
            ));
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    public function download_file(Request $request, $file)
    {
        try {
            if (!($activity_file = $this->app['datastore']->queryOne('\Renogen\Entity\RunItemFile', $file))) {
                throw new NoResultException("No such run item file with id '$file'");
            }
            return $activity_file->returnDownload();
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    public function runitem_completed(Request $request, $runitem)
    {
        try {
            if (!($runitem = $this->app['datastore']->queryOne('\Renogen\Entity\RunItem', $runitem))) {
                throw new NoResultException("No such run item with id '$file'");
            }
            $runitem->status = 'Completed';
            $runitem->defaultUpdatedDate();
            $this->app['datastore']->commit($runitem);
            foreach ($runitem->deployment->items as $item) {
                if ($item->activities->count() == 0) {
                    continue;
                }
                foreach ($item->activities as $activity) {
                    if ($activity->runitem->status != 'Completed') {
                        continue 2;
                    }
                }
                $item->changeStatus('Completed');
                $this->app['datastore']->commit($item);
            }
            return $this->app->entity_redirect('runbook_view', $runitem->deployment, $runitem->id);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    public function runitem_failed(Request $request, $runitem)
    {
        try {
            if (!($runitem = $this->app['datastore']->queryOne('\Renogen\Entity\RunItem', $runitem))) {
                throw new NoResultException("No such run item with id '$file'");
            }
            $runitem->status = 'Failed';
            $runitem->defaultUpdatedDate();
            $this->app['datastore']->commit($runitem);
            foreach ($runitem->activities as $activity) {
                $activity->item->changeStatus('Failed');
                $this->app['datastore']->commit($activity->item);
            }
            return $this->app->entity_redirect('runbook_view', $runitem->deployment, $runitem->id);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }
}