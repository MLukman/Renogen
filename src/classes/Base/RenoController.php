<?php

namespace Renogen\Base;

use Renogen\Entity\Activity;
use Renogen\Entity\Attachment;
use Renogen\Entity\Deployment;
use Renogen\Entity\Item;
use Renogen\Entity\Project;
use Renogen\Entity\Template;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

abstract class RenoController extends Controller
{
    const titleLength = 32;

    protected function addEntityCrumb(Entity $entity)
    {
        if ($entity instanceof Project) {
            $project     = $entity;
            $this->title = $project->title;
            $this->addCrumb($this->title, $this->app->entity_path('project_view', $project), 'cube');
        } elseif ($entity instanceof Deployment) {
            $deployment  = $entity;
            //$this->addEntityCrumb($deployment->project);
            $this->title = $deployment->displayTitle();
            $this->addCrumb($deployment->datetimeString(true), $this->app->entity_path('deployment_view', $deployment), 'calendar check o');
        } elseif ($entity instanceof Item) {
            $item        = $entity;
            //$this->addEntityCrumb($item->deployment);
            $this->title = $item->displayTitle();
            $this->addCrumb(strlen($this->title) > self::titleLength ?
                    substr($this->title, 0, self::titleLength).'...' : $this->title, $this->app->entity_path('item_view', $item), 'idea');
        } elseif ($entity instanceof Activity) {
            $activity    = $entity;
            $this->addEntityCrumb($activity->item);
            $this->title = $activity->displayTitle();
            $this->addCrumb(strlen($this->title) > self::titleLength ?
                    substr($this->title, 0, self::titleLength).'...' : $this->title, $this->app->entity_path('activity_edit', $activity), 'add to cart');
        } elseif ($entity instanceof Attachment) {
            $attachment  = $entity;
            $this->addEntityCrumb($attachment->item);
            $this->title = $attachment->description;
            $this->addCrumb($this->title, $this->app->entity_path('attachment_edit', $attachment), 'attach');
        } elseif ($entity instanceof Template) {
            $template    = $entity;
            $this->addEntityCrumb($template->project);
            $this->addCrumb('Activity templates', $this->app->entity_path('template_list', $template->project), 'clipboard');
            $this->title = $template->title;
            $this->addCrumb($this->title, $this->app->entity_path('template_edit', $template), 'copy');
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
        }
    }
}