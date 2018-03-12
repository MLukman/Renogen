<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Renogen;

class StateModel implements \Silex\ServiceProviderInterface
{
    private $_models = array();

    public function boot(\Silex\Application $app)
    {
        
    }

    public function register(\Silex\Application $app)
    {
        $app['statemodel'] = $this;
    }

    public function registerTransition($type, $old_status, $new_status,
                                       $accesslevel, $attribute = null)
    {
        if (!isset($this->_models[$type])) {
            $this->_models[$type] = array();
        }
        if (!isset($this->_models[$type][$old_status])) {
            $this->_models[$type][$old_status] = array();
        }
        if (is_array($accesslevel)) {
            foreach ($accesslevel as $level) {
                $this->registerTransition($type, $old_status, $new_status, $level, $attribute);
            }
        } else {
            if (!isset($this->_models[$type][$old_status][$accesslevel])) {
                $this->_models[$type][$old_status][$accesslevel] = array();
            }
            $this->_models[$type][$old_status][$accesslevel][$new_status] = $attribute
                    ?: $new_status;
        }
    }

    public function getAvailableTransitions($type, $accesslevel, $old_status)
    {
        if (isset($this->_models[$type]) &&
            isset($this->_models[$type][$old_status]) &&
            isset($this->_models[$type][$old_status][$accesslevel])) {
            return $this->_models[$type][$old_status][$accesslevel];
        }
        return array();
    }

    public function validateTransition($type, $accesslevel, $old_status,
                                       $new_status)
    {
        $available = $this->getAvailableTransitions($type, $accesslevel, $old_status);
        if (isset($available[$new_status])) {
            return $available[$new_status];
        }
        return false;
    }
}