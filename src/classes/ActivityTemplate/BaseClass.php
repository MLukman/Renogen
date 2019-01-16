<?php

namespace Renogen\ActivityTemplate;

use Renogen\Application;
use Renogen\Base\Actionable;
use Renogen\Base\RenoController;
use Renogen\Entity\Activity;
use Renogen\Entity\FileLink;
use Renogen\Entity\RunItemFile;
use Renogen\Runbook\Group;

abstract class BaseClass
{
    protected $app;
    private $_parameters = array();

    public function __construct(Application $app)
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
    public function describeActivityAsArray(Actionable $activity)
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
     * Generate signature for a particular activity
     * @param Activity $activity
     * @return string
     */
    public function activitySignature(Actionable $activity)
    {
        return md5(json_encode($this->describeActivityAsArray($activity)));
    }

    public function getDownloadLink(FileLink $filelink)
    {
        return $this->app->path($filelink instanceof RunItemFile ?
                'runitem_file_download' : 'activity_file_download', RenoController::entityParams($filelink));
    }

    /**
     * @return Group[]
     */
    abstract public function convertActivitiesToRunbookGroups(array $activities);
}