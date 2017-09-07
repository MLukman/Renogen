<?php

namespace Renogen\Validation;

use \Doctrine\ORM\EntityManager;
use \Doctrine\Common\Collections\Criteria;
use \Doctrine\Common\Collections\Expr\Comparison;

class DoctrineValidator implements DataValidator
{
    static $instance = null;
    protected $em    = null;

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

    public function validate($entity, array $validation_rules)
    {
        $errors = array();
        foreach ($validation_rules as $field => $rules) {
            $errors[$field] = array();

            // pre-validation: remove whitespaces from front and back of string
            if (isset($rules['trim']) && $rules['trim'] && is_string($entity->$field)) {
                $entity->$field = trim($entity->$field);
            }

            // pre-validation: truncate string to given length
            if (isset($rules['truncate']) && $rules['truncate'] > 0) {
                $entity->$field = substr($entity->$field, 0, $rules['truncate']);
            }

            // validation: value is not null/empty
            if (isset($rules['required']) && $rules['required'] && empty($entity->$field)) {
                $errors[$field][] = 'Required';
            }

            // further validations
            if (!empty($entity->$field)) {

                // validation: max string length
                if (isset($rules['maxlen']) && strlen($entity->$field) > $rules['maxlen']) {
                    $errors[$field][] = "Max {$rules['maxlen']} chars";
                }

                // validation: string conforms to pattern
                if (isset($rules['preg_match'])) {
                    $preg_match = preg_match($rules['preg_match'], $entity->$field);
                    if ($preg_match === 0) {
                        $errors[$field][] = "Wrong format";
                    }
                }

                // validation: string is one of the valid values
                if (isset($rules['validvalues']) && is_array($rules['validvalues'])
                    && !in_array($entity->$field, $rules['validvalues'])) {
                    $errors[$field][] = "Invalid value";
                }

                // validation: string is not one of invalid values
                if (isset($rules['invalidvalues']) && is_array($rules['invalidvalues'])
                    && in_array($entity->$field, $rules['invalidvalues'])) {
                    $errors[$field][] = "Invalid value";
                }

                // validation: minimum value
                if (isset($rules['minvalue']) && $entity->$field < $rules['minvalue']) {
                    $errors[$field][] = "Min value is {$rules['minvalue']}";
                }

                // validation: maximum value
                if (isset($rules['maxvalue']) && $entity->$field > $rules['maxvalue']) {
                    $errors[$field][] = "Max value is {$rules['maxvalue']}";
                }

                // validation: unique
                if (isset($rules['unique']) && $rules['unique']) {
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
        }

        // for each field remove $errors if no error
        foreach ($validation_rules as $field => $rules) {
            if (empty($errors[$field])) {
                unset($errors[$field]);
            }
        }
        return $errors;
    }
}