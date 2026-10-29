<?php
declare(strict_types=1);
require __DIR__.'/config.php';
if(!is_file(dirname(__DIR__).'/vendor/autoload.php'))throw new RuntimeException('dependencies_missing');
require dirname(__DIR__).'/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
