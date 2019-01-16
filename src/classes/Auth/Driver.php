<?php

namespace Renogen\Auth;

use Exception;
use Securilex\Authentication\Factory\AuthenticationFactoryInterface;
use Securilex\Authentication\User\MutableUserInterface;

abstract class Driver
{
    protected $params = array();

    public function __construct(array $params)
    {
        if (!empty($this->checkParams($params))) {
            throw new Exception('Invalid parameters');
        }
        $this->params = $params;
    }

    /**
     * @return string Friendly title of the authentication method
     */
    abstract static public function getTitle();

    /**
     * @return array Parameters = array of array(id, label, placeholder)
     */
    abstract static public function getParamConfigs();

    /**
     * Check if params are valid.
     * Return array of error messages using param names as keys.
     * Return null if no error.
     * @param array $params
     * @return array|null
     */
    abstract static public function checkParams(array $params);

    /**
     * @return AuthenticationFactoryInterface
     */
    abstract public function getAuthenticationFactory();

    /**
     * Prepare a newly created user record before saving
     * @param MutableUserInterface $user The instance of user record
     */
    abstract public function prepareNewUser(MutableUserInterface $user);

    /**
     * Can this driver support resetting password?
     * @return boolean If this driver supports resetting password
     */
    public function canResetPassword()
    {
        return false;
    }

    /**
     * Perform password reset on a specific user
     * @param MutableUserInterface $user
     * @return string|null Success message. Null if failed.
     */
    public function resetPassword(MutableUserInterface $user)
    {

    }
}