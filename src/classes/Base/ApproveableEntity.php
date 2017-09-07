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

}