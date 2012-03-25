<?php
require_once('../clodoapi.class.php');

$api = new ClodoApi('username', 'api key', 'json');
$api->power_action('5', 'reboot');

echo('Сервер перезагружается...');
?>