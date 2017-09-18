<?php

namespace Renogen\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Renogen\Base\Entity;
use Securilex\Authentication\User\MutableUserInterface;

/**
 * @Entity @Table(name="users")
 */
class User extends Entity implements MutableUserInterface
{
    /**
     * @Id @Column(type="string", length=16)
     */
    public $username;

    /**
     * @Column(type="string", nullable=true, length=100)
     */
    public $shortname;

    /**
     * @Column(type="string", nullable=true, length=100)
     */
    public $password;

    /**
     * @Column(type="json_array", nullable=true)
     */
    public $roles;

    /**
     * @Column(type="string", length=64)
     */
    public $auth;

    /**
     * @OneToMany(targetEntity="UserProject", mappedBy="user")
     * @var ArrayCollection
     */
    public $userProjects = null;

    /**
     * Validation rules
     * @var array
     */
    protected $validation_rules = array(
        'username' => array('trim' => 1, 'required' => 1, 'maxlen' => 16, 'unique' => 1),
        'shortname' => array('trim' => 1, 'maxlen' => 100, 'unique' => 1),
        'auth' => array('required' => 1),
        'roles' => array('required' => 1),
    );

    public function __construct()
    {
        $this->userProjects = new ArrayCollection();
    }

    public function getName()
    {
        return $this->shortname ?: $this->username;
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
        $this->salt = $salt;
    }
}