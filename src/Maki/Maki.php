<?php

namespace Maki;

/**
 * Class Maki
 * @package Maki
 * @todo secure maki.json
 * @todo add {toc} to markodown
 * @todo better css for headers
 * @todo editor look&feel
 * @todo search
 * @todo page renaming
 * @todo who made change on page
 * @todo expandable sections
 * @todo if page has "." (dot) in name there occurs error "No input file specified"
 * @todo "download as file" option for code snippets
 * @todo nav on mobile
 */
class Maki extends \Pimple
{
    protected $url;
    protected $editing = false;
    protected $nav;

    /**
     * @var \Maki\File\Markdown
     * @todo this should be interface
     */
    protected $page;

    protected $sessionId;
    protected $themeManager;

    protected $values = [
        'main_title'    => null
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

        $tm = $this->themeManager = new ThemeManager($this);

        // Set default theme
        $tm->addStylesheet('light', 'resources/light.css');

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

        // Before do anything serve css/js files
        $this->handleResourceRequest();

        // Define default markdown parser
        if ( ! $this->offsetExists('parser.markdown')) {
            $this['parser.markdown'] = $this->share(function($c) {
                $markdown = new Markdown();
                $markdown->baseUrl = $c['url.base'];

                return $markdown;
            });
        }

        // Markdown files directory
        if ( ! $this->offsetExists('docs.path')) {
            $this['docs.path'] = '';
        }

        // Normalize docs.path
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

        $this['user'] = null;
        $this->checkAuthorization();

        if (isset($_GET['change_css'])) {
            // @todo sanitize (check if this stylesheet exist)
            setcookie('theme_css', $_GET['change_css'], time()+2592000, '/');
            header('Location: '.$this->getUrl().$this->getCurrentUrl());
            exit;
        }

        $url = $this->getCurrentUrl();
        $info = pathinfo($url);
        $dirName = isset($info['dirname']) ? $info['dirname'] : '';

        $this->nav = $this->createFileInstance($this->findSidebarFile($dirName));

        // No file specified, so default index is taken
        if ( ! isset($info['extension'])) {
            $url .= $this->findIndexFile($url);
        }

        $this->page = $this->createFileInstance($url);

        if (($this['editable'] or $this['viewable']) and isset($_GET['edit'])) {
            $this->editing = true;
        }

        if ($this['editable'] and isset($_GET['save'])) {
            $this->page->setContent(isset($_POST['content']) ? $_POST['content'] : '')->save();
            exit('1');
        }

        if ($this['editable'] and isset($_GET['delete'])) {
            $this->page->delete();
            $this->redirect($this->getUrl());
        }

        // Simple router
        if (isset($_GET['action'])) {
            $action = $_GET['action'];

            if (!preg_match('/^[a-z]+$/i', $action)) {
                $this->responseFileNotFound();
            }

            $action .= 'Action';

            if (method_exists($this, $action)) {
                $this->$action();
            }
        }
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

    public function handleResourceRequest()
    {
        if (isset($_GET['resource'])) {
            (new ThemeManager($this))->serveResource($_GET['resource']);
        }
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

    public function getPageUrl()
    {
        return $this->page->getUrl();
    }

    /**
     * Return url.
     * @return string
     */
    public function getUrl()
    {
        $url = '';

        $ssl = ( ! empty($_SERVER['HTTPS']) and $_SERVER['HTTPS'] == 'on');
        $protocol = 'http'.($ssl ? 's' : '');
        $port = $_SERVER['SERVER_PORT'];
        $port = ((!$ssl && $port=='80') || ($ssl && $port=='443')) ? '' : ':'.$port;
        $host = $_SERVER['HTTP_HOST'];

        return $protocol.'://'.$host.$port.$this['url.base'];
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

    public function findIndexFile($directory)
    {
        $exts = $this['docs.extensions'];
        $path = $this['docs.path'].rtrim($directory, '/').'/';
        $indexName = $this['docs.index_filename'];

        foreach ($this['docs.extensions'] as $ext) {
            if (is_file($path.$indexName.'.'.$ext)) {
                return $indexName.'.'.$ext;
            }
        }

        return $this['docs.index_filename'].'.'.key($exts);
    }

    public function findSidebarFile($directory)
    {
        $exts = $this['docs.extensions'];
        $path = $this['docroot'].$this['docs.path'].($directory == '' ? '' : rtrim($directory, '/').'/');
        $sidebarName = $this['docs.navigation_filename'];

        foreach ($exts as $ext => $null) {
            if (is_file($path.$sidebarName.'.'.$ext)) {
                return $sidebarName.'.'.$ext;
            }
        }

        return $this['docs.navigation_filename'].'.'.key($exts);
    }

    public function createFileInstance($file)
    {
        $ext = pathinfo($file, PATHINFO_EXTENSION);

        if ( ! isset($this['docs.extensions'][$ext])) {
            throw new \InvalidArgumentException(sprintf('File class for %s not exists.', $file));
        }

        $class = '\\Maki\\File\\'.ucfirst($this['docs.extensions'][$ext]);
        return new $class($this, $file);
    }

    public function createHtAccess()
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

    public function render()
    {
        ob_start();
        $this->defaultTheme();
        $content = ob_get_contents();
        ob_end_clean();

        return $content;
    }

    public function defaultTheme()
    {
        $theme = $this->themeManager;
        $url = $this->getUrl();
        $editable = $this['editable'];
        $viewable = $this['viewable'];
        $editButton = ( ! $editable and $viewable) ? 'view source' : 'edit';
        $activeStylesheet = $theme->getActiveStylesheet();
        $stylesheet = $theme->getStylesheetPath($activeStylesheet);
        $mainTitle = $this['main_title'];

        ?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link href='<?php echo $url.'?resource='.$stylesheet ?>' rel='stylesheet'>
        <script src="<?php echo $url ?>?resource=resources/jquery.js"></script>
        <script src='<?php echo $url ?>?resource=resources/prism.js'></script>
        <script src='<?php echo $url ?>?resource=resources/toc.min.js'></script>
        <script>
            var __PAGE_PATH__ = '<?php echo $this->page->getFilePath() ?>';
        </script>
    </head>
    <body>
        <div class='container'>
            <header class="header">
                <h2><?php echo $mainTitle ?></h2>
                <?php if ($this['users']): ?>
                    <div class="user-actions">
                        hello <a><?php echo $this['user']['username'] ?></a> |
                        <a href="?logout=1">logout</a>
                    </div>
                <?php endif ?>
            </header>
            <div class='nav'>
                <div class='nav-inner'>
                    <?php echo $this->nav->toHTML() ?>
                    <?php if ($editable or $viewable): ?>
                        <div class='page-actions'>
                            <a href='<?php echo $this->nav->getUrl() ?>?edit=1' class='btn btn-xs btn-info pull-right'><?php echo $editButton ?></a>
                        </div>
                    <?php endif ?>
                </div>
            </div>
            <div class='content'>
                <ol class="breadcrumb">
                    <?php foreach ($this->page->getBreadcrumb() as $link): ?>
                        <li <?php echo $link['active'] ? 'class="active"' : '' ?>>
                            <?php if ($link['url']): ?>
                                <a href="<?php echo $link['url'] ?>"><?php echo $link['text'] ?></a>
                            <?php else: ?>
                                <?php echo $link['text'] ?>
                            <?php endif ?>
                        </li>
                    <?php endforeach ?>
                </ol>
                <div class='content-inner'>
                    <?php if ($this->editing): ?>
                        <div class='page-actions'>
                            <a href='<?php echo $this->getPageUrl() ?>' class='btn btn-xs btn-info'>back</a>
                            <?php if ($editable and $this->page->isNotLocked()): ?>
                                <a class='btn btn-xs btn-success save-btn'>save</a>
                                <span class='saved-info'>Document saved.</span>
                            <?php endif ?>

                            <?php if ($this->page->isLocked()): ?>
                                <span class='saved-info' style='display: inline-block'>Someone else is editing this document now.</span>
                            <?php endif ?>
                        </div>
                        <textarea id='textarea' class='textarea editor-textarea'><?php echo $this->page->getContent() ?></textarea>
                    <?php else: ?>
                        <?php echo $this->page->toHTML() ?>

                        <?php if ($editable or $viewable): ?>
                            <div class='page-actions clearfix'>
                                <?php if ($editable): ?>
                                    <a href='<?php echo $this->getPageUrl() ?>?delete=1' data-confirm='Are you sure you want delete this page?' class='btn btn-xs btn-danger pull-right'>delete</a>
                                <?php endif ?>
                                <a href='<?php echo $this->getPageUrl() ?>?edit=1' class='btn btn-xs btn-info pull-right'><?php echo $editButton ?></a>
                            </div>
                        <?php endif ?>

                    <?php endif ?>
                </div>
            </div>
            <footer class='footer text-right'>
                <div class='themes'>
                    <select>
                        <?php foreach ($theme->getStylesheets() as $name => $url): ?>
                            <option value='<?php echo $name ?>' <?php echo $name == $activeStylesheet ? 'selected="selected"' : '' ?>><?php echo $name ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <p class='copyrights'><a href='http://emve.org/maki' target='_blank' class='maki-name'><strong>ma</strong>ki</a> created by <a href='http://darkcinnamon.com/' target='_blank' class='darkcinnamon-name'>emve</a></p>
            </footer>
        </div>
        <script>

        </script>
        <script>
            <?php if ($this->editing and $editable and $this->page->isNotLocked()): ?>
                var $saveBtns = $('.save-btn'),
                    $saved = $('.saved-info');

                function save() {
                    $.ajax({
                        url: '<?php $this->getPageUrl() ?>?save=1',
                        method: 'post',
                        data: {
                            content: $('#textarea').val()
                        },
                        success: function() {
                            $saveBtns.attr('disabled', 'disabled');
                            $saved.show();
                            setTimeout(function() { save(); }, 5000);
                        }
                    });
                };

                var editing = <?php echo var_export($this->editing, true) ?>;

                if (editing) {
                    $('#textarea').on('keyup', function() {
                        $saved.hide();
                        $saveBtns.removeAttr('disabled');
                    });

                    $(document).on('click', '.save-btn', save);

                    save();
                }
            <?php endif ?>

            $(document).on('click', '[data-confirm]', function(e) {
                if (confirm($(this).attr('data-confirm'))) {
                    return true;
                } else {
                    e.preventDefault();
                    return false;
                }
            });

            var codeActionsTmpl = '' +
                '<div class="code-actions">' +
                '   <a href="#download" class="code-action-download">download</a>'
                '</div>';

            $('.content').find('code').each(function(index) {
                var $this = $(this);

                if (this.className != '') {
                    this.className = 'language-'+this.className;
                }

                $(codeActionsTmpl)
                    .find('.code-action-download')
                    .attr('href', '?action=downloadCode&index=' + index)
                    .insertAfter($this.parent());
            });

            Prism.highlightAll();

            $('.themes > select').on('change', function() {
                window.location = '<?php $this->getCurrentUrl() ?>?change_css='+this.value;
            });

            $('.nav-inner [href="/'+__PAGE_PATH__+'"]').closest('li').append('<div id="page-toc"></div>');
            $('#page-toc').toc({
                container: '.content-inner'
            });
        </script>
    </body>
</html>
    <?php

    }

    public function getLoginPageView()
    {
        ob_start();

        $theme = $this->themeManager;
        $url = $this->getUrl();
        $activeStylesheet = $theme->getActiveStylesheet();
        $stylesheet = $theme->getStylesheetPath($activeStylesheet);

        ?>
<!DOCTYPE html>
<html>
    <head>
        <title>maki</title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link href='<?php echo $url.'?resource='.$stylesheet ?>' rel='stylesheet'>
        <script src="<?php echo $url ?>?resource=resources/jquery.js"></script>
    </head>
    <body class='login-page'>
        <div>
            <form>
                <div class="form-group">
                    <input type="text" placeholder="Username">
                </div>
                <div class="form-group">
                    <input type="password" placeholder="Password">
                </div>
                <div class="form-group checkbox">
                    <label for="field-remember_me"><input type="checkbox" id="field-remember_me"> Remember me</label>
                </div>
                <div class="form-group">
                    <button type="submit">username</button>
                </div>
            </form>
        </div>
        <script>
            $(function() {
                'use strict';

                var $form = $('form'),
                    $name = $('input[type=text]'),
                    $password = $('input[type=password]'),
                    $remember = $('input[type=checkbox]');

                $form.on('submit', function(e) {
                    e.preventDefault();

                    $.ajax({
                        url: '?auth=1',
                        type: 'post',
                        data: {
                            username: $name.val(),
                            password: $password.val(),
                            remember: $remember[0].checked ? 1 : 0
                        },
                        success: function() {
                            window.location.reload();
                        },
                        error: function(xhr) {
                            $form.find('.username-form-error').remove();
                            $form.append('<p class="username-form-error">'+xhr.responseJSON.error+'</p>');
                        }
                    });

                    return false;
                });

            });
        </script>
    </body>
</html>
        <?php

        $content = ob_get_contents();
        ob_end_clean();

        return $content;
    }

    /**
     * Check if user is allowed to see wiki.
     */
    protected function checkAuthorization()
    {
        // Logout
        if (isset($_GET['logout'])) {
            $this->deauthorize();
            $this->redirect($this->getUrl());
        }

        // If no users defined wiki is public
        if (!$this['users']) {
            return;
        }

        $users = $this['users'];

        // User authorized
        if (isset($_SESSION['auth'])) {
            foreach ($users as $user) {
                if ($user['username'] == $_SESSION['auth']) {
                    $this['user'] = $user;
                    return true;
                }
            }

            // If user not found on the list it means he/she was logged
            // but in the meantime someone modified maki's config file.
            // We logout this user now.
            $this->deauthorize();
        }

        // Authorization request
        if ($_SERVER['REQUEST_METHOD'] == 'POST' and isset($_GET['auth'])) {
            $username = isset($_POST['username']) ? $_POST['username'] : '';
            $pass = isset($_POST['password']) ? $_POST['password'] : '';
            $remember = isset($_POST['remember']) ? $_POST['remember'] : '0';

            foreach ($users as $user) {
                if ($user['username'] == $username and $user['password'] == $pass) {
                    $this->authorize($username, $remember == '1');
                    $this->response();
                }
            }

            $this->response(json_encode([
                'error' => 'Invalid username or password.'
            ]), 'application/json', 400);
        }

        $cookieName = $this['cookie.auth_name'];

        if (isset($_COOKIE[$cookieName])) {
            $token = $_COOKIE[$cookieName];

            if (strlen($token) == 40 and preg_match('/^[0-9a-z]+$/', $token)) {
                $path = $this['cache_dir'].'users/'.$token;

                if (is_file($path)) {
                    $username = file_get_contents($path);

                    foreach ($users as $user) {
                        if ($user['username'] == $username) {
                            $this->authorize($username);
                            break;
                        }
                    }
                }
            }
        }

        // Display username form
        $this->response($this->getLoginPageView());
    }

    protected function authorize($username, $remember = false)
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

    protected function deauthorize()
    {
        session_destroy();
        unset($_COOKIE[$this['cookie.auth_name']]);
        setcookie($this['cookie.auth_name'], null, -1, '/');
    }

    public function downloadCodeAction()
    {
        $index = (int) $_GET['index'];
        $lines = explode("\n", $this->page->getContent());
        $fileName = pathinfo($this->page->getName(), PATHINFO_FILENAME);

        $counter = 0;
        $opened = false;
        $codeType = '';
        $code = [];

        foreach ($lines as $line) {
            $spacelessLine = preg_replace('/[\t\s]+/', '', $line);

            if (strpos($spacelessLine, '~~~') === 0) {
                if ($opened) {
                    $opened = false;

                    // This is what we are looking for
                    if ($index == $counter) {
                        $this->response(implode("\n", $code), 'application/octet-stream', 200, [
                            'Content-Type: application/octet-stream',
                            'Content-Transfer-Encoding: Binary',
                            'Content-disposition: attachment; filename="'.$fileName.'.'.$codeType.'"'
                        ]);
                    }

                    $counter++;
                } else {
                    $opened = true;
                    $code = [];
                    $codeType = substr($line, 3);
                    continue;
                }
            }

            if ($opened) {
                $code[] = $line;
            }
        }
    }
}