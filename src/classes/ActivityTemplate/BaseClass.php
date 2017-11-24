<?php

namespace Renogen\ActivityTemplate;

use Renogen\Entity\Activity;

abstract class BaseClass
{
    protected $app;
    private $_parameters = array();

    public function __construct(\Renogen\Application $app)
    {
        $this->app = $app;
    }

    final protected function addParameter($name, Parameter $parameter)
    {
        $parameter->setApplication($this->app);
        $this->_parameters[(string) $name] = $parameter;
    }

    /**
     *
     * @return Parameter[]
     */
    final public function getParameters()
    {
        return $this->_parameters;
    }

    /**
     *
     * @param type $name
     * @return Parameter
     */
    final public function getParameter($name)
    {
        return (isset($this->_parameters[(string) $name]) ?
            $this->_parameters[(string) $name] : null);
    }

    abstract public function classTitle();

    /**
     * @return array
     */
    public function describeActivityAsArray(Activity $activity)
    {
        $desc = array();
        foreach ($this->_parameters as $key => $param) {
            if (empty($param->activityLabel)) {
                continue;
            }
            $desc[$param->activityLabel] = $param->displayActivityParameter($activity, $key);
        }
        return $desc;
    }

    /**
     * @return \Renogen\Runbook\Group[]
     */
    abstract public function convertActivitiesToRunbookGroups(array $activities);
}