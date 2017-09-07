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

use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Core\User\User;

/**
 * NoPasswordUserProvider allows adding users without providing passwords. 
 * Useful for remote authentication where passwords are stored on external servers,
 * e.g LDAP, OAuth, generated token-based systems etc
 */
class NoPasswordUserProvider extends InMemoryUserProvider
{

    /**
     * Add user to the list
     * @param string $username The username
     * @param string[] $roles The user roles
     */
    public function addUser($username, array $roles)
    {
        $this->createUser(new User($username, null, $roles));
    }
}