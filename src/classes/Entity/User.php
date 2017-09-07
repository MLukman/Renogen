<?php

namespace Renogen\Entity;

/**
 * @Entity @Table(name="users")
 */
class User extends \Renogen\Base\Entity
{
    /**
     * @Id @Column(type="string", length=16)
     */
    public $username;

    /**
     * @Column(type="string", nullable=true, length=100)
     */
    public $password;

    /**
     * @Column(type="json_array", nullable=true)
     */
    public $roles;

}