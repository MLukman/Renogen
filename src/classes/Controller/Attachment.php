<?php

namespace Renogen\Controller;

use Doctrine\ORM\NoResultException;
use Renogen\Base\RenoController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class Attachment extends RenoController
{
    const entityFields = array('description');

    public function create(Request $request, $project, $deployment, $item)
    {
        try {
            $item_obj       = $this->app['datastore']->fetchItem($project, $deployment, $item);
            $this->addEntityCrumb($item_obj);
            $this->addCreateCrumb('Add attachment', $this->app->path('attachment_create', $this->entityParams($item_obj)));
            $attachment_obj = new \Renogen\Entity\Attachment($item_obj);
            return $this->edit_or_create($attachment_obj, $request);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    public function download(Request $request, $project, $deployment, $item,
                             $attachment)
    {
        try {
            $attachment_obj = $this->app['datastore']->fetchAttachment($project, $deployment, $item, $attachment);
            return $this->app
                    ->sendFile($attachment_obj->getFilesystemPath(), 200, array(
                        'Content-type' => $attachment_obj->mime_type))
                    ->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $attachment_obj->filename);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    public function edit(Request $request, $project, $deployment, $item,
                         $attachment)
    {
        try {
            $attachment_obj = $this->app['datastore']->fetchAttachment($project, $deployment, $item, $attachment);
            $this->addEntityCrumb($attachment_obj);
            return $this->edit_or_create($attachment_obj, $request);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    protected function edit_or_create(\Renogen\Entity\Attachment $attachment,
                                      Request $request)
    {
        $post    = $request->request;
        $context = array('errors' => array());
        if ($post->count() > 0) {
            switch ($post->get('_action')) {
                case 'Delete':
                    $this->app['datastore']->deleteEntity($attachment);
                    $this->app['datastore']->commit();
                    $this->app->addFlashMessage("Attachment has been deleted");
                    return $this->redirect('item_view', $this->entityParams($attachment->item));

                default:
                    $file = $request->files->get('file');
                    if ($file) {
                        $errors = array();
                        $attachment->processUploadedFile($file, $errors);
                        if (!empty($errors)) {
                            $context['errors'] = $context['errors'] + array(
                                'file' => $errors,
                            );
                        }
                    } elseif (!$attachment->filename) {
                        $context['errors'] = $context['errors'] + array(
                            'file' => array('Required'),
                        );
                    }
                    if ($this->app['datastore']->prepareValidateEntity($attachment, static::entityFields, $post)
                        && empty($context['errors'])) {
                        $this->app['datastore']->commit($attachment);
                        $this->app->addFlashMessage("Attachment has been successfully saved");
                        return $this->redirect('item_view', $this->entityParams($attachment->item));
                    } else {
                        $context['errors'] = $context['errors'] + $attachment->errors
                            + array('file' => array('Your file is fine but you need to re-upload your file since other field(s) failed validations'));
                    }
            }
        }
        $context['attachment'] = $attachment;
        return $this->render('attachment_form', $context);
    }
}