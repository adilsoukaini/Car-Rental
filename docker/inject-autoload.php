<?php

// Injects plugin PSR-4 autoload entries into vendor/composer/autoload_psr4.php.
// Docker COPY resolves symlinks, so the Composer path-repository symlinks
// (vendor/carrental/* → plugins/*) become real directories during the build.
// The autoloader must be told about plugin namespaces explicitly.

$map = require 'vendor/composer/autoload_psr4.php';
$pluginDirs = glob('plugins/*/src');
foreach ($pluginDirs as $dir) {
    $pluginName = basename(dirname($dir));
    $parts = explode('-', $pluginName);
    $namespace = 'Plugins\\'.implode('', array_map('ucfirst', $parts)).'\\';
    $map[$namespace] = [$dir.'/'];
}
file_put_contents('vendor/composer/autoload_psr4.php', '<?php return '.var_export($map, true).';');
echo 'Injected '.count($pluginDirs).' plugin autoload entries'.PHP_EOL;
