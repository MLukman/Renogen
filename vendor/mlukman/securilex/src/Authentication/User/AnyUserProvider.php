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

use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * AnyUserProvider allows any user to be authenticated by the corresponding AuthenticationFactory
 */
class AnyUserProvider implements UserProviderInterface
{
    protected $roles = array();

    public function __construct($roles = null)
    {
        if ($roles) {
            if (!is_array($roles)) {
                $roles = array($roles);
            }
            $this->roles = array_merge($this->roles, $roles);
        }
    }

    public function loadUserByUsername($username)
    {
        return new User($username, $username, $this->roles);
    }

    public function refreshUser(UserInterface $user)
    {
        return $this->loadUserByUsername($user->getUsername());
    }

    public function supportsClass($class)
    {
        return $class === 'Symfony\Component\Security\Core\User\User';
    }
}