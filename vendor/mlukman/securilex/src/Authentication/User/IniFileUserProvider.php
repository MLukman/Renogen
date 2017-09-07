<?php
/**
 * This file is part of the Securilex library for Silex framework.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package Securilex\Authentication\User
 * @author Muhammad Lukman Nasaruddin <anatilmizun@gmail.com>
 * @link https://github.com/MLukman/Securilex Securilex Github
 * @link https://packagist.org/packages/mlukman/securilex Securilex Packagist
 */

namespace Securilex\Authentication\User;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * IniFileUserProvider reads list of users from an *.ini file with the following format:
 *
 * [ROLE_ADMIN]
 * username01 = password01 ; if using plaintext password authentication
 * username02 = $2y$04$jg7TwWmiyTR0lgVJ0aOczOzJ1Au7ZolkY83zSDPpa3oeA8FTC4mlK ; if using encoded password
 * username03 = 1 ; if using no-password authentication like LDAP
 * username04 = 0 ; 0 to disable the user from login
 */
class IniFileUserProvider implements UserProviderInterface
{
    protected $users = array();

    public function __construct($ini_filename)
    {
        if (!file_exists($ini_filename)) {
            throw new FileNotFoundException($ini_filename);
        }

        if (!($ini = parse_ini_file($ini_filename, true))) {
            throw new FileException(printf('%s is an invalid INI file', $ini_filename));
        }

        foreach ($ini as $role => $users) {
            foreach ($users as $username => $password) {
                if (empty($password)) {
                    continue;
                }
                $uroles = (isset($this->users[$username]) ?
                    $this->users[$username]->getRoles() : array());
                array_push($uroles, $role);

                $this->users[$username] = $this->createUser($username, $password, $uroles);
            }
        }
    }

    public function loadUserByUsername($username)
    {
        if (!isset($this->users[$username])) {
            throw new UsernameNotFoundException(sprintf('Username "%s" does not exist.', $username));
        }

        return $this->users[$username];
    }

    public function refreshUser(UserInterface $user)
    {
        return $this->createUser($user->getUsername(), $user->getPassword(), $user->getRoles());
    }

    public function supportsClass($class)
    {
        $classRef = new ReflectionClass($class);
        return $classRef->implementsInterface('Symfony\Component\Security\Core\User\UserInterface');
    }

    protected function createUser($username, $password, $roles)
    {
        return new User($username, $password, $roles);
    }
}