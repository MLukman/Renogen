<?php

namespace Securilex\Authentication\User;

use PHPUnit\Framework\TestCase;

class IniFileUserProviderTest extends TestCase
{
    /**
     *
     * @var IniFileUserProvider
     */
    protected $instance = null;

    protected function setUp()
    {
        if (!$this->instance) {
            $inif     = tmpfile();
            fwrite($inif, '[ROLE_USER]'.PHP_EOL);
            fwrite($inif, 'User01 = Pass01'.PHP_EOL);
            $metaData = stream_get_meta_data($inif);

            $this->instance = new IniFileUserProvider($metaData['uri']);
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
     * @covers IniFileUserProvider::loadUserByUsername
     */
    public function testLoadUserByUsername()
    {
        $user = $this->instance->loadUserByUsername('User01');
        $this->assertEquals('Pass01', $user->getPassword());
    }

    /**
     * @covers IniFileUserProvider::loadUserByUsername (negative scenario)
     */
    public function testLoadUserByUsernameNotFound()
    {
        try {
            $this->instance->loadUserByUsername('User02');
        } catch (\Symfony\Component\Security\Core\Exception\UsernameNotFoundException $e) {
            $this->assertTrue(true);
            return;
        }
        $this->assertTrue(false);
    }

    /**
     * @covers IniFileUserProvider::refreshUser
     */
    public function testRefreshUser()
    {
        $user = $this->instance->loadUserByUsername('User01');
        $this->assertEquals('Pass01', $this->instance->refreshUser($user)->getPassword());
    }
}