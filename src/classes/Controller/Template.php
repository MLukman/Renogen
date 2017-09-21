<?php

namespace Renogen\Controller;

use Doctrine\ORM\NoResultException;
use Renogen\Base\RenoController;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

class Template extends RenoController
{
    const entityFields = array('class', 'title', 'description', 'priority', 'parameters');

    public function index(Request $request, $project)
    {
        try {
            $project_obj = $this->fetchProject($project);
            $this->addEntityCrumb($project_obj);
            $this->addCrumb('Activity templates', $this->app->path('template_list', $this->entityParams($project_obj)), 'clipboard');
            return $this->render('template_list', array('project' => $project_obj));
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    public function create(Request $request, $project)
    {
        try {
            $project_obj = $this->fetchProject($project);
            $this->addEntityCrumb($project_obj);
            $this->addCrumb('Activity templates', $this->app->path('template_list', $this->entityParams($project_obj)), 'clipboard');
            $this->addCreateCrumb('Create activity template', $this->app->path('template_create', $this->entityParams($project_obj)));
            return $this->edit_or_create(new \Renogen\Entity\Template($project_obj), $request->request);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    public function view(Request $request, $project, $template)
    {
        try {
            $template_obj = $this->fetchTemplate($project, $template);
            $this->addEntityCrumb($template_obj);
            return $this->render('template_view', array('template' => $template_obj));
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    public function edit(Request $request, $project, $template)
    {
        try {
            $template_obj = $this->fetchTemplate($project, $template);
            $this->addEntityCrumb($template_obj);
            $this->addEditCrumb($this->app->path('template_edit', $this->entityParams($template_obj)));
            return $this->edit_or_create($template_obj, $request->request);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    protected function edit_or_create(\Renogen\Entity\Template $template,
                                      ParameterBag $post,
                                      array $context = array())
    {
        if (($class = ($template->class ?: $post->get('class')))) {
            $templateClass = $this->app->getActivityTemplateClass($class);
            if ($templateClass) {
                $context['class']          = $class;
                $context['class_instance'] = $templateClass;
            }
        }
        if ($post->count() > 0 &&
            isset($context['class_instance']) &&
            $post->get('_action') != 'Next') {

            if ($post->get('_action') == 'Delete') {
                $template->delete($this->app['em']);
                // Adjust priority of the other templates
                $qb = $this->app['em']->createQueryBuilder()
                    ->select('e')
                    ->from('\Renogen\Entity\Template', 'e')
                    ->where('e.priority > :from')
                    ->setParameter('from', $template->priority);
                foreach ($qb->getQuery()->getResult() as $atemplate) {
                    $atemplate->priority--;
                }
                $this->app['em']->flush();
                $this->app->addFlashMessage("Template '$template->title' has been deleted");
                return $this->redirect('template_list', $this->entityParams($template));
            }

            $parameters = $post->get('parameters', array());
            $errors     = array();
            foreach ($context['class_instance']->getParameters() as $param => $parameter) {
                $parameter->validateTemplateInput($parameters, $param, $errors, 'parameters');
            }
            $post->set('parameters', $parameters);
            $oldpriority = $template->priority ?:
                $template->project->templates->count() + 1;

            if ($this->prepareValidateEntity($template, static::entityFields, $post)
                && empty($errors)) {
                if ($oldpriority != $template->priority) {
                    $qb = $this->app['em']->createQueryBuilder()
                        ->select('e')
                        ->from('\Renogen\Entity\Template', 'e')
                        ->where('e.priority >= :from')
                        ->andWhere('e.priority <= :to');
                    if ($oldpriority > $template->priority) {
                        $qb->setParameter('from', $template->priority)
                            ->setParameter('to', $oldpriority - 1);
                        foreach ($qb->getQuery()->getResult() as $atemplate) {
                            $atemplate->priority++;
                        }
                    } else {
                        $qb->setParameter('from', $oldpriority + 1)
                            ->setParameter('to', $template->priority);
                        foreach ($qb->getQuery()->getResult() as $atemplate) {
                            $atemplate->priority--;
                        }
                    }
                    $this->app['em']->flush();
                }
                $this->saveEntity($template, static::entityFields, $post);
                $this->app->addFlashMessage("Template '$template->title' has been successfully saved");
                return $this->redirect('template_view', $this->entityParams($template));
            } else {
                $context['errors'] = $errors + $template->errors;
            }
        }

        $context['project']  = $template->project;
        $context['template'] = $template;
        return $this->render('template_form', $context);
    }
}