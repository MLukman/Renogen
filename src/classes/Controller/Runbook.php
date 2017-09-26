<?php

namespace Renogen\Controller;

use Doctrine\ORM\NoResultException;
use Renogen\Base\RenoController;
use Symfony\Component\HttpFoundation\Request;

class Runbook extends RenoController
{

    public function view(Request $request, $project, $deployment)
    {
        try {
            $deployment_obj = $this->fetchDeployment($project, $deployment);
            if (is_string($deployment) && $deployment != $deployment_obj->datetimeString()) {
                return $this->redirect('runbook_view', $this->entityParams($deployment_obj));
            }
            $this->addEntityCrumb($deployment_obj);
            $this->addCrumb('Run Book', $this->app->path('runbook_view', $this->entityParams($deployment_obj)), 'checkmark box');
            return $this->render('runbook_view', array(
                    'deployment' => $deployment_obj,
            ));
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }
}