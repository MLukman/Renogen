<?php

namespace Securilex\Authentication\Factory;

use Securilex\Authentication\Provider\AuthenticationProviderWrapper;
use Securilex\Authentication\Provider\LdapSearchBindAuthenticationProvider;
use Silex\Application;
use Symfony\Component\Ldap\Ldap;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class LdapSearchBindAuthenticationFactory implements AuthenticationFactoryInterface
{
    protected $serviceAcctDnString,
        $serviceAcctPassword, $baseDn,
        $searchUserProperty, $searchLdapAttribute;
    protected $ldapClient;

    public function __construct($host, $port, $serviceAcctDnString,
                                $serviceAcctPassword, $baseDn,
                                $searchUserProperty, $searchLdapAttribute,
                                $version = 3)
    {
        $config = array('host' => $host, 'port' => $port, 'version' => $version);
        if (substr($host, 0, 8) === 'ldaps://') {
            $config['host'] = substr($host, 8);
            $config['encryption'] = 'ssl';
        } elseif (substr($host, 0, 7) === 'ldap://') {
            $config['host'] = substr($host, 7);
        }
        $this->ldapClient = Ldap::create('ext_ldap', $config);
        $this->serviceAcctDnString = $serviceAcctDnString;
        $this->serviceAcctPassword = $serviceAcctPassword;
        $this->baseDn = $baseDn;
        $this->searchUserProperty = $searchUserProperty;
        $this->searchLdapAttribute = $searchLdapAttribute;
    }

    public function createAuthenticationProvider(Application $app,
                                                 UserProviderInterface $userProvider,
                                                 $providerKey)
    {
        return new AuthenticationProviderWrapper(new LdapSearchBindAuthenticationProvider($userProvider, $app['security.user_checker'], $providerKey, $this->ldapClient, $this->serviceAcctDnString, $this->serviceAcctPassword, $this->baseDn, $this->searchUserProperty, $this->searchLdapAttribute));
    }

    public function getId()
    {
        static $instanceId = 0;
        if (!$this->id) {
            $this->id = 'ldapsearch'.($instanceId++);
        }
        return $this->id;
    }

    /**
     *
     * @return LdapInterface
     */
    public function getLdapClient()
    {
        return $this->ldapClient;
    }
}