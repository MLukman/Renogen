<?php

namespace Renogen\Entity;

use Renogen\Application;
use Renogen\Base\RenoController;

/**
 * @Entity
 */
class RunItemFile extends FileLink
{

    public function __construct(RunItem $runitem)
    {
        $this->runitem = $runitem;
    }

    public function getHtmlLink()
    {
        return '<a href="'.htmlentities(Application::instance()->path('runitem_file_download', RenoController::entityParams($this))).'">'.htmlentities($this->filename).'</a>';
    }
}