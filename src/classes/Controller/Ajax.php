<?php

namespace Renogen\Controller;

use Renogen\ActivityTemplate\Parameter\Markdown;
use Symfony\Component\HttpFoundation\Request;

class Ajax
{

    public function markdown(Request $request)
    {
        return Markdown::parse($request->request->get("code"));
    }
}