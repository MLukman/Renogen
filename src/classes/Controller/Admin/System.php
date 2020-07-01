<?php

namespace Renogen\Controller\Admin;

use Renogen\Base\RenoController;
use Symfony\Component\HttpFoundation\Request;

class System extends RenoController
{

    public function phpinfo(Request $request)
    {
        $this->addCrumb('PHP Info', $this->app->path('admin_phpinfo'), 'php');
        return $this->render("renobase", array(
                'content' => '<iframe id="topmargin" style="position:fixed; top:0px; left:0; bottom:0; right:0; width:100%; height:100%; border:none; margin:0; padding:0; overflow:hidden;" src="'.$this->app->path('admin_phpinfo_content').'" />'
        ));
    }

    public function phpinfo_content(Request $request)
    {
        ob_start();
        phpinfo();
        $html = ob_get_contents();
        ob_end_clean();
        return $html;
    }
}