<?php

namespace Renogen\Base;

use Doctrine\ORM\NoResultException;
use Renogen\Entity\Activity;
use Renogen\Entity\Deployment;
use Renogen\Entity\Item;
use Renogen\Entity\Project;
use Renogen\Entity\Template;
use Symfony\Component\HttpFoundation\ParameterBag;

abstract class RenoController extends Controller
{
    const titleLength = 40;

    protected function addEntityCrumb(Entity $entity)
    {
        if ($entity instanceof Project) {
            $project     = $entity;
            $this->title = $project->title;
            $this->addCrumb($this->title, $this->app->path('project_view', $this->entityPathParameters($project)), 'cube');
        } elseif ($entity instanceof Deployment) {
            $deployment  = $entity;
            $this->addEntityCrumb($deployment->project);
            $this->title = $deployment->displayTitle();
            $this->addCrumb($this->title, $this->app->path('deployment_view', $this->entityPathParameters($deployment)), 'calendar check o');
        } elseif ($entity instanceof Item) {
            $item        = $entity;
            $this->addEntityCrumb($item->deployment);
            $this->title = $item->displayTitle();
            $this->addCrumb(strlen($this->title) > self::titleLength ?
                    substr($this->title, 0, self::titleLength).'...' : $this->title, $this->app->path('item_view', $this->entityPathParameters($item)), 'tag');
        } elseif ($entity instanceof Activity) {
            $activity    = $entity;
            $this->addEntityCrumb($activity->item);
            $this->title = $activity->displayTitle();
            $this->addCrumb(strlen($this->title) > self::titleLength ?
                    substr($this->title, 0, self::titleLength).'...' : $this->title, $this->app->path('activity_view', $this->entityPathParameters($activity)), 'list');
        } elseif ($entity instanceof Template) {
            $template    = $entity;
            $this->addEntityCrumb($template->project);
            $this->title = $template->title;
            $this->addCrumb($this->title, $this->app->path('template_view', $this->entityPathParameters($template)), 'code');
        }
    }

    public function entityPathParameters(Entity $entity)
    {
        if ($entity instanceof Project) {
            return array(
                'project' => $entity->name,
            );
        } elseif ($entity instanceof Deployment) {
            return $this->entityPathParameters($entity->project) + array(
                'deployment' => $entity->name,
            );
        } elseif ($entity instanceof Item) {
            return $this->entityPathParameters($entity->deployment) + array(
                'item' => $entity->id,
            );
        } elseif ($entity instanceof Activity) {
            return $this->entityPathParameters($entity->item) + array(
                'activity' => $entity->id,
            );
        } elseif ($entity instanceof Template) {
            return $this->entityPathParameters($entity->project) + array(
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
     * @param Entity $entity
     * @param type $fields
     * @param ParameterBag $data
     * @return boolean
     */
    protected function saveEntity(Entity &$entity, $fields, ParameterBag $data)
    {
        foreach ($fields as $field) {
            if (!$data->has($field)) {
                continue;
            }
            if (substr($field, -5) == '_date') {
                $raw_date       = $data->get($field);
                $entity->$field = (!$raw_date ? null : \DateTime::createFromFormat('d/m/Y', $raw_date));
            } elseif (substr($field, -3) == '_by') {
                $entity->$field = $this->queryOne('\Renogen\Entity\User', $data->get($field));
            } else {
                $entity->$field = $data->get($field);
            }
        }
        if ($entity->validate($this->app['em'])) {
            $this->app['em']->persist($entity);
            $this->app['em']->flush($entity);
            return true;
        } else {
            return false;
        }
    }
}