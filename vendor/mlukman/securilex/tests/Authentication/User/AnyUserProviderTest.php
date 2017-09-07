<?php

namespace Securilex\Authentication\User;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\User;

class AnyUserProviderTest extends TestCase
{
    protected $instance = null;

    protected function setUp()
    {
        if (!$this->instance) {
            $this->instance = new AnyUserProvider();
        }
    }

    public function testConstructorRoles()
    {
        $instance1 = new AnyUserProvider();
        $this->assertEquals(array(),
            $instance1->loadUserByUsername('test')->getRoles());

        $instance2 = new AnyUserProvider('ROLE_ADMIN');
        $this->assertEquals(array('ROLE_ADMIN'),
            $instance2->loadUserByUsername('test')->getRoles());

        $instance3 = new AnyUserProvider(array('ROLE_USER', 'ROLE_ADMIN'));
        $this->assertEquals(array('ROLE_USER', 'ROLE_ADMIN'),
            $instance3->loadUserByUsername('test')->getRoles());
    }

    public function testLoadUserByUsername()
    {
        $user = $this->instance->loadUserByUsername('User01');
        $this->assertEquals('User01', $user->getUsername());
    }

    public function testRefreshUser()
    {
        $user = new User('User02', 'Password02');
        $this->assertEquals('User02', $this->instance->refreshUser($user));
    }
}