<?php namespace Boson;
/**
* @name      Boson PHP framework
* @author    Tishchenko Alexander (info@alex-tisch.ru)
* @copyright Copyright (c) 2018 All rights reserved
*/

class NativeException extends \Exception {}

class Native
{
    public static $correctXHTML = false;
    private static $globals;
    protected $_file, $properties, $template_dir;

    const XHTML_CRRECTION_ON  = true;
    const XHTML_CRRECTION_OFF = false;
    const EXTENSION           = 'phtml';

    public function __construct($tplDirectory)
    {
        $this->setTemplateDir($tplDirectory);
    }

    public function __destruct()
    {
        unset($this->properties);
    }

    public function setTemplateDir($tplDir)
    {
        if( !empty($tplDir) && is_dir($tplDir) ) {
            $this->template_dir = path_correct( $tplDir, true );
        } else {
            throw new NativeException("Incorrect template `{$tplDir}` directory");
        }
    }

    /**
    * Для совместимости с Smarty
    * 
    * @param mixed $name
    * @param mixed $value
    */
    public function assignGlobal($name, $value = '')
    {
        self::setGlobal($name, $value);
    }
    
    public static function setGlobal($name, $value = '')
    {
        if( !is_array(self::$globals) ) {
            self::$globals = [];
        }
    
        if( !is_numeric($name) && is_string($name) && !is_array($name)) {
            self::$globals[ $name ] = $value;
    
        } else if( !is_numeric($name) && is_string($name) && $value === null ) {
            unset( self::$globals[$name] );

        } else if( is_array($name) && $value === '') {
            foreach($name as $key=>$val) {
                if( !is_numeric($key) ) {
                    self::$globals[ $key ] = $val;
                }
            }
        }
    }

    public function assign($name, $value = '')
    {
        if( !is_numeric($name) && is_string($name) && !is_array($name)) {
            $this->properties[ $name ] = $value;

        } else if( !is_numeric($name) && is_string($name) && $value === null ) {
            $this->remove($name);

        } else if( is_array($name) && $value === '') {
            foreach($name as $key=>$val) {
                if( !is_numeric($key) ) {
                    $this->properties[ $key ] = $val;
                }
            }
        }

        return $this;
    }

    public function remove($name)
    {
        if( isset($this->properties[ $name ]) ) {
            unset($this->properties[ $name ]);
        }

        return $this;
    }

    public function fetch( $file_name, $need_ext = true, $xhtml = Native::XHTML_CRRECTION_OFF )
    {
        if( $need_ext && !preg_match('#\.' . Native::EXTENSION . '$#', $file_name) ) {
            $file_name = $file_name . '.' . Native::EXTENSION;
        }

        $file_path = $this->template_dir . $file_name;

        if( !is_file($file_path) ) {
            throw new NativeException("Unknown template file {$file_path}");
        }

        if( $xhtml === Native::XHTML_CRRECTION_ON ) {
            $_ = $this->_xhtml_correction( $this->_exec( $file_path ) );

        } else {
            $_ = $this->_exec( $file_path );
        }

        return $_;
    }

    public function display( $file_name, $xhtml = Native::XHTML_CRRECTION_OFF )
    {
        $_ = $this->fetch( $file_name, true, $xhtml );
        
        send_header_app_info();

        echo $_;
    }

    public function flushProperties()
    {
        $this->_properties = [];
    }

    protected function _exec( $_native_execute_prepared_file )
    {
        if( !is_file($_native_execute_prepared_file) ) {
            throw new NativeException(
                "Prepared file {$_native_execute_prepared_file} not found"
            );
        }

        if( is_array(self::$globals) ) {
            extract( self::$globals );
        }

        if( is_array($this->properties) ) {
            extract( $this->properties );
        }

        ob_start();
        ob_implicit_flush(true);

        include($_native_execute_prepared_file);

        return ob_get_clean();
    }

    protected function _xhtml_correction($text)
    {
        if( !empty($text) ) {
            $ret = preg_replace("#\s*(cellspacing|cellpadding|border|width|height|colspan|rowspan)\s*=\s*(\d+)\s*((\%|px)?)(\s*)#si", " $1=\"$2$3\" ", $text);
            $ret = preg_replace("#\s*(align|valign)\s*=\s*(\w+)\s*#si", " $1=\"$2\"", $ret);
            $ret = preg_replace("#<(img|input|meta|link|base)\s*(.*?)\s*/?>#is", "<$1 $2 />", $ret);
            $ret = preg_replace("#<br\s*/?>#is", "<br />", $ret);
            $ret = preg_replace("#<hr(.*?)\s*/?>#is", "<hr$1 />", $ret);
            $ret = preg_replace("#\s+>#is", ">", $ret);
            $ret = preg_replace("#\s*=\s*#is", "=", $ret);

            return $ret;
        }
        
        return '';
    }
}

