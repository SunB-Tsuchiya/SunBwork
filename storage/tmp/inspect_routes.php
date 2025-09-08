<?php
$routes = app('router')->getRoutes();
foreach ($routes as $r) {
    if ($r->uri() === 'broadcasting/auth') {
        echo 'Methods: ' . implode(',', $r->methods()) . "\n";
        echo 'Name: ' . ($r->getName() ?: '(none)') . "\n";
        echo 'Action: ' . $r->getActionName() . "\n";
        echo 'Middleware: ' . implode(',', $r->gatherMiddleware()) . "\n";
    }
}
