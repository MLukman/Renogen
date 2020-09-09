<?php

namespace Renogen\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\OneToMany;
use Renogen\Base\Entity;
use Securilex\Authentication\User\MutableUserInterface;

/**
 * @Entity @Table(name="users")
 */
class User extends Entity implements MutableUserInterface
{
    /**
     * @Id @Column(type="string", length=25)
     */
    public $username;

    /**
     * @Column(type="string", nullable=true, length=100)
     */
    public $shortname;

    /**
     * @Column(type="string", length=50)
     */
    public $email;

    /**
     * @Column(type="string", nullable=true, length=100)
     */
    public $password = '';

    /**
     * @Column(type="json_array", nullable=true)
     */
    public $roles;

    /**
     * @Column(type="string", length=64)
     */
    public $auth;

    /**
     * @Column(type="boolean", nullable=true)
     */
    public $blocked;

    /**
     * @Column(type="datetime", nullable=true)
     */
    public $last_login;

    /**
     * @OneToMany(targetEntity="UserProject", mappedBy="user", orphanRemoval=true)
     * @var ArrayCollection|UserProject[]
     */
    public $userProjects = null;

    /**
     * Validation rules
     * @var array
     */
    protected $validation_rules = array(
        'username' => array('trim' => 1, 'required' => 1, 'maxlen' => 25, 'unique' => 1,
            'preg_match' => array('/^[0-9a-zA-Z][0-9a-zA-Z\._-]*$/', 'Username must start with an alphanumerical character and contains only alphanumeric, underscores, dashes and dots')),
        'shortname' => array('trim' => 1, 'required' => 1, 'truncate' => 100, 'unique' => 1),
        'email' => array('required' => 1, 'maxlen' => 50, 'unique' => 1, 'email' => 1),
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