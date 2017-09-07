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

namespace Securilex\Authentication\Factory;

use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Authentication Factory creates instances of Authentication Providers using
 * information from an instance of \Silex\Application, UserProviderInterface and a provider key
 */
interface AuthenticationFactoryInterface
{

    /**
     * Create Authentication Provider instance.
     * @param \Silex\Application $app
     * @param UserProviderInterface $userProvider
     * @param string $providerKey
     * @return AuthenticationProviderInterface
     */
    public function createAuthenticationProvider(\Silex\Application $app,
                                                 UserProviderInterface $userProvider,
                                                 $providerKey);

    /**
     * Get the unique id of this instance of authentication factory.
     * @return string
     */
    public function getId();
}