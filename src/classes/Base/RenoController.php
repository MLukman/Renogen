<?php

namespace Renogen\Base;

use Renogen\Entity\Activity;
use Renogen\Entity\Attachment;
use Renogen\Entity\Checklist;
use Renogen\Entity\Deployment;
use Renogen\Entity\Item;
use Renogen\Entity\Project;
use Renogen\Entity\Template;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

abstract class RenoController extends Controller
{
    const titleLength           = 32;
    const hideOnMobileThreshold = 1;

    protected function addEntityCrumb(Entity $entity, $level = 0)
    {
        if ($entity instanceof Project) {
            $project     = $entity;
            $this->title = $project->title;
            $this->addCrumb($this->title, $this->app->entity_path('project_view', $project), 'cube',
                $level > self::hideOnMobileThreshold);
        } elseif ($entity instanceof Deployment) {
            $deployment  = $entity;
            $this->addEntityCrumb($deployment->project, $level + 1);
            $this->title = $deployment->displayTitle();
            $this->addCrumb($deployment->datetimeString(true), $this->app->entity_path('deployment_view', $deployment), 'calendar check o',
                $level > self::hideOnMobileThreshold);
        } elseif ($entity instanceof Item) {
            $item        = $entity;
            $this->addEntityCrumb($item->deployment, $level + 1);
            $this->title = $item->displayTitle();
            $this->addCrumb(strlen($this->title) > self::titleLength ?
                    substr($this->title, 0, self::titleLength).'...' : $this->title, $this->app->entity_path('item_view', $item), 'idea',
                $level > self::hideOnMobileThreshold);
        } elseif ($entity instanceof Activity) {
            $activity    = $entity;
            $this->addEntityCrumb($activity->item, $level + 1);
            $this->title = $activity->displayTitle();
            $this->addCrumb(strlen($this->title) > self::titleLength ?
                    substr($this->title, 0, self::titleLength).'...' : $this->title, $this->app->entity_path('activity_edit', $activity), 'add to cart',
                $level > self::hideOnMobileThreshold);
        } elseif ($entity instanceof Attachment) {
            $attachment  = $entity;
            $this->addEntityCrumb($attachment->item, $level + 1);
            $this->title = $attachment->description;
            $this->addCrumb($this->title, $this->app->entity_path('attachment_edit', $attachment), 'attach',
                $level > self::hideOnMobileThreshold);
        } elseif ($entity instanceof Template) {
            $template    = $entity;
            $this->addEntityCrumb($template->project, $level + 1);
            $this->addCrumb('Activity templates', $this->app->entity_path('template_list', $template->project), 'clipboard',
                $level > self::hideOnMobileThreshold);
            $this->title = $template->title;
            $this->addCrumb($this->title, $this->app->entity_path('template_edit', $template), 'copy',
                $level > self::hideOnMobileThreshold);
        } elseif ($entity instanceof Checklist) {
            $checklist   = $entity;
            $this->addEntityCrumb($checklist->deployment, $level + 1);
            $this->title = $checklist->title;
            $this->addCrumb($this->title, $this->app->entity_path('checklist_edit', $checklist), 'tasks',
                $level > self::hideOnMobileThreshold);
        }
    }

    protected function addEditCrumb($path)
    {
        $this->addCrumb('Edit', $path, 'pencil');
    }

    protected function addCreateCrumb($text, $path)
    {
        $this->addCrumb($text, $path, 'plus');
    }

    protected function checkAccess($attr, Entity $entity)
    {
        if ($attr == 'any') {
            $attr = array('view', 'execute', 'entry', 'review', 'approval');
        }
        if ($entity instanceof Project) {
            if (!$this->app['securilex']->isGranted($attr, $entity)) {
                throw new AccessDeniedException();
            }
        } elseif ($entity instanceof Deployment) {
            $this->checkAccess($attr, $entity->project);
        } elseif ($entity instanceof Item) {
            $this->checkAccess($attr, $entity->deployment->project);
        } elseif ($entity instanceof Activity) {
            $this->checkAccess($attr, $entity->item->deployment->project);
        } elseif ($entity instanceof Attachment) {
            $this->checkAccess($attr, $entity->item->deployment->project);
        } elseif ($entity instanceof Template) {
            $this->checkAccess($attr, $entity->project);
        } elseif ($entity instanceof Checklist) {
            $this->checkAccess($attr, $entity->deployment->project);
        }
    }
}