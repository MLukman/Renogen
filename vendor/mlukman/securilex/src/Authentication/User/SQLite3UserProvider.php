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

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * SQLite3UserProvider is a mutable user provider backed by an SQLite3 database
 */
class SQLite3UserProvider implements MutableUserProviderInterface
{
    /**
     *
     * @var \SQLite3
     */
    protected $sqlite = null;

    /**
     *
     * @var array
     */
    protected $defs = null;

    /**
     *
     * @var \SQLite3Stmt
     */
    protected $loadQuery;

    /**
     *
     * @var \SQLite3Stmt
     */
    protected $insertQuery;

    /**
     *
     * @var \SQLite3Stmt
     */
    protected $updateQuery;

    /**
     *
     * @var \SQLite3Stmt
     */
    protected $removeQuery;

    /**
     *
     * @var string
     */
    protected $userClass = '\Securilex\Authentication\User\SimpleMutableUser';

    /**
     *
     * @param \SQLite3 $sqlite
     * @param type $userTable
     * @param type $usernameColumn
     * @param type $passwordColumn
     * @param type $rolesColumn
     */
    public function __construct(\SQLite3 $sqlite, $userTable = 'users',
                                $usernameColumn = 'username',
                                $passwordColumn = 'password',
                                $rolesColumn = 'roles')
    {
        $this->sqlite = $sqlite;

        // create table if not exists
        $this->sqlite->exec("CREATE TABLE IF NOT EXISTS $userTable ($usernameColumn TEXT CONSTRAINT user_pk PRIMARY KEY, $passwordColumn TEXT, $rolesColumn TEXT)");

        $this->defs = array(
            $userTable, $usernameColumn, $passwordColumn, $rolesColumn
        );
    }

    public function __destruct()
    {
        $this->sqlite->close();
    }

    public function createUser($username, $password = null,
                               array $roles = array())
    {
        return new $this->userClass($username, $password, $roles);
    }

    public function loadUserByUsername($username, $exception_if_not_found = true)
    {
        if (!$this->loadQuery) {
            $this->loadQuery = $this->sqlite->prepare("SELECT {$this->defs[1]}, {$this->defs[2]}, {$this->defs[3]} FROM {$this->defs[0]} WHERE {$this->defs[1]} = :username");
        }
        $this->loadQuery->bindValue(':username', $username);
        if (!($result = $this->loadQuery->execute()->fetchArray())) {
            if ($exception_if_not_found) {
                throw new UsernameNotFoundException(sprintf('Username "%s" does not exist.', $username));
            }
            return null;
        }
        return $this->userInstanceFromArray($result);
    }

    protected function userInstanceFromArray($rec)
    {
        $roles = json_decode($rec[$this->defs[3]]) ?: array();
        return $this->createUser($rec[$this->defs[1]], $rec[$this->defs[2]], $roles);
    }

    public function refreshUser(UserInterface $user)
    {
        if (!$this->supportsClass(get_class($user))) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        return $this->createUser($user->getUsername(), $user->getPassword(), $user->getRoles());
    }

    public function removeUser(UserInterface $user)
    {
        if (!$this->removeQuery) {
            $this->removeQuery = $this->sqlite->prepare("DELETE FROM {$this->defs[0]} WHERE {$this->defs[1]} = :username");
        }
        $this->removeQuery->bindValue(':username', $user->getUsername());
        return $this->removeQuery->execute();
    }

    public function saveUser(UserInterface $user)
    {
        $username = $user->getUsername();
        $password = $user->getPassword();
        $roles    = json_encode($user->getRoles() ?: array());

        if (!$this->insertQuery || !$this->updateQuery) {
            $this->insertQuery = $this->sqlite->prepare("INSERT OR IGNORE INTO {$this->defs[0]} ({$this->defs[1]}, {$this->defs[2]}, {$this->defs[3]}) VALUES (:username, :password, :roles)");
            $this->updateQuery = $this->sqlite->prepare("UPDATE {$this->defs[0]} SET {$this->defs[2]} = :password, {$this->defs[3]} = :roles WHERE {$this->defs[1]} = :username");
        }
        $this->insertQuery->bindValue(':username', $username);
        $this->insertQuery->bindValue(':password', $password);
        $this->insertQuery->bindValue(':roles', $roles);
        $this->updateQuery->bindValue(':username', $username);
        $this->updateQuery->bindValue(':password', $password);
        $this->updateQuery->bindValue(':roles', $roles);

        return ($this->insertQuery->execute() && $this->updateQuery->execute());
    }

    public function supportsClass($class)
    {
        return is_a($class, $this->userClass, true);
    }

    public function getUserClass()
    {
        return $this->userClass;
    }

    public function setUserClass($class)
    {
        $this->userClass = $class;
    }

    public function countAll()
    {
        $row = $this->sqlite->query("SELECT COUNT(*) as count FROM {$this->defs[0]}")->fetchArray();
        return $row['count'];
    }

    public function selectAll()
    {
        $all = array();
        $res = $this->sqlite->query("SELECT {$this->defs[1]}, {$this->defs[2]}, {$this->defs[3]} FROM {$this->defs[0]}");
        while ($rec = $res->fetchArray()) {
            $all[$rec[$this->defs[1]]] = $this->userInstanceFromArray($rec);
        }
        return $all;
    }
}