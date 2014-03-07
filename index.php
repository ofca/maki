<?php

namespace {

    // @@@:remove
    $loader = require 'vendor/autoload.php';
    $loader->add('Maki', __DIR__.'/src');

    error_reporting(E_ALL);
    ini_set('display_errors', 'On');
    // @@@:end


    $app = new \Maki\Maki(array(
        'docroot'   => __DIR__.DIRECTORY_SEPARATOR,
        'docs.path' => 'docs/',
        'editable'  => true
    ));

    /*
    if (file_exists(DOCROOT.'maki-config.json')) {
        $app->setConfig(file_get_contents(DOCROOT.'maki-config.json'));
    }
    */
    echo $app->render();

}