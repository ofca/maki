<?php

namespace Maki\Controller;

use Maki\Controller;
use Maki\Maki;

/**
 * Serves media resources (css, js).
 *
 * Class ResourceController
 * @package Maki\Controller
 */
class ServeResourceController extends Controller
{
    public static function match(Maki $app)
    {
        if (isset($_GET['resource'])) {
            return 'serve';
        }
    }

    public function isSecured($action)
    {
        return false;
    }

    public function serve()
    {
        $this->app->getThemeManager()->serveResource($_GET['resource']);
    }
}