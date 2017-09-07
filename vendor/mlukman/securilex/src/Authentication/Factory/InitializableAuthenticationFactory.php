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

use Securilex\Authentication\User\MutableUserInterface;
use Securilex\Authentication\User\MutableUserProviderInterface;
use Symfony\Component\Security\Core\Authentication\Provider\SimpleAuthenticationProvider;
use Symfony\Component\Security\Core\Authentication\SimpleAuthenticatorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * InitializableAuthenticationFactory allows initializing a user with the current password
 * when the password associated with the user matched with a specific string.
 *
 * This is useful for implementing an authentication system that remembers the first password
 * that a user enters after registration or password-reset.
 * 
 * Note: this authentication factory works best if used with a user provider that implements
 * MutableUserProviderInterface and provides user objects that implements MutableUserInterface.
 * @see MutableUserProviderInterface
 * @see MutableUserInterface
 */
class InitializableAuthenticationFactory extends SimpleAuthenticationFactory implements SimpleAuthenticatorInterface
{
    /**
     * Id of this factory
     * @var string
     */
    protected $id = null;

    /**
     * The initial password
     * @var string
     */
    protected $initPassword;

    /**
     * The password encoder
     * @var PasswordEncoderInterface
     */
    protected $passwordEncoder;

    /**
     * Construct an instance.
     * @staticvar int $instanceId
     * @param PasswordEncoderInterface $passwordEncoder The password encoder
     * @param string $initPassword The initial password
     */
    public function __construct(PasswordEncoderInterface $passwordEncoder,
                                $initPassword = '')
    {
        parent::__construct($this);
        $this->initPassword    = $initPassword;
        $this->passwordEncoder = $passwordEncoder;
    }

    /**
     * Create Authentication Provider instance.
     * @param \Silex\Application $app
     * @param UserProviderInterface $userProvider
     * @param string $providerKey
     * @return SimpleAuthenticationProvider
     */
    public function createAuthenticationProvider(\Silex\Application $app,
                                                 UserProviderInterface $userProvider,
                                                 $providerKey)
    {
        return parent::createAuthenticationProvider($app, $userProvider, $providerKey);
    }

    /**
     * Get the unique id of this instance of authentication factory.
     * @return string
     */
    public function getId()
    {
        static $instanceId = 0;
        if (!$this->id) {
            $this->id = 'init'.($instanceId++);
        }
        return $this->id;
    }

    /**
     * Attempt to authenticate the provided token using the provided user provider.
     * @param TokenInterface $token
     * @param UserProviderInterface $userProvider
     * @param string $providerKey
     * @return UsernamePasswordToken
     * @throws BadCredentialsException
     */
    public function authenticateToken(TokenInterface $token,
                                      UserProviderInterface $userProvider,
                                      $providerKey)
    {
        if (($user = $userProvider->loadUserByUsername($token->getUsername()))) {
            $userPassword     = $user->getPassword();
            $tokenCredentials = $token->getCredentials();
            if ($userPassword == $this->initPassword && $user instanceof MutableUserInterface
                && $userProvider instanceof MutableUserProviderInterface) {
                $this->encodePassword($user, $tokenCredentials);
                $userProvider->saveUser($user);
                // return it
                return new UsernamePasswordToken(
                    $user, $user->getPassword(), $providerKey, $user->getRoles()
                );
            } else if ($this->passwordEncoder->isPasswordValid($userPassword, $tokenCredentials, $user->getSalt())) {
                return new UsernamePasswordToken(
                    $user, $userPassword, $providerKey, $user->getRoles()
                );
            }
        }
        throw new BadCredentialsException('The presented password is invalid.');
    }

    public function encodePassword(MutableUserInterface $user, $new_password)
    {
        $user->setSalt(substr(md5(rand(1, 10000)), 0, 5));
        $user->setPassword($this->passwordEncoder->encodePassword($new_password, $user->getSalt()));
    }

    /**
     * Determine if this instance supports the provided token.
     * @param TokenInterface $token
     * @param string $providerKey
     * @return type
     */
    public function supportsToken(TokenInterface $token, $providerKey)
    {
        return $token instanceof UsernamePasswordToken && $token->getProviderKey()
            === $providerKey;
    }
}