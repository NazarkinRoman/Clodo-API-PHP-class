<?php
require_once('../clodoapi.class.php');
$api = new ClodoApi('username', 'api key', 'json');

$data = json_decode($api->get_server('5'), true);

print_r($data); // выводит массив с данными о выбранном сервере
?>