<?php

namespace Renogen\Entity;

/**
 * @Entity @Table(name="item_status_log")
 */
class ItemStatusLog extends \Renogen\Base\Entity
{
    /**
     * @Id @Column(type="string") @GeneratedValue(strategy="UUID")
     */
    public $id;

    /**
     * @ManyToOne(targetEntity="Item")
     * @JoinColumn(name="item_id", referencedColumnName="id", onDelete="CASCADE")
     * @var Item
     */
    public $item;

    /**
     * @Column(type="string", length=100)
     */
    public $status;

    /**
     * @Column(type="text", nullable=true)
     */
    public $remark;

    public function __construct(Item $item, $status, User $user = null,
                                \DateTime $datetime = null)
    {
        $this->item         = $item;
        $this->created_date = $datetime ?: new \DateTime();
        $this->created_by   = $user ?: \Renogen\App::instance()->userEntity();
        $this->status       = $status;
    }
}