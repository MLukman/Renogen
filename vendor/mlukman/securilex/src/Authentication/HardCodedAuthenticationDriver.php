<?php
/**
 * This file is part of the Securilex library for Silex framework.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package Securilex\Authentication
 * @author Muhammad Lukman Nasaruddin <anatilmizun@gmail.com>
 * @link https://github.com/MLukman/Securilex Securilex Github
 * @link https://packagist.org/packages/mlukman/securilex Securilex Packagist
 */

namespace Securilex\Authentication;

use Securilex\Authentication\Factory\PlaintextPasswordAuthenticationFactory;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * HardCodedAuthenticationDriver combines both Authentication Factory and UserProvider
 * into single instance. Use this class for a simple hard-coded list of users.
 */
class HardCodedAuthenticationDriver extends PlaintextPasswordAuthenticationFactory implements AuthenticationDriverInterface
{
    /**
     * Id of this factory
     * @var string
     */
    protected $id = null;

    /**
     * The UserProvider used internally to stored the users
     * @var InMemoryUserProvider
     */
    protected $userProvider;

    /**
     * Construct an instance.
     * @staticvar int $instanceId
     */
    public function __construct()
    {
        parent::__construct();
        $this->userProvider = new InMemoryUserProvider();
    }

    /**
     * Get the unique id of this instance of authentication factory.
     * @return string
     */
    public function getId()
    {
        static $instanceId = 0;
        if (!$this->id) {
            $this->id = 'hardcoded'.($instanceId++);
        }
        return $this->id;
    }

    /**
     * Add a new user to the list of hard-coded users.
     * @param string $username Username
     * @param string $password Plain-text password
     * @param array $roles
     */
    public function addUser($username, $password, array $roles)
    {
        $this->userProvider->createUser(new User($username, $password, $roles));
    }

    /**
     * Load user by username.
     * @param string $username
     * @return UserInterface
     * @throws UsernameNotFoundException
     */
    public function loadUserByUsername($username)
    {
        return $this->userProvider->loadUserByUsername($username);
    }

    /**
     * Refresh user.
     * @param UserInterface $user
     * @return UserInterface
     */
    public function refreshUser(UserInterface $user)
    {
        return $this->userProvider->refreshUser($user);
    }

    /**
     * Determine if the provided class name is supported or not.
     * @param string $class
     * @return boolean
     */
    public function supportsClass($class)
    {
        return $this->userProvider->supportsClass($class);
    }
}