<?php
/**
* @name      Boson PHP framework
* @author    Tishchenko Alexander (info@alex-tisch.ru)
* @copyright Copyright (c) 2018 All rights reserved
*/
                      
define('APP_DIR'     , ROOT . DIR_SEP . 'app');
define('TEMP_DIR'    , ROOT. DIR_SEP . 'temp');
define('ACTIONS_DIR' , APP_DIR . DIR_SEP . 'controllers');
define('SETTINGS_DIR', APP_DIR . DIR_SEP . 'configs');
define('LIBRARY_DIR' , APP_DIR . DIR_SEP . 'library');
define('BOSON_DIR'   , ROOT . DIR_SEP . 'boson');
define('ASSETS_DIR'  , ROOT . DIR_SEP . 'assets');
define('LANG_DIR'    , APP_DIR . DIR_SEP . 'lang');

define('THEMES_DIR'  , ROOT. DIR_SEP . 'themes');
define('THEMES_URL'  , BASE_URL . '/themes');

define('CONTENT_DIR' , ROOT. DIR_SEP . 'content');
define('CONTENT_URL' , BASE_URL . '/content');

define('MODELS_DIR'  , APP_DIR . DIR_SEP . 'models');

define('SMARTY_TEMP_DIR', TEMP_DIR. DIR_SEP . 'smarty');

define('CONTENT_TYPE_XML'  , 'Content-type: text/xml; charset=utf-8');
define('CONTENT_TYPE_CSS'  , 'Content-type: text/css; charset=utf-8');
define('CONTENT_TYPE_HTML' , 'Content-type: text/html; charset=utf-8');
define('CONTENT_TYPE_PLAIN', 'Content-type: text/plain; charset=utf-8');
define('CONTENT_TYPE_JS'   , 'Content-type: application/javascript; charset=utf-8');
define('CONTENT_TYPE_JSON' , 'Content-type: application/json; charset=utf-8');
define('CONTENT_TYPE_PDF'  , 'Content-type: application/pdf; charset=utf-8');

define('BOSON_SQL_DATE'      , 'Y-m-d');
define('BOSON_SQL_TIME'      , 'H:i:s');
define('BOSON_SQL_DATETIME'  , 'Y-m-d H:i:s');
define('BOSON_HUMAN_DATE'    , 'd.m.Y');
define('BOSON_HUMAN_DATETIME', 'd.m.Y H:i:s');
define('BOSON_EURO_DATE'     , 'd/m/Y');
define('BOSON_EURO_DATETIME' , 'd/m/Y H:i:s');

define('UNIXTIME_HOUR' , 3600);
define('UNIXTIME_DAY'  , 86400);
define('UNIXTIME_WEEK' , 604800);
define('UNIXTIME_MONTH', 2678400);
define('UNIXTIME_YEAR' , 31536000);

define('UNIXTIME_100_YEARS', UNIXTIME_YEAR * 100);

