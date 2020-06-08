<?php

namespace Renogen\Controller;

use Renogen\Base\RenoController;
use Renogen\Entity\Checklist as ChecklistEntity;
use Renogen\Exception\NoResultException;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class Checklist extends RenoController
{
    const entityFields = array('title', 'start_datetime', 'end_datetime', 'status');

    public function create(Request $request, $project, $deployment)
    {
        try {
            $deployment_obj = $this->app['datastore']->fetchDeployment($project, $deployment);
            $this->checkAccess(array('entry', 'approval'), $deployment_obj);
            $this->addEntityCrumb($deployment_obj);
            $this->addCreateCrumb('Add checklist task', $this->app->entity_path('checklist_create', $deployment_obj));
            $checklist = new ChecklistEntity($deployment_obj);
            $checklist->pics->add($this->app->userEntity());
            return $this->edit_or_create($checklist, $request->request);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    public function edit(Request $request, $project, $deployment, $checklist)
    {
        try {
            $checklist_obj = $this->app['datastore']->fetchChecklist($project, $deployment, $checklist);
            if (!$checklist_obj->isUsernameAllowed($this->app->userEntity()->username, 'edit')) {
                throw new AccessDeniedException();
            }
            $this->addEntityCrumb($checklist_obj);
            return $this->edit_or_create($checklist_obj, $request->request);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    protected function edit_or_create(ChecklistEntity $checklist,
                                      ParameterBag $post)
    {
        $context = array();
        $ds = $this->app['datastore'];
        if ($post->count() > 0) {
            switch ($post->get('_action')) {
                case 'Delete':
                    $ds->deleteEntity($checklist);
                    $ds->commit();
                    $this->app->addFlashMessage("Checklist task '$checklist->title' has been deleted");
                    return $this->app->entity_redirect('deployment_view', $checklist->deployment, 'checklist');

                default:
                    $checklist->pics->clear();
                    if ($post->has('pics')) {
                        foreach ($post->get('pics') as $username) {
                            $checklist->pics->add($this->app['datastore']->fetchUser($username));
                        }
                    } else {
                        $checklist->errors['pics'][] = 'Required';
                    }
                    if (empty($post->get('title'))) {
                        $post->set('title', $post->get('template'));
                    }
                    if ($ds->prepareValidateEntity($checklist, static::entityFields, $post)) {
                        $ds->commit($checklist);
                        $this->app->addFlashMessage("Checklist task '$checklist->title' has been successfully saved");
                        return $this->app->entity_redirect('deployment_view', $checklist->deployment, 'checklist');
                    } else {
                        $context['errors'] = $checklist->errors;
                    }
            }
        }
        $context['checklist'] = $checklist;
        $context['project'] = $checklist->deployment->project;
        return $this->render('checklist_form', $context);
    }
}