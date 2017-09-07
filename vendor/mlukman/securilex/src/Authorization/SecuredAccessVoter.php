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

namespace Securilex\Authorization;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * SecuredAccessVoter is Symfony Voter subclass which votes on objects which
 * implement SecuredAccessInterface.
 */
class SecuredAccessVoter extends Voter
{

    /**
     * Determines if the attribute and subject are supported by this voter.
     *
     * @param string $attribute An attribute
     * @param mixed  $subject   The subject to secure, e.g. an object the user wants to access or any other PHP type
     *
     * @return bool True if the attribute and subject are supported, false otherwise
     */
    protected function supports($attribute, $subject)
    {
        return ($subject instanceof SecuredAccessInterface);
    }

    /**
     * Perform a single access check operation on a given attribute, subject and token.
     *
     * @param string         $attribute
     * @param mixed          $subject
     * @param TokenInterface $token
     *
     * @return bool
     */
    protected function voteOnAttribute($attribute, $subject,
                                       TokenInterface $token)
    {
        /* @var $subject SecuredAccessInterface */
        if ($subject->isUsernameAllowed($token->getUsername(), $attribute)) {
            return true;
        }

        foreach ($token->getRoles() as $role) {
            if ($subject->isRoleAllowed($role->getRole(), $attribute)) {
                return true;
            }
        }

        return false;
    }
}