<?php

namespace Renogen\Base;

use Doctrine\ORM\NoResultException;
use Renogen\Entity\Activity;
use Renogen\Entity\Attachment;
use Renogen\Entity\Deployment;
use Renogen\Entity\Item;
use Renogen\Entity\Project;
use Renogen\Entity\Template;
use Symfony\Component\HttpFoundation\ParameterBag;

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
            $this->addEntityCrumb($deployment->project);
            $this->title = $deployment->title;
            $this->addCrumb($this->title, $this->app->path('deployment_view', $this->entityParams($deployment)), 'calendar check o');
        } elseif ($entity instanceof Item) {
            $item        = $entity;
            $this->addEntityCrumb($item->deployment);
            $this->title = $item->displayTitle();
            $this->addCrumb(strlen($this->title) > self::titleLength ?
                    substr($this->title, 0, self::titleLength).'...' : $this->title, $this->app->path('item_view', $this->entityParams($item)), 'idea');
        } elseif ($entity instanceof Activity) {
            $activity    = $entity;
            $this->addEntityCrumb($activity->item);
            $this->title = $activity->displayTitle();
            $this->addCrumb(strlen($this->title) > self::titleLength ?
                    substr($this->title, 0, self::titleLength).'...' : $this->title, $this->app->path('activity_edit', $this->entityParams($activity)), 'list');
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
                'deployment' => $entity->name,
            );
        } elseif ($entity instanceof Item) {
            return static::entityParams($entity->deployment) + array(
                'item' => $entity->id,
            );
        } elseif ($entity instanceof Activity) {
            return static::entityParams($entity->item) + array(
                'activity' => $entity->id,
            );
        } elseif ($entity instanceof Attachment) {
            return static::entityParams($entity->item) + array(
                'attachment' => $entity->id,
            );
        } elseif ($entity instanceof Template) {
            return static::entityParams($entity->project) + array(
                'template' => $entity->id,
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

    /**
     *
     * @param type $project
     * @return Project
     * @throws NoResultException
     */
    protected function fetchProject($project)
    {
        if (!($project instanceof Project)) {
            $name    = $project;
            if (!($project = $this->queryOne('\Renogen\Entity\Project', array('name' => $name)))) {
                throw new NoResultException("There is not such project with name '$name'");
            }
        }
        return $project;
    }

    /**
     *
     * @param type $project
     * @param type $deployment
     * @return Deployment
     * @throws NoResultException
     */
    protected function fetchDeployment($project, $deployment)
    {
        if (!($deployment instanceof Deployment)) {
            $name        = $deployment;
            $project_obj = $this->fetchProject($project);
            if (!($deployment  = $project_obj->deployments->get($name))) {
                throw new NoResultException("There is not such deployment with name '$name'");
            }
        }
        return $deployment;
    }

    /**
     *
     * @param type $project
     * @param type $deployment
     * @param type $item
     * @return Item
     * @throws NoResultException
     */
    protected function fetchItem($project, $deployment, $item)
    {
        if (!($item instanceof Item)) {
            $id   = $item;
            if (!($item = $this->queryOne('\Renogen\Entity\Item', array('id' => $id)))) {
                throw new NoResultException("There is not such deployment with id '$id'");
            }
        }
        return $item;
    }

    /**
     *
     * @param type $project
     * @param type $deployment
     * @param type $item
     * @param type $activity
     * @return Activity
     * @throws NoResultException
     */
    protected function fetchActivity($project, $deployment, $item, $activity)
    {
        if (!($activity instanceof Activity)) {
            $id       = $activity;
            $item_obj = $this->fetchItem($project, $deployment, $item);
            if (!($activity = $item_obj->activities->get($id))) {
                throw new NoResultException("There is not such activity with id '$id'");
            }
        }
        return $activity;
    }

    /**
     *
     * @param type $project
     * @param type $deployment
     * @param type $item
     * @param type $attachment
     * @return Attachment
     * @throws NoResultException
     */
    protected function fetchAttachment($project, $deployment, $item, $attachment)
    {
        if (!($attachment instanceof Attachment)) {
            $id         = $attachment;
            $item_obj   = $this->fetchItem($project, $deployment, $item);
            if (!($attachment = $item_obj->attachments->get($id))) {
                throw new NoResultException("There is not such attachment with id '$id'");
            }
        }
        return $attachment;
    }

    /**
     *
     * @param type $project
     * @param type $template
     * @return Template
     * @throws NoResultException
     */
    protected function fetchTemplate($project, $template)
    {
        if (!($template instanceof Template)) {
            $id          = $template;
            $project_obj = $this->fetchProject($project);
            if (!($template    = $project_obj->templates->get($id))) {
                throw new NoResultException("There is not such template with id '$name'");
            }
        }
        return $template;
    }

    /**
     * 
     * @param Entity $entity
     * @param type $fields
     * @param ParameterBag $data
     * @return boolean
     */
    protected function saveEntity(Entity &$entity, $fields, ParameterBag $data)
    {
        if ($this->prepareValidateEntity($entity, $fields, $data)) {
            $this->app['em']->persist($entity);
            $this->app['em']->flush($entity);
            return true;
        } else {
            return false;
        }
    }

    protected function prepareValidateEntity(Entity &$entity, $fields,
                                             ParameterBag $data)
    {
        foreach ($fields as $field) {
            if (!$data->has($field)) {
                continue;
            }
            if (substr($field, -5) == '_date') {
                $raw_date = $data->get($field);
                if (strlen($raw_date) <= 10) {
                    $raw_date .= ' 00:00 AM';
                }
                $entity->$field = (!$raw_date ? null : \DateTime::createFromFormat('d/m/Y h:i A', $raw_date));
            } elseif (substr($field, -3) == '_by') {
                $entity->$field = $this->queryOne('\Renogen\Entity\User', $data->get($field));
            } else {
                $entity->$field = $data->get($field);
            }
        }
        return $entity->validate($this->app['em']);
    }

    protected function checkAccess($attr, Entity $entity)
    {
        if ($entity instanceof Project) {
            if (!$this->app['securilex']->isGranted($attr, $entity)) {
                throw new \Symfony\Component\Security\Core\Exception\AccessDeniedException();
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