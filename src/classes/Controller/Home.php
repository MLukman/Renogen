<?php

namespace Renogen\Controller;

use Renogen\Base\Controller;
use Renogen\Entity\Project;
use Symfony\Component\HttpFoundation\Request;

class Home extends Controller
{

    public function index(Request $request)
    {
        /** @var Project[] $projects */
        $projects = $this->app['datastore']->queryMany('\Renogen\Entity\Project',
            array('archived' => false),
            array('title' => 'ASC')
        );

        // No project yet and the current user is an admin so go to create project screen
        if (count($projects) == 0 && $this->app['securilex']->isGranted('ROLE_ADMIN')) {
            return $this->app->params_redirect('project_create');
        }

        $contexts = array(
            'projects_with_access' => array(),
            'projects_no_access' => array(),
            'need_actions' => array(),
        );

        $roles = ['view', 'execute', 'entry', 'review', 'approval'];

        // Split projects with access and without access
        foreach ($projects as $project) {
            if ($this->app['securilex']->isGranted($roles, $project)) {
                $contexts['projects_with_access'][] = $project;
            } elseif (!$project->private) {
                $contexts['projects_no_access'][] = $project;
            }
        }

        // Need actions
        foreach ($contexts['projects_with_access'] as $project) {
            $project_role = null;
            foreach ($roles as $role) {
                if ($this->app['securilex']->isGranted($role, $project)) {
                    $project_role = $role;
                }
            }
            foreach ($project->upcoming() as $deployment) {
                $d = array(
                    'deployment' => $deployment,
                    'items' => array(),
                    'checklists' => array(),
                    'activities' => array(),
                );
                foreach ($deployment->items as $item) {
                    switch ($item->status()) {
                        case Project::ITEM_STATUS_INIT:
                        case Project::ITEM_STATUS_REJECTED:
                        case Project::ITEM_STATUS_FAILED:
                            if ($project_role == 'entry') {
                                $d['items'][] = $item;
                            }
                            break;

                        case Project::ITEM_STATUS_REVIEW:
                            if ($project_role == 'review' || $project_role == 'approval') {
                                $d['items'][] = $item;
                            }
                            break;

                        case Project::ITEM_STATUS_APPROVAL :
                            if ($project_role == 'approval') {
                                $d['items'][] = $item;
                            }
                            break;
                        case Project::ITEM_STATUS_READY :
                            if ($project_role == 'execute') {
                                foreach ($item->activities as $activity) {
                                    if ($activity->runitem->status != 'New') {
                                        continue;
                                    }
                                    if (!isset($d['activities'][$activity->runitem->template->id])) {
                                        $d['activities'][$activity->runitem->template->id]
                                            = array(
                                            'status' => Project::ITEM_STATUS_READY,
                                            'template' => $activity->runitem->template,
                                            'runitems' => array(),
                                        );
                                    }
                                    $d['activities'][$activity->runitem->template->id]['runitems'][$activity->runitem->id]
                                        = $activity->runitem;
                                }
                            }
                            break;
                    }
                }
                foreach ($deployment->checklists as $checklist) {
                    if (!$checklist->isPending()) {
                        continue;
                    } elseif ($checklist->pics->contains($this->app->userEntity())) {
                        $d['checklists'][] = $checklist;
                    }
                }
                if ((!empty($d['items']) || !empty($d['checklists']) || !empty($d['activities']))) {
                    $contexts['need_actions'][] = $d;
                }
            }
        }
        $contexts['has_actions'] = !empty($contexts['need_actions']);

        return $this->render('home', $contexts);
    }

    public function archived(Request $request)
    {
        $this->addCrumb('Archived Projects', $this->app->path('archived'), 'archive');
        $projects = $this->app['datastore']->queryMany('\Renogen\Entity\Project',
            array('archived' => true),
            array('title' => 'ASC')
        );
        return $this->render('archived', array('projects' => $projects));
    }
}