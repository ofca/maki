<?php

namespace Maki\Controller;

use Maki\Controller;
use Maki\Maki;

/**
 * Theme manager.
 *
 * @package Maki\Controller
 */
class ThemeManagerController extends Controller
{
    public static function match(Maki $app)
    {
        if (isset($_GET['change_css'])) {
            return 'changeThemeAction';
        }
    }

    public function changeThemeAction()
    {
        // @todo sanitize (check if this stylesheet exist)
        setcookie('theme_css', $_GET['change_css'], time()+(60 * 60 * 24 * 30 * 12), '/');
        $this->app->redirect($this->app->getUrl().$this->app->getCurrentUrl());
    }
}