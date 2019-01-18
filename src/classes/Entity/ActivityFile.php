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
        return '<a href="'.htmlentities(Application::instance()->entity_path('activity_file_download', $this)).'">'.htmlentities($this->filename).'</a>';
    }
}