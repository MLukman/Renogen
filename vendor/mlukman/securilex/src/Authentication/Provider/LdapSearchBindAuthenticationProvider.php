<?php

namespace Securilex\Authentication\Provider;

use Symfony\Component\Ldap\Adapter\QueryInterface;
use Symfony\Component\Ldap\Exception\ConnectionException;
use Symfony\Component\Ldap\LdapInterface;
use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Core\Authentication\Provider\UserAuthenticationProvider;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class LdapSearchBindAuthenticationProvider extends UserAuthenticationProvider
{
    /** @var LdapInterface */
    protected $ldap;
    protected $userProvider;
    protected $serviceAcctDnString,
        $serviceAcctPassword;
    protected $baseDn;
    protected $searchUserProperty,
        $searchLdapAttribute;

    public function __construct(UserProviderInterface $userProvider,
                                UserCheckerInterface $userChecker, $providerKey,
                                LdapInterface $ldap, $serviceAcctDnString,
                                $serviceAcctPassword, $baseDn,
                                $searchUserProperty = 'email',
                                $searchLdapAttribute = 'mail',
                                $hideUserNotFoundExceptions = true)
    {
        parent::__construct($userChecker, $providerKey, $hideUserNotFoundExceptions);
        $this->ldap = $ldap;
        $this->userProvider = $userProvider;
        $this->baseDn = $baseDn;
        $this->serviceAcctDnString = $serviceAcctDnString;
        $this->serviceAcctPassword = $serviceAcctPassword;
        $this->searchUserProperty = $searchUserProperty;
        $this->searchLdapAttribute = $searchLdapAttribute;
    }

    protected function retrieveUser($username, UsernamePasswordToken $token)
    {
        if (AuthenticationProviderInterface::USERNAME_NONE_PROVIDED === $username) {
            throw new UsernameNotFoundException('Username can not be null');
        }

        return $this->userProvider->loadUserByUsername($username);
    }

    protected function checkAuthentication(UserInterface $user,
                                           UsernamePasswordToken $token)
    {
        $password = $token->getCredentials();

        try {
            $conn = $this->ldap->bind($this->serviceAcctDnString, $this->serviceAcctPassword);
        } catch (ConnectionException $e) {
            throw new BadCredentialsException('LDAP: unable to bind using service account. Please contact admin.');
        }

        /** @var QueryInterface */
        $search = $this->searchUserProperty;
        $queryString = sprintf("(%s=%s)", $this->searchLdapAttribute, $this->ldap->escape($user->$search, '', LdapInterface::ESCAPE_FILTER));

        $query = $this->ldap->query($this->baseDn, $queryString);
        $matches = $query->execute();

        if ($matches->count() == 0) {
            throw new BadCredentialsException(sprintf('LDAP: unable to find your LDAP account using your %s', $search));
        }
        if ($matches->count() > 1) {
            throw new BadCredentialsException(sprintf('LDAP: found more than one LDAP accounts matching your %s', $search));
        }

        try {
            $dn = $matches[0]->getDn();
            $this->ldap->bind($dn, $password);
        } catch (ConnectionException $e) {
            throw new BadCredentialsException('The presented password is invalid.');
        }
    }
}