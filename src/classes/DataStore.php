<?php

namespace Renogen;

use DateTime;
use Renogen\Base\Entity;
use Renogen\Entity\Activity;
use Renogen\Entity\Attachment;
use Renogen\Entity\Deployment;
use Renogen\Entity\FileLink;
use Renogen\Entity\FileStore;
use Renogen\Entity\Item;
use Renogen\Entity\Project;
use Renogen\Exception\NoResultException;
use Silex\ServiceProviderInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\ParameterBag;
use Twig\Template;

class DataStore implements ServiceProviderInterface
{
    /** @var Application */
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

    public function manage(Entity $entity)
    {
        $this->app['em']->persist($entity);
    }

    public function unmanage(Entity $entity)
    {
        $this->app['em']->detach($entity);
    }

    public function prepareValidateEntity(Entity &$entity, Array $fields,
                                          ParameterBag $data)
    {
        foreach ($fields as $field) {
            if (!$data->has($field)) {
                continue;
            }
            $entity->storeOldValues(array($field));
            $field_value = $data->get($field);
            if (substr($field, -5) == '_date' && !($field_value instanceof \DateTime)) {
                if (!empty($field_value) && strlen($field_value) <= 10) {
                    $field_value .= ' 00:00 AM';
                }
                $entity->$field = (!$field_value ? null : DateTime::createFromFormat('d/m/Y h:i A', $field_value));
            } elseif (substr($field, -3) == '_by') {
                $entity->$field = $this->queryOne('\Renogen\Entity\User', $field_value);
            } else {
                $entity->$field = $field_value;
            }
        }
        return $entity->validate($this->app['em']);
    }

    public function deleteEntity(Entity &$entity)
    {
        $entity->updated_by   = $this->app->userEntity() ?: $entity->updated_by;
        $entity->updated_date = new \DateTime();
        $entity->delete($this->app['em']);
    }

    public function processFileUpload(UploadedFile $file,
                                      FileLink $filelink = null,
                                      array &$errors = array())
    {
        if ($file->isValid() && $filelink) {
            $sha1      = sha1_file($file->getRealPath());
            $filestore = $this->queryOne('\\Renogen\\Entity\\FileStore', array('id' => $sha1));
            if (!$filestore) {
                $filestore            = new FileStore();
                $filestore->id        = $sha1;
                $filestore->data      = fopen($file->getRealPath(),'rb');
                $filestore->filesize  = $file->getClientSize();
                $filestore->mime_type = $file->getMimeType();
            }
            $filelink->filestore = $filestore;
            $filelink->filename  = $file->getClientOriginalName();
            if (!$filelink->classifier) {
                $filelink->classifier = $filelink->filename;
            }
        } else {
            $errors = array(
                'Unable to process uploaded file',
            );
        }
        return $filelink;
    }
}