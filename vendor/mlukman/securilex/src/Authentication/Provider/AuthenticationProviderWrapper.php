<?php

namespace Securilex\Authentication\Provider;

use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;

class AuthenticationProviderWrapper implements AuthenticationProviderInterface
{
    /** @var AuthenticationProviderInterface */
    protected $authProvider;
    protected $exceptionHandler;

    public function __construct(AuthenticationProviderInterface $authProvider,
                                callable $exceptionHandler = null)
    {
        $this->authProvider = $authProvider;
        if ($exceptionHandler) {
            $this->exceptionHandler = $exceptionHandler;
        } else {
            $this->exceptionHandler = function(\Exception $ex) {
                if ($ex instanceof UsernameNotFoundException) {
                    return null;
                }
                throw $ex;
            };
        }
    }

    public function authenticate(TokenInterface $token)
    {
        try {
            return $this->authProvider->authenticate($token);
        } catch (\Exception $ex) {
            return call_user_func($this->exceptionHandler, $ex);
        }
    }

    public function supports(TokenInterface $token)
    {
        return $this->authProvider->supports($token);
    }
}