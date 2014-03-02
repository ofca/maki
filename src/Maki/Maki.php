<?php

namespace Maki;

class Maki extends \Pimple
{
    protected $url;
    protected $editing = false;
    protected $sidebar;
    protected $page;

    public function __construct(array $values = array())
    {
        parent::__construct($values);

        // Define default markdown parser
        if ( ! $this->offsetExists('parser.markdown')) {
            $this['parser.markdown'] = $this->share(function($c) {
                $markdown = new \Maki\Markdown();
                $markdown->baseUrl = $c['url.base'];
                
                return $markdown;
            });
        }

        if ( ! $this->offsetExists('docroot')) {
            throw new \InvalidaArgumentException(sprintf('`docroot` is not defined.'));
        }

        // jQuery url
        if ( ! $this->offsetExists('theme.jQuery')) {
            $this['theme.jQuery'] = 'http://code.jquery.com/jquery-2.1.0.min.js';
        }

        // Bootstrap
        if ( ! $this->offsetExists('theme.bootstrap')) {
            $this['theme.bootstrap'] = 'http://netdna.bootstrapcdn.com/bootstrap/3.1.1/css/bootstrap.min.css';
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
        $this['url.base'] = '/'.trim($this['url.base'], '/').'/';

        if ( ! $this->offsetExists('editable')) {
            $this['editable'] = true;
        }

        if ( ! $this->offsetExists('source_viewable')) {
            $this['source_viewable'] = true;
        }

        if ( ! $this->offsetExists('title')) {
            $this['title'] = '<strong>ma</strong>ki';
        }

        if ( ! $this->offsetExists('subtitle')) {
            $this['subtitle'] = 'SIMPLE <strong>MA</strong>RKDOWN WI<strong>KI</strong>';
        }

        // Create htaccess if not exists yet
        if ( ! is_file($this['docroot'].'.htaccess')) {
            $this->createHtAccess();

            header('HTTP/1.1 302 Moved Temporarily');
            header('Location: '.$this->getUrl());
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

        if ($this['editable'] and isset($_GET['edit'])) {
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
    RewriteCond %{REQUEST_FILENAME} !\.('.implode('|', $this['docs.extensions']).')$
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
        $docroot = $this['docroot'];
        $url = $this->getUrl();
        $bootstrapPath = $url.'_maki_cache/bootstrap.min.css';
        $jQueryPath = $url.'_maki_cache/jquery.min.js';

        if ( ! file_exists($docroot.'_maki_cache')) {
            mkdir($docroot.'_maki_cache', 0777);

            file_put_contents($docroot.$bootstrapPath, file_get_contents($this->bootstrapUrl));
            file_put_contents($docroot.$jQueryPath, file_get_contents($this->jQueryUrl));
        }

        $editButton = ( ! $this['editable'] and $this['source_viewable']) ? 'view source' : 'edit';

        ?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel='stylesheet' href='<?php echo $bootstrapPath ?>'>
        <link href='http://fonts.googleapis.com/css?family=Open+Sans:400,300,800&subset=latin,latin-ext' rel='stylesheet' type='text/css'>
        <script src='<?php echo $jQueryPath ?>'></script>
        <style>
            body {
                background: #303030;
                font-family: "Open sans";
                color: #535353;
            }

            h1, h2, h3, h4, h5, h6 {
                color: #867642;
                font-weight: 300;
            }

            p, ul, li {
                color: #777171;
            }

            a {
                color: #B4893D;
            }

            a:hover, a:focus {
                color: #B4893D;                
            }

            code {
                background: transparent;
                color: #B6A9AC;
            }

            pre {
                color: #919191;
                background: #4B4B4B;
                border: 0;
            }

            .page-container {  }
            .content { 
                background: #3D3D3D; 
                border-radius: 6px; 
                box-shadow:         0px 0px 5px 0px rgba(50, 50, 50, 0.15); 
            }
            .content-inner { padding: 20px; }

            .content .page-actions {
            }

            .sidebar { border-radius: 0 0 6px 6px; }
            .sidebar a {  }
            .sidebar-inner { padding: 20px; }

            .sidebar ul { list-style: none; margin: 0; }
            .sidebar-inner > ul { padding: 0 0 20px 0; }
            .sidebar-inner > ul ul { padding-left: 20px; }
                .sidebar li {  }
                .sidebar li:last-child { border: 0; }

            .sidebar-inner > ul > li > ul { font-size: 13px; }

            .sidebar .page-actions {
                margin-top: 10px;
            }


            .page-actions a { margin-left: 5px; }

            .form-control.textarea {
                width: 100%;
                height: 600px;
                font-size: 11px;
                font-family: "Courier New";
                word-wrap: no-wrap;
                margin-top: 20px;
            }

            .saved-info {
                font-size: 11px;
                color: gray;
                margin-left: 20px;
                display: none;
            }

            .copyrights {
                color: gray;
            }

            .maki-name {
                text-decoration: none !important;
            }
            .maki-name strong {
                color: gray;
                text-decoration: none !important;
            }

            .main-header {
                margin-bottom: 20px;
            }
            .header-title {
                margin-left: 40px;
                margin-bottom: -10px;
            }
            .header-title a {
                text-decoration: none;
                color: gray;
            }
            .header-subtitle {
                color: gray;
                font-size: 10px;
                margin-left: 40px;
            }

            .breadcrumb {
                background: #464646;
                margin: 0 -15px;
                border-radius: 0;
                font-size: 12px;
                border-radius: 6px 6px 0 0;
            }

            .footer {
                margin-top: 10px;
                font-size: 11px;
            }

            .btn-info {
                color: #0C70BE;
                background-color: #BBD9E2;
                border-color: transparent;
                opacity: .5;
            }

            .btn-danger {
                color: #AF2121;
                background-color: #ECB6B4;
                border-color: transparent;
                opacity: .5;
            }
        </style>
    </head>
    <body>
        <div class='container page-container'>
            <header class='row main-header'>
                <h1 class='header-title'><a href='<?php echo $this->getUrl() ?>'><?php echo $this['title'] ?></a></h1>
                <span class='header-subtitle'><?php echo $this['subtitle'] ?></span>
            </header>
            <div class='row'>
                <div class='col-md-3 sidebar'>
                    <div class='sidebar-inner'>
                        <?php echo $this->sidebar->toHTML() ?>
                        <?php if ($this['editable']): ?>
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
                                <a class='btn btn-xs btn-success save-btn'>save</a>
                                <span class='saved-info'>Document saved.</span>
                            </div>
                            <textarea id='textarea' class='textarea form-control'><?php echo $this->page->getContent() ?></textarea>
                        <?php else: ?>
                            <?php echo $this->page->toHTML() ?>
                            <?php if ($this['editable']): ?>
                                <div class='page-actions clearfix'>
                                    <a href='<?php echo $this->getPageUrl() ?>?delete=1' data-confirm='Are you sure you want delete this page?' class='btn btn-xs btn-danger pull-right'>delete</a>
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

            $(document).on('click', '[data-confirm]', function(e) {
                if (confirm($(this).attr('data-confirm'))) {
                    return true;
                } else {
                    e.preventDefault();
                    return false;
                }
            });
        </script>
    </body>
</html>
    <?php

    }
}

namespace Maki\File;

class Markdown
{
    protected $app;
    protected $filePath;
    protected $directoryPath;
    protected $fileAbsPath;
    protected $name;
    protected $exists = false;
    protected $content;
    protected $loaded = false;
    protected $breadcrumb = null;

    public function __construct($app, $filePath)
    {
        $this->app = $app;
        $this->filePath = $filePath;
        $this->fileAbsPath = $app['docs.path'].$filePath;
        $this->name = pathinfo($filePath, PATHINFO_BASENAME);

        if (is_file($this->fileAbsPath)) {
            $this->exists = true;
        }
    }

    public function getName()
    {
        return $this->name;
    }

    public function getContent($forceRefresh = false)
    {
        if (($this->exists and ! $this->loaded) or $forceRefresh) {
            $this->content = file_get_contents($this->fileAbsPath);
            $this->loaded = true;
            return $this->content;
        }

        return $this->content;
    }

    public function setContent($content)
    {
        $this->content = $content;

        return $this;
    }

    public function getBreadcrumb()
    {
        if ($this->breadcrumb === null) {
            $content = $this->getContent();

            preg_match('/<\!\-\-\-\s*@breadcrumb:([^(?:\-\->)]*)/', $content, $match);

            if ( ! isset($match[1])) {
                $this->breadcrumb = array(array(
                    'text'   => $this->getName(),
                    'url'    => $this->getUrl(),
                    'active' => true
                ));
            } else {
                $pages = array();
                $parts = explode(';', trim($match[1]));

                foreach ($parts as $part) {
                    $page = explode('/', $part);
                    end($page);

                    $pages[] = array(
                        'text'      => current($page),
                        'url'       => strpos($part, '.md') === false ? false : $this->app->getUrl().$part,
                        'active'    => false
                    );
                }

                $pages[count($pages)-1]['active'] = true;

                $this->breadcrumb = $pages;
            }
        }

        return $this->breadcrumb;
    }

    public function save()
    {
        $dirName = pathinfo($this->fileAbsPath, PATHINFO_DIRNAME);

        if ( ! is_dir($dirName)) {
            mkdir($dirName, 0777, true);
        }

        file_put_contents($this->fileAbsPath, $this->content);

        return $this;
    }

    public function delete()
    {
        if ($this->exists) {
            @unlink($this->fileAbsPath);

            $this->exists = false;
            $this->loaded = false;
        }

        return $this;
    }

    public function toHTML()
    {
        return $this->app['parser.markdown']->transform($this->getContent());
    }

    public function getUrl()
    {
        return $this->app['url.base'].'/'.$this->filePath;
    }
}