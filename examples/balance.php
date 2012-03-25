<?php
require_once('../clodoapi.class.php');
$api = new ClodoApi('username', 'token', 'json');

$balance = json_decode($api->get_balance(), true);

echo('Текущий баланс на аккаунте: '.$balance['balance'].' руб.');

?>