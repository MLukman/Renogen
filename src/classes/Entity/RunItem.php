<?php

namespace Renogen\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;

/**
 * @Entity @Table(name="runitems")
 * @HasLifecycleCallbacks
 */
class RunItem extends \Renogen\Base\Actionable
{
    /**
     * @ManyToOne(targetEntity="Deployment")
     * @JoinColumn(name="deployment_id", referencedColumnName="id", onDelete="CASCADE")
     * @var Deployment
     */
    public $deployment;

    /**
     * @OneToMany(targetEntity="Activity", mappedBy="runitem", indexBy="id", fetch="EXTRA_LAZY")
     * @var ArrayCollection|Activity[]
     */
    public $activities = null;

    /**
     * @OneToMany(targetEntity="RunItemFile", mappedBy="runitem", indexBy="classifier", orphanRemoval=true, cascade={"persist", "remove"}, fetch="EXTRA_LAZY")
     * @var ArrayCollection|RunItemFile[]
     */
    public $files = null;

    /**
     * @Column(type="string", length=100, nullable=true)
     */
    public $status         = 'New';
    public $fileClass      = '\Renogen\Entity\RunItemFile';
    public $actionableType = 'runitem';

    public function __construct(Deployment $deployment)
    {
        $this->deployment = $deployment;
        $this->activities = new ArrayCollection();
        $this->files      = new ArrayCollection();
    }

    public function isUsernameAllowed($username, $attribute)
    {
        return parent::isUsernameAllowed($username, $attribute) ||
            $this->deployment->isUsernameAllowed($username, $attribute);
    }
}