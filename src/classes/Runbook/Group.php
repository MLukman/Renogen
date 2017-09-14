<?php

namespace Renogen\Runbook;

class Group
{
    protected $title;
    protected $instruction;
    protected $template;
    protected $data = array();

    public function __construct($title)
    {
        $this->title = $title;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setInstruction($instruction)
    {
        $this->instruction = $instruction;
        return $this;
    }

    public function getInstruction()
    {
        return $this->instruction;
    }

    public function setTemplate($template)
    {
        $this->template = $template;
    }

    public function getTemplate()
    {
        return $this->template;
    }

    public function addRow(array $row)
    {
        $this->data[] = $row;
    }

    public function getData()
    {
        return $this->data;
    }
}