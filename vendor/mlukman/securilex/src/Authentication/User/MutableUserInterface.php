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

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A MutableUserInterface allows setting password, salt and roles of the user account.
 */
interface MutableUserInterface extends UserInterface
{

    /**
     * Set the password.
     * @param string $password
     */
    public function setPassword($password);

    /**
     * Set the salt for the password encoding.
     * @param string $salt
     */
    public function setSalt($salt);

    /**
     * Set the roles.
     * @param string[]  $roles
     */
    public function setRoles(array $roles);
}