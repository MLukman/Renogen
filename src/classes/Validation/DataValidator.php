<?php

namespace Renogen\Validation;

interface DataValidator
{

    static public function instance();

    public function validate($entity, array $validation_rules);
}