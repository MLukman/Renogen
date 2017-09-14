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
use Renogen\Controller\Attachment;
use Renogen\Controller\Deployment;
use Renogen\Controller\Home;
use Renogen\Controller\Item;
use Renogen\Controller\Project;
use Renogen\Controller\Runbook;
use Renogen\Controller\Template;
use Securilex\Authentication\Factory\AuthenticationFactoryInterface;
use Securilex\Authentication\Factory\PlaintextPasswordAuthenticationFactory;
use Securilex\Authentication\User\SQLite3UserProvider;
use Securilex\Authorization\SecuredAccessVoter;
use Securilex\Firewall;
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
    static protected $instance;
    protected $_templateClasses = array();
    protected $security;

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

        $authFactory  = new PlaintextPasswordAuthenticationFactory();
        $userProvider = new SQLite3UserProvider(new \SQLite3($sqlitefile));
        $this->activateSecurity($authFactory, $userProvider);


        /* Routes: Home */
        $this['home.controller'] = $this->share(function() {
            return new Home($this);
        });
        $this->match('/', 'home.controller:index')->bind('home');

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

        /* Routes: Run Book */
        $this['runbook.controller'] = $this->share(function() {
            return new Runbook($this);
        });
        $this->match('/{project}/{deployment}/*', 'runbook.controller:view')->bind('runbook_view');

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

        /* Routes: Activity */
        $this['activity.controller'] = $this->share(function() {
            return new Activity($this);
        });
        $this->match('/{project}/{deployment}/{item}/+', 'activity.controller:create')->bind('activity_create');
        $this->match('/{project}/{deployment}/{item}/{activity}/', 'activity.controller:edit')->bind('activity_edit');

        /* Init activity template classes */
        $this->addActivityTemplateClass(new Rundeck($this));

        static::$instance = $this;
    }

    public function activateSecurity(AuthenticationFactoryInterface $authFactory,
                                     UserProviderInterface $userProvider)
    {
        if (!$this->security) {
            $this->security = new \Securilex\ServiceProvider();
            $this->firewall = new Firewall('/', 'login');
            $this->firewall->addAuthenticationFactory($authFactory, $userProvider);
            $this->security->addFirewall($this->firewall);
            $this->security->addAuthorizationVoter(new SecuredAccessVoter());
            $this->register($this->security);

            /* Login page (not using controller because too simple) */
            $this->get('/login/', function(Request $request) {
                return $this['twig']->render("login.twig", array(
                        'error' => $this['security.last_error']($request),
                ));
            })->bind('login');
        } else {
            $this->firewall->addAuthenticationFactory($authFactory, $userProvider);
        }
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
        return $this['em']->getRepository('\Renogen\Entity\User')->find($username
                    ?: (isset($this['user']) ? $this['user']->getUsername() : null));
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
        return static::$instance;
    }

    public function initializeOrRefreshDatabaseSchemas()
    {
        $tool    = new SchemaTool($this['em']);
        $classes = array();
        foreach (glob(__DIR__.'/Entity/*.php') as $entityfn) {
            $classes[] = $this['em']->getClassMetadata('Renogen\Entity\\'.basename($entityfn, '.php'));
        }

        // update once
        $tool->updateSchema($classes, true);
        // update twice to ensure foreign keys are mapped completely
        $tool->updateSchema($classes, true);
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
}