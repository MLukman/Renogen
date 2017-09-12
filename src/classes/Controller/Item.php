<?php

namespace Renogen\Controller;

use Doctrine\ORM\NoResultException;
use Renogen\Base\RenoController;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

class Item extends RenoController
{
    const entityFields = array('refnum', 'title', 'category');

    public function create(Request $request, $project, $deployment)
    {
        try {
            $deployment_obj = $this->fetchDeployment($project, $deployment);
            $this->addEntityCrumb($deployment_obj);
            $this->addCreateCrumb('Add deployment item', $this->app->path('item_create', $this->entityParams($deployment_obj)));
            return $this->edit_or_create(new \Renogen\Entity\Item($deployment_obj), $request->request);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    public function view(Request $request, $project, $deployment, $item)
    {
        try {
            $item_obj = $this->fetchItem($project, $deployment, $item);
            $this->addEntityCrumb($item_obj);
            return $this->render('item_view', array(
                    'item' => $item_obj,
            ));
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    public function edit(Request $request, $project, $deployment, $item)
    {
        try {
            $item_obj = $this->fetchItem($project, $deployment, $item);
            $this->addEntityCrumb($item_obj);
            $this->addEditCrumb($this->app->path('item_edit', $this->entityParams($item_obj)));
            return $this->edit_or_create($item_obj, $request->request);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    protected function edit_or_create(\Renogen\Entity\Item $item,
                                      ParameterBag $post)
    {
        $context = array('item' => $item);
        if ($post->count() > 0) {
            switch ($post->get('_action')) {
                case 'Move':
                    try {
                        $ndeployment = $this->fetchDeployment($item->deployment->project, $post->get('deployment'));
                        if ($ndeployment != $item->deployment) {
                            $data = new ParameterBag(array(
                                'deployment' => $ndeployment,
                                'approved_date' => null,
                                'approved_by' => null,
                                'submitted_date' => null,
                                'submitted_by' => null,
                            ));
                            if ($this->saveEntity($item, $data->keys(), $data)) {
                                $this->app->addFlashMessage("Item '$item->title' has been moved to deployment '".$item->deployment->displayTitle()."'");
                                return $this->redirect('item_view', $this->entityParams($item));
                            }
                        }
                        $context['errors'] = array(
                            'deployment' => array('Please select another deployment')
                        );
                    } catch (NoResultException $ex) {
                        $context['errors'] = array(
                            'deployment' => array('Please select another deployment')
                        );
                    }
                    break;

                case 'Delete':
                    $this->app['em']->remove($item);
                    $this->app['em']->flush();
                    $this->app->addFlashMessage("Item '$item->title' has been deleted");
                    return $this->redirect('deployment_view', $this->entityParams($item->deployment));

                default:
                    if ($this->saveEntity($item, static::entityFields, $post)) {
                        $this->app->addFlashMessage("Item '$item->title' has been successfully saved");
                        return $this->redirect('item_view', $this->entityParams($item));
                    } else {
                        $context['errors'] = $item->errors;
                    }
            }
        }
        return $this->render('item_form', $context);
    }
}