<?php
@ini_set('date.timezone', 'Europe/Moscow');
@ini_set('display_errors', '0');
error_reporting(0);

define('BOSON_START_TIME', microtime(true));
define('DIR_SEP'    , DIRECTORY_SEPARATOR);
define('ROOT'       , __DIR__);
define('VENDOR_DIR' , ROOT . DIR_SEP . 'app' . DIR_SEP . 'vendor');
define('PROTOCOL'   , 'http' . ( (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 's' : '') );
define('SELF_DOMAIN', $_SERVER['HTTP_HOST']);
define('BASE_URL'   , PROTOCOL . '://' . SELF_DOMAIN);

require('boson' . DIR_SEP . 'Bootstrap.php');
