<?php

// Shared vendor symlinks may contain Composer's optimized App class map for another checkout.
// Redirect project classes to this isolated source tree without modifying shared dependencies.
$root = dirname(__DIR__, 2);
$loader = require $root.'/vendor/autoload.php';
$vendorRoot = dirname(realpath($root.'/vendor'));
$map = [];
foreach ($loader->getClassMap() as $class => $file) {
    $file = realpath($file) ?: $file;
    foreach (['app', 'tests', 'database'] as $directory) {
        if (str_starts_with($file, $vendorRoot.'/'.$directory.'/')) {
            $map[$class] = $root.substr($file, strlen($vendorRoot));
            break;
        }
    }
}
$loader->addClassMap($map);
$loader->setClassMapAuthoritative(false);
$loader->addPsr4('App\\', $root.'/app', true);
$loader->addPsr4('Tests\\', $root.'/tests', true);
