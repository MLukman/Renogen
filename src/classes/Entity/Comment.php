<?php

namespace Renogen\Entity;

use Renogen\Base\Entity;

/**
 * @Entity @Table(name="comments")
 */
class Comment extends Entity
{
    /**
     * @Id @Column(type="string") @GeneratedValue(strategy="UUID")
     */
    public $id;

    /**
     * @Column(type="text")
     */
    public $text;

}