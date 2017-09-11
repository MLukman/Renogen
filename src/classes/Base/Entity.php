<?php

namespace Renogen\Base;

use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\ORM\EntityManager;
use Renogen\Application;
use Renogen\Validation\DoctrineValidator;
use Symfony\Component\Security\Core\User\User;

/**
 * @MappedSuperclass @HasLifecycleCallbacks
 */
class Entity
{
    /**
     * @ManyToOne(targetEntity="User")
     * @JoinColumn(name="created_by", referencedColumnName="username", onDelete="CASCADE")
     * @var User
     */
    public $created_by;

    /**
     * @Column(type="datetime")
     */
    public $created_date;

    /**
     * @ManyToOne(targetEntity="User")
     * @JoinColumn(name="updated_by", referencedColumnName="username", onDelete="CASCADE")
     * @var User
     */
    public $updated_by;

    /**
     * @Column(type="datetime", nullable=true)
     */
    public $updated_date;

    /**
     * Validation rules
     * @var array
     */
    protected $validation_default = array();
    protected $validation_rules   = array();
    public $errors                = array();

    /**
     * Cache
     * @var array
     */
    protected $_caches = array();

    public function __get($property)
    {
        return $this->$property;
    }

    public function __set($property, $value)
    {
        $this->$property = $value;
    }

    static public function createCondition($f, $c, $v)
    {
        return Criteria::create()->where(new Comparison($f, $c, $v));
    }

    public function validate(EntityManager $em, array $selectiveFields = null)
    {
        $rules = array();
        foreach ($this->validation_rules as $field => $rule) {
            if (empty($selectiveFields) || in_array($field, $selectiveFields)) {
                $rules[$field] = array_merge($this->validation_default, $rule);
            }
        }

        $validator    = DoctrineValidator::instance();
        $validator->setEntityManager($em);
        $this->errors = $validator->validate($this, $rules);
        return empty($this->errors);
    }

    /** @PrePersist */
    public function defaultCreatedDate()
    {
        $this->created_date = new \DateTime();
        $this->created_by   = Application::instance()->user();
    }

    /** @PreUpdate */
    public function defaultUpdatedDate()
    {
        $this->updated_date = new \DateTime();
        $this->updated_by   = Application::instance()->user();
    }

    protected function cached($cacheid, callable $create, $force = false)
    {
        if (!isset($this->_caches[$cacheid]) || $force) {
            $this->_caches[$cacheid] = $create();
        }
        return $this->_caches[$cacheid];
    }
}