<?php

namespace {
    // @@@:remove
    $loader = require 'vendor/autoload.php';
    $loader->add('Maki', __DIR__.'/src');

    error_reporting(E_ALL);
    ini_set('display_errors', 'On');
    // @@@:end

    if (isset($_GET['resource'])) {
        $resource = $_GET['resource'];

        switch ($resource) {
            case 'css':
                header('HTTP/1.1 200 OK');
                header('Content-Type: text/css');
                echo \Maki\Theme::getCSS();
                break;
            case 'bootstrap':
                header('HTTP/1.1 200 OK');
                header('Content-Type: text/css');
                echo \Maki\Theme::getBootstrap();
                break;            
            case 'jquery':
                header('HTTP/1.1 200 OK');
                header('Content-Type: application/javascript');
                echo \Maki\Theme::getJQuery();
                break;
            case 'prism-js':
                header('HTTP/1.1 200 OK');
                header('Content-Type: application/javascript');
                echo \Maki\Theme::getPrismJS();
                break;
            case 'prism-css':
                header('HTTP/1.1 200 OK');
                header('Content-Type: text/css');
                echo \Maki\Theme::getPrismCSS();
                break;
        }

        exit;
    }

    

    $app = new \Maki\Maki(array(
        'docroot'   => __DIR__.DIRECTORY_SEPARATOR
    ));

    echo $app->render();
}