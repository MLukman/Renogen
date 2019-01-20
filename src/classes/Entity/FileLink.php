<?php

namespace Renogen\Entity;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\DiscriminatorColumn;
use Doctrine\ORM\Mapping\DiscriminatorMap;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\InheritanceType;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Renogen\Base\Entity;
use Renogen\Entity\RunItem;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Entity @Table(name="file_links")
 * @InheritanceType("SINGLE_TABLE")
 * @DiscriminatorColumn(name="parent_type", type="string")
 * @DiscriminatorMap({"item" = "Attachment", "activity" = "ActivityFile", "runitem" = "RunItemFile"})
 * @HasLifecycleCallbacks
 */
abstract class FileLink extends Entity
{
    /**
     * @Id @Column(type="string") @GeneratedValue(strategy="UUID")
     */
    public $id;

    /**
     * @Column(type="string", length=100)
     */
    public $filename;

    /**
     * @Column(type="string", nullable=true, length=100)
     */
    public $classifier;

    /**
     * @Column(type="text", nullable=true)
     */
    public $description;

    /**
     * @ManyToOne(targetEntity="FileStore", cascade={"persist"})
     * @JoinColumn(name="filestore_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     * @var FileStore
     */
    public $filestore;

    /**
     * @ManyToOne(targetEntity="Item")
     * @JoinColumn(name="item_id", referencedColumnName="id", nullable=true, onDelete="CASCADE")
     * @var Item
     */
    public $item;

    /**
     * @ManyToOne(targetEntity="Activity")
     * @JoinColumn(name="activity_id", referencedColumnName="id", nullable=true, onDelete="CASCADE")
     * @var Activity
     */
    public $activity;

    /**
     * @ManyToOne(targetEntity="RunItem")
     * @JoinColumn(name="runitem_id", referencedColumnName="id", nullable=true, onDelete="CASCADE")
     * @var RunItem
     */
    public $runitem;

    /**
     * Validation rules
     * @var array
     */
    protected $validation_rules = array(
        'filename' => array('truncate' => 100),
        'classifier' => array('truncate' => 100),
        'description' => array('trim' => 1),
    );

    public function delete($em)
    {
        parent::delete($em);
        if ($this->filestore->links->count() == 1) {
            $this->filestore->delete($em);
        }
    }

    abstract public function downloadUrl();

    public function getHtmlLink()
    {
        $base      = log($this->filestore->filesize) / log(1024);
        $suffix    = array(" bytes", " KB", " MB", " GB", " TB")[floor($base)];
        $humansize = round(pow(1024, $base - floor($base)), 2).$suffix;
        return '<a href="'.htmlentities($this->downloadUrl()).'" title="'.$humansize.' '.$this->filestore->mime_type.'">'.htmlentities($this->filename).'</a>';
    }

    public function __toString()
    {
        return $this->filename;
    }

    public function returnDownload()
    {
        return new Response(stream_get_contents($this->filestore->data), 200, array(
            'Content-type' => $this->filestore->mime_type,
            'Content-Disposition' => "attachment; filename='{$this->filename}'",
        ));
    }
}