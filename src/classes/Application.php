<?php

namespace Renogen;

define('ROOTDIR', realpath(__DIR__.'/../..'));

use DateTime;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Proxy\AbstractProxyFactory;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\DebugStack;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\Setup;
use Exception;
use Renogen\ActivityTemplate\BaseClass;
use Renogen\Auth\Driver\LDAP;
use Renogen\Auth\Driver\Password;
use Renogen\Controller\Activity;
use Renogen\Controller\Admin;
use Renogen\Controller\Attachment;
use Renogen\Controller\Deployment;
use Renogen\Controller\Home;
use Renogen\Controller\Item;
use Renogen\Controller\Project;
use Renogen\Controller\Runbook;
use Renogen\Controller\Template;
use Renogen\Entity\AuthDriver;
use Renogen\Entity\User;
use Securilex\Authorization\SecuredAccessVoter;
use Securilex\Authorization\SubjectPrefixVoter;
use Securilex\Doctrine\DoctrineMutableUserProvider;
use Securilex\Firewall;
use Securilex\ServiceProvider;
use Silex\Application\UrlGeneratorTrait;
use Silex\Provider\ServiceControllerServiceProvider;
use Silex\Provider\SessionServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The main Application class for GitSync. This class is the entrypoint for all
 * request handlings within GitSync.
 */
class Application extends \Silex\Application
{

    use UrlGeneratorTrait;
    const PROJECT_ROLES = array('none', 'view', 'entry', 'review', 'approval',
        'execute');

    static protected $instance;
    protected $_templateClasses = array();
    protected $_pluginClasses   = array();
    protected $_authClassNames  = array();
    protected $security;
    protected $admin_route      = null;

    public function __construct($values = array())
    {
        parent::__construct($values);

        $app = $this;
        $app->register(new UrlGeneratorServiceProvider());
        $app->register(new ServiceControllerServiceProvider());
        $app->register(new SessionServiceProvider());

        /* Doctrine */
        $app['db'] = $app->share(function () {
            return DriverManager::getConnection(array(
                    'dbname' => getenv('DB_NAME') ?: 'renogen',
                    'user' => getenv('DB_USER') ?: 'renogen',
                    'password' => getenv('DB_PASSWORD') ?: 'reno123gen',
                    'host' => getenv('DB_HOST') ?: 'localhost',
                    'port' => getenv('DB_PORT') ?: '3306',
                    'driver' => 'pdo_mysql',
            ));
        });

        $app['em'] = $app->share(function () use ($app) {
            $config = Setup::createAnnotationMetadataConfiguration(array(
                    __DIR__), $app['debug']);

            if ($app['debug']) {
                $cache = new ArrayCache;
                $config->setAutoGenerateProxyClasses(AbstractProxyFactory::AUTOGENERATE_ALWAYS);
                $config->setSQLLogger(new DebugStack());
            } else {
                $cache = new ArrayCache;
                $config->setAutoGenerateProxyClasses(AbstractProxyFactory::AUTOGENERATE_FILE_NOT_EXISTS);
            }

            $config->setMetadataCacheImpl($cache);
            $config->setQueryCacheImpl($cache);
            $config->setProxyDir(__DIR__.'/Entity/Proxy');
            $config->setProxyNamespace('Renogen\Entity\Proxy');

            return EntityManager::create($app['db'], $config);
        });

        if (!$app['db']->getSchemaManager()->tablesExist('projects')) {
            $this->initializeOrRefreshDatabaseSchemas();
        }

        /* Data Store */
        $app->register(new DataStore());

        /* Twig Template Engine */
        $app->register(new TwigServiceProvider(), array(
            'twig.path' => realpath(__DIR__."/../views"),
        ));
        // for plugins
        $app['twig.loader.filesystem']->addPath(realpath(__DIR__."/Plugin"), 'plugin');


        /* Security */
        foreach (glob(__DIR__.'/Auth/Driver/*.php') as $fn) {
            $shortName = basename($fn, '.php');
            $className = 'Renogen\Auth\Driver\\'.$shortName;
            $classId   = strtolower($shortName);

            $this->_authClassNames[$classId] = $className;
        }

        /* Init activity template classes */
        foreach (glob(__DIR__.'/ActivityTemplate/Impl/*.php') as $templateClass) {
            $templateClassName = 'Renogen\ActivityTemplate\Impl\\'.basename($templateClass, '.php');
            $this->addActivityTemplateClass(new $templateClassName($this));
        }
        uasort($this->_templateClasses, function($a, $b) {
            return strcmp($a->classTitle(), $b->classTitle());
        });

        foreach (glob(__DIR__.'/Plugin/*', GLOB_ONLYDIR) as $plugin) {
            $plugin                        = basename($plugin);
            $pclass                        = "\\Renogen\\Plugin\\$plugin\\Core";
            $this->_pluginClasses[$plugin] = array(
                'name' => $plugin,
                'class' => $pclass,
                'title' => $pclass::getPluginTitle(),
            );
        }

        static::$instance = $this;
    }

    public function activateSecurity()
    {
        if ($this->security) {
            return;
        }
        $this->security = new ServiceProvider();
        $this->firewall = new Firewall('/', 'login');

        /* @var $em EntityManager */
        $em = $this['em'];

        foreach ($em->getRepository('\Renogen\Entity\AuthDriver')->findAll() as $driver) {
            $driverName = $driver->name;
            $className  = $driver->class;
            if (($errors     = $className::checkParams($driver->parameters))) {
                print_r($errors);
                print json_encode($driver->parameters);
                exit;
            }
            $driverClass = new $className($driver->parameters ?: array());
            $this->firewall->addAuthenticationFactory($driverClass->getAuthenticationFactory(), new DoctrineMutableUserProvider($em, '\Renogen\Entity\User', 'username', array(
                'auth' => $driverName)));
        }

        $this->security->addFirewall($this->firewall);
        $this->security->addAuthorizationVoter(new SecuredAccessVoter());
        $this->security->addAuthorizationVoter(SubjectPrefixVoter::instance());
        $this->register($this->security);

        SubjectPrefixVoter::instance()
            ->addSubjectPrefix(array('admin', 'project_create'), 'ROLE_ADMIN');

        /* Login page (not using controller because too simple) */
        $this->get('/login/', function(Request $request) {
            return $this['twig']->render("login.twig", array(
                    'error' => $this['security.last_error']($request),
            ));
        })->bind('login');

        $this->before(function(Request $request, \Silex\Application $app) {
            if (!$app['securilex']->getFirewall()) {
                return;
            }

            if (($routeName = $request->get('_route')) &&
                !$app['securilex']->isGranted('prefix', $routeName)) {
                throw new AccessDeniedException();
            }
        });

        $this->error(function (Exception $e, $code) {
            $error = array(
                'message' => $e->getMessage(),
            );

            if ($e instanceof AccessDeniedHttpException) {
                $error['message'] = 'You are not authorized to access this page.';
                $error['title']   = 'Access Denied';
            }

            return $this['twig']->render("exception.twig", array(
                    'error' => $error,
            ));
        });
    }

    protected function configureRoutes()
    {
        /* Routes: Home */
        $this['home.controller'] = $this->share(function() {
            return new Home($this);
        });
        $this->match('/', 'home.controller:index')->bind('home');

        /* Routes: Admin */
        $this['admin.controller'] = $this->share(function() {
            return new Admin($this);
        });
        $this->admin_route = 'admin_index';
        $this->match('/!/', 'admin.controller:index')->bind('admin_index');
        $this->match('/!/users/', 'admin.controller:users')->bind('admin_users');
        $this->match('/!/users/+', 'admin.controller:user_create')->bind('admin_user_add');
        $this->match('/!/users/{username}/', 'admin.controller:user_edit')->bind('admin_user_edit');
        $this->match('/!/auth/', 'admin.controller:auth')->bind('admin_auth');
        $this->match('/!/auth/{driver}', 'admin.controller:auth_edit')->bind('admin_auth_edit');

        /* Routes: Project */
        $this['project.controller'] = $this->share(function() {
            return new Project($this);
        });
        $this->match('/+', 'project.controller:create')->bind('project_create');
        $this->match('/{project}/', 'project.controller:view')->bind('project_view');
        $this->match('/{project}/edit', 'project.controller:edit')->bind('project_edit');
        $this->match('/{project}/past', 'project.controller:past')->bind('project_past');

        /* Routes: Plugins */
        $this['plugin.controller'] = $this->share(function() {
            return new Controller\Plugin($this);
        });
        $this->match('/{project}/plugins', 'plugin.controller:index')->bind('plugin_index');

        foreach ($this->_pluginClasses as $plugin => $details) {
            $this["plugin.$plugin.controller"] = $this->share(function() use ($details) {
                $ctrlname = '\\Renogen\\Plugin\\'.$details['name'].'\\Controller';
                return new $ctrlname($this);
            });
            $this->match("/{project}/plugins/$plugin", "plugin.$plugin.controller:configure")->bind("plugin_{$plugin}_configure");
        }

        /* Routes: Template */
        $this['template.controller'] = $this->share(function() {
            return new Template($this);
        });
        $this->match('/{project}/templates/', 'template.controller:index')->bind('template_list');
        $this->match('/{project}/templates/+', 'template.controller:create')->bind('template_create');
        $this->match('/{project}/templates/{template}/', 'template.controller:edit')->bind('template_edit');

        /* Routes: Deployment */
        $this['deployment.controller'] = $this->share(function() {
            return new Deployment($this);
        });
        $this->match('/{project}/+', 'deployment.controller:create')->bind('deployment_create');
        $this->match('/{project}/{deployment}/', 'deployment.controller:view')->bind('deployment_view');
        $this->match('/{project}/{deployment}/edit', 'deployment.controller:edit')->bind('deployment_edit');
        $this->match('/{project}/{deployment}/releasenote', 'deployment.controller:release_note')->bind('release_note');

        /* Routes: Run Book */
        $this['runbook.controller'] = $this->share(function() {
            return new Runbook($this);
        });
        $this->match('/{project}/{deployment}/runbook', 'runbook.controller:view')->bind('runbook_view');
        $this->match('/{project}/{deployment}/runbook/{runitem}/completed', 'runbook.controller:runitem_completed')->bind('runitem_completed');
        $this->match('/{project}/{deployment}/runbook/{runitem}/failed', 'runbook.controller:runitem_failed')->bind('runitem_failed');
        $this->match('/{project}/{deployment}/runbook/{runitem}/{file}', 'runbook.controller:download_file')->bind('runitem_file_download');

        /* Routes: Item */
        $this['item.controller'] = $this->share(function() {
            return new Item($this);
        });
        $this->match('/{project}/{deployment}/+', 'item.controller:create')->bind('item_create');
        $this->match('/{project}/{deployment}/{item}/', 'item.controller:view')->bind('item_view');
        $this->match('/{project}/{deployment}/{item}/edit', 'item.controller:edit')->bind('item_edit');
        $this->match('/{project}/{deployment}/{item}/submit', 'item.controller:action')->value('action', 'submit')->bind('item_submit');
        $this->match('/{project}/{deployment}/{item}/approve', 'item.controller:action')->value('action', 'approve')->bind('item_approve');
        $this->match('/{project}/{deployment}/{item}/unapprove', 'item.controller:action')->value('action', 'unapprove')->bind('item_unapprove');
        $this->match('/{project}/{deployment}/{item}/reject', 'item.controller:action')->value('action', 'reject')->bind('item_reject');
        $this->match('/{project}/{deployment}/{item}/changeStatus', 'item.controller:changeStatus')->bind('item_change_status');

        /* Routes: Attachment */
        $this['attachment.controller'] = $this->share(function() {
            return new Attachment($this);
        });
        $this->match('/{project}/{deployment}/{item}/attachments', 'attachment.controller:create')->bind('attachment_create');
        $this->match('/{project}/{deployment}/{item}/attachments/{attachment}/', 'attachment.controller:download')->bind('attachment_download');
        $this->match('/{project}/{deployment}/{item}/attachments/{attachment}/edit', 'attachment.controller:edit')->bind('attachment_edit');

        /* Routes: Comment */
        $this->match('/{project}/{deployment}/{item}/comments', 'item.controller:comment_add')->bind('item_comment_add');
        $this->match('/{project}/{deployment}/{item}/comments/{comment}/delete', 'item.controller:comment_delete')->bind('item_comment_delete');
        $this->match('/{project}/{deployment}/{item}/comments/{comment}/undelete', 'item.controller:comment_undelete')->bind('item_comment_undelete');

        /* Routes: Activity */
        $this['activity.controller'] = $this->share(function() {
            return new Activity($this);
        });
        $this->match('/{project}/{deployment}/{item}/+', 'activity.controller:create')->bind('activity_create');
        $this->match('/{project}/{deployment}/{item}/{activity}/', 'activity.controller:edit')->bind('activity_edit');
        $this->match('/{project}/{deployment}/{item}/{activity}/{file}', 'activity.controller:download_file')->bind('activity_file_download');
    }

    /**
     * Get the logged in user, null if security is not enabled
     * @return UserInterface
     */
    public function user()
    {
        return (isset($this['user']) ? $this['user'] : null);
    }

    public function userEntity($username = null)
    {
        if (!$username) {
            $username = (isset($this['user']) && !empty($this['user']) ? $this['user']->getUsername()
                    : null);
        }
        if (!$username) {
            return null;
        }
        return $this['em']->getRepository('\Renogen\Entity\User')->find($username);
    }

    static public function execute($debug = false)
    {
        $app          = new static();
        $app['debug'] = $debug;
        $app->activateSecurity();
        $app->configureRoutes();
        $app->run();
    }

    public function getBaseUri($append = '')
    {
        if (substr($append, 1, 1) != '/') {
            $append = '/'.$append;
        }
        return $this['request_stack']->getMasterRequest()->getBaseUrl().$append;
    }

    public function addFlashMessage($message, $title = '', $type = 'notice',
                                    $persistent = false)
    {
        $this['session']->getFlashBag()->add('message', array(
            'title' => $title,
            'text' => $message,
            'type' => $type,
            'persistent' => $persistent,
        ));
    }

    public function title()
    {
        return strtok(get_class(), '\\');
    }

    public function icon()
    {
        return 'magic';
    }

    public function logo()
    {
        return null;
        //return $this['request']->getBaseUrl().'/ui/logo.png';
    }

    /**
     *
     * @return static
     */
    static public function instance()
    {
        return static::$instance ?: new static();
    }

    public function initializeOrRefreshDatabaseSchemas()
    {
        /* @var $em EntityManager */
        $em = $this['em'];

        // for specific database platform
        switch ($em->getConnection()->getDatabasePlatform()->getName()) {
            case "mysql":
                $em->getConnection()->exec("SET foreign_key_checks = 0");
                break;
        }
        $tool    = new SchemaTool($em);
        $classes = array();
        foreach (glob(__DIR__.'/Entity/*.php') as $entityfn) {
            $classes[] = $this['em']->getClassMetadata('Renogen\Entity\\'.basename($entityfn, '.php'));
        }

        // update once
        $tool->updateSchema($classes, true);
        // update twice to ensure foreign keys are mapped completely
        $tool->updateSchema($classes, true);

        if (count($em->getRepository('\Renogen\Entity\AuthDriver')->findAll()) == 0) {
            $auth_password               = new AuthDriver('password');
            $auth_password->class        = Password::class;
            $auth_password->created_date = new DateTime();
            $auth_password->parameters   = array();
            $em->persist($auth_password);

            if (getenv("LDAP_HOST")) {
                $auth_ldap               = new AuthDriver('gems');
                $auth_ldap->class        = LDAP::class;
                $auth_ldap->created_date = new DateTime();
                $auth_ldap->parameters   = array(
                    "host" => getenv("LDAP_HOST"),
                    "port" => getenv("LDAP_PORT") ?: 389,
                    "dn" => getenv("LDAP_DN"),
                );
                $em->persist($auth_ldap);
            }

            $em->flush();
        }

        $has_admin = false;
        foreach ($em->getRepository('\Renogen\Entity\User')->findAll() as $user) {
            if (in_array('ROLE_ADMIN', $user->roles)) {
                $has_admin = true;
                break;
            }
        }

        // if no admin then create new admin user admin/admin123
        if (!$has_admin) {
            $newUser            = new User();
            $newUser->username  = 'admin';
            $newUser->shortname = 'Administrator';
            $newUser->password  = 'admin'.date_format(new DateTime(), 'Ymd');
            $newUser->roles     = array('ROLE_ADMIN');
            $newUser->auth      = 'password';

            $this->addFlashMessage("Auto-created administrator id '{$newUser->username}' with password '{$newUser->password}'", "Administrator id", 'notice', true);

            $authClass = $this->getAuthDriver('password');
            $authClass->prepareNewUser($newUser);
            $em->persist($newUser);
            $em->flush($newUser);
        }
    }

    public function getAuthDriver($classId)
    {
        /* @var $em EntityManager */
        $em   = $this['em'];
        if (($auth = $em->getRepository('\Renogen\Entity\AuthDriver')->find($classId))) {
            /* @var $auth AuthDriver */
            $authDriver = new $auth->class($auth->parameters ?: array());
            return $authDriver;
        }
        return null;
    }

    public function getAuthClassNames()
    {
        return $this->_authClassNames;
    }

    public function addActivityTemplateClass(BaseClass $templateClass)
    {
        $this->_templateClasses[get_class($templateClass)] = $templateClass;
    }

    /**
     *
     * @param type $name
     * @return BaseClass|array
     */
    public function getActivityTemplateClass($name = null)
    {
        if (empty($name)) {
            return $this->_templateClasses;
        } else {
            return (!isset($this->_templateClasses[$name]) ? null : $this->_templateClasses[$name]);
        }
    }

    public function getAdminRoute()
    {
        return $this->admin_route;
    }

    public function getBaseUrl()
    {
        return $this->path('home');
    }

    public function entityParams(Base\Entity $entity)
    {
        if ($entity instanceof Entity\Project) {
            return array(
                'project' => $entity->name,
            );
        } elseif ($entity instanceof Entity\Deployment) {
            return $this->entityParams($entity->project) + array(
                'deployment' => $entity->execute_date->format('YmdHi'),
            );
        } elseif ($entity instanceof Entity\Item) {
            return $this->entityParams($entity->deployment) + array(
                'item' => $entity->id,
            );
        } elseif ($entity instanceof Entity\Activity) {
            return $this->entityParams($entity->item) + array(
                'activity' => $entity->id,
            );
        } elseif ($entity instanceof Entity\ActivityFile) {
            return $this->entityParams($entity->activity) + array(
                'file' => $entity->id,
            );
        } elseif ($entity instanceof Entity\ItemComment) {
            return $this->entityParams($entity->item) + array(
                'comment' => $entity->id,
            );
        } elseif ($entity instanceof Entity\Attachment) {
            return $this->entityParams($entity->item) + array(
                'attachment' => $entity->id,
            );
        } elseif ($entity instanceof Entity\Template) {
            return $this->entityParams($entity->project) + array(
                'template' => $entity->id,
            );
        } elseif ($entity instanceof Entity\RunItem) {
            return $this->entityParams($entity->deployment) + array(
                'runitem' => $entity->id,
            );
        } elseif ($entity instanceof Entity\RunItemFile) {
            return $this->entityParams($entity->runitem) + array(
                'file' => $entity->id,
            );
        } else {
            return array();
        }
    }

    public function entity_path($path, Base\Entity $entity,
                                array $extras = array())
    {
        return $this->path($path, $this->entityParams($entity) + $extras);
    }

    public function redirect($path = null, Array $params = array(),
                             $anchor = null)
    {
        return new RedirectResponse($path ? $this->path($path, $params).
            ($anchor ? "#$anchor" : "") : $this['request']->getUri());
    }

    public function entity_redirect($path, Base\Entity $entity, $anchor = null)
    {
        return $this->redirect($path, $this->entityParams($entity), $anchor);
    }
}