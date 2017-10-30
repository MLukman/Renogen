<?php
/**
 * This file is part of the Securilex library for Silex framework.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package Securilex\Authentication\Factory
 * @author Muhammad Lukman Nasaruddin <anatilmizun@gmail.com>
 * @link https://github.com/MLukman/Securilex Securilex Github
 * @link https://packagist.org/packages/mlukman/securilex Securilex Packagist
 */

namespace Securilex\Authentication\Factory;

use Symfony\Component\Ldap\Ldap;
use Symfony\Component\Ldap\LdapInterface;
use Symfony\Component\Security\Core\Authentication\Provider\LdapBindAuthenticationProvider;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * LdapBindAuthenticationFactory creates instances of LdapBindAuthenticationProvider using
 * information from an instance of \Silex\Application, UserProviderInterface and a provider key
 */
class LdapBindAuthenticationFactory implements AuthenticationFactoryInterface
{
    /**
     * Id of this factory
     * @var string
     */
    protected $id = null;

    /**
     * The LDAP client object
     * @var LdapInterface
     */
    protected $ldapClient;

    /**
     * The distinguished name string containing '{username}' phrase,
     * which will be replaced with the username entered by user
     * @var string
     */
    protected $dnString;

    /**
     * Construct an instance.
     * @staticvar int $instanceId
     * @param string $host The LDAP server host/ip
     * @param string $port The LDAP server port
     * @param string $dnString The distinguished name string containing '{username}' phrase,
     * which will be replaced with the username entered by user
     * @param integer $version The LDAP version (default = 3)
     */
    public function __construct($host, $port, $dnString, $version = 3)
    {
        $config = array('host' => $host, 'port' => $port, 'version' => $version);
        if (substr($host, 0, 8) === 'ldaps://') {
            $config['host']       = substr($host, 8);
            $config['encryption'] = 'ssl';
        } elseif (substr($host, 0, 7) === 'ldap://') {
            $config['host'] = substr($host, 7);
        }
        $this->ldapClient = Ldap::create('ext_ldap', $config);
        $this->dnString   = $dnString;
    }

    /**
     * Create Authentication Provider instance.
     * @param \Silex\Application $app
     * @param UserProviderInterface $userProvider
     * @param string $providerKey
     * @return LdapBindAuthenticationProvider
     */
    public function createAuthenticationProvider(\Silex\Application $app,
                                                 UserProviderInterface $userProvider,
                                                 $providerKey)
    {
        return new LdapBindAuthenticationProvider($userProvider, $app['security.user_checker'], $providerKey, $this->ldapClient, $this->dnString);
    }

    /**
     * Get the unique id of this instance of authentication factory.
     * @return string
     */
    public function getId()
    {
        static $instanceId = 0;
        if (!$this->id) {
            $this->id = 'ldap'.($instanceId++);
        }
        return $this->id;
    }
}