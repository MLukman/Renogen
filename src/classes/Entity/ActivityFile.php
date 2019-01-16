<?php

namespace Renogen\Entity;

use Renogen\Application;
use Renogen\Base\RenoController;

/**
 * @Entity
 */
class ActivityFile extends FileLink
{

    public function __construct(Activity $activity)
    {
        $this->activity = $activity;
    }

    public function getHtmlLink()
    {
        return '<a href="'.htmlentities(Application::instance()->path('activity_file_download', RenoController::entityParams($this->activity)
                    + array('file' => $this->id))).'">'.htmlentities($this->filename).'</a>';
    }
}