<?php
/**
 * This file is part of the Securilex-Doctrine library for Silex framework.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package Securilex\Doctrine
 * @author Muhammad Lukman Nasaruddin <anatilmizun@gmail.com>
 * @link https://github.com/MLukman/Securilex-Doctrine Securilex-Doctrine Github
 * @link https://packagist.org/packages/mlukman/securilex-doctrine Securilex-Doctrine Packagist
 */

namespace Securilex\Doctrine;

use Securilex\Authentication\User\MutableUserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * DoctrineMutableUserProvider extends DoctrineUserProvider by implementing MutableUserProviderInterface
 * that allows saving/removing user accounts from database.
 */
class DoctrineMutableUserProvider extends DoctrineUserProvider implements MutableUserProviderInterface
{

    /**
     * Save the user.
     * @param UserInterface $user The user account instance
     */
    public function saveUser(UserInterface $user)
    {
        $this->em->persist($user);
        $this->em->flush($user);
    }

    /**
     * Remove the user.
     * @param UserInterface $user The user account instance
     */
    public function removeUser(UserInterface $user)
    {
        $this->em->remove($user);
        $this->em->flush($user);
    }
}