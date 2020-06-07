<?php

namespace Renogen\Controller;

use DateTime;
use Doctrine\Common\Collections\Criteria;
use Renogen\Base\RenoController;
use Renogen\Entity\Item as ItemEntity;
use Renogen\Entity\ItemComment;
use Renogen\Entity\Project;
use Renogen\Entity\RunItem;
use Renogen\Entity\RunItemFile;
use Renogen\Exception\NoResultException;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

class Item extends RenoController
{
    const entityFields = array('refnum', 'title', 'external_url', 'category', 'modules',
        'description');

    public function create(Request $request, $project, $deployment)
    {
        try {
            $deployment_obj = $this->app['datastore']->fetchDeployment($project, $deployment);
            $this->checkAccess(array('entry', 'approval'), $deployment_obj);
            $this->addEntityCrumb($deployment_obj);
            $this->addCreateCrumb('Add deployment item', $this->app->entity_path('item_create', $deployment_obj));
            return $this->edit_or_create(new ItemEntity($deployment_obj), $request->request);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    public function view(Request $request, $project, $deployment, $item)
    {
        try {
            $item_obj = $this->app['datastore']->fetchItem($project, $deployment, $item);
            $this->checkAccess('any', $item_obj);
            $this->addEntityCrumb($item_obj);
            $editable = (
                $this->app['securilex']->isGranted(['entry', 'approval'], $item_obj->deployment->project)
                && 0 < $item_obj->compareCurrentStatusTo(Project::ITEM_STATUS_APPROVAL));
            $commentable = $this->app['securilex']->isGranted(['execute', 'entry',
                'review', 'approval'], $item_obj->deployment->project);
            return $this->render('item_view', array(
                    'item' => $item_obj,
                    'project' => $item_obj->deployment->project,
                    'editable' => $editable,
                    'commentable' => $commentable,
            ));
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    public function edit(Request $request, $project, $deployment, $item)
    {
        try {
            $item_obj = $this->app['datastore']->fetchItem($project, $deployment, $item);
            if ($item_obj->approved_date) {
                $this->checkAccess(array('approval'), $item_obj);
            } else {
                $this->checkAccess(array('entry', 'approval'), $item_obj);
            }
            $this->addEntityCrumb($item_obj);
            $this->addEditCrumb($this->app->entity_path('item_edit', $item_obj));
            return $this->edit_or_create($item_obj, $request->request);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    public function changeStatus(Request $request, $project, $deployment, $item)
    {
        $new_status = $request->request->get('new_status');
        $item_obj = $this->app['datastore']->fetchItem($project, $deployment, $item);
        $user = $this->app->user();

        $remark = trim($request->request->get('remark'));
        if (empty($remark) && $request->request->get('remark_required', 0)) {
            $this->app->addFlashMessage("Remark is required", "Unable to change status", "error");
        } else {
            $old_status = $item_obj->status;
            $direction = $item_obj->changeStatus($new_status, $remark);
            if ($item_obj->status == Project::ITEM_STATUS_READY && $direction > 0) {
                /* @var $activity Activity */
                foreach ($item_obj->activities as $activity) {
                    if ($activity->runitem == null ||
                        $activity->runitem->status == Project::ITEM_STATUS_FAILED) {
                        $runitems = $item_obj->deployment->runitems->matching(Criteria::create()
                                ->where(Criteria::expr()->eq('signature', $activity->signature))
                                ->andWhere(Criteria::expr()->eq('status', 'New'))
                        );
                        if ($runitems->isEmpty()) {
                            $activity->runitem = new RunItem($item_obj->deployment);
                            $activity->runitem->signature = $activity->signature;
                            $activity->runitem->template = $activity->template;
                            $activity->runitem->stage = $activity->stage;
                            $activity->runitem->parameters = $activity->parameters;
                            $activity->runitem->priority = $activity->priority;
                            foreach ($activity->files as $file) {
                                $rfile = new RunItemFile($activity->runitem);
                                $rfile->filename = $file->filename;
                                $rfile->classifier = $file->classifier;
                                $rfile->description = $file->description;
                                $rfile->filestore = $file->filestore;
                                $activity->runitem->files->add($rfile);
                            }
                            $this->app['datastore']->commit($activity->runitem);
                        } else {
                            $activity->runitem = $runitems->get(0);
                        }
                    }
                }
            } elseif ($old_status == Project::ITEM_STATUS_READY && $direction < 0) {
                /* @var $activity Activity */
                foreach ($item_obj->activities as $activity) {
                    if ($activity->runitem != null && $activity->runitem->status
                        == 'New') {
                        $activity->runitem = null;
                    }
                }
                // cleanup run items
                $used_runitem_ids = array();
                foreach ($item_obj->deployment->items as $d_item) {
                    foreach ($d_item->activities as $d_activity) {
                        if ($d_activity->runitem) {
                            $used_runitem_ids[] = $d_activity->runitem->id;
                        }
                    }
                }
                foreach ($item_obj->deployment->runitems as $d_runitem) {
                    if (!in_array($d_runitem->id, $used_runitem_ids) &&
                        $d_runitem->status == 'New') {
                        $this->app['datastore']->deleteEntity($d_runitem);
                    }
                }
            }
            $this->app['datastore']->commit();
            $this->app->addFlashMessage("Item '$item_obj->title' has been changed status to $new_status");
        }
        return $this->app->entity_redirect('item_view', $item_obj);
    }

    protected function edit_or_create(ItemEntity $item, ParameterBag $post)
    {
        $context = array();
        $ds = $this->app['datastore'];
        if ($post->count() > 0) {
            switch ($post->get('_action')) {
                case 'Move':
                    $ndeployment = $ds->queryOne('\Renogen\Entity\Deployment', $post->get('deployment'));
                    if (!$ndeployment) {
                        $context['errors'] = array(
                            'deployment' => array('Please select a deployment')
                        );
                    } elseif ($ndeployment != $item->deployment) {
                        if ($ndeployment->project != $item->deployment->project) {
                            // Different project = copy
                            $ds->unmanage($item);
                            $item->id = null;
                        }
                        $data = new ParameterBag(array(
                            'deployment' => $ndeployment,
                        ));
                        if ($ds->prepareValidateEntity($item, $data->keys(), $data)) {
                            $ds->commit($item);
                            $this->app->addFlashMessage("Item '$item->title' has been moved to deployment '".$item->deployment->displayTitle()."'");
                            return $this->app->entity_redirect('item_view', $item);
                        }
                    } else {
                        $context['errors'] = array(
                            'deployment' => array('Please select another deployment')
                        );
                    }
                    break;

                case 'Delete':
                    $ds->deleteEntity($item);
                    $ds->commit();
                    $this->app->addFlashMessage("Item '$item->title' has been deleted");
                    return $this->app->entity_redirect('deployment_view', $item->deployment);

                default:
                    if ($ds->prepareValidateEntity($item, static::entityFields, $post)) {
                        $ds->commit($item);
                        $this->app->addFlashMessage("Item '$item->title' has been successfully saved");
                        return $this->app->entity_redirect('item_view', $item);
                    } else {
                        $context['errors'] = $item->errors;
                    }
            }
        }
        $context['item'] = $item;
        $context['project'] = $item->deployment->project;
        return $this->render('item_form', $context);
    }

    public function comment_add(Request $request, $project, $deployment, $item)
    {
        try {
            $item_obj = $this->app['datastore']->fetchItem($project, $deployment, $item);
            $comment = new ItemComment($item_obj);
            if ($this->app['datastore']->prepareValidateEntity($comment, array('text'), $request->request)) {
                $this->app['datastore']->commit($comment);
                $this->app->addFlashMessage("Succesfully post a comment");
            } else {
                $this->app->addFlashMessage("Failed to post a comment: please ensure you enter a reply and please try again");
            }
            return $this->app->entity_redirect('item_view', $item_obj);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    public function comment_delete(Request $request, $project, $deployment,
                                   $item, $comment)
    {
        return $this->comment_setDeletedDate($project, $deployment, $item, $comment, new DateTime());
    }

    public function comment_undelete(Request $request, $project, $deployment,
                                     $item, $comment)
    {
        return $this->comment_setDeletedDate($project, $deployment, $item, $comment, null);
    }

    protected function comment_setDeletedDate($project, $deployment, $item,
                                              $comment, DateTime $date = null)
    {
        try {
            $item_obj = $this->app['datastore']->fetchItem($project, $deployment, $item);
            if ($item_obj->comments->containsKey($comment)) {
                $comment_obj = $item_obj->comments->get($comment);
                $comment_obj->deleted_date = $date;
                $this->app['datastore']->commit($comment_obj);
            }
            return $this->app->entity_redirect('item_view', $item_obj);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }
}