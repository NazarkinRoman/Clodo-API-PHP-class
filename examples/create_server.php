<?php
require_once ('../clodoapi.class.php');
$api = new ClodoApi('username', 'api key', 'json');

$data = json_decode( $api->create_server('oversun', 'test server', 'VirtualServer', '512', '', '5', '1', '521'), true );

echo ('ID сервера: ' . $data['server']['id'] . '<br />'
. 'Название сервера: ' . $data['server']['name'] . '<br />'
. 'Пароль сервера: ' . $data['server']['adminPass']);
?>