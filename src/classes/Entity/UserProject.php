<?php

namespace Renogen\Entity;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Renogen\Base\Entity;

/**
 * @Entity @Table(name="user_projects")
 */
class UserProject extends Entity
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
    public $role;

    public function __construct(Project $project, User $user)
    {
        $this->project = $project;
        $this->user = $user;
    }
}