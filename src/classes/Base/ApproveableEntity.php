<?php

namespace Renogen\Base;

use DateTime;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\MappedSuperclass;
use Renogen\Application;
use Renogen\Entity\User;

/**
 * @MappedSuperclass
 */
class ApproveableEntity extends Entity
{
    /**
     * @ManyToOne(targetEntity="User")
     * @JoinColumn(name="submitted_by", referencedColumnName="username", onDelete="CASCADE")
     * @var User
     */
    public $submitted_by;

    /**
     * @Column(type="datetime", nullable=true)
     */
    public $submitted_date;

    /**
     * @ManyToOne(targetEntity="User")
     * @JoinColumn(name="approved_by", referencedColumnName="username", onDelete="CASCADE")
     * @var User
     */
    public $approved_by;

    /**
     * @Column(type="datetime", nullable=true)
     */
    public $approved_date;

    /**
     * @ManyToOne(targetEntity="User")
     * @JoinColumn(name="approved_by", referencedColumnName="username", onDelete="CASCADE")
     * @var User
     */
    public $rejected_by;

    /**
     * @Column(type="datetime", nullable=true)
     */
    public $rejected_date;

    /**
     * Approve this entity
     * @param User $user Approved by
     */
    public function submit(User $user = null)
    {
        $this->submitted_date = new DateTime();
        $this->submitted_by   = $user ?: Application::instance()->userEntity();
        $this->rejected_date  = null;
        $this->rejected_by    = null;
    }

    /**
     *
     */
    public function unsubmit()
    {
        $this->submitted_date = null;
        $this->submitted_by   = null;
    }

    /**
     * Approve this entity
     * @param User $user Approved by
     */
    public function approve(User $user = null)
    {
        $this->approved_date = new DateTime();
        $this->approved_by   = $user ?: Application::instance()->userEntity();
    }

    /**
     *
     */
    public function unapprove()
    {
        $this->approved_date = null;
        $this->approved_by   = null;
    }

    /**
     * Reject this entity
     * @param User $user Rejected by
     */
    public function reject(User $user = null)
    {
        $this->rejected_date  = new DateTime();
        $this->rejected_by    = $user ?: Application::instance()->userEntity();
        $this->submitted_date = null;
        $this->submitted_by   = null;
    }

    public function isApproved()
    {
        return !empty($this->approved_date);
    }
}