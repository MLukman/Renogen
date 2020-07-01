<?php

namespace Renogen\Validation;

use \Doctrine\ORM\EntityManager;
use \Doctrine\Common\Collections\Criteria;
use \Doctrine\Common\Collections\Expr\Comparison;

class DoctrineValidator implements DataValidator
{
    static $instance = null;
    protected $em = null;

    static public function instance()
    {
        if (!static::$instance) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    public function setEntityManager(EntityManager $em)
    {
        $this->em = $em;
    }

    public function validate(&$entity, array $validation_rules)
    {
        $errors = array();
        foreach ($validation_rules as $field => $rules) {
            $errors[$field] = static::validateValue($entity->$field, $rules);

            // validation: unique
            if (!empty($entity->$field) && isset($rules['unique']) && $rules['unique']) {
                $criteria = Criteria::create()->where(new Comparison($field, '=', $entity->$field));
                if (is_string($rules['unique'])) {
                    // require uniqueness among all records with same value of a particular column
                    $rules['unique'] = array($rules['unique']);
                }
                if (is_array($rules['unique'])) {
                    // require uniqueness among all records with same value of particular list of columns
                    foreach ($rules['unique'] as $group_field) {
                        $criteria = $criteria->andWhere(new Comparison($group_field, '=', $entity->$group_field));
                    }
                }
                $list = $this->em->getRepository(get_class($entity))->matching($criteria);
                foreach ($list as $item) {
                    if ($item != $entity) {
                        $error = 'Value must be unique';
                        if (is_array($rules['unique'])) {
                            $error .= ' for each '.implode(' + ', $rules['unique']);
                        }
                        $errors[$field][] = $error;
                        break;
                    }
                }
            }
        }

        // for each field remove $errors if no error
        foreach ($validation_rules as $field => $rules) {
            if (empty($errors[$field])) {
                unset($errors[$field]);
            }
        }
        return $errors;
    }

    static public function validateValue(&$value, array $rules)
    {
        $errors = array();

        if (isset($rules['trim']) && $rules['trim'] && is_string($value)) {
            $value = trim($value);
        }

        // pre-validation: truncate string to given length
        if (isset($rules['truncate']) && $rules['truncate'] > 0 && strlen($value)
            > $rules['truncate']) {
            $value = substr($value, 0, $rules['truncate'] - 6).'…';
        }

        // validation: value is not null/empty
        if (isset($rules['required']) && $rules['required'] && empty($value)) {
            $errors[] = 'Required';
        }

        // further validations
        if (!empty($value)) {

            // validation: max string length
            if (isset($rules['maxlen']) && strlen($value) > $rules['maxlen']) {
                $errors[] = "Max {$rules['maxlen']} chars";
            }

            // validation: string conforms to pattern
            if (isset($rules['preg_match'])) {
                if (is_array($rules['preg_match'])) {
                    $pattern = $rules['preg_match'][0];
                    $errmsg = $rules['preg_match'][1];
                } else {
                    $pattern = $rules['preg_match'];
                    $errmsg = "Wrong format";
                }
                $preg_match = preg_match($pattern, $value);
                if ($preg_match === 0) {
                    $errors[] = $errmsg;
                }
            }

            // validation: string is one of the valid values
            if (isset($rules['validvalues']) && is_array($rules['validvalues']) && !in_array($value, $rules['validvalues'])) {
                $errors[] = "Invalid value";
            }

            // validation: string is not one of invalid values
            if (isset($rules['invalidvalues']) && is_array($rules['invalidvalues'])
                && in_array($value, $rules['invalidvalues'])) {
                $errors[] = "'{$value}' is an invalid value";
            }

            // validation: minimum value
            if (isset($rules['minvalue']) && $value < $rules['minvalue']) {
                $errors[] = "Min value is {$rules['minvalue']}";
            }

            // validation: maximum value
            if (isset($rules['maxvalue']) && $value > $rules['maxvalue']) {
                $errors[] = "Max value is {$rules['maxvalue']}";
            }

            // validation: url
            if (isset($rules['url']) && $rules['url'] && !filter_var($value, FILTER_VALIDATE_URL)) {
                $errors[] = "Must be a valid URL";
            }

            // validation: email
            if (isset($rules['email']) && $rules['email'] && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Must be a valid email address";
            }

            // validation: ip
            if (isset($rules['ip']) && $rules['ip'] && !filter_var($value, FILTER_VALIDATE_IP)) {
                $errors[] = "Must be a valid IP address";
            }
        }

        return $errors;
    }
}