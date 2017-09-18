<?php

namespace Renogen\Auth\Driver;

use Renogen\Auth\Driver;
use Securilex\Authentication\Factory\LdapBindAuthenticationFactory;
use Securilex\Authentication\User\MutableUserInterface;

class LDAP extends Driver
{

    static public function getTitle()
    {
        return 'LDAP integration';
    }

    static public function getParamConfigs()
    {
        return array(
            array('host', 'Host Name', 'LDAP server hostname / IP address'),
            array('port', 'Port', 'LDAP port'),
            array('dn', 'DN String', 'DN String (use {username} where the username should be substituted)'),
        );
    }

    static public function checkParams(array $params)
    {
        $errors = array();
        if (!isset($params['host']) || empty($params['host'])) {
            $errors['host'] = 'Host Name is required';
        }
        if (!isset($params['port']) || empty($params['port']) || !is_int($params['port'])) {
            $errors['port'] = 'Port is required and must be integer';
        }
        if (!isset($params['dn']) || empty($params['dn']) || strpos($params['dn'], '{username}')
            === false) {
            $errors['dn'] = 'DN string is required and must contain {username}';
        }
        return (empty($errors) ? null : $errors);
    }

    public function prepareNewUser(MutableUserInterface $user)
    {
        $user->setPassword('-');
    }

    public function getAuthenticationFactory()
    {
        return new LdapBindAuthenticationFactory($this->params['host'], $this->params['port'], $this->params['dn']);
    }
}