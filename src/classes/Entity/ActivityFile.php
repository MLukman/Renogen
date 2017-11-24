<?php

namespace Renogen\Entity;

use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Renogen\Base\FileEntity;

/**
 * @Entity @Table(name="activity_files")
 */
class ActivityFile extends FileEntity
{
    /**
     * @ManyToOne(targetEntity="Activity")
     * @JoinColumn(name="activity_id", referencedColumnName="id", onDelete="CASCADE")
     * @var Activity
     */
    public $activity;

    /**
     * Validation rules
     * @var array
     */
    protected $validation_rules = array(
        'filename' => array('truncate' => 255),
    );

    public function __construct(Activity $activity)
    {
        $this->activity = $activity;
    }

    public function getFolder()
    {
        return $this->activity->item->deployment->project->getAttachmentFolder();
    }

    public function getHtmlLink()
    {
        return '<a href="'.htmlentities(\Renogen\Application::instance()->path('activity_file_download', \Renogen\Base\RenoController::entityParams($this->activity)
                    + array('file' => $this->id))).'">'.htmlentities($this->filename).'</a>';
    }
}