<?php

namespace Renogen;

class TwigSortByProperty extends \Twig_Extension
{

    public function getFilters()
    {
        return array(
            new \Twig_SimpleFilter('psort', array($this, 'sortByProperty')),
        );
    }

    public function sortByProperty($var, $prop)
    {
        if (!is_array($var)) {
            return $var;
        }
        uasort($var, function($a, $b) use ($prop) {
            $va = $this->extractProperty($a, $prop);
            $vb = $this->extractProperty($b, $prop);
            if ($va == $vb) {
                return 0;
            }
            return ($va < $vb) ? -1 : 1;
        });
        return $var;
    }

    public function extractProperty($var, $prop)
    {
        $tokens = explode(".", $prop);
        $p      = array_shift($tokens);
        $v      = (is_array($var) ?
            (isset($var[$p]) ? $var[$p] : null) : (is_object($var) ? $var->$p : null));
        if (empty($tokens)) {
            return $v;
        } else {
            return $this->extractProperty($v, implode(".", $tokens));
        }
    }
}