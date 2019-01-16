<?php

namespace Renogen\Base;

use Renogen\Entity\Activity;
use Renogen\Entity\ActivityFile;
use Renogen\Entity\Attachment;
use Renogen\Entity\Deployment;
use Renogen\Entity\Item;
use Renogen\Entity\Project;
use Renogen\Entity\RunItem;
use Renogen\Entity\RunItemFile;
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
            $this->addCrumb($this->title, $this->app->path('project_view', $this->entityParams($project)), 'cube');
        } elseif ($entity instanceof Deployment) {
            $deployment  = $entity;
            //$this->addEntityCrumb($deployment->project);
            $this->title = $deployment->displayTitle();
            $this->addCrumb($deployment->datetimeString(true), $this->app->path('deployment_view', $this->entityParams($deployment)), 'calendar check o');
        } elseif ($entity instanceof Item) {
            $item        = $entity;
            //$this->addEntityCrumb($item->deployment);
            $this->title = $item->displayTitle();
            $this->addCrumb(strlen($this->title) > self::titleLength ?
                    substr($this->title, 0, self::titleLength).'...' : $this->title, $this->app->path('item_view', $this->entityParams($item)), 'idea');
        } elseif ($entity instanceof Activity) {
            $activity    = $entity;
            $this->addEntityCrumb($activity->item);
            $this->title = $activity->displayTitle();
            $this->addCrumb(strlen($this->title) > self::titleLength ?
                    substr($this->title, 0, self::titleLength).'...' : $this->title, $this->app->path('activity_edit', $this->entityParams($activity)), 'add to cart');
        } elseif ($entity instanceof Attachment) {
            $attachment  = $entity;
            $this->addEntityCrumb($attachment->item);
            $this->title = $attachment->description;
            $this->addCrumb($this->title, $this->app->path('attachment_edit', $this->entityParams($attachment)), 'attach');
        } elseif ($entity instanceof Template) {
            $template    = $entity;
            $this->addEntityCrumb($template->project);
            $this->addCrumb('Activity templates', $this->app->path('template_list', $this->entityParams($template->project)), 'clipboard');
            $this->title = $template->title;
            $this->addCrumb($this->title, $this->app->path('template_view', $this->entityParams($template)), 'copy');
        }
    }

    static public function entityParams(Entity $entity)
    {
        if ($entity instanceof Project) {
            return array(
                'project' => $entity->name,
            );
        } elseif ($entity instanceof Deployment) {
            return static::entityParams($entity->project) + array(
                'deployment' => $entity->execute_date->format('YmdHi'),
            );
        } elseif ($entity instanceof Item) {
            return static::entityParams($entity->deployment) + array(
                'item' => $entity->id,
            );
        } elseif ($entity instanceof Activity) {
            return static::entityParams($entity->item) + array(
                'activity' => $entity->id,
            );
        } elseif ($entity instanceof ActivityFile) {
            return static::entityParams($entity->activity) + array(
                'file' => $entity->id,
            );
        } elseif ($entity instanceof Attachment) {
            return static::entityParams($entity->item) + array(
                'attachment' => $entity->id,
            );
        } elseif ($entity instanceof Template) {
            return static::entityParams($entity->project) + array(
                'template' => $entity->id,
            );
        } elseif ($entity instanceof RunItem) {
            return static::entityParams($entity->deployment) + array(
                'runitem' => $entity->id,
            );
        } elseif ($entity instanceof RunItemFile) {
            return static::entityParams($entity->runitem) + array(
                'file' => $entity->id,
            );
        } else {
            return array();
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