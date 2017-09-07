<?php
/**
 * This file is part of the Securilex library for Silex framework.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package Securilex\Authentication\Factory
 * @author Muhammad Lukman Nasaruddin <anatilmizun@gmail.com>
 * @link https://github.com/MLukman/Securilex Securilex Github
 * @link https://packagist.org/packages/mlukman/securilex Securilex Packagist
 */

namespace Securilex\Authentication\Factory;

use Symfony\Component\Security\Core\Encoder\BCryptPasswordEncoder;

/**
 * Description of BCryptAuthenticationFactory
 */
class BCryptAuthenticationFactory extends InitializableAuthenticationFactory
{

    public function __construct()
    {
        parent::__construct(new BCryptPasswordEncoder(4), '');
    }
}