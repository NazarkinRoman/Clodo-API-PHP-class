<?php

/* ===============================================================
 * ClodoApi PHP class
 * ---------------------------------------------------------------
 * PHP класс для управления серверами на хостинге CLODO через API.
 * ---------------------------------------------------------------
 * Лицензия: BSD, для подробной информации смотрите файл LICENSE
 * ---------------------------------------------------------------
 * Автор: Назаркин Роман <roman@nazarkin.su>
 * ===============================================================
 */

class ClodoApi {
  private $apiurl = 'http://api.clodo.ru';  // Адрес API
  private $token = null;
  private $managment_url = null;
  private $accept = null;

  /* Отвечает за первое подключение к API при декларации класса */

  function __construct($usr, $pass, $data_format) {
    if ( in_array( $data_format, array('xml', 'json')))
      $this->accept = $data_format;
    else
      throw new Exception('Start failed: invalid accept!');
    $data = $this->sendRequest( array( 'X-Auth-User: ' . trim($usr), 'X-Auth-Key: ' . trim($pass)), true );
    $this->token = $this->getHeader($data, 'X-Auth-Token');
    $this->managment_url = $this->getHeader($data, 'X-Server-Management-Url');

  }

  /* Функция login. Является контроллером функции sendRequest
  * оперирует хранением авторизационных данных,
  * может использоваться для переподключения к другому аккаунту. */

  public function login($usr, $pass) {
    $data = $this->sendRequest( array( 'X-Auth-User: ' . trim($usr), 'X-Auth-Key: ' . trim($pass)), true );
    $this->token = $this->getHeader($data, 'X-Auth-Token');
    $this->managment_url = $this->getHeader($data, 'X-Server-Management-Url');
  }

  /* Отправляет запросы к API серверу
  * используется для авторизации и получения данных.
  * Основная функция класса. */

  private function sendRequest($headers, $login = false, $uri = '', $post = '', $custom_url = '') {
    $ch = curl_init();

    $url = ($custom_url) ? $custom_url.$uri : $this->managment_url . $uri;

    curl_setopt( $ch, CURLOPT_URL, ($login) ? $this->apiurl : $url );
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    if ($post)
      curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    if ($login)
      curl_setopt($ch, CURLOPT_HEADER, true);
    else {
      $headers[] = 'Accept: application/' . $this->accept;
      $headers[] = 'X-Auth-Token: ' . $this->token;
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $out = curl_exec($ch);
    $info = curl_getinfo($ch);

    if ( curl_errno($ch))
      throw new Exception( 'HTTP ERROR - ' . curl_errno($ch));
    switch ($info['http_code']) {
      case '401' :
        throw new Exception('Authorization failed! Invalid username or password!');
        break;

      case '404' :
        throw new Exception('CLODO API: Not found!');
        break;

      case '405' :
        throw new Exception('CLODO API: The function is temporarily unavailable!');
        break;

      case '500' :
        throw new Exception('CLODO API: Internal Error!');
        break;

      case '400' :
        throw new Exception('CLODO API: Bad request!');
        break;

      default :
        return $out;
    }
  }

  /* Парсит заголовки ответов, используется при авторизации */

  private function getHeader($data, $headerName) {
    list($headers, $body) = preg_split("#\r\n\r\n#", $data, 2);
    $headers = explode("\r\n", $headers);
    foreach ($headers as $header) {
      if ( preg_match('#^' . $headerName . ':\s+(.*)$#', $header, $matches)) {
        return $matches[1];
      }
    }
  }

  /* Возвращает информацию о сервере */

  public function get_server($id) {
    if ( is_numeric($id))
      return $this->sendRequest( array(), false, '/servers/' . $id );
    else throw new Exception('GET_SERVER ERROR: input parameter are invalid!');
  }

  /* Выводит список серверов */

  public function server_list($detail = false) {
    if ($detail)
      return $this->sendRequest( array(), false, '/servers/detail' );
    else
      return $this->sendRequest( array(), false, '/servers' );
  }

  /* Выводит лимиты по запросам на текущий аккаунт */

  public function get_limits() {
    return $this->sendRequest( array(), false, '/limits' );
  }

  /* Создает новый виртуальный сервер.
  * Входные параметры:
  * $datacenter - датацентр сервера, разрешенные значения - oversun, kh
  * $name - имя сервера
  * $type - тип сервера, значения - VirtualServer, ScaleServer
  * $min_memory - минимальный порог RAM(в мегабайтах) для сервера (для VirtualServer это значение = кол-во оперативной памяти)
  * $max_memory - максимальный порог RAM(для VirtualServer установить в 0)
  * $hdd - размер жесткого диска в GB
  * $support - тип поддержки(1 - обычная, 3 - расширенная)
  * $os - операционная система */

  public function create_server($datacenter, $name, $type, $min_memory, $max_memory, $hdd, $support, $os) {
    if ( !in_array( $datacenter, array('oversun', 'kh')) or !in_array( $type, array('VirtualServer', 'ScaleServer')) or !is_numeric($min_memory) or !is_numeric($max_memory) or !is_numeric($hdd) or !in_array( $support, array(1, 3)) or !is_numeric($os))
      throw new Exception('CREATE_SERVER ERROR: input data are invalid!');

    $post = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><server>
    <vps_title>$name</vps_title>
    <vps_type>$type</vps_type>
    <vps_memory>$min_memory</vps_memory>
    <vps_memory_max>$max_memory</vps_memory_max>
    <vps_hdd>$hdd</vps_hdd>
    <vps_admin>$support</vps_admin>
    <vps_os>$os</vps_os></server>";

    return $this->sendRequest( array('Content-type: application/xml'), false, '/servers', $post, "http://api.{$datacenter}.clodo.ru" );
  }

  /* Выполняет команды с питанием сервера(вкл/выкл, перезагрузка) */

  public function power_action($id, $act) {
    if(!in_array($act, array('start', 'stop', 'reboot'))) throw new Exception('POWER_ACTION ERROR: input data are invalid!');

    $post_data = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
    <{$act}/>";
    $this->sendRequest( array('Content-type: application/xml'), false, "/servers/{$id}/action", $post_data );
  }

  /* Выполняет переустановку VPS
   * Параметры:
   * $imageId - id операционной системы, которую нужно установить
   * $isp - флаг установки ispmanager */

  public function rebuild_server($id, $imageId, $isp) {
    if(!is_numeric($id) OR !is_numeric($imageId) OR !is_numeric($isp)) throw new Exception('REBUILD_SERVER ERROR: input data are invalid!');
    $post_data = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
    <rebuild imageId=\"{$imageId}\" vps_isp=\"{$isp}\"/>";
    return $this->sendRequest(array('Content-type: application/xml'), false, "/servers/{$id}/action", $post_data);
  }

  /* Возвращает текущий баланс */

  public function get_balance() {
      return $this->sendRequest( array(), false, '/billing/balance' );
  }

  /* Выводит список всех операций биллинга за указанный период */

  public function get_billing_info($from, $to, $id = '') {
    $uri = '/billing';
    if ($id AND !is_numeric($id))
      throw new Exception('GET_BILLING_info ERROR: input data are invalid!');
    else $uri = '/billing/'.$id;
    $post_data = json_encode( array( 'billing' => array( 'from' => strtotime($from), 'to' => strtotime($to))));
    return $this->sendRequest(array('Content-type: application/json'), false, '/billing', $post_data);
  }

  /* Возвращает статистику использования системных ресурсов
  * по указанному($id) серверу, параметры $to и $from должны
  * указывать на дату статистики(период с $from по $to) */

  public function get_server_stats($id, $from, $to) {
    if ( !is_numeric($id))
      throw new Exception('GET_SERVER_STATS ERROR: input data are invalid!');
    $post_data = json_encode( array( 'stats' => array( 'from' => strtotime($from), 'to' => strtotime($to))));
    return $this->sendRequest( array('Content-type: application/json'), false, '/stats/' . $id, $post_data );
  }

  /* Возвращает лог работы сервера($id) */

  public function get_server_log($id) {
    if ( !is_numeric($id))
      throw new Exception('GET_SERVER_LOG ERROR: input data are invalid!');
    else
      return $this->sendRequest( array(), false, "/servers/{$id}/log/" );
  }

  /* Список доступных ОС */

  public function get_os_list($detail = false) {
    if($detail) return $this->sendRequest(array(), false, '/images/detail');
    else return $this->sendRequest(array(), false, '/images');
  }


}


?>