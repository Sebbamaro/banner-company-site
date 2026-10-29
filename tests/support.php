<?php
require dirname(__DIR__).'/src/config.php';
spl_autoload_register(function(string $class):void{
 if(str_starts_with($class,'Banner\\')) require dirname(__DIR__).'/src/'.substr($class,7).'.php';
});
