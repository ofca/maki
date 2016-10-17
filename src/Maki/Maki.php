<?php

namespace Maki;

/**
 * Class Maki
 * @package Maki
 * @todo secure maki.json
 * @todo better css for headers
 * @todo editor look&feel
 * @todo search
 * @todo page renaming
 * @todo who made change on page
 * @todo expandable sections
 * @todo if page has "." (dot) in name there occurs error "No input file specified"
 * @todo "download as file" option for code snippets
 * @todo nav on mobile
 * @todo make nicer error page for "maki.dev/something.php" url.
 */
class Maki extends \Pimple
{
    protected $url;

    protected $sessionId;
    /**
     * @var ThemeManager
     */
    protected $themeManager;

    /**
     * Base container values.
     * @var array
     */
    protected $values = [
        'main_title'    => null
    ];

    protected $controllers = [
        'Maki\Controller\ServeResourceController' => 9999,
        'Maki\Controller\UserController' => 1000,
        'Maki\Controller\ThemeManagerController' => 1000,
        'Maki\Controller\PageController' => 0
    ];

    /**
     * Config:
     *
     * - docroot - Document root path (must ends with trailing slash)
     *
     * @param array $config
     * @throws \InvalidArgumentException
     */
    public function __construct(array $config = array())
    {
        session_start();
        $this->sessionId = session_id();

        $config = new Collection($config);

        // Document root path must be defined
        if ( ! $config->has('docroot')) {
            throw new \InvalidArgumentException('`docroot` is not defined.');
        }

        $this['docroot'] = $config->pull('docroot');

        // Base url
        $this['url.base'] = $config->pull('url.base') ?: pathinfo($_SERVER['SCRIPT_NAME'], PATHINFO_DIRNAME);
        $this['url.base'] = str_replace('//', '/', '/'.trim($this['url.base'], '/').'/');

        // Create htaccess as soon as possible (if needed)
        $this->createHtAccess();
        $this->initThemeManager($config);

        // Documentation files extensions
        $this['docs.extensions'] = $config->pull('docs.extensions') ?: [
            'md'        => 'markdown',
            'markdown'  => 'markdown'
        ];

        $this['cookie.auth_name'] = $config->pull('cookie.auth_name', 'maki');
        $this['cookie.auth_expire'] = $config->pull('cookie.auth_expire', 3600 * 24 * 30); // 30 days
        $this['users'] = $config->pull('users', []);
        $this['salt'] = $config->pull('salt', '');

        $this->values = array_merge($this->values, $config->toArray());

        $this['user'] = null;

        // Define default markdown parser
        if ( ! $this->offsetExists('parser.markdown')) {
            $this['parser.markdown'] = $this->share(function($c) {
                $markdown = new Markdown();
                $markdown->baseUrl = $c['url.base'];

                return $markdown;
            });
        }

        //
        if ( ! $this->offsetExists('docs.path')) {
            $this['docs.path'] = '';
        }

        // Markdown files directory
        $this['docs.path'] = $this['docs.path'] == '' ? '' : rtrim($this['docs.path'], '/').'/';

        // Index file in directory
        if ( ! $this->offsetExists('docs.index_filename')) {
            $this['docs.index_filename'] = 'index';
        }

        // Sidebar filename
        if ( ! $this->offsetExists('docs.navigation_filename')) {
            $this['docs.navigation_filename'] = '_nav';
        }

        if ( ! $this->offsetExists('editable')) {
            $this['editable'] = true;
        }

        // Whats for is "viewable"?
        if ( ! $this->offsetExists('viewable')) {
            $this['viewable'] = true;
        }

        if ( ! $this->offsetExists('cache_dir')) {
            $this['cache_dir'] = '_maki_cache';
        }

        // Normalize path
        $this['cache_dir'] = rtrim($this['cache_dir'], '/').'/';

        // Create cache dir
        if ( ! is_dir($this->getCacheDirAbsPath())) {
            mkdir($this->getCacheDirAbsPath(), 0700, true);
        }

        $this->handleRequest();
    }

    /**
     * @return ThemeManager
     */
    public function getThemeManager()
    {
        return $this->themeManager;
    }

    public function getResource($path)
    {
        $func = 'resource_'.md5($path);

        if (function_exists($func)) {
            return $func();
        }

        $realpath = realpath($this['docroot'].$path);

        if ($realpath == false or strpos($realpath, $this['docroot']) !== 0) {
            return false;
        }

        $ext = pathinfo($realpath, PATHINFO_EXTENSION);

        if ( ! in_array($ext, ['css', 'js'])) {
            return false;
        }

        if (is_file($realpath)) {
            return file_get_contents($realpath);
        }

        return false;
    }

    public function response($body = '', $type = 'text/html', $code = 200, $headers = [])
    {
        switch ($code) {
            case 200: header('HTTP/1.1 200 OK'); break;
            case 400: header('HTTP/1.1 400 Bad Request'); break;
            case 404: header('HTTP/1.1 404 Not Found'); break;
        }

        header('Content-Type: '.$type);

        foreach ($headers as $header) {
            header($header);
        }

        echo $body;

        exit(0);
    }

    public function responseFileNotFound($text = 'File not found')
    {
        $this->response($text, 'text/plain', 404);
    }

    public function getCurrentUrl()
    {
        if ($this->url === null) {
            $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            $script = trim($this['url.base'], '/');
            $this->url = trim(str_replace($script, '', trim($uri, '/')), '/');
        }

        return $this->url;
    }

    /**
     * Return url.
     * @return string
     */
    public function getUrl()
    {
        static $url;

        if (!$url) {
            $ssl = (!empty($_SERVER['HTTPS']) and $_SERVER['HTTPS'] == 'on');
            $protocol = 'http' . ($ssl ? 's' : '');
            $port = $_SERVER['SERVER_PORT'];
            $port = ((!$ssl && $port == '80') || ($ssl && $port == '443')) ? '' : ':' . $port;
            $host = $_SERVER['HTTP_HOST'];

            $url = $protocol . '://' . $host . $port . $this['url.base'];
        }

        return $url;
    }

    public function getCacheDirAbsPath()
    {
        return $this['docroot'].$this['cache_dir'];
    }

    public function getSessionId()
    {
        return $this->sessionId;
    }

    public function redirect($url, $permanent = false)
    {
        if ($permanent) {
            header('HTTP/1.1 301 Moved Permanently');
        } else {
            header('HTTP/1.1 302 Moved Temporarily');
        }

        header('Location: '.$url);
        exit(0);
    }

    /**
     * Return url to specified resource.
     *
     *     $app->getResourceUrl('resources/jquery.js');
     *     // Return "http://domain.com?resource=resources/jquery.js
     *
     * @param $resource
     * @return string
     */
    public function getResourceUrl($resource)
    {
        return $this->getUrl().'?resource='.$resource;
    }

    /**
     * Render view.
     *
     * @param $path Path to view (relative from document root).
     * @param array $data Data passed to view.
     * @return string
     */
    public function render($path, array $data = [])
    {
        $data['app'] = $this;
        $func = 'view_'.md5($path);

        if (function_exists($func)) {
            $content = $func($data);
        } else {
            extract($data);
            $path = $this['docroot'] . $path;

            if (!is_file($path)) {
                throw new \InvalidArgumentException(sprintf('View "%s" does not exists.', $path));
            }

            ob_start();
            include $path;
            $content = ob_get_contents();
            ob_end_clean();
        }

        return $content;
    }

    /**
     * Checks if user is authenticated.
     *
     * @return bool
     */
    public function isUserAuthenticated()
    {
        return isset($_SESSION['auth']);
    }

    /**
     * Return user data for specified username.
     *
     * @param $username User name.
     * @return array User data.
     * @return \InvalidArgumentException If user with specified username does not exists.
     */
    public function getUser($username)
    {
        foreach ($this['users'] as $user) {
            if ($user['username'] === $username) {
                return $user;
            }
        }

        return new \InvalidArgumentException(sprintf('User "%s" does not exists.', $username));
    }

    /**
     * Authenticate user.
     *
     * @param $username User name.
     * @param bool|false $remember Remember user.
     */
    public function authenticate($username, $remember = false)
    {
        $_SESSION['auth'] = $username;

        if ($remember) {
            $token = sha1($username.$this['salt']);
            setcookie($this['cookie.auth_name'], $token, time() + $this['cookie.auth_expire'], '/');

            $path = $this['cache_dir'].'users/';
            if (!is_dir($path)) {
                mkdir($path, 0777, true);
            }

            file_put_contents($path.$token, $username);

            // Garbage collector
            foreach (scandir($path) as $fileName) {
                if ($fileName == '.' or $fileName == '..') {
                    continue;
                }

                if (filemtime($path.$fileName) < time() - $this['cookie.auth_expire']) {
                    @unlink($path.$fileName);
                }
            }
        }
    }

    /**
     * Deauthenticate user, destroys session, removes "remember me" cookies.
     */
    public function deauthenticate()
    {
        session_destroy();
        unset($_COOKIE[$this['cookie.auth_name']]);
        setcookie($this['cookie.auth_name'], null, -1, '/');
    }

    protected function handleRequest()
    {
        foreach ($this->controllers as $class => $priority) {
            $action = forward_static_call([$class, 'match'], $this);
            if (is_string($action)) {
                $this->dispatchController($class, $action);
            }
        }
    }

    protected function dispatchController($class, $action)
    {
        $controller = new $class($this);

        if ($controller->isSecured($action)) {
            $this->checkAuthentication();
        }

        if (!method_exists($controller, $action)) {
            throw new \InvalidArgumentException(sprintf('Method "%s" does not exists in "%s" controller.', $action, $class));
        }

        call_user_func([$controller, $action]);
    }

    /**
     * Creates and inits theme manager.
     *
     * - defines default theme
     * - adds themes specified in config
     * - resolve active theme
     *
     * @param Collection $config
     */
    protected function initThemeManager(Collection $config)
    {
        $tm = new ThemeManager($this);

        // Set default theme
        $tm->addStylesheet('light', 'resources/light.css');

        // Add styles defined in config
        if ($config->has('theme.stylesheets')) {
            $tm->addStylesheets($config->pull('theme.stylesheets'));
        }

        // Set active theme
        if (isset($_COOKIE['theme_css']) and $tm->isStylesheetExist($_COOKIE['theme_css'])) {
            $tm->setActiveStylesheet($_COOKIE['theme_css']);
        } else if ($config->has('theme.active')) {
            $tm->setActiveStylesheet($config->pull('theme.active'));
        } else {
            $tm->setActiveStylesheet('light');
        }

        $this->themeManager = $tm;
    }

    /**
     * Creates .htaccess file inside root directory.
     */
    protected function createHtAccess()
    {
        // Create htaccess if not exists yet
        if ( ! is_file($this['docroot'].'.htaccess')) {
            file_put_contents($this['docroot'].'.htaccess', '<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews
    </IfModule>

    RewriteEngine On
    RewriteBase '.$this['url.base'].'

    # Redirect Trailing Slashes...
    RewriteRule ^(.*)/$ /$1 [L,R=301]

    # Handle Front Controller...
    # RewriteCond %{REQUEST_FILENAME} !-d
    # RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>');

            $this->redirect($this->getUrl(), false);
        }

    }

    /**
     * Check if user is authenticated
     */
    protected function checkAuthentication()
    {
        // If no users defined wiki is public
        if (!$this['users']) {
            return;
        }

        // User authorized
        if ($this->isUserAuthenticated()) {
            try {
                $this['user'] = $this->getUser($_SESSION['auth']);
                return;
            } catch (\InvalidArgumentException $e) {
                // If user not found on the list it means he/she was logged
                // but in the meantime someone modified maki's config file.
                // We logout this user now.
                $this->deauthenticate();
            }
        }

        $cookieName = $this['cookie.auth_name'];

        // Check if user was remembered
        if (isset($_COOKIE[$cookieName])) {
            $token = $_COOKIE[$cookieName];

            if (strlen($token) == 40 and preg_match('/^[0-9a-z]+$/', $token)) {
                $path = $this['cache_dir'].'users/'.$token;

                if (is_file($path)) {
                    $username = file_get_contents($path);

                    try {
                        // We call this method only to make sure
                        // username from cookie exists in our database.
                        $this->getUser($username);
                        $this->authenticate($username, true);
                        return;
                    } catch (\InvalidArgumentException $e) {
                        // If getUser throw exception login view will be displayed
                    }
                }
            }
        }

        // Display username form
        $this->dispatchController('Maki\Controller\UserController', 'loginPageAction');
    }

}