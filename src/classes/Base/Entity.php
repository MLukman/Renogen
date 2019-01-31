<?php

namespace Renogen\Base;

use DateTime;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\MappedSuperclass;
use Doctrine\ORM\Mapping\PrePersist;
use Doctrine\ORM\Mapping\PreUpdate;
use Renogen\App;
use Renogen\Entity\User;
use Renogen\Validation\DoctrineValidator;
use Securilex\Authorization\SecuredAccessInterface;
use Securilex\Authorization\SecuredAccessTrait;

/**
 * @MappedSuperclass @HasLifecycleCallbacks
 */
class Entity implements SecuredAccessInterface
{

    use SecuredAccessTrait;
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
     *
     * @var array Old values
     */
    public $old_values = array();

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

    /**
     * @PrePersist 
     */
    public function defaultCreatedDate()
    {
        if (!$this->created_date) {
            $this->created_date = new DateTime();
        }
        if (!$this->created_by) {
            $this->created_by = App::instance()->userEntity();
        }
    }

    /**
     * @PreUpdate 
     */
    public function defaultUpdatedDate()
    {
        if (($user = App::instance()->userEntity())) {
            $this->storeOldValues(array('updated_date', 'updated_by'));
            $this->updated_date = new DateTime();
            $this->updated_by   = $user;
        }
    }

    public function storeOldValues(array $fields)
    {
        foreach ($fields as $field) {
            $this->old_values[$field] = $this->$field;
        }
    }

    protected function cached($cacheid, callable $create, $force = false)
    {
        if (!isset($this->_caches[$cacheid]) || $force) {
            $this->_caches[$cacheid] = $create();
        }
        return $this->_caches[$cacheid];
    }

    public function delete($em)
    {
        $em->remove($this);
    }

    public function isUsernameAllowed($username, $attribute)
    {
        $allowed = false;

        switch ($attribute) {
            case 'delete':
                $allowed = ($this->created_by->username == $username);
                break;
        }

        return $allowed;
    }
}