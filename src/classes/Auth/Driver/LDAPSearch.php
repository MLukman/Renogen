<?php

namespace Renogen\Auth\Driver;

use Renogen\Auth\Driver;
use Securilex\Authentication\Factory\LdapSearchBindAuthenticationFactory;
use Securilex\Authentication\User\MutableUserInterface;
use Symfony\Component\Ldap\Exception\ConnectionException;

class LDAPSearch extends Driver
{

    static public function getTitle()
    {
        return 'LDAP integration using ldapsearch';
    }

    static public function getParamConfigs()
    {
        return array(
            array('host', 'Host Name', 'LDAP server hostname / IP address'),
            array('port', 'Port', 'LDAP port'),
            array('saDn', 'Service Account DN String', 'DN string of the service account to be used for performing ldapsearch'),
            array('saPwd', 'Service Account Password', 'The password for the above service account'),
            array('baseDn', 'Base DN', 'Base DN to search from'),
        );
    }

    static public function checkParams(array $params)
    {
        $errors = array();
        if (!isset($params['host']) || empty($params['host'])) {
            $errors['host'] = 'Host Name is required';
        }
        if (!isset($params['port']) || empty($params['port']) || !is_numeric($params['port'])) {
            $errors['port'] = 'Port is required and must be integer';
        }
        if (!isset($params['saDn']) || empty($params['saDn'])) {
            $errors['saDn'] = 'Service Account DN String is required';
        }
        if (!isset($params['saPwd']) || empty($params['saPwd'])) {
            $errors['saPwd'] = 'Service Account Password is required';
        }
        if (!isset($params['baseDn']) || empty($params['baseDn'])) {
            $errors['baseDn'] = 'Base DN is required';
        }

        if (empty($errors)) {
            try {
                $ldapFact = new LdapSearchBindAuthenticationFactory($params['host'], $params['port'], $params['saDn'], $params['saPwd'], $params['baseDn'], 'email', 'mail');
                $ldapFact->getLdapClient()->bind($params['saDn'], $params['saPwd']);
            } catch (ConnectionException $ldapex) {
                $bindError = 'Unable to bind';
                $errors['host'] = $bindError;
                $errors['port'] = $bindError;
                $errors['saDn'] = $bindError;
                $errors['saPwd'] = $bindError;
            }
        }

        return (empty($errors) ? null : $errors);
    }

    public function prepareNewUser(MutableUserInterface $user)
    {
        $user->setPassword('-');
    }

    public function getAuthenticationFactory()
    {
        return new LdapSearchBindAuthenticationFactory($this->params['host'], $this->params['port'], $this->params['saDn'], $this->params['saPwd'], $this->params['baseDn'], 'email', 'mail');
    }
}