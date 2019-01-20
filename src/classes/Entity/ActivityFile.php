<?php

namespace Renogen\Entity;

use Doctrine\ORM\Mapping\Entity;
use Renogen\Application;

/**
 * @Entity
 */
class ActivityFile extends FileLink
{

    public function __construct(Activity $activity)
    {
        $this->activity = $activity;
    }

    public function downloadUrl()
    {
        return Application::instance()->entity_path('activity_file_download', $this);
    }
}