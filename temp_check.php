<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
var_dump(base_path('server.php'));
echo "\n";
$pathExists = file_exists(base_path('server.php'));
var_dump($pathExists);
echo "\n";
