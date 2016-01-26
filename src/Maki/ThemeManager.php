<?php

namespace Maki;

class ThemeManager 
{
    protected $app;
    protected $stylesheets = array();
    protected $activeStylesheet;

    public function __construct(Maki $app)
    {
        $this->app = $app;
    }

    public function getStylesheet($name)
    {
        if ( ! $this->validName($name)) {
            throw new \InvalidArgumentException(sprintf('Stylesheet "%s" has not allowed chars or its name is too long or stylesheet with this name does not exists.', $name));
        }

        if ( ! isset($this->stylesheets[$name])) {
            throw new \InvalidArgumentException(sprintf('Stylesheet "%s" not exist.', $name));
        }

        return file_get_contents($this->app['docroot'].$this->stylesheets[$name]);
    }

    public function addStylesheet($name, $file)
    {
        $this->stylesheets[$name] = $file;

        return $this;
    }

    public function addStylesheets(array $array)
    {
        foreach ($array as $name => $file) {
            $this->addStylesheet($name, $file);
        }

        return $this;
    }

    public function getStylesheets()
    {
        return $this->stylesheets;
    }

    public function getActiveStylesheet()
    {
        return $this->activeStylesheet;
    }

    public function getStylesheetPath($name)
    {
        if ( ! $this->isStylesheetExist($name)) {
            throw new \InvalidArgumentException(sprintf('Stylesheet "%s" not exists.', $name));
        }

        return $this->stylesheets[$name];
    }

    public function setActiveStylesheet($name)
    {
        $this->activeStylesheet = $name;
    }

    public function isStylesheetExist($name)
    {
        return isset($this->stylesheets[$name]);
    }

    public function validName($name)
    {
        if (strlen($name) > 20) {
            return false;
        }

        if ( ! preg_match('/^[_a-z]+$/', $name)) {
            return false;
        }

        return true;
    }

    public function serveResource($name)
    {
        if ( ! preg_match('/^[-a-z0-9_\.\/]+$/', $name) or ! ($resource = $this->app->getResource($name))) {
            $this->app->response('File not found.', 'text/html', 404);
        }

        $ext = pathinfo($name, PATHINFO_EXTENSION);

        switch ($ext) {
            case 'js':
                $type = 'text/javascript';
                break;
            case 'css':
                $type = 'text/css';
                break;
            default:
                $type = 'text/html';
                break;
        }

        $this->app->response($resource, $type);
    }
}