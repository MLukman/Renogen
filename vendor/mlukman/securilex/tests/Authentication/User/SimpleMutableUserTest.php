<?php

namespace Securilex\Authentication\User;

use PHPUnit\Framework\TestCase;

class SimpleMutableUserTest extends TestCase
{
    protected $instance = null;

    protected function setUp()
    {
        if (!$this->instance) {
            $this->instance = new SimpleMutableUser('username', 'password', array(
                'role01'));
        }
    }

    /**
     * @covers SimpleMutableUser::getUsername
     */
    public function testGetUsername()
    {
        $this->assertEquals('username', $this->instance->getUsername());
    }

    /**
     * @covers SimpleMutableUser::getPassword
     */
    public function testGetPassword()
    {
        $this->assertEquals('password', $this->instance->getPassword());
    }

    /**
     * @covers SimpleMutableUser::getRoles
     */
    public function testGetRoles()
    {
        $this->assertEquals(array('role01'), $this->instance->getRoles());
    }

    /**
     * @covers SimpleMutableUser::setPassword
     */
    public function testSetPassword()
    {
        $password = md5(random_bytes(8));
        $this->instance->setPassword($password);
        $this->assertEquals($password, $this->instance->getPassword());
    }

    /**
     * @covers SimpleMutableUser::setRoles
     */
    public function testSetRoles()
    {
        $roles = array(md5(random_bytes(8)));
        $this->instance->setRoles($roles);
        $this->assertEquals($roles, $this->instance->getRoles());
    }
}