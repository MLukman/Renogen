<?php

namespace Renogen;

class TwigInstanceOf extends \Twig_Extension
{

    public function getTests()
    {
        return array(
            new \Twig_SimpleTest('instanceof', array($this, 'isInstanceOf')),
        );
    }

    public function isInstanceof($var, $instance)
    {
        if (!is_object($var)) {
            return false;
        }
        $reflexionClass = new \ReflectionClass($instance);
        return $reflexionClass->isInstance($var);
    }
}