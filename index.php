<?php
include 'src/import.php';

$db = Db::getInstance();
var_dump($db->countAllRows('movies'));