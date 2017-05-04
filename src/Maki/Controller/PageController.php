<?php

namespace Maki\Controller;

use Maki\Controller;
use Maki\Maki;

/**
 * Page controller.
 * @package Maki\Controller
 */
class PageController extends Controller
{
    public static function match(Maki $app)
    {
        if ($app['editable'] and isset($_GET['save'])) {
            return 'saveContentAction';
        }

        if ($app['editable'] and isset($_GET['delete'])) {
            return 'deleteAction';
        }

        if (isset($_GET['action']) and $_GET['action'] == 'downloadCode') {
            return 'downloadCodeAction';
        }

        if (isset($_GET['ctrl']) and $_GET['ctrl'] == 'page' and isset($_GET['action'])) {
            switch ($_GET['action']) {
                case 'get':
                    return 'getAction';
                case 'archive':
                    return 'archiveAction';
            }
        }

        // Default action.
        return 'pageAction';
    }

    public function archiveAction()
    {
        $path = $this->app->createArchiveFile();
        $name = $_SERVER['HTTP_HOST'].'-'.date('Y-m-d');
        if (is_string($path)) {
            header('Content-Type: application/octet-stream');
            header("Content-Transfer-Encoding: Binary");
            header("Content-disposition: attachment; filename=\"{$name}.zip\"");
            readfile($path);
            exit;
        }
    }

    public function getAction()
    {
        if (!isset($_GET['page'])) {
            $this->app->responseFileNotFound();
        }

        $page = $this->createFileInstance($_GET['page']);
        return $this->jsonResponse(['content' => $page->toHTML()]);
    }

    /**
     * @param $file
     * @return \Maki\File\Markdown
     */
    protected function createFileInstance($file)
    {
        $app = $this->app;
        $ext = pathinfo($file, PATHINFO_EXTENSION);

        if ( ! isset($app['docs.extensions'][$ext])) {
            throw new \InvalidArgumentException(sprintf('File class for "%s" not exists.', $file));
        }

        $class = '\\Maki\\File\\'.ucfirst($app['docs.extensions'][$ext]);
        return new $class($app, $file);
    }

    public function findSidebarFile($directory)
    {
        $app = $this->app;
        $exts = $app['docs.extensions'];
        $path = $app['docroot'].$app['docs.path'].($directory == '' ? '' : rtrim($directory, '/').'/');
        $sidebarName = $app['docs.navigation_filename'];

        foreach ($exts as $ext => $null) {
            if (is_file($path.$sidebarName.'.'.$ext)) {
                return $sidebarName.'.'.$ext;
            }
        }

        return $sidebarName.'.'.key($exts);
    }

    public function findIndexFile($directory)
    {
        $app = $this->app;

        $exts = $app['docs.extensions'];
        $path = $app['docs.path'].rtrim($directory, '/').'/';
        $indexName = $app['docs.index_filename'];

        foreach ($app['docs.extensions'] as $ext) {
            if (is_file($path.$indexName.'.'.$ext)) {
                return $indexName.'.'.$ext;
            }
        }

        return $indexName.'.'.key($exts);
    }

    protected function createPageFileInstanceFromRequest()
    {
        $url = $this->app->getCurrentUrl();
        $info = pathinfo($url);

        // No file specified, so default index is taken
        if ( ! isset($info['extension'])) {
            $url .= $this->findIndexFile($url);
        }

        return $this->createFileInstance($url);
    }

    public function pageAction()
    {
        $app = $this->app;
        $info = pathinfo($app->getCurrentUrl());
        $dirName = isset($info['dirname']) ? $info['dirname'] : '';

        $nav = $this->createFileInstance($this->findSidebarFile($dirName));
        $page = $this->createPageFileInstanceFromRequest();

        $activeStylesheet = $app->getThemeManager()->getActiveStylesheet();

        $this->viewResponse('resources/views/page.php', [
            'page' => $page,
            'nav' => $nav,
            'editable' => $app['editable'],
            'viewable' => false,
            'editing' => ($app['editable'] and isset($_GET['edit'])),
            'activeStylesheet' => $activeStylesheet,
            'stylesheet' => $app->getThemeManager()->getStylesheetPath($activeStylesheet),
            'editButton' => 'edit'
        ]);
    }

    /**
     * Saves page content.
     */
    public function saveContentAction()
    {
        $page = $this->createPageFileInstanceFromRequest();
        $page->setContent(isset($_POST['content']) ? $_POST['content'] : '')->save();
        $this->jsonResponse(['success' => true]);
    }

    /**
     * Deletes page.
     */
    public function deleteAction()
    {
        $page = $this->createPageFileInstanceFromRequest();
        $page->delete();
        $this->app->redirect($this->app->getUrl());
    }

    public function downloadCodeAction()
    {
        $page = $this->createPageFileInstanceFromRequest();
        $index = (int) $_GET['index'];
        $lines = explode("\n", $page->getContent());
        $fileName = pathinfo($page->getName(), PATHINFO_FILENAME);

        $counter = 0;
        $opened = false;
        $codeType = '';
        $code = [];

        foreach ($lines as $line) {
            $spacelessLine = preg_replace('/[\t\s]+/', '', $line);

            if (strpos($spacelessLine, '```') === 0) {
                if ($opened) {
                    $opened = false;

                    // This is what we are looking for
                    if ($index == $counter) {
                        $this->app->response(implode("\n", $code), 'application/octet-stream', 200, [
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