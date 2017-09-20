<?php

namespace Renogen\Base;

/**
 * @MappedSuperclass @HasLifecycleCallbacks
 */
abstract class FileEntity extends Entity
{
    /**
     * @Id @Column(type="string") @GeneratedValue(strategy="UUID")
     */
    public $id;

    /**
     * @Column(type="string")
     */
    public $filename;

    /**
     * @Column(type="integer")
     */
    public $filesize = 0;

    /**
     * @Column(type="string", nullable=true)
     */
    public $mime_type;

    /**
     * @Column(type="string")
     */
    public $stored_filename;

    /**
     * Temporary uploaded file
     * @var \Symfony\Component\HttpFoundation\File\UploadedFile
     */
    protected $uploaded_file;

    abstract public function getFolder();

    public function delete(\Doctrine\ORM\EntityManager $em)
    {
        $this->deleteFile();
        parent::delete($em);
    }

    final public function getFilesystemPath()
    {
        return $this->getFolder().$this->stored_filename;
    }

    /** @PreRemove */
    public function deleteFile()
    {
        if (file_exists($this->getFilesystemPath())) {
            unlink($this->getFilesystemPath());
        }
    }

    public function processUploadedFile(\Symfony\Component\HttpFoundation\File\UploadedFile $file,
                                        array &$errors = array())
    {
        if ($file->isValid()) {
            $this->filename      = $file->getClientOriginalName();
            $this->filesize      = $file->getClientSize();
            $this->mime_type     = $file->getMimeType();
            $this->uploaded_file = $file;
            if (!$this->stored_filename) {
                $this->stored_filename = \uniqid('file', true);
            }
        } else {
            $errors = array(
                'Unable to process uploaded file',
            );
        }
    }

    /** @PreFlush */
    public function storeFile()
    {
        if (!$this->uploaded_file) {
            return;
        }
        $targetdir = $this->getFolder();
        if (!file_exists($targetdir)) {
            mkdir($targetdir, 0777, true);
        }
        $this->uploaded_file->move($targetdir, $this->stored_filename);
        $this->uploaded_file = null;
    }
}