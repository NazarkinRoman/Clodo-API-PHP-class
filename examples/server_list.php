<?php
require_once('../clodoapi.class.php');

$api = new ClodoApi('username', 'api key', 'json');
$data = json_decode($api->server_list(), true);

print_r($data);
?>