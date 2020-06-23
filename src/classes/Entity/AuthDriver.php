<?php

namespace Renogen\Entity;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Id;
use Renogen\Base\Entity;

/**
 * @Entity @Table(name="auth_drivers")
 */
class AuthDriver extends Entity
{
    /**
     * @Id @Column(type="string")
     */
    public $name;

    /**
     * @Column(type="string")
     */
    public $class;

    /**
     * @Column(type="json_array", nullable=true)
     */
    public $parameters = array();

    /**
     * Validation rules
     * @var array
     */
    protected $validation_rules = array(
        'name' => array('trim' => 1, 'required' => 1, 'unique' => 1),
        'class' => array('required' => 1),
    );

    public function __construct($name = null)
    {
        $this->name = $name;
    }
}