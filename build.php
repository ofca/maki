<?php

error_reporting(E_ALL);
ini_set('display_errors', 'On');

date_default_timezone_set('Europe/Warsaw');

$files = array(
    'vendor/michelf/php-markdown/Michelf/MarkdownInterface.php',    
    'vendor/michelf/php-markdown/Michelf/Markdown.php',
    'vendor/pimple/pimple/lib/Pimple.php',
    'src/Maki/Markdown.php',
    'src/Maki/File/Markdown.php',
    'src/Maki/Theme.php',        
    'src/Maki/Maki.php',
    'index.php'
);

$output = array();

foreach ($files as $file) {
     //$content = file_get_contents($file);
     $handle = fopen($file, 'r');
     $content = fread($handle, filesize($file));
     $content = process($content, $file);

     $output[$file] = $content;
}

if ( ! is_dir('dist')) {
    mkdir('dist', 0777);
}

$today = date('l jS \of F Y h:i:s A');
$info = <<<EOF
<?php

/**
 * This is compiled version of Maki script.
 * For proper source code go to http://darkcinnamon.com/maki
 *
 * Compiled at: $today
 * Created by: Tomasz "ofca" Zeludziewicz <tomek@darkcinnamon.com>
 */


EOF;

file_put_contents('dist/index.php', $info.implode("\n\n\n", $output));

function process($content, $file)
{
    $name = "// $file\n\n";

    // Replace php start tags to file name
    $content = preg_replace('/(\<\?php)/', $name, $content, 1);

    // Remove development parts
    $content = preg_replace('/(\/\/ @@@\:remove.*?\/\/ @@@\:end)/s', '', $content);

    // Pimple has no namespace
    // Add him to global scope to get rid of syntax errors
    if ($file == 'vendor/pimple/pimple/lib/Pimple.php') {
        $content = "\n\nnamespace {\n\n".$content."\n\n}\n\n";
    } else {
        // Markdown classes have closing tags (why, tell me why?!)
        if (strpos($file, 'Michelf/MarkdownInterface.php') !== false) {
            $content = preg_replace('/\?>/', '', $content, 1);
        }

        if ($file !== 'index.php') {
            // Because Pimple is in global scope we need to change
            // namespace syntax for every file.
            $content = preg_replace('/(namespace ([^;]+);)/', 'namespace $2 {', $content, 1)."\n\n}\n\n";
        }
    }

    return $content;
}