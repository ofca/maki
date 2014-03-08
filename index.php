<?php

namespace {
    // @@@:remove
    $loader = require 'vendor/autoload.php';
    $loader->add('Maki', __DIR__.'/src');

    error_reporting(E_ALL);
    ini_set('display_errors', 'On');
    // @@@:end

    

    

    $app = new \Maki\Maki(array(
        'docroot'   => __DIR__.DIRECTORY_SEPARATOR
    ));

    echo $app->render();
}