<?php namespace Boson;
/**
* @name      Boson PHP framework
* @author    Tishchenko Alexander (info@alex-tisch.ru)
* @copyright Copyright (c) 2017 All rights reserved
* @version   2.1
*
* Работа с временными файлами. Поддерживает сериализацию любых типов,
* опциональное шифрование и gzip-сжатие.
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
      * @param string $name    Имя файла
      * @param string $directory Директория (по умолчанию TEMP_DIR)
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
       * Включить шифрование (RC4) при записи/чтении.
       */
      public function encryption()
      {
          $this->_encrypt = true;
          
          return $this;
      }
      
      /**
       * Сменить директорию.
       */
      public function path( $dir )
      {
          if( is_dir($dir) ) {
              $this->_dir = path_correct($dir, true);
          }
          
          return $this;
      }

      /**
       * Полный путь к файлу.
       */
      public function filePath(): string
      {
          return $this->_dir . $this->_name;
      }

      /**
       * Проверить существование файла.
       */
      public function exists(): bool
      {
          return is_file($this->filePath());
      }

      /**
      * Установить содержимое. Принимает любой сериализуемый тип.
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
      * Записать данные в файл (сериализация + gzip + опционально шифрование).
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
      * Прочитать данные из файла (десериализация).
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
      * Удалить файл.
      *
      * @return bool true если файл существовал и был удалён
      */
      public function delete(): bool
      {
          if( $this->exists() ) {
              return unlink($this->filePath());
          }
          
          return false;
      }
      
      /**
       * Создать и сразу записать.
       */
      public static function create($filename, $content)
      {
          $temp = new self($filename);
          $temp->content($content);
          $temp->write();
          
          return $temp;
      }
      
      /**
       * Прочитать и сразу удалить.
       */
      public static function pull($filename)
      {
          $temp = new self($filename);
          $data = $temp->read();
          
          $temp->delete();
          
          return $data;
      }
  }
