<?php namespace Boson;
/**
* @name      Boson PHP framework
* @author    Tishchenko Alexander (info@alex-tisch.ru)
* @copyright Copyright (c) 2017 All rights reserved
* @version   2.1
*
* Working with temporary files. Supports serialization of any types,
* optional encryption and gzip compression.
*/

  class TempException extends \Exception {}  

  class Temp
  {
      use \Boson\Traits\ClassName;
      
      protected $_name    = '';
      protected $_dir     = '';
      protected $_content = '';
      protected $_encrypt = false;

      /**
      * Constructor
      * 
      * @param string $name    File name
      * @param string $directory Directory (TEMP_DIR by default)
      */
      public function __construct( $name, $directory = null )
      {
          if( empty($name) ) {
              throw new TempException( 
                  $this->className() . '::__construct - Need temp name' 
              );
          }
          
          $this->_dir  = ( !empty($directory) && is_dir($directory) ) 
                             ? path_correct($directory, true) 
                             : path_correct(TEMP_DIR, true);
                             
          $this->_name = $name;
      }

      /**
       * Enable encryption (RC4) on write/read.
       */
      public function encryption()
      {
          $this->_encrypt = true;
          
          return $this;
      }
      
      /**
       * Change the directory.
       */
      public function path( $dir )
      {
          if( is_dir($dir) ) {
              $this->_dir = path_correct($dir, true);
          }
          
          return $this;
      }

      /**
       * Full path to the file.
       */
      public function filePath(): string
      {
          return $this->_dir . $this->_name;
      }

      /**
       * Check whether the file exists.
       */
      public function exists(): bool
      {
          return is_file($this->filePath());
      }

      /**
      * Set the content. Accepts any serializable type.
      * 
      * @param mixed $content
      * @return Temp
      */
      public function content( $content = null )
      {
          $this->_content = $content;
          
          return $this;
      }

      /**
      * Write data to the file (serialization + gzip + optional encryption).
      */
      public function write()
      {
          $data = $this->_encrypt ? encrypt( serialize($this->_content) ) 
                                  : serialize($this->_content);
          
          if( !file_put_gz_content( $this->filePath(), $data ) ) {
              throw new TempException( 
                  $this->className() . '::write - Could not write a temporary file. Check the correctness of the path.' 
              );
          }
          
          return true;
      }

      /**
      * Read data from the file (deserialization).
      */
      public function read()
      {
          if( $this->exists() && is_readable($this->filePath()) ) {
              $this->_content = $this->_encrypt ? unserialize( decrypt( file_get_gz_content($this->filePath()) ) )
                                                : unserialize( file_get_gz_content($this->filePath()) );
          }
          
          return $this->_content;
      }

      /**
      * Delete the file.
      *
      * @return bool true if the file existed and was deleted
      */
      public function delete(): bool
      {
          if( $this->exists() ) {
              return unlink($this->filePath());
          }
          
          return false;
      }
      
      /**
       * Create and write immediately.
       */
      public static function create($filename, $content)
      {
          $temp = new self($filename);
          $temp->content($content);
          $temp->write();
          
          return $temp;
      }
      
      /**
       * Read and delete immediately.
       */
      public static function pull($filename)
      {
          $temp = new self($filename);
          $data = $temp->read();
          
          $temp->delete();
          
          return $data;
      }
  }
