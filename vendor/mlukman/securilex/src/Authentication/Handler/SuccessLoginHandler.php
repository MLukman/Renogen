<?php

namespace Securilex\Authentication\Handler;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\DefaultAuthenticationSuccessHandler;

class SuccessLoginHandler extends DefaultAuthenticationSuccessHandler
{
    protected $handlers = array();

    public function addLoginHandler($name, callable $handler)
    {
        $this->handlers[$name] = $handler;
    }

    public function onAuthenticationSuccess(Request $request,
                                            TokenInterface $token)
    {
        foreach ($this->handlers as $handler) {
            $handler($request, $token);
        }
        return parent::onAuthenticationSuccess($request, $token);
    }
}