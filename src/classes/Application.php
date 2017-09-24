<?php

namespace Renogen;

define('ROOTDIR', realpath(__DIR__.'/../..'));

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Proxy\AbstractProxyFactory;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\DebugStack;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\Setup;
use Renogen\ActivityTemplate\BaseClass;
use Renogen\ActivityTemplate\Impl\Rundeck;
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
use Securilex\Authentication\Factory\AuthenticationFactoryInterface;
use Securilex\Authorization\SecuredAccessVoter;
use Securilex\Doctrine\DoctrineMutableUserProvider;
use Securilex\Firewall;
use Securilex\ServiceProvider;
use Silex\Application\UrlGeneratorTrait;
use Silex\Provider\ServiceControllerServiceProvider;
use Silex\Provider\SessionServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * The main Application class for GitSync. This class is the entrypoint for all
 * request handlings within GitSync.
 */
class Application extends \Silex\Application
{

    use UrlGeneratorTrait;
    const PROJECT_ROLES = array('none', 'view', 'entry', 'approval', 'execute');

    static protected $instance;
    protected $_templateClasses = array();
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
        $sqlitefile = __DIR__.'/../../data/database.sqlite';
        $app['db']  = $app->share(function () use ($sqlitefile) {
            return DriverManager::getConnection(array(
                    'path' => $sqlitefile,
                    'driver' => 'pdo_sqlite',
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

        if (!file_exists($sqlitefile)) {
            $this->initializeOrRefreshDatabaseSchemas();
        }

        /* Twig Template Engine */
        $app->register(new TwigServiceProvider(), array(
            'twig.path' => realpath(__DIR__."/../views"),
        ));

        /* Security */
        foreach (glob(__DIR__.'/Auth/Driver/*.php') as $fn) {
            $shortName = basename($fn, '.php');
            $className = '\Renogen\Auth\Driver\\'.$shortName;
            $classId   = strtolower($shortName);

            $this->_authClassNames[$classId] = $className;
        }

        $this->activateSecurity();
        $this->configureRoutes();

        /* Init activity template classes */
        $this->addActivityTemplateClass(new Rundeck($this));

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
        $this->security->addAuthorizationVoter(\Securilex\Authorization\SubjectPrefixVoter::instance());
        $this->register($this->security);

        \Securilex\Authorization\SubjectPrefixVoter::instance()
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

            if (!($routeName = $request->get('_route')) ||
                $app['securilex']->isGranted('prefix', $routeName)) {
                return; // allow access
            }

            throw new \Symfony\Component\Security\Core\Exception\AccessDeniedException();
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
        $this->match('/admin/', 'admin.controller:index')->bind('admin_index');
        $this->match('/admin/users/', 'admin.controller:users')->bind('admin_users');
        $this->match('/admin/users/+', 'admin.controller:user_create')->bind('admin_user_add');
        $this->match('/admin/users/{username}/', 'admin.controller:user_edit')->bind('admin_user_edit');
        $this->match('/admin/auth/', 'admin.controller:auth')->bind('admin_auth');
        $this->match('/admin/auth/{driver}', 'admin.controller:auth_edit')->bind('admin_auth_edit');
        $this->admin_route = 'admin_index';

        /* Routes: Project */
        $this['project.controller'] = $this->share(function() {
            return new Project($this);
        });
        $this->match('/+', 'project.controller:create')->bind('project_create');
        $this->match('/{project}/', 'project.controller:view')->bind('project_view');
        $this->match('/{project}/!', 'project.controller:edit')->bind('project_edit');

        /* Routes: Template */
        $this['template.controller'] = $this->share(function() {
            return new Template($this);
        });
        $this->match('/{project}/templates/', 'template.controller:index')->bind('template_list');
        $this->match('/{project}/templates/+', 'template.controller:create')->bind('template_create');
        $this->match('/{project}/templates/{template}/', 'template.controller:view')->bind('template_view');
        $this->match('/{project}/templates/{template}/!', 'template.controller:edit')->bind('template_edit');

        /* Routes: Deployment */
        $this['deployment.controller'] = $this->share(function() {
            return new Deployment($this);
        });
        $this->match('/{project}/+', 'deployment.controller:create')->bind('deployment_create');
        $this->match('/{project}/{deployment}/', 'deployment.controller:view')->bind('deployment_view');
        $this->match('/{project}/{deployment}/!', 'deployment.controller:edit')->bind('deployment_edit');
        $this->match('/{project}/{deployment}/*', 'deployment.controller:release_note')->bind('release_note');

        /* Routes: Run Book */
        $this['runbook.controller'] = $this->share(function() {
            return new Runbook($this);
        });
        $this->match('/{project}/{deployment}/**', 'runbook.controller:view')->bind('runbook_view');

        /* Routes: Item */
        $this['item.controller'] = $this->share(function() {
            return new Item($this);
        });
        $this->match('/{project}/{deployment}/+', 'item.controller:create')->bind('item_create');
        $this->match('/{project}/{deployment}/{item}/', 'item.controller:view')->bind('item_view');
        $this->match('/{project}/{deployment}/{item}/!', 'item.controller:edit')->bind('item_edit');
        $this->match('/{project}/{deployment}/{item}/!!', 'item.controller:action')->value('action', 'submit')->bind('item_submit');
        $this->match('/{project}/{deployment}/{item}/!!!', 'item.controller:action')->value('action', 'approve')->bind('item_approve');
        $this->match('/{project}/{deployment}/{item}/!!-', 'item.controller:action')->value('action', 'unapprove')->bind('item_unapprove');

        /* Routes: Attachment */
        $this['attachment.controller'] = $this->share(function() {
            return new Attachment($this);
        });
        $this->match('/{project}/{deployment}/{item}/@', 'attachment.controller:create')->bind('attachment_create');
        $this->match('/{project}/{deployment}/{item}/@/{attachment}/', 'attachment.controller:download')->bind('attachment_download');
        $this->match('/{project}/{deployment}/{item}/@/{attachment}/!', 'attachment.controller:edit')->bind('attachment_edit');

        /* Routes: Comment */
        $this->match('/{project}/{deployment}/{item}/@@', 'item.controller:comment_add')->bind('item_comment_add');
        $this->match('/{project}/{deployment}/{item}/@@/{comment}/-', 'item.controller:comment_delete')->bind('item_comment_delete');

        /* Routes: Activity */
        $this['activity.controller'] = $this->share(function() {
            return new Activity($this);
        });
        $this->match('/{project}/{deployment}/{item}/+', 'activity.controller:create')->bind('activity_create');
        $this->match('/{project}/{deployment}/{item}/{activity}/', 'activity.controller:edit')->bind('activity_edit');
        $this->match('/{project}/{deployment}/{item}/{activity}/@/{file}', 'activity.controller:download_file')->bind('activity_file_download');
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
        $app->run();
    }

    public function getRequestUri()
    {
        return $this['request_stack']->getMasterRequest()->getRequestUri();
    }

    public function addFlashMessage($message)
    {
        $this['session']->getFlashBag()->add('message', $message);
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
        $em      = $this['em'];
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
            $auth_password->class        = Auth\Driver\Password::class;
            $auth_password->created_date = new \DateTime();
            $auth_password->parameters   = array();
            $em->persist($auth_password);

            $auth_ldap               = new AuthDriver('gems');
            $auth_ldap->class        = Auth\Driver\LDAP::class;
            $auth_ldap->created_date = new \DateTime();
            $auth_ldap->parameters   = array(
                "host" => "10.41.86.223",
                "port" => 389,
                "dn" => "uid={username},ou=People,o=Telekom",
            );
            $em->persist($auth_ldap);

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
            $newUser->password  = 'admin'.date_format(new \DateTime(), 'Ymd');
            $newUser->roles     = array('ROLE_ADMIN');
            $newUser->auth      = 'password';

            $this->addFlashMessage("Auto-created administrator id '{$newUser->username}' with password '{$newUser->password}'");

            $authClass = $this->getAuthClass('password');
            $authClass->prepareNewUser($newUser);
            $em->persist($newUser);
            $em->flush($newUser);
        }
    }

    protected function getAuthClass($classId)
    {
        /* @var $em EntityManager */
        $em   = $this['em'];
        if (($auth = $em->getRepository('\Renogen\Entity\AuthDriver')->find($classId))) {
            /* @var $auth AuthDriver */
            $authClass = new $auth->class($auth->parameters ?: array());
            return $authClass;
        }
        return null;
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
}