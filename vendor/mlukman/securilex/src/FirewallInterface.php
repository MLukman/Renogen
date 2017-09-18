<?php
/**
 * This file is part of the Securilex library for Silex framework.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package Securilex
 * @author Muhammad Lukman Nasaruddin <anatilmizun@gmail.com>
 * @link https://github.com/MLukman/Securilex Securilex Github
 * @link https://packagist.org/packages/mlukman/securilex Securilex Packagist
 */

namespace Securilex;

interface FirewallInterface
{

    /**
     * Get the generated name of this firewall
     * @return string
     */
    public function getName();

    /**
     * Register the Firewall
     * @param SecurityServiceProvider $provider Service Provider
     */
    public function register(SecurityServiceProvider $provider);

    /**
     * Check if the provided path is covered by this firewall or not
     * @param string $path
     * @return boolean
     */
    public function isPathCovered($path);

    /**
     * Get login check path.
     * @return string
     */
    public function getLoginCheckPath();

    /**
     * Get logout path.
     * @return string
     */
    public function getLogoutPath();
}