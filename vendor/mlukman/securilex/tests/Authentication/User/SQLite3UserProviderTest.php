<?php

namespace Securilex\Authentication\User;

use PHPUnit\Framework\TestCase;

class SQLite3UserProviderTest extends TestCase
{
    /**
     *
     * @var SQLite3UserProvider
     */
    protected $instance = null;

    protected function setUp()
    {
        if (!$this->instance) {
            $this->instance = new SQLite3UserProvider(new \SQLite3(':memory:'));
        }
    }

    protected function tearDown()
    {
        if ($this->instance) {
            $this->instance = null;
        }
        parent::tearDown();
    }

    /**
     * @covers SQLite3UserProvider::saveUser
     */
    public function testSaveUser()
    {
        $countBefore = $this->instance->countAll();
        $user        = $this->instance->createUser('User'.\rand(10000, 99999), 'password');
        $this->instance->saveUser($user);
        $countAfter  = $this->instance->countAll();
        $this->assertEquals(1, $countAfter - $countBefore);
    }

    /**
     * @covers SQLite3UserProvider::loadUserByUsername
     */
    public function testLoadUserByUsername()
    {
        $this->instance->saveUser($this->instance->createUser('User01', 'password'));
        $user = $this->instance->loadUserByUsername('User01');
        $this->assertEquals('User01', $user->getUsername());
    }

    /**
     * @covers SQLite3UserProvider::refreshUser
     */
    public function testRefreshUser()
    {
        $user = $this->instance->createUser('User02', 'password');
        $this->assertEquals('User02', $this->instance->refreshUser($user)->getUsername());
    }

    /**
     * @covers SQLite3UserProvider::removeUser
     */
    public function testRemoveUser()
    {
        $this->instance->saveUser($this->instance->createUser('User01', 'password'));
        $countBefore = $this->instance->countAll();
        $this->instance->removeUser($this->instance->createUser('User01', 'password'));
        $countAfter  = $this->instance->countAll();
        $this->assertEquals(-1, $countAfter - $countBefore);
    }
}