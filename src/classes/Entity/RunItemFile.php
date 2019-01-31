<?php

namespace Renogen\Entity;

use Doctrine\ORM\Mapping\Entity;
use Renogen\App;

/**
 * @Entity
 */
class RunItemFile extends FileLink
{

    public function __construct(RunItem $runitem)
    {
        $this->runitem = $runitem;
    }

    public function downloadUrl()
    {
        return App::instance()->entity_path('runitem_file_download', $this);
    }
}