<?php

namespace Renogen\Controller;

use Doctrine\ORM\NoResultException;
use Renogen\Base\RenoController;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

class Template extends RenoController
{
    const entityFields = array(
        'class',
        'title',
        'description',
        'disabled',
        'stage',
        'priority',
        'parameters',
    );

    public function index(Request $request, $project)
    {
        try {
            $project_obj = $this->app['datastore']->fetchProject($project);
            $this->checkAccess(array('approval', 'ROLE_ADMIN'), $project_obj);
            $this->addEntityCrumb($project_obj);
            $this->addCrumb('Activity templates', $this->app->entity_path('template_list', $project_obj), 'clipboard');
            return $this->render('template_list', array('project' => $project_obj));
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    public function create(Request $request, $project)
    {
        try {
            $project_obj = $this->app['datastore']->fetchProject($project);
            $this->checkAccess(array('approval', 'ROLE_ADMIN'), $project_obj);
            $this->addEntityCrumb($project_obj);
            //$this->addCrumb('Activity templates', $this->app->entity_path('template_list', $project_obj), 'clipboard');
            $this->addCreateCrumb('Create activity template', $this->app->entity_path('template_create', $project_obj));
            $template    = null;
            if (($copyfrom    = $request->query->get('copy')) && ($copytmpl    = $this->app['datastore']->fetchTemplate($copyfrom))) {
                $template          = clone $copytmpl;
                $template->id      = null;
                $template->project = $project_obj;
            } else {
                $template = new \Renogen\Entity\Template($project_obj);
            }
            return $this->edit_or_create($request, $template, $request->request);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    public function edit(Request $request, $project, $template)
    {
        try {
            $project_obj  = $this->app['datastore']->fetchProject($project);
            $this->checkAccess(array('approval', 'ROLE_ADMIN'), $project_obj);
            $template_obj = $this->app['datastore']->fetchTemplate($template, $project_obj);
            $this->addEntityCrumb($template_obj);
            return $this->edit_or_create($request, $template_obj, $request->request);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    protected function edit_or_create(Request $request,
                                      \Renogen\Entity\Template $template,
                                      ParameterBag $post)
    {
        $context = array();

        $this->setTemplateClassContext($context, $template->class);

        if ($post->count() > 0) {
            switch ($post->get('_action')) {

                case 'Next':
                    if (!$this->setTemplateClassContext($context, $post->get('class'))) {
                        $context['errors'] = array('class' => 'Please select a category');
                    }
                    break;

                case 'Import':
                    $file     = $request->files->get('import');
                    if ($file && ($imported = json_decode(file_get_contents($file->getRealPath()), true))) {
                        if (isset($imported['class']) && $this->setTemplateClassContext($context, $imported['class'])) {
                            foreach (self::entityFields as $k) {
                                if (isset($imported[$k])) {
                                    $template->$k = $imported[$k];
                                }
                            }
                            $template->priority = 0;
                            break;
                        }
                    }
                    $context['errors'] = array('import' => 'Please select a valid activity template exported file');
                    break;

                case 'Delete':
                    $this->app['datastore']->deleteEntity($template);
                    // Adjust priority of the other templates
                    $qb   = $this->app['em']->createQueryBuilder()
                        ->select('e')
                        ->from('\Renogen\Entity\Template', 'e')
                        ->where('e.project = :p')
                        ->andWhere('e.priority > :from')
                        ->setParameter('p', $template->project)
                        ->setParameter('from', $template->priority)
                        ->orderBy('e.priority', 'ASC');
                    $prio = 0;
                    foreach ($qb->getQuery()->getResult() as $atemplate) {
                        $atemplate->priority = ++$prio;
                    }
                    $this->app['datastore']->commit();
                    $this->app->addFlashMessage("Template '$template->title' has been deleted");
                    return $this->app->entity_redirect('template_list', $template);

                case 'Test Form Validation':
                    $context['sample']                       = array(
                        'data' => array(),
                        'errors' => array(),
                    );
                    $context['sample']['activity']           = new \Renogen\Entity\Activity(new \Renogen\Entity\Item(new \Renogen\Entity\Deployment($template->project)));
                    $context['sample']['activity']->template = $template;
                    $parameters                              = $post->get('parameters', array());
                    foreach ($template->templateClass()->getParameters() as $param => $parameter) {
                        $parameter->handleActivityFiles($request, $context['sample']['activity'], $parameters, $param);
                        $parameter->validateActivityInput($template->parameters, $parameters, $param, $context['sample']['errors'], 'parameters');
                    }
                    $post->set('parameters', $parameters);
                    if ($this->app['datastore']->prepareValidateEntity($context['sample']['activity'], array(
                            'parameters'), $post) && empty($context['sample']['errors'])) {
                        $this->app->addFlashMessage("Form validation success");
                    } else {
                        $this->app->addFlashMessage("Form validation failure");
                    }
                    $context['project']  = $template->project;
                    $context['template'] = $template;
                    return $this->render('template_form', $context);

                case 'Create activity template':
                case 'Save activity template':
                    $this->setTemplateClassContext($context, $post->get('class'));
                    $parameters = $post->get('parameters', array());
                    $errors     = array();
                    foreach ($context['class_instance']->getParameters() as $param => $parameter) {
                        $parameter->validateTemplateInput($parameters, $param, $errors, 'parameters');
                    }
                    $post->set('parameters', $parameters);
                    $oldpriority = $template->priority ?:
                        $template->project->templates->count() + 1;

                    if ($this->app['datastore']->prepareValidateEntity($template, static::entityFields, $post)
                        && empty($errors)) {
                        if ($oldpriority != $template->priority) {
                            $qb = $this->app['em']->createQueryBuilder()
                                ->select('e')
                                ->from('\Renogen\Entity\Template', 'e')
                                ->where('e.project = :p')
                                ->andWhere('e.priority >= :from')
                                ->andWhere('e.priority <= :to')
                                ->setParameter('p', $template->project);
                            if ($oldpriority > $template->priority) {
                                $qb->setParameter('from', $template->priority)
                                    ->setParameter('to', $oldpriority - 1)
                                    ->orderBy('e.priority', 'ASC');
                                $prio = $template->priority;
                                foreach ($qb->getQuery()->getResult() as $atemplate) {
                                    $atemplate->priority = ++$prio;
                                }
                            } else {
                                $qb->setParameter('from', $oldpriority + 1)
                                    ->setParameter('to', $template->priority)
                                    ->orderBy('e.priority', 'DESC');
                                $prio = $template->priority;
                                foreach ($qb->getQuery()->getResult() as $atemplate) {
                                    $atemplate->priority = --$prio;
                                }
                            }
                            $this->app['datastore']->commit();
                        }
                        $this->app['datastore']->commit($template);
                        $this->app->addFlashMessage("Template '$template->title' has been successfully saved");
                        return $this->app->entity_redirect('template_edit', $template);
                    } else {
                        $context['errors'] = $errors + $template->errors;
                    }
                    break;
            }
        }

        $context['project']  = $template->project;
        $context['template'] = $template;
        return $this->render('template_form', $context);
    }

    private function setTemplateClassContext(&$context, $class)
    {
        if ($class && ($templateClass = $this->app->getActivityTemplateClass($class))) {
            $context['class']          = $class;
            $context['class_instance'] = $templateClass;
            return true;
        }
        return false;
    }

    public function export(Request $request, $project, $template)
    {
        try {
            $project_obj  = $this->app['datastore']->fetchProject($project);
            $this->checkAccess(array('approval', 'ROLE_ADMIN'), $project_obj);
            $template_obj = $this->app['datastore']->fetchTemplate($template, $project_obj);
            $export       = array();
            foreach (self::entityFields as $k) {
                $export[$k] = $template_obj->$k;
            }
            $filename = preg_replace('/[^a-z0-9]+/', '-', strtolower($template_obj->title));
            return new \Symfony\Component\HttpFoundation\JsonResponse($export, 200, array(
                'Content-Disposition' => "attachment; filename=\"{$filename}.json\"",
            ));
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }
}