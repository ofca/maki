<?php

namespace {

    // @@@:remove

    $loader = require 'vendor/autoload.php';
    $loader->add('Maki', __DIR__.'/src');


    // @@@:end
    error_reporting(E_ALL);
    ini_set('display_errors', 'On');

    $config = [];
    $dir = __DIR__.DIRECTORY_SEPARATOR;

    // Load configuration file
    if (is_file($dir.'maki.json')) {
        $config = (array) json_decode(file_get_contents($dir.'maki.json'), true);
    }

    $config['docroot'] = $dir;

    $app = new \Maki\Maki($config);

    echo $app->render();

}