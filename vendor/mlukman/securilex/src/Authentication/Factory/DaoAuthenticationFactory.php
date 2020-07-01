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

use Symfony\Component\Security\Core\Authentication\Provider\DaoAuthenticationProvider;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * DaoAuthenticationFactory creates instances of DaoAuthenticationProvider
 * using information from an instance of \Silex\Application and a provider key
 */
class DaoAuthenticationFactory implements AuthenticationFactoryInterface
{
    /**
     * Id of this factory
     * @var string
     */
    protected $id = null;

    /**
     * The encoder factory for encoding passwords
     * @var EncoderFactoryInterface
     */
    protected $encoderFactory;

    /**
     * Construct an instance.
     * @staticvar int $instanceId
     * @param EncoderFactoryInterface $encoderFactory The encoder factory for encoding passwords
     */
    public function __construct(EncoderFactoryInterface $encoderFactory = null)
    {
        $this->encoderFactory = $encoderFactory;
    }

    /**
     * Create Authentication Provider instance.
     * @param \Silex\Application $app
     * @param UserProviderInterface $userProvider
     * @param string $providerKey
     * @return DaoAuthenticationProvider
     */
    public function createAuthenticationProvider(\Silex\Application $app,
                                                 UserProviderInterface $userProvider,
                                                 $providerKey)
    {
        return new AuthenticationProviderWrapper(new DaoAuthenticationProvider($userProvider,
                $app['security.user_checker'], $providerKey,
                $this->encoderFactory ?: $app['security.encoder_factory']));
    }

    /**
     * Get the unique id of this instance of authentication factory.
     * @return string
     */
    public function getId()
    {
        static $instanceId = 0;
        if (!$this->id) {
            $this->id = 'dao'.($instanceId++);
        }
        return $this->id;
    }
}