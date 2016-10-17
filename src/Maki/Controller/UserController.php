<?php

namespace Maki\Controller;

use Maki\Controller;
use Maki\Maki;

class UserController extends Controller
{
    public function isSecured($action)
    {
        if (in_array($action, ['loginPageAction', 'authAction'])) {
            return false;
        }

        return true;
    }

    public static function match(Maki $app)
    {
        // Log out action
        if (isset($_GET['logout'])) {
            return 'logoutAction';
        }

        // Authorization request
        if ($_SERVER['REQUEST_METHOD'] == 'POST' and isset($_GET['auth'])) {
            return 'authAction';
        }
    }

    /**
     * This action is dispatched manually, there is no url "login" or something like this.
     */
    public function loginPageAction()
    {
        $this->viewResponse('resources/views/login.php');
    }

    public function logoutAction()
    {
        $this->app->deauthenticate();
        $this->app->redirect($this->app->getUrl());
    }

    public function authAction()
    {
        $username = isset($_POST['username']) ? $_POST['username'] : '';
        $pass = isset($_POST['password']) ? $_POST['password'] : '';
        $remember = isset($_POST['remember']) ? $_POST['remember'] : '0';

        $users = $this->app['users'];

        foreach ($users as $user) {
            if ($user['username'] == $username and $user['password'] == $pass) {
                $this->app->authenticate($username, $remember == '1');
                $this->app->response();
            }
        }

        $this->jsonResponse([
            'error' => 'Invalid username or password.'
        ], 'application/json', 400);
    }
}