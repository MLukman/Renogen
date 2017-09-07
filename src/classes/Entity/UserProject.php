<?php

namespace Renogen\Entity;

/**
 * @Entity @Table(name="user_projects")
 */
class UserProject extends \Renogen\Base\Entity
{
    /**
     * @Id @ManyToOne(targetEntity="User")
     * @JoinColumn(name="username", referencedColumnName="username", onDelete="CASCADE")
     * @var User
     */
    public $user;

    /**
     * @Id @ManyToOne(targetEntity="Project")
     * @JoinColumn(name="project_id", referencedColumnName="id", onDelete="CASCADE")
     * @var Project
     */
    public $project;

    /**
     * @Column(type="string", length=16)
     */
    public $roles;

}