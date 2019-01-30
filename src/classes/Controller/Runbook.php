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

    public function runitem_update(Request $request, $runitem)
    {
        try {
            if (!($runitem = $this->app['datastore']->queryOne('\Renogen\Entity\RunItem', $runitem))) {
                throw new NoResultException("No such run item with id '$file'");
            }
            if (($status = $request->request->get('new_status'))) {
                $runitem->status = $status;
                $runitem->defaultUpdatedDate();
                $this->app['datastore']->commit($runitem);
                $remark          = $request->request->get('remark');

                switch ($status) {
                    case 'Completed':
                        foreach ($runitem->activities as $activity) {
                            if ($activity->item->status == $status) {
                                continue;
                            }
                            foreach ($activity->item->activities as $item_activity) {
                                if ($item_activity->runitem->status != $status) {
                                    continue 2;
                                }
                            }
                            $old_status = $activity->item->status;
                            $activity->item->changeStatus($status, $remark);
                            $this->app['datastore']->commit($activity->item);
                        }
                        break;
                    case 'Failed':
                        foreach ($runitem->activities as $activity) {
                            if ($activity->item->status == $status) {
                                continue;
                            }
                            $old_status = $activity->item->status;
                            $activity->item->changeStatus($status, $remark);
                            $this->app['datastore']->commit($activity->item);
                        }
                        break;
                }
            }
            return $this->app->entity_redirect('runbook_view', $runitem->deployment, $runitem->id);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }
}