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

use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * UserProviderSegment is intended to be used by UserProviderSegmentalizer. Refer
 * UserProviderSegmentalizer for further references.
 */
class UserProviderSegment implements UserProviderInterface
{
    /**
     * The parent segmentalizer
     * @var UserProviderSegmentalizer
     */
    protected $segmentalizer;

    /**
     * The filter function
     * @var callable
     */
    protected $filter;

    /**
     * Construct an instance using the provided UserProviderSegmentalizer and filter function.
     * @param \Securilex\Authentication\UserProviderSegmentalizer $segmentalizer
     * @param \Securilex\Authentication\callable $filter
     */
    public function __construct(UserProviderSegmentalizer $segmentalizer,
                                callable $filter)
    {
        $this->segmentalizer = $segmentalizer;
        $this->filter        = $filter;
    }

    /**
     * Load user by username from the UserProviderSegmentalizer and apply filter
     * function on the returned User object.
     * @param string $username
     * @return UserInterface
     * @throws UsernameNotFoundException
     */
    public function loadUserByUsername($username)
    {
        $user = $this->segmentalizer->loadUserByUsername($username);
        if (call_user_func($this->filter, $user)) {
            return $user;
        }
        throw new UsernameNotFoundException(sprintf('Username "%s" does not exist.', $username));
    }

    /**
     * Invoke UserProviderSegmentalizer::refreshUser method.
     * @param UserInterface $user
     * @return UserInterface
     */
    public function refreshUser(UserInterface $user)
    {
        return $this->segmentalizer->refreshUser($user);
    }

    /**
     * Invoke UserProviderSegmentalizer::supportsClass.
     * @param string $class
     * @return boolean
     */
    public function supportsClass($class)
    {
        return $this->segmentalizer->supportsClass($class);
    }
}