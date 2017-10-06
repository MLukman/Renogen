<?php

namespace Renogen\Base;

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
     * @param \Renogen\Entity\User $user Approved by
     */
    public function submit(\Renogen\Entity\User $user = null)
    {
        $this->submitted_date = new \DateTime();
        $this->submitted_by   = $user ?: \Renogen\Application::instance()->userEntity();
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
     * @param \Renogen\Entity\User $user Approved by
     */
    public function approve(\Renogen\Entity\User $user = null)
    {
        $this->approved_date = new \DateTime();
        $this->approved_by   = $user ?: \Renogen\Application::instance()->userEntity();
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
     * Approve this entity
     * @param \Renogen\Entity\User $user Approved by
     */
    public function reject(\Renogen\Entity\User $user = null)
    {
        $this->rejected_date  = new \DateTime();
        $this->rejected_by    = $user ?: \Renogen\Application::instance()->userEntity();
        $this->submitted_date = null;
        $this->submitted_by   = null;
    }
}