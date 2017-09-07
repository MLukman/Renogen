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

use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

/**
 * SecurityServiceProvider is the core class to enable Securilex service in a Silex-powered application.
 *
 * Example:
 *     $app->register(new \Securilex\SecurityServiceProvider());
 *     $app['securilex']->addFirewall(...);
 *
 * Important: Securilex works best if registered after all routes have been defined.
 */
class SecurityServiceProvider extends \Silex\Provider\SecurityServiceProvider
{
    /**
     * The list of added firewalls
     * @var FirewallInterface[]
     */
    protected $firewalls = array();

    /**
     * The application instance
     * @var \Silex\Application
     */
    protected $app = null;

    /**
     * The firewall configurations
     * @var array
     */
    protected $firewallConfig = array();

    /**
     * The list of patterns to be excluded from security
     * @var array
     */
    protected $unsecuredPatterns = array();

    /**
     * The list of voters for authorization
     * @var VoterInterface[]
     */
    protected $voters = array();

    /**
     * Register with \Silex\Application.
     * @param \Silex\Application $app
     */
    public function register(\Silex\Application $app)
    {
        parent::register($app);

        // Register voters
        $app->extend('security.voters', function($voters) {
            return array_merge($voters, $this->voters);
        });

        // Register firewalls
        $this->app = $app;
        foreach ($this->firewalls as $firewall) {
            $firewall->register($this);
        }

        // Add reference to this in application instance
        $this->app['securilex'] = $this;
    }

    /**
     * Boot with Silex Application
     * @param \Silex\Application $app
     */
    public function boot(\Silex\Application $app)
    {
        $i         = 0;
        $firewalls = array();
        foreach ($this->unsecuredPatterns as $pattern => $v) {
            $firewalls['unsecured_'.($i++)] = array('pattern' => $pattern);
        }
        $finalConfig = array_merge($firewalls, $this->firewallConfig);

        $app['security.firewalls'] = $finalConfig;

        parent::boot($app);
    }

    /**
     * Add Firewall
     * @param FirewallInterface $firewall
     */
    public function addFirewall(FirewallInterface $firewall)
    {
        $this->firewalls[$firewall->getName()] = $firewall;

        if ($this->app) {
            $firewall->register($this);
        }
    }

    /**
     * Get the firewall with the specific path.
     * @param string $path
     * @return FirewallInterface
     */
    public function getFirewall($path = null)
    {
        if (!$path) {
            $path = $this->getCurrentPathRelativeToBase();
        }
        foreach ($this->firewalls as $firewall) {
            if ($firewall->isPathCovered($path)) {
                return $firewall;
            }
        }
        return null;
    }

    /**
     * Get login check path.
     * @return string
     */
    public function getLoginCheckPath()
    {
        $login_check = $this->app['request']->getBasePath();

        if (($firewall = $this->getFirewall($this->getCurrentPathRelativeToBase()))) {
            $login_check .= $firewall->getLoginCheckPath();
        }

        return $login_check;
    }

    /**
     * Get logout path.
     * @return string
     */
    public function getLogoutPath()
    {
        $logout = $this->app['request']->getBasePath();

        if (($firewall = $this->getFirewall($this->getCurrentPathRelativeToBase()))) {
            $logout .= $firewall->getLogoutPath();
        }

        return $logout;
    }

    /**
     * Get the Application
     * @return \Silex\Application
     */
    public function getApp()
    {
        return $this->app;
    }

    /**
     * Add a path pattern to list of unsecured paths.
     * @param string $pattern
     */
    public function addUnsecurePattern($pattern)
    {
        $this->unsecuredPatterns[$pattern] = true;
    }

    /**
     * Prepend additional data to firewall configuration.
     * @param string $name
     * @param mixed $config
     */
    public function prependFirewallConfig($name, $config)
    {
        $this->firewallConfig = array_merge(
            array($name => $config), $this->firewallConfig);
    }

    /**
     * Append additional data to firewall configuration.
     * @param string $name
     * @param mixed $config
     */
    public function appendFirewallConfig($name, $config)
    {
        $this->firewallConfig[$name] = $config;
    }

    /**
     * Add additional voter to authorization module.
     * @param VoterInterface $voter
     */
    public function addAuthorizationVoter(VoterInterface $voter)
    {
        if (!in_array($voter, $this->voters)) {
            $this->voters[] = $voter;
        }
    }

    /**
     * Check if the attributes are granted against the current authentication token and optionally supplied object.
     * @param mixed $attributes
     * @param mixed $object
     * @param boolean $catchException
     * @return boolean
     * @throws AuthenticationCredentialsNotFoundException
     */
    public function isGranted($attributes, $object = null,
                              $catchException = true)
    {
        try {
            return $this->app['security.authorization_checker']->isGranted($attributes, $object);
        } catch (AuthenticationCredentialsNotFoundException $e) {
            if ($catchException) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Get current path relative to base path.
     * @param string $path Path to process. Optional, default to current path
     * @return string
     */
    protected function getCurrentPathRelativeToBase($path = null)
    {
        if (!$path) {
            // using $_SERVER instead of using Request method
            // to get original request path instead of any forwarded request
            $path = $_SERVER['REQUEST_URI'];
        }
        $base_path = $this->app['request']->getBasePath();
        return substr(strtok($path, '?'), strlen($base_path));
    }
}