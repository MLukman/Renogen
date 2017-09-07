<?php

namespace Renogen;

//include_once __DIR__.'/../constants.php';


use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Proxy\AbstractProxyFactory;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\DebugStack;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\Setup;
use Securilex\Authentication\Factory\AuthenticationFactoryInterface;
use Securilex\Authorization\SecuredAccessVoter;
use Securilex\Firewall;
use Securilex\ServiceProvider;
use Silex\Application\UrlGeneratorTrait;
use Silex\Provider\ServiceControllerServiceProvider;
use Silex\Provider\SessionServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * The main Application class for GitSync. This class is the entrypoint for all
 * request handlings within GitSync.
 */
class Application extends \Silex\Application
{
    static protected $instance;

    use UrlGeneratorTrait;

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

        /* Controllers */
        $this['home.controller'] = $this->share(function() {
            return new Controller\Home($this);
        });
        $this['project.controller'] = $this->share(function() {
            return new Controller\Project($this);
        });
        $this['deployment.controller'] = $this->share(function() {
            return new Controller\Deployment($this);
        });
        $this['item.controller'] = $this->share(function() {
            return new Controller\Item($this);
        });

        /* Add routes */
        $this->match('/', 'home.controller:index')->bind('home');
        $this->match('/!project', 'project.controller:create')->bind('project_create');
        $this->match('/{project}/', 'project.controller:view')->bind('project_view');
        $this->match('/{project}/!edit', 'project.controller:edit')->bind('project_edit');
        $this->match('/{project}/!deployment', 'deployment.controller:create')->bind('deployment_create');
        $this->match('/{project}/{deployment}/', 'deployment.controller:view')->bind('deployment_view');
        $this->match('/{project}/{deployment}/!edit', 'deployment.controller:edit')->bind('deployment_edit');
        $this->match('/{project}/{deployment}/!item', 'item.controller:create')->bind('item_create');
        $this->match('/{project}/{deployment}/{item}', 'item.controller:view')->bind('item_view');
        $this->match('/{project}/{deployment}/{item}/!edit', 'item.controller:edit')->bind('item_edit');

        static::$instance = $app;
    }

    /**
     * Activate security using the provided Authentication Factory and User Provider.
     * It is possible to use multiple pairs of Authentication Factory and User Provider
     * by calling this method multiple times.
     *
     * @param AuthenticationFactoryInterface $authFactory
     * @param UserProviderInterface $userProvider
     */
    public function activateSecurity(AuthenticationFactoryInterface $authFactory,
                                     UserProviderInterface $userProvider)
    {
        if (!$this->security) {
            $this->security = new ServiceProvider();
            $this->firewall = new Firewall('/', '/login/');
            $this->firewall->addAuthenticationFactory($authFactory, $userProvider);
            $this->security->addFirewall($this->firewall);
            $this->security->addAuthorizationVoter(new SecuredAccessVoter());
            $this->register($this->security);

            /* Auth controller */
            $this['auth.controller'] = $this->share(function() {
                return new Controller\Auth($this);
            });

            /* Add routes */
            $this->match('/login/', 'auth.controller:login')->bind('login');
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

    static public function instance()
    {
        if (!static::$instance) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    public function initializeOrRefreshDatabaseSchemas()
    {
        $tool    = new \Doctrine\ORM\Tools\SchemaTool($this['em']);
        $classes = array();
        foreach (glob(__DIR__.'/Entity/*.php') as $entityfn) {
            $classes[] = $this['em']->getClassMetadata('Renogen\Entity\\'.basename($entityfn, '.php'));
        }

        // update once
        $tool->updateSchema($classes, true);
        // update twice to ensure foreign keys are mapped completely
        $tool->updateSchema($classes, true);
    }
}