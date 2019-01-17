<?php

namespace Renogen\Controller;

use DateTime;
use Doctrine\Common\Collections\Criteria;
use Renogen\Base\RenoController;
use Renogen\Entity\ItemComment;
use Renogen\Entity\RunItem;
use Renogen\Entity\RunItemFile;
use Renogen\Exception\NoResultException;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

class Item extends RenoController
{
    const entityFields = array('refnum', 'title', 'category', 'modules', 'description');

    public function create(Request $request, $project, $deployment)
    {
        try {
            $deployment_obj = $this->app['datastore']->fetchDeployment($project, $deployment);
            $this->checkAccess(array('entry', 'approval'), $deployment_obj);
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
            $item_obj = $this->app['datastore']->fetchItem($project, $deployment, $item);
            $this->addEntityCrumb($item_obj);
            return $this->render('item_view', array(
                    'item' => $item_obj,
                    'project' => $item_obj->deployment->project,
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
            $this->addEditCrumb($this->app->path('item_edit', $this->entityParams($item_obj)));
            return $this->edit_or_create($item_obj, $request->request);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    public function changeStatus(Request $request, $project, $deployment, $item)
    {
        $new_status = $request->request->get('new_status');
        $item_obj   = $this->app['datastore']->fetchItem($project, $deployment, $item);
        $user       = $this->app->user();

        $remark = trim($request->request->get('remark'));
        if (empty($remark) && $request->request->get('remark_required', 0)) {
            $this->app->addFlashMessage("Remark is required", "Unable to change status", "error");
        } else {
            $old_status = $item_obj->status;
            $direction  = $item_obj->changeStatus($new_status);
            if (!empty($remark)) {
                $comment        = new ItemComment($item_obj);
                $comment->event = "$old_status > $new_status";
                $comment->text  = $remark;
                $item_obj->comments->add($comment);
            }
            if ($item_obj->status == 'Ready For Release' && $direction > 0) {
                /* @var $activity Activity */
                foreach ($item_obj->activities as $activity) {
                    if ($activity->runitem == null || $activity->runitem->status
                        == 'Failed') {
                        $runitems = $item_obj->deployment->runitems->matching(Criteria::create()
                                ->where(Criteria::expr()->eq('signature', $activity->signature))
                                ->andWhere(Criteria::expr()->eq('status', 'New'))
                        );
                        if ($runitems->isEmpty()) {
                            $activity->runitem             = new RunItem($item_obj->deployment);
                            $activity->runitem->signature  = $activity->signature;
                            $activity->runitem->template   = $activity->template;
                            $activity->runitem->stage      = $activity->stage;
                            $activity->runitem->parameters = $activity->parameters;
                            $activity->runitem->priority   = $activity->priority;
                            foreach ($activity->files as $file) {
                                $rfile              = new RunItemFile($activity->runitem);
                                $rfile->filename    = $file->filename;
                                $rfile->classifier  = $file->classifier;
                                $rfile->description = $file->description;
                                $rfile->filestore   = $file->filestore;
                                $activity->runitem->files->add($rfile);
                            }
                            $this->app['datastore']->commit($activity->runitem);
                        } else {
                            $activity->runitem = $runitems->get(0);
                        }
                    }
                }
            } elseif ($old_status == 'Ready For Release' && $direction < 0) {
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
        return $this->redirect('item_view', $this->entityParams($item_obj));
    }

    public function action(Request $request, $project, $deployment, $item,
                           $action)
    {
        try {
            $item_obj = $this->app['datastore']->fetchItem($project, $deployment, $item);
            $actioned = null;
            switch ($action) {
                case 'submit':
                    $this->checkAccess(array('entry', 'approval'), $item_obj);
                    if (!$item_obj->deployment->isActive() && !$this->app['securilex']->isGranted('approval', $item_obj)) {
                        $this->app->addFlashMessage("You cannot submit an item for a deployment that was in the past.\nPlease move to another upcoming deployment and submit for approval there.", "Invalid action", "error");
                        break;
                    }
                    $item_obj->submit();
                    $actioned = 'submitted for approval';
                    break;

                case 'approve':
                    $this->checkAccess('approval', $item_obj);
                    $item_obj->approve();
                    $actioned = 'approved for deployment';
                    break;

                case 'unapprove':
                    $this->checkAccess('approval', $item_obj);
                    $item_obj->unapprove();
                    $actioned = 'unapproved';
                    break;

                case 'reject':
                    $this->checkAccess('approval', $item_obj);
                    $remark = trim($request->request->get('remark', '-'));

                    if (empty($remark)) {
                        $this->app->addFlashMessage("Rejection remark is required", "Unable to reject", "error");
                        break;
                    }

                    $item_obj->reject();
                    $actioned       = 'rejected';
                    $comment        = new ItemComment($item_obj);
                    $comment->event = 'Rejected';
                    $comment->text  = $remark;
                    $item_obj->comments->add($comment);
                    break;
            }
            if ($actioned) {
                $this->app['datastore']->commit($item_obj);
                $this->app->addFlashMessage("Item '$item_obj->title' has been $actioned");
            }
            return $this->redirect('item_view', $this->entityParams($item_obj));
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    protected function edit_or_create(\Renogen\Entity\Item $item,
                                      ParameterBag $post)
    {
        $context = array();
        $ds      = $this->app['datastore'];
        if ($post->count() > 0) {
            switch ($post->get('_action')) {
                case 'Move':
                    try {
                        $ndeployment = $ds->queryOne('\Renogen\Entity\Deployment', $post->get('deployment'));
                        if ($ndeployment != $item->deployment) {
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
                    $ds->deleteEntity($item);
                    $ds->commit();
                    $this->app->addFlashMessage("Item '$item->title' has been deleted");
                    return $this->redirect('deployment_view', $this->entityParams($item->deployment));

                default:
                    if ($ds->prepareValidateEntity($item, static::entityFields, $post)) {
                        $ds->commit($item);
                        $this->app->addFlashMessage("Item '$item->title' has been successfully saved");
                        return $this->redirect('item_view', $this->entityParams($item));
                    } else {
                        $context['errors'] = $item->errors;
                    }
            }
        }
        $context['item']    = $item;
        $context['project'] = $item->deployment->project;
        return $this->render('item_form', $context);
    }

    public function comment_add(Request $request, $project, $deployment, $item)
    {
        try {
            $item_obj = $this->app['datastore']->fetchItem($project, $deployment, $item);
            $comment  = new ItemComment($item_obj);
            if ($this->app['datastore']->prepareValidateEntity($comment, array('text'), $request->request)) {
                $this->app['datastore']->commit($comment);
                $this->app->addFlashMessage("Succesfully post a comment");
            } else {
                $this->app->addFlashMessage("Failed to post a comment: please ensure you enter a reply and please try again");
            }
            return $this->redirect('item_view', $this->entityParams($item_obj));
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
                $comment_obj               = $item_obj->comments->get($comment);
                $comment_obj->deleted_date = $date;
                $this->app['datastore']->commit($comment_obj);
            }
            return $this->redirect('item_view', $this->entityParams($item_obj));
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }
}