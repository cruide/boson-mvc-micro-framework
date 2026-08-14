<?php
/**
* @name      Boson PHP micro framework
* @author    Tishchenko Alexander (info@alex-tisch.ru)
* @link      http://alex-tisch.ru
* @copyright Copyright (c) 2018 All rights reserved
*/

require('Constants.php');
require('Functions.php');

require('Traits/SingletonTrait.php');
require('Traits/ClassName.php');
require('Abstracts/Registry.php');

require('Classes/BosonObject.php');
require('Classes/Str.php');
require('Classes/Input.php');
require('Classes/Cookies.php');
require('Classes/Session.php');
require('Classes/Native.php');
require('Classes/App.php');
require('Classes/Validator.php');
require('Classes/I18n.php');
require('Classes/Temp.php');
require('Classes/Theme.php');
require('Classes/MicroRouter.php');
require('Classes/TableCache.php');

require('Interfaces/Resource.php');
require('Helpers.php');

if( is_file(VENDOR_DIR . DIR_SEP . 'autoload.php') ) {
    require(VENDOR_DIR . DIR_SEP . 'autoload.php');
}

if( !is_dir(TEMP_DIR) ) {
    @mkdir(TEMP_DIR, 0777, true);
    @mkdir(SMARTY_TEMP_DIR, 0777, true);
    @mkdir(SMARTY_TEMP_DIR . DIR_SEP . 'mails', 0777, true);
}

if( !is_dir(CONTENT_DIR) ) {
    @mkdir(CONTENT_DIR, 0777, true);
}

require('Classes/Eloquent.php');
require('Abstracts/EloquentModel.php');
require('Casts/EncryptedCast.php');

if( is_dir(LIBRARY_DIR) ) {
    foreach(@glob(LIBRARY_DIR . DIR_SEP . '*.php') as $item) {
        require($item);
    }
    
    unset($item);
}

if( is_dir(MODELS_DIR) ) {
    foreach(@glob(MODELS_DIR . DIR_SEP . '*.php') as $item) {
        require($item);
    }
    
    unset($item);
}

if( is_file(APP_DIR . DIR_SEP . 'Functions.php') ) {
    require(APP_DIR . DIR_SEP . 'Functions.php');
}

if( is_file(APP_DIR . DIR_SEP . 'Routes.php') ) {
    require(APP_DIR . DIR_SEP . 'Routes.php');
}

if( is_file(APP_DIR . DIR_SEP . 'Bootstrap.php') ) {
    require(APP_DIR . DIR_SEP . 'Bootstrap.php');
}

app()->execute();
