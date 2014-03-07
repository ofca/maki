<?php

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
    protected $locked = false;
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

        $cacheDir = $this->app->getCacheDirAbsPath().'docs/';

        if (is_file($cacheDir.$this->name)) {            
            $time = time() - filemtime($cacheDir.$this->name);

            // Last edited more then 2 minutes ago
            if ($time > 120) {
                unlink($cacheDir.$this->name);
            } else {
                // See who editing
                $id = file_get_contents($cacheDir.$this->name);

                // Someone else is editing this file now
                if ($this->app->getSessionId() != $id) {
                    $this->locked = true;
                }
            }
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
        if ($this->locked) {
            return false;
        }

        $dirName = pathinfo($this->fileAbsPath, PATHINFO_DIRNAME);

        if ( ! is_dir($dirName)) {
            mkdir($dirName, 0777, true);
        }

        file_put_contents($this->fileAbsPath, $this->content);

        $cacheDir = $this->app->getCacheDirAbsPath().'docs/';

        if ( ! is_dir($cacheDir)) {
            mkdir($cacheDir, 0700, true);
        }

        file_put_contents($cacheDir.$this->name, $this->app->getSessionId());

        return $this;
    }

    public function delete()
    {
        if ($this->locked) {
            return false;
        }

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
        return $this->app->getUrl().$this->filePath;
    }

    public function isNotLocked()
    {
        return $this->locked === false;
    }

    public function isLocked()
    {
        return $this->locked;
    }
}