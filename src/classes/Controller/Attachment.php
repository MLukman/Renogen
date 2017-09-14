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
            $item_obj       = $this->fetchItem($project, $deployment, $item);
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
            $attachment_obj = $this->fetchAttachment($project, $deployment, $item, $attachment);
            $targetdir      = $this->getFolder($attachment_obj);
            return $this->app
                    ->sendFile($targetdir.$attachment_obj->id, 200, array('Content-type' => $attachment_obj->mime_type))
                    ->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $attachment_obj->filename);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    public function edit(Request $request, $project, $deployment, $item,
                         $attachment)
    {
        try {
            $attachment_obj = $this->fetchAttachment($project, $deployment, $item, $attachment);
            $this->addEntityCrumb($attachment_obj);
            return $this->edit_or_create($attachment_obj, $request);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    protected function edit_or_create(\Renogen\Entity\Attachment $attachment,
                                      Request $request)
    {
        $post = $request->request;
        if ($post->count() > 0) {
            $targetdir = $this->getFolder($attachment);
            switch ($post->get('_action')) {
                case 'Delete':
                    $attachment->delete($this->app['em']);
                    $this->app['em']->flush();
                    $this->app->addFlashMessage("Attachment has been deleted");
                    return $this->redirect('item_view', $this->entityParams($attachment->item));

                default:
                    if ($this->prepareValidateEntity($attachment, static::entityFields, $post)) {
                        $file = $request->files->get('file');
                        if (!$file && !$attachment->filename) {
                            $context['errors'] = $attachment->errors + array(
                                'file' => array('Required'),
                            );
                        } else {
                            if ($file && $file->isValid()) {
                                $attachment->filename  = $file->getClientOriginalName();
                                $attachment->filesize  = $file->getClientSize();
                                $attachment->mime_type = $file->getMimeType();
                            }
                            $this->saveEntity($attachment, static::entityFields, $post);
                            if ($file && $file->isValid()) {
                                if (!file_exists($targetdir)) {
                                    mkdir($targetdir, 0777, true);
                                }
                                $file->move($targetdir, $attachment->id);
                            }
                            $this->app->addFlashMessage("Attachment has been successfully saved");
                            return $this->redirect('item_view', $this->entityParams($attachment->item));
                        }
                    } else {
                        $context['errors'] = $attachment->errors;
                    }
            }
        }
        $context['attachment'] = $attachment;
        return $this->render('attachment_form', $context);
    }

    protected function getFolder(\Renogen\Entity\Attachment $attachment)
    {
        return dirname($attachment->getFilesystemPath()).'/';
    }
}