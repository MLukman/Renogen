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

use Doctrine\ORM\EntityManager;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * DoctrineUserProvider loads user accounts from database using Doctrine ORM library. 
 * The entity that represents user accounts is required to implement UserInterface.
 */
class DoctrineUserProvider implements UserProviderInterface
{
    const NOT_FOUND = 'Username "%s" does not exist.';

    /**
     *
     * @var EntityManager
     */
    protected $em;

    /**
     * The User entity full class name
     * @var string
     */
    protected $userClass;

    /**
     * The column name for the username in the database table
     * @var string
     */
    protected $usernameColumn;

    /**
     * Additional criteria when querying for User
     * @var array
     */
    protected $additionalCriteria;

    /**
     * Construct an instance.
     * @param EntityManager $em The Doctrine ORM Entity Manager
     * @param string $userClass The fully qualified name of the entity class for user account
     * @param string $usernameColumn The column name that stores the username
     * @param array $additionalCriteria Any additional criteria to further filter the query of the entity
     */
    public function __construct(EntityManager $em, $userClass,
                                $usernameColumn = 'username',
                                array $additionalCriteria = array())
    {
        $this->em                 = $em;
        $this->userClass          = $userClass;
        $this->usernameColumn     = $usernameColumn;
        $this->additionalCriteria = $additionalCriteria;
    }

    /**
     * Load User by username
     * @param string $username
     * @return UserInterface
     * @throws UsernameNotFoundException
     */
    public function loadUserByUsername($username)
    {
        $user = $this->em->getRepository($this->userClass)->findOneBy(
            array_merge(
                $this->additionalCriteria,
                array($this->usernameColumn => $username)
            )
        );
        if (!$user) {
            $ex = new UsernameNotFoundException(
                sprintf(self::NOT_FOUND, $username));
            $ex->setUsername($username);
            throw $ex;
        }
        return $user;
    }

    /**
     * Refresh the User object
     * @param UserInterface $user
     * @return UserInterface
     */
    public function refreshUser(UserInterface $user)
    {
        try {
            return $this->loadUserByUsername($user->getUsername());
        } catch (UsernameNotFoundException $e) {
            throw new UnsupportedUserException(sprintf('User "%s" did not come from this provider (%s).',
                $user->getUsername(), get_called_class()));
        }
    }

    /**
     * Check if the passed class name is supported or not
     * @param string $class The class name
     * @return boolean Whether the passed class is supported
     */
    public function supportsClass($class)
    {
        if ($class == $this->userClass) {
            return true;
        }
        $c = new \ReflectionClass($class);
        return $c->isSubclassOf($this->userClass);
    }
}