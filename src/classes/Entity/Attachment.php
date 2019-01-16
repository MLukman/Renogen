<?php

namespace Renogen\Entity;

/**
 * @Entity
 */
class Attachment extends FileLink
{

    public function __construct(Item $item)
    {
        $this->item                            = $item;
        $this->validation_rules['description'] = array('required' => 1, 'trim' => 1);
    }

    public function isUsernameAllowed($username, $attribute)
    {
        return parent::isUsernameAllowed($username, $attribute) ||
            $this->item->isUsernameAllowed($username, $attribute);
    }
}