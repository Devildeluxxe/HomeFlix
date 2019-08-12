<?php
//import config file
$configJson = file_get_contents("./config.json");
$config = json_decode($configJson, true);

//import controller
$files = scandir('src/controller/');
foreach ($files as $controller) {
    if($controller != '.' AND $controller != '..'){
        require_once 'src/controller/'. $controller;
    }
}

//import repository
$files = scandir('src/repository/');
foreach ($files as $repository) {
    if($repository != '.' AND $repository != '..'){
        require_once 'src/controller/'. $repository;
    }
}


//import services
$files = scandir('src/services/');
foreach ($files as $service) {
    if($service != '.' AND $service != '..'){
        require_once 'src/controller/'. $service;
    }
}
