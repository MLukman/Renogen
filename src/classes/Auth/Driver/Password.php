<?php

namespace Renogen\Auth\Driver;

use Renogen\Auth\Driver;
use Securilex\Authentication\Factory\BCryptAuthenticationFactory;
use Securilex\Authentication\User\MutableUserInterface;

class Password extends Driver
{
    protected $factory = null;

    public function __construct(array $params)
    {
        parent::__construct($params);
        $this->factory = new BCryptAuthenticationFactory();
    }

    static public function getTitle()
    {
        return 'Simple username & password';
    }

    static public function getParamConfigs()
    {
        return array();
    }

    static public function checkParams(array $params)
    {
        return null;
    }

    public function prepareNewUser(MutableUserInterface $user)
    {
        $this->factory->encodePassword($user, $user->getPassword());
    }

    public function getAuthenticationFactory()
    {
        return $this->factory;
    }
}