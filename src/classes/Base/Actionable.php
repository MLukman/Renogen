<?php

namespace Renogen\Base;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\MappedSuperclass;
use Doctrine\ORM\Mapping\PrePersist;
use Doctrine\ORM\Mapping\PreUpdate;
use Renogen\Entity\Template;

/**
 * @MappedSuperclass
 * @HasLifecycleCallbacks
 */
class Actionable extends Entity
{
    /**
     * @Id @Column(type="string") @GeneratedValue(strategy="UUID")
     */
    public $id;

    /**
     * @Column(type="string", length=100, nullable=true)
     */
    public $signature;

    /**
     * @ManyToOne(targetEntity="Template")
     * @JoinColumn(name="template_id", referencedColumnName="id", onDelete="CASCADE")
     * @var Template
     */
    public $template;

    /**
     * @Column(type="integer", nullable=true)
     */
    public $stage;

    /**
     * @Column(type="integer", nullable=true)
     */
    public $priority;

    /**
     * @Column(type="json_array", nullable=true)
     */
    public $parameters;

    /**
     * @PrePersist
     * @PreUpdate
     */
    public function calculateSignature()
    {
        $this->signature = sha1(($this->template ? $this->template->id : '?').'|'.$this->stage.'|'.json_encode($this->parameters));
    }
}