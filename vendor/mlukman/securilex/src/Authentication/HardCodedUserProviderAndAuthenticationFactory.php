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

/**
 * HardCodedUserProviderAndAuthenticationFactory combines both Authentication Factory and UserProvider
 * into single instance. Use this class for a simple hard-coded list of users.
 * @deprecated since version 1.2. Use HardCodedAuthenticationDriver instead
 */
class HardCodedUserProviderAndAuthenticationFactory extends HardCodedAuthenticationDriver
{

}