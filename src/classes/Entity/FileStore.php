<?php

namespace Renogen\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\OneToMany;
use Renogen\Base\Entity;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @Entity @Table(name="file_store")
 * @HasLifecycleCallbacks
 */
class FileStore extends Entity
{
    /**
     * @Id @Column(type="string")
     */
    public $id;

    /**
     * @Column(type="integer")
     */
    public $filesize = 0;

    /**
     * @Column(type="string", nullable=true)
     */
    public $mime_type;

    /**
     * @Column(type="blob")
     */
    public $data;

    /**
     * @OneToMany(targetEntity="FileLink", mappedBy="filestore", indexBy="id", orphanRemoval=true, cascade={"persist","remove"}, fetch="EXTRA_LAZY")
     * @var ArrayCollection|FileLink
     */
    public $links = null;

    /**
     * Temporary uploaded file
     * @var UploadedFile
     */
    protected $uploaded_file;

}