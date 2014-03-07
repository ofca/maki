<?php

namespace Maki;

class Maki extends \Pimple
{
    protected $url;
    protected $editing = false;
    protected $sidebar;
    protected $page;
    protected $sessionId;

    public function __construct(array $values = array())
    {
        session_start();
        $this->sessionId = session_id();

        if ( ! isset($values['docroot'])) {
            throw new \InvalidaArgumentException(sprintf('`docroot` is not defined.'));
        }

        // Normalize path
        $values['docroot'] = rtrim($values['docroot'], '/').'/';

        // Look for config file
        if (is_file($values['docroot'].'maki-config.json')) {
            $config = file_get_contents($values['docroot'].'maki-config.json');
            $config = json_decode($config, true);

            $values = array_merge($values, $config);
        }

        // Theme css
        if ( ! isset($values['theme.css'])) {
            $values['theme.css'] = array();
        }

        if ( ! is_array($values['theme.css'])) {
            $values['theme.css'] = array($values['theme.css']);
        }

        $values['theme.css']['default'] = '?resource=css';

        parent::__construct($values);

        // Define default markdown parser
        if ( ! $this->offsetExists('parser.markdown')) {
            $this['parser.markdown'] = $this->share(function($c) {
                $markdown = new \Maki\Markdown();
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

        // Documentation files extensions
        if ( ! $this->offsetExists('docs.extensions')) {
            $this['docs.extensions'] = array('md' => 'markdown');
        }

        // Index file in directory
        if ( ! $this->offsetExists('docs.index_name')) {
            $this['docs.index_name'] = 'index';
        }

        // Sidebar filename
        if ( ! $this->offsetExists('docs.sidebar_name')) {
            $this['docs.sidebar_name'] = '_sidebar';
        }

        if ( ! $this->offsetExists('url.base')) {
            $this['url.base'] = pathinfo($_SERVER['SCRIPT_NAME'], PATHINFO_DIRNAME);
        }

        // Normalize base url
        $this['url.base'] = str_replace('//', '/', '/'.trim($this['url.base'], '/').'/');

        if ( ! $this->offsetExists('editable')) {
            $this['editable'] = true;
        }

        if ( ! $this->offsetExists('viewable')) {
            $this['viewable'] = true;
        }

        if ( ! $this->offsetExists('title')) {
            $this['title'] = '<strong>ma</strong>ki';
        }

        if ( ! $this->offsetExists('subtitle')) {
            $this['subtitle'] = 'SIMPLE <strong>MA</strong>RKDOWN WI<strong>KI</strong>';
        }

        if ( ! $this->offsetExists('cache_dir')) {
            $this['cache_dir'] = '_maki_cache';
        }

        // Normalize path
        $this['cache_dir'] = rtrim($this['cache_dir'], '/').'/';

        if ( ! $this->offsetExists('theme.default_css')) {
            $this['theme.default_css'] = 'default';
        }

        if (isset($_COOKIE['theme_css'])) {
            $themes = $this['theme.css'];

            if (isset($themes[$_COOKIE['theme_css']])) {
                $this['theme.default_css'] = $_COOKIE['theme_css'];
            }
        }

        // Create htaccess if not exists yet
        if ( ! is_file($this['docroot'].'.htaccess')) {
            $this->createHtAccess();

            header('HTTP/1.1 302 Moved Temporarily');
            header('Location: '.$this->getUrl());
            exit;
        }

        // Create cache dir
        if ( ! is_dir($this->getCacheDirAbsPath())) {
            mkdir($this->getCacheDirAbsPath(), 0700, true);
        }

        if (isset($_GET['change_css'])) {
            $name = $_GET['change_css'];

            if (preg_match('/^[-_a-z0-9]+$/', $name)) {
                setcookie('theme_css', $name, time()+2678400, '/'); // 31 days
            }

            header('Location: '.$this->getUrl().$this->getCurrentUrl());
            exit;
        }

        $url = $this->getCurrentUrl();
        $info = pathinfo($url);
        $dirName = isset($info['dirname']) ? $info['dirname'] : '';

        $this->sidebar = $this->createFileInstance($this->findSidebarFile($dirName));

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
        if($permanent) {
            header('HTTP/1.1 301 Moved Permanently');
        }

        header('Location: '.$url);
        exit();
    }

    public function findIndexFile($directory)
    {
        $exts = $this['docs.extensions'];        
        $path = $this['docs.path'].rtrim($directory, '/').'/';
        $indexName = $this['docs.index_name'];

        foreach ($this['docs.extensions'] as $ext) {
            if (is_file($path.$indexName.'.'.$ext)) {
                return $indexName.'.'.$ext;
            }
        }

        return $this['docs.index_name'].'.'.key($exts);
    }

    public function findSidebarFile($directory)
    {   
        $exts = $this['docs.extensions'];
        $path = $this['docroot'].$this['docs.path'].($directory == '' ? '' : rtrim($directory, '/').'/');
        $sidebarName = $this['docs.sidebar_name'];
        
        foreach ($exts as $ext => $null) {
            if (is_file($path.$sidebarName.'.'.$ext)) {
                return $sidebarName.'.'.$ext;
            }
        }
        
        return $this['docs.sidebar_name'].'.'.key($exts);
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
        file_put_contents($this['docroot'].'.htaccess', '<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews
    </IfModule>

    RewriteEngine On
    RewriteBase '.$this['url.base'].'

    # Redirect Trailing Slashes...
    RewriteRule ^(.*)/$ /$1 [L,R=301]

    # Do not allow displaying markdown files directly
    RewriteCond %{REQUEST_FILENAME} \.('.implode('|', array_keys($this['docs.extensions'])).')$
    RewriteRule ^ index.php [L]

    # Handle Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>');
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
        $url = $this->getUrl();
        $editable = $this['editable'];
        $viewable = $this['viewable'];
        $editButton = ( ! $editable and $viewable) ? 'view source' : 'edit';
        $css = $this['theme.css'];
        $defaultCss = $this['theme.default_css'];

        ?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link href='<?php echo $url ?>?resource=bootstrap' rel='stylesheet'>
        <link href='<?php echo $url.$css[$defaultCss] ?>' rel='stylesheet'>
        <link href='http://fonts.googleapis.com/css?family=Open+Sans:400,300,800&subset=latin,latin-ext' rel='stylesheet' type='text/css'>        
        <script src='<?php echo $url ?>?resource=jquery'></script>
        <script src='<?php echo $url ?>?resource=prism-js'></script>
    </head>
    <body>
        <div class='container page-container'>
            <?php if (count($css) > 1): ?>
                <div class='themes'>
                    <select>
                        <?php foreach ($css as $name => $item): ?>
                            <option value='<?php echo $name ?>' <?php echo $name == $defaultCss ? 'selected="selected"' : '' ?>><?php echo $name ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
            <?php endif ?>
            <header class='row main-header'>
                <h1 class='header-title'><a href='<?php echo $this->getUrl() ?>'><?php echo $this['title'] ?></a></h1>
                <span class='header-subtitle'><?php echo $this['subtitle'] ?></span>
            </header>
            <div class='row'>
                <div class='col-md-3 sidebar'>
                    <div class='sidebar-inner'>
                        <?php echo $this->sidebar->toHTML() ?>
                        <?php if ($editable or $viewable): ?>
                            <div class='page-actions'>
                                <a href='<?php echo $this->sidebar->getUrl() ?>?edit=1' class='btn btn-xs btn-info pull-right'><?php echo $editButton ?></a>
                            </div>
                        <?php endif ?>
                    </div>
                </div>
                <div class='col-md-9 content'>
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
                            <textarea id='textarea' class='textarea form-control'><?php echo $this->page->getContent() ?></textarea>
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
            </div>
            <div class='row'>
                <footer class='col-md-9 col-md-offset-3 footer text-right'>
                    <p class='copyrights'><a href='http://darkcinnamon.com/maki' target='_blank' class='maki-name'><strong>ma</strong>ki</a> created by <a href='http://darkcinnamon.com/' target='_blank' class='darkcinnamon-name'><strong>dark</strong>cinnamon</a></p>
                </footer>
            </div>
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

            $('code').each(function() {
                if (this.className != '') {
                    this.className = 'language-'+this.className;
                }
            });

            Prism.highlightAll();

            $('.themes > select').on('change', function() {
                window.location = '<?php $this->getCurrentUrl() ?>?change_css='+this.value;
            });
        </script>
    </body>
</html>
    <?php

    }
}