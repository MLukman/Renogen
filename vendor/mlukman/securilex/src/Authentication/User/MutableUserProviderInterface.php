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
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * A MutableUserProviderInterface allows saving or removing user accounts from
 * the persistence sources.
 */
interface MutableUserProviderInterface extends UserProviderInterface
{

    /**
     * Save the user.
     * @param UserInterface $user The user account instance
     * @return boolean TRUE if successfully saved, FALSE otherwise
     */
    public function saveUser(UserInterface $user);

    /**
     * Remove the user.
     * @param UserInterface $user The user account instance
     * @return boolean TRUE if successfully removed, FALSE otherwise
     */
    public function removeUser(UserInterface $user);
}