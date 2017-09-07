<?php
/**
 * This file is part of the Securilex library for Silex framework.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package Securilex\Authorization
 * @author Muhammad Lukman Nasaruddin <anatilmizun@gmail.com>
 * @link https://github.com/MLukman/Securilex Securilex Github
 * @link https://packagist.org/packages/mlukman/securilex Securilex Packagist
 */

namespace Securilex\Authentication;

use Securilex\Authentication\Factory\AuthenticationFactoryInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * AuthenticationDriverInterface extends both AuthenticationFactoryInterface and UserProviderInterface.
 */
interface AuthenticationDriverInterface extends AuthenticationFactoryInterface, UserProviderInterface
{

}