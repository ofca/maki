<?php

namespace Maki;

abstract class Controller
{
    /**
     * @var Maki
     */
    protected $app;

    public function __construct(Maki $app)
    {
        $this->app = $app;
    }

    public static function match(Maki $app)
    {

    }

    public function isSecured($action)
    {
        return true;
    }

    public function viewResponse($path, array $data = [], $type = 'text/html', $code = 200, $headers = [])
    {
        return $this->app->response($this->app->render($path, $data), $type, $code, $headers);
    }

    public function jsonResponse(array $array, $type = 'text/html', $code = 200, $headers = [])
    {
        return $this->app->response(json_encode($array), $type, $code, $headers);
    }
}