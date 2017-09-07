<?php

namespace Securilex\Authentication\User;

class SimpleMutableUser implements MutableUserInterface
{
    protected $username, $password, $roles;

    public function __construct($username, $password, $roles)
    {
        $this->username = $username;
        $this->password = $password;
        $this->roles    = $roles;
    }

    public function eraseCredentials()
    {
        
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function getRoles()
    {
        return $this->roles;
    }

    public function getSalt()
    {
        return null;
    }

    public function getUsername()
    {
        return $this->username;
    }

    public function setPassword($password)
    {
        $this->password = $password;
    }

    public function setRoles(array $roles)
    {
        $this->roles = $roles;
    }

    public function setSalt($salt)
    {

    }
}