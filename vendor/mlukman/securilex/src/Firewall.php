<?php
/**
 * This file is part of the Securilex library for Silex framework.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package Securilex
 * @author Muhammad Lukman Nasaruddin <anatilmizun@gmail.com>
 * @link https://github.com/MLukman/Securilex Securilex Github
 * @link https://packagist.org/packages/mlukman/securilex Securilex Packagist
 */

namespace Securilex;

use Securilex\Authentication\AuthenticationDriverInterface;
use Securilex\Authentication\Factory\AuthenticationFactoryInterface;
use Symfony\Component\Security\Core\User\ChainUserProvider;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\EntryPoint\BasicAuthenticationEntryPoint;
use Symfony\Component\Security\Http\EntryPoint\FormAuthenticationEntryPoint;
use Symfony\Component\Security\Http\Firewall\ContextListener;

/**
 * Firewall holds configurations on a secured area of your application and
 * the authentication mechanism to allow user to login.
 */
class Firewall implements FirewallInterface
{
    /**
     * The patterns (or simple path) to firewall
     * @var string[]
     */
    protected $patterns = array();

    /**
     * The list of user providers
     * @var UserProviderInterface[]
     */
    protected $userProviders = array();

    /**
     * The list of authentication factories
     * @var array
     */
    protected $authFactories = array();

    /**
     * The path to access login form
     * @var string
     */
    protected $loginPath = null;

    /**
     * The path to perform checking of user login
     * @var string
     */
    protected $loginCheckPath = null;

    /**
     * The path to trigger user logout
     * @var string
     */
    protected $logoutPath = null;

    /**
     * The name of this firewall instance
     * @var string
     */
    protected $name = null;

    /**
     * The Securilex service provider that registers this firewall. Remain null until this firewall is registered.
     * @var ServiceProvider
     */
    protected $provider = null;

    /**
     * Construct firewall instance.
     * @param array|string $patterns
     * @param string $loginPath
     */
    public function __construct($patterns, $loginPath = null)
    {
        if (!is_array($patterns)) {
            $patterns = array($patterns);
        }
        foreach ($patterns as &$pattern) {
            if (substr($pattern, 0, 1) != '^') {
                $pattern = '^'.$pattern;
            }
        }
        $this->patterns  = $patterns;
        $this->loginPath = $loginPath;
        $this->name      = md5(json_encode($this->patterns));

        // generate paths
        $this->generatePaths();
    }

    /**
     * Static factory method
     * @param array|string $patterns
     * @param string $loginPath
     * @return self
     */
    static public function create($patterns, $loginPath = null)
    {
        return new static($patterns, $loginPath);
    }

    /**
     * Get the generated name of this firewall
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Check if the provided path is covered by this firewall or not
     * @param string $path
     * @return boolean
     */
    public function isPathCovered($path)
    {
        foreach ($this->patterns as $pattern) {
            if (1 === preg_match('{'.$pattern.'}', $path)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get login check path.
     * @return string
     */
    public function getLoginCheckPath()
    {
        return $this->loginCheckPath;
    }

    /**
     * Get logout path.
     * @return string
     */
    public function getLogoutPath()
    {
        return $this->logoutPath;
    }

    /**
     * Add additional authentication factory and corresponding user provider.
     * @param AuthenticationFactoryInterface $authFactory
     * @param UserProviderInterface $userProvider
     * @return $this Returning $this to allow method chaining
     */
    public function addAuthenticationFactory(AuthenticationFactoryInterface $authFactory,
                                             UserProviderInterface $userProvider)
    {
        $id = $authFactory->getId();

        $this->authFactories[$id] = array(
            'factory' => $authFactory,
            'userProvider' => $userProvider,
        );

        $this->userProviders[] = $userProvider;

        if ($this->provider) {
            $this->registerAuthenticationFactory($this->provider->getApp(), $id, $authFactory, $userProvider);
        }

        return $this;
    }

    /**
     * Add additional authentication driver that is both authentication factory and user provider.
     * @param AuthenticationDriverInterface $driver
     * @return $this Returning $this to allow method chaining
     */
    public function addAuthenticationDriver(AuthenticationDriverInterface $driver)
    {
        return $this->addAuthenticationFactory($driver, $driver);
    }

    /**
     * Register the Firewall
     * @param ServiceProvider $provider Service Provider
     */
    public function register(ServiceProvider $provider)
    {
        $this->provider = $provider;

        if ($this->loginPath) {
            $this->provider->addUnsecurePattern("^{$this->loginPath}$");
        }

        $config = array(
            'logout' => array('logout_path' => $this->logoutPath),
            'pattern' => implode('|', $this->patterns)
        );

        $app = $this->provider->getApp();

        foreach ($this->authFactories as $id => $authFactory) {
            $this->registerAuthenticationFactory($app, $id, $authFactory['factory'], $authFactory['userProvider']);
            $config[$id] = array();
            if ($this->loginPath) {
                $config[$id]['login_path'] = $this->loginPath;
                $config[$id]['check_path'] = $this->loginCheckPath;
            }
        }

        $this->registerUserProvider($app);
        $this->registerContextListener($app);
        $this->registerEntryPoint($app);

        $this->provider->appendFirewallConfig($this->name, $config);
    }

    /**
     * Register authentication factory and user provider.
     * @param \Silex\Application $app
     * @param string $id
     * @param AuthenticationFactoryInterface $authFactory
     * @param UserProviderInterface $userProvider
     */
    protected function registerAuthenticationFactory(\Silex\Application $app,
                                                     $id,
                                                     AuthenticationFactoryInterface $authFactory,
                                                     UserProviderInterface $userProvider)
    {
        $fac = 'security.authentication_listener.factory.'.$id;
        if (isset($app[$fac])) {
            return;
        }

        $app[$fac] = $app->protect(function ($name, $options) use ($app, $id, $authFactory, $userProvider) {
            // the authentication type
            $type        = (isset($options['login_path']) ? 'form' : 'http');
            $entry_point = "security.entry_point.$name.$type";
            if ($type == 'form') {
                $options['failure_forward'] = true;
            }

            // the authentication provider id
            $auth_provider       = "security.authentication_provider.$name.$id";
            $app[$auth_provider] = $app->share(function () use ($app, $name, $authFactory, $userProvider) {
                return $authFactory->createAuthenticationProvider($app, $userProvider, $name);
            });

            // the authentication listener id
            $auth_listener = "security.authentication_listener.$name.$type";
            if (!isset($app[$auth_listener])) {
                $app[$auth_listener] = $app["security.authentication_listener.$type._proto"]($name, $options);
            }

            return array($auth_provider, $auth_listener, $entry_point, 'pre_auth');
        });
    }

    /**
     * Register User Provider
     * @param \Silex\Application $app
     */
    protected function registerUserProvider(\Silex\Application $app)
    {
        $user_provider       = 'security.user_provider.'.$this->name;
        $app[$user_provider] = $app->share(function () {
            return new ChainUserProvider($this->userProviders);
        });
        return $user_provider;
    }

    /**
     * Register Context Listener
     * @param \Silex\Application $app
     */
    protected function registerContextListener(\Silex\Application $app)
    {
        $context_listener       = 'security.context_listener.'.$this->name;
        $app[$context_listener] = $app->share(function () use ($app) {
            return new ContextListener(
                $app['security.token_storage'], $this->userProviders, $this->name, $app['logger'], $app['dispatcher']
            );
        });
        return $context_listener;
    }

    /**
     * Register Entry Point
     * @param \Silex\Application $app
     */
    protected function registerEntryPoint(\Silex\Application $app)
    {
        $entry_point       = 'security.entry_point.'.$this->name.
            (empty($this->loginPath) ? '.http' : '.form');
        $app[$entry_point] = $app->share(function () use ($app) {
            return $this->loginPath ?
                new FormAuthenticationEntryPoint($app, $app['security.http_utils'], $this->loginPath, true)
                    :
                new BasicAuthenticationEntryPoint('Secured');
        });
        return $entry_point;
    }

    /**
     * Generate loginCheckPath & logoutPath.
     */
    protected function generatePaths()
    {
        if ($this->loginPath && !$this->loginCheckPath) {
            foreach ($this->patterns as $pattern) {
                // remove the ^ prefix
                $base = substr($pattern, 1);
                // skip a regex pattern
                if (preg_quote($base) != $base) {
                    continue;
                }
                // now that we found one
                if (substr($base, -1) != '/') {
                    $base .= '/';
                }
                $this->loginCheckPath = $base.'login_check';
                $this->logoutPath     = $base.'logout';
                break;
            }
            // unable to generate since all patterns are regex
            if (!$this->loginCheckPath) {
                static $underscorePad = 0;
                $underscorePad++;
                $this->loginCheckPath = '/'.str_repeat('_', $underscorePad).'login_check';
                $this->logoutPath     = '/'.str_repeat('_', $underscorePad).'logout';
                $this->patterns[]     = "^{$this->loginCheckPath}$";
                $this->patterns[]     = "^{$this->logoutPath}$";
            }
        }
    }
}