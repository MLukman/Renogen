<?php

namespace Renogen;

use DateTime;
use Doctrine\ORM\NoResultException;
use Renogen\Base\Entity;
use Renogen\Entity\Activity;
use Renogen\Entity\Attachment;
use Renogen\Entity\Deployment;
use Renogen\Entity\Item;
use Renogen\Entity\Project;
use Silex\ServiceProviderInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Twig\Template;

class DataStore implements ServiceProviderInterface
{
    protected $app;

    public function boot(\Silex\Application $app)
    {

    }

    public function register(\Silex\Application $app)
    {
        $this->app              = $app;
        $this->app['datastore'] = $this;
    }

    public function queryOne($entity, $id_or_criteria)
    {
        if (empty($id_or_criteria)) {
            return null;
        }

        $repo = $this->app['em']->getRepository($entity);
        return (is_array($id_or_criteria) ?
            $repo->findOneBy($id_or_criteria) :
            $repo->find($id_or_criteria));
    }

    public function queryMany($entity, Array $criteria = array(),
                              Array $sort = array())
    {
        $repo = $this->app['em']->getRepository($entity);
        return $repo->findBy($criteria, $sort);
    }

    /**
     *
     * @param type $project
     * @return Project
     * @throws NoResultException
     */
    public function fetchProject($project)
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
    public function fetchDeployment($project, $deployment)
    {
        if ($deployment instanceof Deployment) {
            return $deployment;
        }
        $project_obj = $this->fetchProject($project);
        if (($deployments = $project_obj->getDeploymentsByDateString($deployment))
            && $deployments->count() > 0) {
            return $deployments->first();
        }
        throw new NoResultException("There is not such deployment matching '$deployment'");
    }

    /**
     *
     * @param type $project
     * @param type $deployment
     * @param type $item
     * @return Item
     * @throws NoResultException
     */
    public function fetchItem($project, $deployment, $item)
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
    public function fetchActivity($project, $deployment, $item, $activity)
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
    public function fetchAttachment($project, $deployment, $item, $attachment)
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
    public function fetchTemplate($project, $template)
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
    public function commit(Entity &$entity = null)
    {
        if ($entity) {
            $this->app['em']->persist($entity);
            $this->app['em']->flush($entity);
        } else {
            $this->app['em']->flush();
        }
    }

    public function prepareValidateEntity(Entity &$entity, Array $fields,
                                          ParameterBag $data)
    {
        foreach ($fields as $field) {
            if (!$data->has($field)) {
                continue;
            }
            if (substr($field, -5) == '_date') {
                $raw_date = $data->get($field);
                if (!empty($raw_date) && strlen($raw_date) <= 10) {
                    $raw_date .= ' 00:00 AM';
                }
                $entity->$field = (!$raw_date ? null : DateTime::createFromFormat('d/m/Y h:i A', $raw_date));
            } elseif (substr($field, -3) == '_by') {
                $entity->$field = $this->queryOne('\Renogen\Entity\User', $data->get($field));
            } else {
                $entity->$field = $data->get($field);
            }
        }
        return $entity->validate($this->app['em']);
    }

    public function deleteEntity(Entity &$entity)
    {
        $entity->delete($this->app['em']);
    }
}