<?php
/**
* @name      Boson PHP micro framework
* @author    Tishchenko Alexander (info@alex-tisch.ru)
* @link      http://alex-tisch.ru
* @copyright Copyright (c) 2018 All rights reserved
*/

  /**
  * Corretion for directory path
  *
  * @param string $dir
  * @return string
  */
  function path_correct( $dir, $end_slash = false )
  {
	  if( empty($dir) ) return $dir;
      
      $sep = DIRECTORY_SEPARATOR;

	  $dir = str_replace(':', '||'   , $dir);
	  $dir = str_replace('\\\\', '::', $dir);
	  $dir = str_replace('\\', '::'  , $dir);
	  $dir = str_replace('//', '::'  , $dir);
	  $dir = str_replace('/', '::'   , $dir);
//	  $dir = str_replace('::', '/'   , $dir);
      $dir = str_replace('::', $sep, $dir);
	  $dir = str_replace('||', ':'   , $dir);

	  if( $end_slash && !preg_match("#(.*){$sep}$#is" , $dir, $tmp) ) {
		  $dir = $dir . $sep;
	  }

	  return $dir;
  }
// -----------------------------------------------------------------------------
  /**
  * Check action
  *
  * @param string $action
  * @return bool
  */
  function controller_exists( $action )
  {
	  if( is_file( ACTIONS_DIR . DIR_SEP . "{$action}.php" ) ) {
		  return true;
	  }

	  return false;
  }
// -----------------------------------------------------------------------------
  /**
  * Return IP adddress of client
  *
  */
  function get_ip_address()
  {

	  if( !empty($_SERVER['REMOTE_ADDR']) && !empty($_SERVER['HTTP_CLIENT_IP']) ) {
		  $ipaddr = $_SERVER['HTTP_CLIENT_IP'];
		  
	  } else if( !empty($_SERVER['HTTP_CLIENT_IP']) ) {
		  $ipaddr = $_SERVER['HTTP_CLIENT_IP'];
		  
	  } else if( !empty($_SERVER['HTTP_X_FORWARDED_FOR']) ) {
		  $ipaddr = $_SERVER['HTTP_X_FORWARDED_FOR'];
		  
	  } else {
		  $ipaddr = $_SERVER['REMOTE_ADDR'];
	  }

	  if( $ipaddr === false ) {
		  return '0.0.0.0';
	  }

	  if( strstr($ipaddr, ',') ) {
		  $x      = explode(',', $ipaddr);
		  $ipaddr = end( $x );
	  }

	  if( filter_var($ipaddr, FILTER_VALIDATE_IP) === false ) {
		  $ipaddr = '0.0.0.0';
	  }

	  return $ipaddr;
  }
// -----------------------------------------------------------------------------
  /**
  * Check URL
  *
  * @param string $url
  */
  function is_url_exists($url)
  {
	  if( !filter_var($url, FILTER_VALIDATE_URL) ) {
		  return false;
	  }

	  $hdrs = @get_headers($url);

	  if( isset($hdrs[0]) && preg_match('/HTTP\/1\.[0-1]\s+\d{3}/is', $hdrs[0]) ) {
		  return true;
	  }

	  return false;
  }
// -----------------------------------------------------------------------------
  function escape($str, $escapemethod = 'htmlspecialchars')
  {
	  if( array_count($str) > 0 ) {
		  $_ = [];
          
		  foreach($str as $key=>$val) {
			  $_[ $key ] = escape($val);
		  }
          
		  return $_;
          
	  } else if( is_string($str) ) {
		  if( in_array($escapemethod, ['htmlspecialchars', 'htmlentities']) ) {
			  return call_user_func($escapemethod, $str, ENT_QUOTES, 'UTF-8');
		  }

		  return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
	  }

	  return $str;
  }
// -----------------------------------------------------------------------------
  function unescape($str)
  {
	  if( is_array($str) && count($str) ) {
		  $_ = [];
          
		  foreach($str as $key=>$val) {
			  $_[ $key ] = unescape($val);
		  }
          
		  return $_;
          
	  } else if( is_string($str) ) {
		  return htmlspecialchars_decode($str, ENT_QUOTES);
	  }

	  return $str;
  }
// -----------------------------------------------------------------------------
  /**
  * Check if the number is even
  */
  function is_even($num)
  {
	  if( !is_numeric($num) ) {
          return false;
      }
      
	  if( !preg_match('/[\.]/s', $num) ) {
		  return ( fract($num) & 1 ) ? false : true;
          
	  } else {
		  return ( $num & 1 ) ? false : true;
	  }
  }
// -----------------------------------------------------------------------------
  /**
  * extract the fractional part of a fractional number
  *
  * @param mixed $num
  * @return mixed
  */
  function fract($num)
  {
	  if( is_numeric($num) ) {
		  $num -= floor( (float)$num );
		  return (int)str_replace('0.', '', (string)$num);
	  }

	  return null;
  }
// -----------------------------------------------------------------------------
  function float_extract($num)
  {
	  return ( is_float($num) ) ? explode('.', (string)$num) : false;
  }
// -----------------------------------------------------------------------------
  /**
  * Getting the number of entries in the array.
  * If this is not an array, it returns 0
  *
  * @param array $_
  * @param mixed $mode
  * @return integer
  */
  function array_count($_, $mode = null)
  {
      if (!is_array($_)) {
          return 0;
      }

      return $mode === null ? count($_) : count($_, $mode);
  }
// -----------------------------------------------------------------------------
  /**
  * A faster version of the check for key in the array
  *
  * @param string $key
  * @param array $_array
  * @return bool
  */
  function array_key_isset($key, $_array)
  {
	  if( !is_array($_array) ) return false;

	  return (isset($_array[ $key ]) || array_key_exists($key, $_array));
  }
// -----------------------------------------------------------------------------
  /**
  * Salt generator
  *
  * @return string $lenght
  */
  function salt_generation( $lenght = 18 )
  {
	  return substr( sha1( random_bytes(32) ), 0, $lenght );
  }
// -----------------------------------------------------------------------------
  /**
  * Password encryption (bcrypt)
  *
  * @param string $password
  */
  function password_crypt( $password )
  {
	  if( $password == '' || $password == null || !is_scalar($password) ) { 
		  return false; 
	  }

	  return password_hash( $password, PASSWORD_BCRYPT );
  }

  /**
  * Verify a password encrypted with the legacy method (MD5+crypt).
  * Kept for backward compatibility. On successful verification the password should be rehashed.
  *
  * @param string $password
  * @param string $storedHash
  */
  function password_verify_legacy( $password, $storedHash )
  {
	  return hash_equals( $storedHash, crypt( md5($password), $storedHash ) );
  }
// -----------------------------------------------------------------------------
  /**
  * Password generator
  *
  * @param int $number
  */
  function password_generate( $number = 8 )
  {
	  $arr = [
		  'a','b','c','d','e','f','g','h','i','j','k','l',
		  'm','n','o','p','r','s','t','u','v','x','y','z',
		  'A','B','C','D','E','F','G','H','I','J','K','L',
		  'M','N','O','P','R','S','T','U','V','X','Y','Z',
		  '1','2','3','4','5','6','7','8','9','0',
	  ];

	  $pass = '';

	  for($i = 0; $i < $number; $i++) {
		$pass .= $arr[ random_int(0, count($arr) - 1) ];
	  }

	  return $pass;
  }
// -----------------------------------------------------------------------------
  /**
  * Reversible encryption method (AES-256-GCM)
  *
  * @param mixed $string
  * @param mixed $key
  */
  function encrypt($string, $key = null)
  {
	  $key = (null === $key) ? md5('BOSON') : (string)$key;
	  $iv  = random_bytes(16);
	  $ciphertext = openssl_encrypt((string)$string, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

	  return "\x02" . $iv . $tag . $ciphertext;
  }
// -----------------------------------------------------------------------------
  /**
  * Decryption after the encrypt method
  * Supports AES-256-GCM (v2) and RC4 (legacy) for backward compatibility.
  *
  * @param mixed $cipher
  * @param mixed $key
  */
  function decrypt($cipher, $key = null)
  {
	  $key = (null === $key) ? md5('BOSON') : (string)$key;

	  if( strlen($cipher) > 1 && $cipher[0] === "\x02" ) {
		  // AES-256-GCM
		  $data = substr($cipher, 1);
		  $iv   = substr($data, 0, 16);
		  $tag  = substr($data, 16, 16);
		  $enc  = substr($data, 32);

		  $result = openssl_decrypt($enc, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

		  if( $result !== false ) {
			  return $result;
		  }
	  }

	  // Legacy RC4 (for backward compatibility)
	  return legacy_rc4_crypt($cipher, $key);
  }

  /**
  * Legacy RC4 encryption/decryption (for backward compatibility)
  *
  * @param mixed $cipher
  * @param mixed $key
  */
  function legacy_rc4_crypt($cipher, $key)
  {
	  $s   = [];
	  $key = (null === $key) ? md5('BOSON') : (string)$key;

	  for( $i = 0; $i < 256; $i++ ) {
		  $s[$i] = $i;
	  }

	  $j = 0;
	  $x = null;

	  for( $i = 0; $i < 256; $i++ ) {
		  $j       = ( $j + $s[$i] + ord($key[$i % strlen($key)]) ) % 256;
		  $x       = $s[ $i ];
		  $s[ $i ] = $s[ $j ];
		  $s[ $j ] = $x;
	  }

	  $result = '';
	  $i = $j = $y = 0;

	  for($y = 0; $y < strlen($cipher); $y++ ) {
		  $i       = ( $i + 1 ) % 256;
		  $j       = ( $j + $s[$i] ) % 256;
		  $x       = $s[ $i ];
		  $s[ $i ] = $s[ $j ];
		  $s[ $j ] = $x;

		  $result .= $cipher[$y] ^ chr( $s[ ($s[$i] + $s[$j]) % 256 ] );
	  }

	  return $result;
  }
// -----------------------------------------------------------------------------
  function str_base64_encrypt($str, $key = 'boson_encryption', $gz = false)
  {
	  return $gz ? base64_encode( gzcompress( encrypt($str, $key), 1 ) )
				 : base64_encode( encrypt($str, $key) );
  }
// -----------------------------------------------------------------------------
  function str_base64_decrypt($str, $key = 'boson_encryption', $gz = false)
  {
	  return $gz ? decrypt( gzuncompress( base64_decode($str) ), $key )
				 : decrypt( base64_decode($str), $key );
  }
// -----------------------------------------------------------------------------

  /**
   * Get configuration from .ini files as a BosonObject
   *
   * @param string $name     File name (without the .ini extension)
   * @param string|null $keyname Key inside the configuration
   * @param mixed  $default  Default value (if the key/file is not found)
   * @return \Boson\BosonObject|mixed|null
   */
  function cfg(string $name, ?string $keyname = null, $default = null)
  {
      static $settings;

      if( !isset($settings) ) {
          $settings = new \Boson\BosonObject();
      }

      $makeObject = static function(array $data) use (&$makeObject): \Boson\BosonObject {
          $obj = new \Boson\BosonObject();
          
          foreach($data as $key => $val) {
              $keySanitized = str_replace(['.', '-'], '_', strtolower($key));
              
              if( is_array($val) ) {
                  $obj->set($keySanitized, $makeObject($val));
              } else {
                  $obj->set($keySanitized, $val);
              }
          }
          
          return $obj;
      };

      $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '', $name);

      if( !$settings->has($safeName) ) {
          $filePath = SETTINGS_DIR . DIR_SEP . $safeName . '.ini';
          
          if( is_file($filePath) ) {
              $parsed = @parse_ini_file($filePath, true);
            
              if( $parsed === false ) {
                  return $default;
              }
              
              $settings->set($safeName, $makeObject($parsed));
          } else {
              return $default;
          }
      }

      if( !$settings->has($safeName) ) {
          return $default;
      }

      $config = $settings->get($safeName);

      if( $keyname === null || $keyname === '' ) {
          return $config;
      }

      return $config->has($keyname) ? $config->get($keyname) : $default;
  }
// -----------------------------------------------------------------------------
  /**
  * Check for ajax request
  *
  */
  function is_ajax()
  {

	  if( !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest' ) {
		  return true;
		  
	  } else if( !empty($_SERVER['X_REQUESTED_WITH']) && $_SERVER['X_REQUESTED_WITH'] == 'XMLHttpRequest' ) {
		  return true;
		  
	  } else if( !empty($_SERVER['HTTP_ACCEPT']) && (false !== strpos($_SERVER['HTTP_ACCEPT'], 'text/x-ajax')) ) {
		  return true;
	  }

	  return false;
  }
// -----------------------------------------------------------------------------
  function studly_case( $value )
  {
	  static $cache;

	  if( isset($cache[$value]) ) {
		  return $cache[ $value ];
	  }

	  return $cache[ $value ] = str_replace(' ', '', ucwords( str_replace(['-', '_'], ' ', $value) ));
  }
// ------------------------------------------------------------------------------
  function camel_case( $value )
  {
	  return lcfirst( studly_case($value) );
  }
// ------------------------------------------------------------------------------
  /**
   * Convert a string to snake case.
   *
   * @param  string  $value
   * @param  string  $delimiter
   * @return string
   */
  function snake_case($value, $delimiter = '_', $convert_spaces = false)
  {
	  static $cache;

	  if( !is_array($cache) ) $cache = [];

	  $key = $value . $delimiter;

	  if( isset($cache[ $key ]) ) return $cache[ $key ];

	  if( !ctype_lower($value) ) {
		  $value = !$convert_spaces ? $value = preg_replace('/\s+/', '', $value)
									: str_replace(' ', $delimiter, $value);

		  $value = strtolower(
			  preg_replace('/(.)(?=[A-Z])/', '$1' . $delimiter, $value)
		  );
	  }

	  return $cache[ $key ] = $value;
  }
// -----------------------------------------------------------------------------
  function is_action_name($str)
  {
	  if( empty($str) || !is_scalar($str) || preg_match('/^[0-9_\-]/', (string)$str) || !preg_match('/^[a-z0-9_\-]+$/i', (string)$str) ) {
		  return false;
	  }

	  return true;
  }
// -----------------------------------------------------------------------------
  function is_varible_name($str)
  {
	  if( !isset($str)
	   || !is_scalar($str)
	   || !preg_match('/^[a-z0-9\_]+$/i', (string)$str)
	   || preg_match('/^[0-9]/', (string)$str) )
	  {
		  return false;
	  }

	  return true;
  }
// -----------------------------------------------------------------------------
  function is_email($str)
  {
	  return filter_var($str, FILTER_VALIDATE_EMAIL);
  }
// -----------------------------------------------------------------------------
  function is_alphanum($str)
  {
	  if( !isset($str) || !is_scalar($str) || !preg_match('/^[a-zA-Zа-яА-ЯЁё0-9]+$/u', (string)$str) ) {
		  return false;
	  }

	  return true;
  }
// -----------------------------------------------------------------------------
  function is_date($str)
  {
	  if( empty($str) || !is_scalar($str) || !preg_match('%^(0[1-9]|[12][0-9]|3[01])[- /.](0[1-9]|1[012])[- /.](19|20)[0-9]{2}+$%', (string)$str) ) {
		  return false;
	  }

	  return true;
  }
// -----------------------------------------------------------------------------
  function is_url($str)
  {
	  return filter_var($str, FILTER_VALIDATE_URL);
  }
// -----------------------------------------------------------------------------
  function is_alpha($str)
  {
	  if( !isset($str) || !is_scalar($str) || !preg_match('/^[a-zA-Zа-яА-ЯЁё]+$/u', (string)$str) ) {
		  return false;
	  }

	  return true;
  }
// -----------------------------------------------------------------------------
  function is_ipaddress($str)
  {
	  return filter_var($str, FILTER_VALIDATE_IP);
  }
// -----------------------------------------------------------------------------
  function is_name($str)
  {
	  if( !isset($str) || !is_scalar($str) || !preg_match('/^[a-zA-Zа-яА-ЯЁё0-9\s\']+$/u', (string)$str) ) {
		  return false;
	  }

	  return true;
  }
// -------------------------------------------------------------------------------------
  function get_x_response_type()
  {

	  return $_SERVER['HTTP_X_RESPONSE_TYPE'] ?? '';
  }
// ------------------------------------------------------------------------------
  function javascript( $str )
  {
	  return "<script type=\"text/javascript\">\n{$str}\n</script>";
  }
// -------------------------------------------------------------------------------------
  /**
   * HTTP redirect.
   *
   * Usage examples:
   *   redirect('/users')           → Location: BASE_URL/users
   *   redirect()                   → Location: BASE_URL
   *   redirect(['controller' => 'users', 'message' => 'Saved'])
   *   router()->redirect('users.show', ['id' => 5])  → named route
   *
   * @param string|array|null $data   URL, array or null (to the home page)
   * @param int  $redirect_status     HTTP status (301 by default)
   */
  function redirect($data = null, $redirect_status = 301)
  {
      $url  = BASE_URL;

      if( is_string($data) && $data !== '' ) {
          $url = make_url($data);
          
      } elseif( is_array($data) ) {

          // Flash messages
          foreach(['message', 'error'] as $k) {
              if( !empty($data[$k]) ) {
                  session()->flash($k, $data[$k]);
                  unset($data[$k]);
              }
          }

          // Build the URL: /controller/method/param1/param2...
          if( !empty($data['controller']) ) {
              $url .= '/' . $data['controller'];
              unset($data['controller']);

              if( !empty($data['method']) ) {
                  $url .= '/' . $data['method'];
                  unset($data['method']);
              }
          }

          foreach($data as $val) {
              if( is_scalar($val) ) {
                  $url .= '/' . urlencode($val);
              }
          }

      } elseif( !empty($data) ) {
          abort('Incorrect redirect path');
      }

      // For JSON/AJAX — JavaScript redirect instead of Location
      if( input()->expectsJson() && !headers_sent() ) {
          http_cache_off();

          $type = $_SERVER['HTTP_X_RESPONSE_TYPE'] ?? '';

          if( $type === 'html' ) {
              exit("<script type=\"text/javascript\">\nwindow.location.href = \"{$url}\";\n</script>");
          } elseif( $type === 'script' ) {
              exit('window.location.href = "' . $url . '";');
          }
      }

      header('Location: ' . $url, true, $redirect_status);
      exit();
  }
// -----------------------------------------------------------------------------
  /**
  * Make correct URL
  * 
  * @param string $url
  * @param bool $add_suf
  */
  function make_url($url, $add_suf = true) 
  {
	  if( empty($url) || !is_scalar($url) ) return false;
	  if( preg_match('#^(http|https|ftp)://(.*?)#i', $url) ) return $url;
	  
	  if( !preg_match('/^\//', $url) ) {
		  $url = '/' . $url;
	  }
	  
	  return BASE_URL . $url;
  }
// -----------------------------------------------------------------------------
  /**
  * Generate a unique key
  */
  function uuid()
  {
	  $data = random_bytes(16);

	  $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
	  $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

	  return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
  }
// ------------------------------------------------------------------------------
  /**
  * Checking the unique key
  * 
  * @param string $uuid
  */
  function is_uuid($uuid)
  {
	  return preg_match('/^\{?[0-9a-f]{8}\-?[0-9a-f]{4}\-?[0-9a-f]{4}\-?[0-9a-f]{4}\-?[0-9a-f]{12}\}?$/i', $uuid) === 1;
  }
// -----------------------------------------------------------------------------
  function abort($message, $code = 500)
  {
      if( ($cfg = cfg('config', 'onlyjson')) ) {
          return abort_json([
            'message' => $message,
            'code'    => $code,
          ], $code);
      }
      
	  $views_path = theme()->getThemeViewsPath();
	  $status     = get_http_status($code);

	  if( !headers_sent() ) {
		  header('HTTP/1.1 ' . $status);
		  header('Status: ' . $status);
		  
		  if( is_array($message) ) {
			  header(CONTENT_TYPE_JSON);
			  
			  exit(
				  json_encode($message)
			  );
		  }
	  }
	  
	  if( is_file($views_path . DIR_SEP . 'errors' . DIR_SEP . "{$code}." . \Boson\Native::EXTENSION) ) {
		  theme()->setGlobals()
				 ->display( 
					theme()->view()
						   ->assign('message', $message)
						   ->fetch("errors/{$code}") 
				 );
		
		  exit();
	  }

	  exit( $message );
  }
  
  function abort_json($data, $code = 500)
  {
      if( !headers_sent() ) {
          header('Content-Type: application/json');
      }

      if( is_numeric($code) && $code != 200 ) {
          $_status = get_http_status($code);
        
          if( $_status && !headers_sent() ) {
              header('HTTP/1.1 ' . $_status);
              header('Status: ' . $_status);
          }
      }
      
      if( is_scalar($data) ) {
          $data = [
            'message' => $data,
            'code'    => $code,
          ];
      }
      
      send_header_app_info();
      
      exit(
          json_encode($data, JSON_UNESCAPED_UNICODE)
      );
  }
// -----------------------------------------------------------------------------
  /**
  * Sending header to disable caching pages
  */
  function http_cache_off()
  {
	  if( !headers_sent() ) {
		  header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		  header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		  header('Cache-Control: no-cache, must-revalidate');
		  header('Cache-Control: post-check=0,pre-check=0', false);
		  header('Cache-Control: max-age=0', false);
		  header('Pragma: no-cache');
	  }
  }
// -----------------------------------------------------------------------------
  /**
  * Get ststus by code number
  * 
  * @param integer $code
  */
  function get_http_status($code)
  {
	  if( !is_numeric($code) ) { 
		  return false;
	  }
	
	  $_HTTP_STATUS = [
			100 => '100 Continue',
			101 => '101 Switching Protocols',
			102 => '102 Processing',
			200 => '200 OK',
			201 => '201 Created',
			202 => '202 Accepted',
			203 => '203 Non-SB_Authoritative Information',
			204 => '204 No Content',
			205 => '205 Reset Content',
			206 => '206 Partial Content',
			207 => '207 Multi Status',
			226 => '226 IM Used',
			300 => '300 Multiple Choices',
			301 => '301 Moved Permanently',
			302 => '302 Found',
			303 => '303 See Other',
			304 => '304 Not Modified',
			305 => '305 Use Proxy',
			306 => '306 (Unused)',
			307 => '307 Temporary Redirect',
			400 => '400 Bad Request',
			401 => '401 Unauthorized',
			402 => '402 Payment Required',
			403 => '403 Forbidden',
			404 => '404 Not Found',
			405 => '405 Method Not Allowed',
			406 => '406 Not Acceptable',
			407 => '407 Proxy nCore_Authentication Required',
			408 => '408 Request Timeout',
			409 => '409 Conflict',
			410 => '410 Gone',
			411 => '411 Length Required',
			412 => '412 Precondition Failed',
			413 => '413 Request Entity Too Large',
			414 => '414 Request-URI Too Long',
			415 => '415 Unsupported Media Type',
			416 => '416 Requested Range Not Satisfiable',
			417 => '417 Expectation Failed',
			420 => '420 Policy Not Fulfilled',
			421 => '421 Bad Mapping',
			422 => '422 Unprocessable Entity',
			423 => '423 Locked',
			424 => '424 Failed Dependency',
			426 => '426 Upgrade Required',
			449 => '449 Retry With',
			500 => '500 Internal Server Error',
			501 => '501 Not Implemented',
			502 => '502 Bad Gateway',
			503 => '503 Service Unavailable',
			504 => '504 Gateway Timeout',
			505 => '505 HTTP Version Not Supported',
			506 => '506 Variant Also Varies',
			507 => '507 Insufficient Storage',
			509 => '509 Bandwidth Limit Exceeded',
			510 => '510 Not Extended',
	  ];
	
	  if( !empty($_HTTP_STATUS[$code]) ) {
		  return $_HTTP_STATUS[$code];
	  }
	
	  return false;
  }
// -----------------------------------------------------------------------------
  /**
  * Trim for array recursive
  * 
  * @param array $items
  * 
  * @return array
  */
  function trim_array( $items )
  {
	  if( !is_array($items) ) {
		  return $items;
	  }
		  
	  foreach($items as $key=>$item) {
		  if( is_array($item)  ) {
			  $items[ $key ] = trim_array($item);
			
		  } else if( is_scalar($item) ) {
			  $items[ $key ] = trim($item);
				 
		  } else {
			  $items[ $key ] = $item;
		  }
	  }
		  
	  return $items;
  }
// -----------------------------------------------------------------------------
  function get_image_extension($filepath)
  {
	  if( is_file($filepath) && function_exists('getimagesize') ) {
		  $img = getimagesize($filepath);

		  switch($img['mime']) {
			  case 'image/pjpeg':         return 'jpg';
			  case 'image/jpeg':          return 'jpg';
			  case 'image/gif':           return 'gif';
			  case 'image/x-png':         return 'png';
			  case 'image/png':           return 'png';
			  case 'image/bmp':           return 'bmp';
			  case 'image/ms-bmp':        return 'bmp';
			  case 'image/x-bmp':         return 'bmp';
			  case 'image/x-bitmap':      return 'bmp';
			  case 'image/x-ms-bmp':      return 'bmp';
			  case 'image/x-windows-bmp': return 'bmp';
			  case 'image/x-win-bitmap':  return 'bmp';
			  case 'image/vnd.wap.wbmp':  return 'wbmp';
			  case 'image/tiff':          return 'tiff';
			  case 'image/webp':          return 'webp';
			  case 'image/x-icon':        return 'ico';
			  case 'image/x-ico':         return 'ico';
			  case 'image/svg+xml':       return 'svg';
			  case 'image/vnd.microsoft.icon': return 'ico';
		  }
	  }

	  return pathinfo($filepath, PATHINFO_EXTENSION);
  }
// -----------------------------------------------------------------------------
  function rating_stars( $rating )
  {
	  $_      = '';
	  $rating = round( floatval($rating) );

	  if( empty($rating) ) return '-';

	  while( $rating > 0 ) {
		  $_ .= '&#9733;';
		  $rating--;
	  }

	  return $_;
  }
// -----------------------------------------------------------------------------
  function get_file_extension($filename)
  {
	  return substr( strrchr($filename, '.'), 1 );
  }
// -----------------------------------------------------------------------------
  function is_image($filepath)
  {
	  if( is_file($filepath) && function_exists('getimagesize') ) {
		  $img = getimagesize($filepath);

		  if( !empty($img['mime']) ) {
			  return true;
		  }
	  }

	  return false;
  }
// -----------------------------------------------------------------------------
  /**
  * Write packed content to file with gz compression
  *
  * @param string $filename
  * @param string $content
  * @param integer $compression_level
  */
  function file_put_gz_content($filename, $content, $compression_level = 3)
  {
	  if( !empty($filename) && is_scalar($filename) && is_scalar($content) ) {
		  return file_put_contents(
			  $filename,
			  gzcompress(
				  (string)$content,
				  (int)$compression_level
			  )
		  );
	  }

	  return false;
  }
// -----------------------------------------------------------------------------
  /**
  * Read and unpack gz file
  *
  * @param string $filename
  */
  function file_get_gz_content($filename)
  {
	  if( !empty($filename) && is_file($filename) ) {
		  $_ = file_get_contents($filename);
		  return ( $_ != '' ) ? gzuncompress( $_ ) : '';
	  }

	  return false;
  }
// -----------------------------------------------------------------------------
  /**
  * Forced cleaning memory
  */
  function memory_clear()
  {
	  if( function_exists('gc_collect_cycles') ) {
		  gc_enable();
		  $_ = gc_collect_cycles();
		  gc_disable();

		  return (int)$_;
	  }

	  return 0;
  }
// -----------------------------------------------------------------------------
  /**
  * Get max memoru usage
  *
  * @param bool $max
  * @param integer $round_to
  * @return mixed
  */
  function get_mem_use( $max = true, $round_to = 2 )
  {
	  if( function_exists('memory_get_peak_usage') && $max ) {
		  return round( (memory_get_peak_usage(true)) / 1048576, $round_to );
			
	  } else if( function_exists('memory_get_usage') && !$max ) {
		  return round( memory_get_usage()/1048576, $round_to );
	  }

	  return 0;
  }
// -----------------------------------------------------------------------------
  /**
  * Create random file name
  *
  * @param string $extension
  */
  function generate_file_name($extension)
  {
	  return time() . substr( md5(microtime()), 0, rand(5, 12) ) . $extension;
  }
// -----------------------------------------------------------------------------
  function get_filename($path_string)
  {
	  $_ = explode(DIRECTORY_SEPARATOR, path_correct($path_string));
		
	  return end($_);
  }
// -----------------------------------------------------------------------------
  function boson_date_format($string, $format = null, $default_date = '', $formatter = 'auto')
  {
	  if( $format === null ) {
		  $format = '%b %e, %Y';
	  }

	  if( $string != '' && $string != '0000-00-00' && $string != '0000-00-00 00:00:00' ) {
		  $timestamp = boson_make_timestamp($string);
	  } else if( $default_date != '' ) {
		  $timestamp = boson_make_timestamp($default_date);
	  } else {
		  return;
	  }

	  if( $formatter == 'strftime' || ($formatter == 'auto' && strpos($format,'%') !== false) ) {
		  if( DIRECTORY_SEPARATOR == '\\' ) {
			  $_win_from = array('%D', '%h', '%n', '%r', '%R', '%t', '%T');
			  $_win_to   = array('%m/%d/%y', '%b', "\n", '%I:%M:%S %p', '%H:%M', "\t", '%H:%M:%S');

			  if( strpos($format, '%e') !== false ) {
				  $_win_from[] = '%e';
				  $_win_to[]   = sprintf('%\' 2d', date('j', $timestamp));
			  }

			  if( strpos($format, '%l') !== false ) {
				  $_win_from[] = '%l';
				  $_win_to[]   = sprintf('%\' 2d', date('h', $timestamp));
			  }

			  $format = str_replace($_win_from, $_win_to, $format);
		  }

		  return strftime($format, $timestamp);
			
	  } else {
		  return date($format, $timestamp);
	  }
  }
// ------------------------------------------------------------------------------
  function boson_make_timestamp($string)
  {
	  if( empty($string) ) {
		  return time();
		  
	  } else if( $string instanceof \DateTime ) {
		  return $string->getTimestamp();
		  
	  } else if( strlen($string) == 14 && ctype_digit($string) ) {
		  return mktime( substr($string, 8, 2), substr($string, 10, 2), substr($string, 12, 2),
				 substr($string, 4, 2), substr($string, 6, 2), substr($string, 0, 4));
				 
	  } else if( is_numeric($string) ) {
		  return (int) $string;
		  
	  }

	  $time = strtotime($string);

	  if( $time == -1 || $time === false ) {
		  return time();
	  }

	  return $time;
  }

// ------------------------------------------------------------------------------
  /**
  * Backup file by GZ
  *
  * @param string $file_in
  * @param string $file_out
  */
  function gz_file_pack($file_in, $file_out = null)
  {
	  if( !is_file($file_in) ) {
		  return false;
	  }

	  $content = file_get_contents($file_in);

	  if( !isset($file_out) ) {
		  $file_out = $file_in . '.gz';
	  }

	  if( $gz = gzopen($file_out, 'w9') ) {
		  gzwrite($gz, $content);
		  gzclose($gz);
		  
		  return true;
	  }
	  
	  return false;
  }
// -----------------------------------------------------------------------------
  function obj_to_array( $obj )
  {
	  if( !is_object($obj) ) {
		  return false;
	  }

	  $publics = function($obj) {
		  return get_object_vars($obj);
	  };

	  $values = $publics($obj);

	  foreach($values as $key=>$val) {
		  if( is_object($val) ) $values[ $key ] = obj_to_array($val);
	  }

	  return $values;
  }
// -----------------------------------------------------------------------------
  function get_object_public_vars($obj)
  {
	  if( !is_object($obj) ) {
		  return null;
	  }

	  return get_object_vars($obj);
  }
// -----------------------------------------------------------------------------
  /**
  * Get the first element of an array
  * If $key = true, an array with the key
  * and value of the first element is returned
  *
  * @param array $_array
  * @param bool $key
  */
  function array_get_first( $_array, $key = false )
  {
	  if( array_count($_array) <= 0 ) {
		  return false;
	  }

	  reset($_array);

	  $k       = key($_array);
	  $element = $_array[ $k ];

	  unset($_array);

	  if( $key === true ) {
		  return [$k => $element];
	  }

	  return $element;
  }
// -----------------------------------------------------------------------------
  /**
  * Get the key of the first element of an array
  *
  * @param array $_array
  */
  function array_get_first_key( $_array )
  {
	  if( array_count($_array) <= 0 ) {
		  return false;
	  }

	  reset($_array);
	  $k = key($_array);

	  unset($_array);

	  return $k;
  }

// ------------------------------------------------------------------------------
  /**
  * Get the last element of an array
  * If $key = true, an array with the key
  * and value of the last element is returned
  *
  * @param array $_array
  * @param bool $key
  */
  function array_get_last( $_array, $key = false )
  {
	  if( array_count($_array) <= 0 ) {
		  return false;
	  }

	  end($_array);

	  $k       = key($_array);
	  $element = $_array[ $k ];

	  unset($_array);

	  if( $key === true ) {
		  return [$k => $element];
	  }

	  return $element;
  }

// ------------------------------------------------------------------------------
  /**
  * Get the key of the last element of an array
  *
  * @param array $_array
  */
  function array_get_last_key( $_array )
  {
	  if( array_count($_array) <= 0 ) {
		  return false;
	  }

	  end($_array);
	  $k = key($_array);

	  unset($_array);

	  return $k;
  }
// ------------------------------------------------------------------------------
  function send_header_app_info()
  {
	  if( !headers_sent() ) {
		  header('X-Boson-Time: ' . round( microtime(true) - BOSON_START_TIME, 3) . 's');
		  header('X-Boson-Memory: ' . round( (memory_get_peak_usage(true)) / 1048576, 2) . 'Mb');
	  }
  }
// ------------------------------------------------------------------------------
  function view($phtml = null, array $varibles = [])
  {
      $view  = \Boson\Theme::getInstance()->view();
      $cover = cfg('config', 'cover');
      
      if( !empty($varibles) ) {
          $view->assign($varibles);
      }

      if( !empty($phtml) ) {
          if( $cover == 'smarty' && !preg_match('#\.tpl$#', $phtml) ) {
              $phtml .= '.tpl';
          }
          
          return $view->fetch($phtml);
      }
          
      return $view;
  }
// ------------------------------------------------------------------------------
  function theme( $name = null )
  {
	  if( !empty($name) ) {
		  return \Boson\Theme::getInstance()->setTheme($name);
	  }
	  
	  return \Boson\Theme::getInstance();
  }
// ------------------------------------------------------------------------------
  function json_response($data, $status = 200)
  {
	  \Boson\Theme::getInstance()->setHeader(CONTENT_TYPE_JSON);
	  \Boson\Theme::getInstance()->disableLayout();
	  
	  if( is_numeric($status) && $status != 200 ) {
		  $_status = get_http_status($status);
		
		  if( $_status && !headers_sent() ) {
			  header('HTTP/1.1 ' . $_status);
			  header('Status: ' . $_status);
		  }
	  }
	  
	  return json_encode($data, JSON_UNESCAPED_UNICODE);
  }
// ------------------------------------------------------------------------------
  /**
  * Helper for Carbon
  *
  * @param string $datetime
  * @param string $format
  * @return mixed
  */
  function carbon( $datetime = null, $format = null )
  {
	  if( !class_exists('\Carbon\Carbon') ) {
		  throw new \Exception(
			  '\Carbon\Carbon not installed'
		  );
	  }
	  
	  if( $datetime === null ) {
		  return new \Carbon\Carbon();
	  }
	  
	  return !empty($format)
				 ? \Carbon\Carbon::parse( $datetime )->format($format)
					 : \Carbon\Carbon::parse( $datetime );
  }
  
  if( !function_exists('now') ) {
      function now()
      {
          return (new \Carbon\Carbon())->now();
      }
  }
  
// ------------------------------------------------------------------------------
  /**
  * Alias for \Boson\Str::ucfirst method
  */
  if( !function_exists('str_ucfirst') ) {
	  function str_ucfirst( $str ) {
		  return \Boson\Str::ucfirst( $str );
	  }
  }
// ------------------------------------------------------------------------------
  /**
  * Alias for \Boson\Str::length method
  */
  if( !function_exists('str_length') ) {
	  function str_length( $str ) {
		  return \Boson\Str::length( $str );
	  }
  }
// ------------------------------------------------------------------------------
  /**
  * Alias for \Boson\Str::lower method
  */
  if( !function_exists('str_lower') ) {
	  function str_lower( $str ) {
		  return \Boson\Str::lower( $str );
	  }
  }
// -----------------------------------------------------------------------------
  /**
  * Alias for \Boson\Str::upper method
  */
  if( !function_exists('str_upper') ) {
	  function str_upper( $str ) {
		  return \Boson\Str::upper( $str );
	  }
  }
// -----------------------------------------------------------------------------
  /**
  * $words = ['литр', 'литра', 'литров']
  * $words = ['минута', 'минуты', 'минут']
  * 
  * Forms
  *     ['одна минута', 'две минуты', 'пять минут']
  *     ['один литр', 'два литра', 'пять литров'] 
  * 
  * @param mixed $num
  * @param mixed $words
  */
  function num2word($num, array $words) 
  {
	  $num = (int)$num % 100;
		
	  if( $num > 19 ) {
		  $num = (int)$num % 10;
	  }

	  switch( $num ) {
		  case 1: return "{$words[0]}";
		  
		  case 2: 
		  case 3: 
		  case 4: return "{$words[1]}"; 
		  
		  default: return "{$words[2]}"; 
	  }
  }
// -----------------------------------------------------------------------------
  /**
  * Check if a point is inside the circle radius
  * 
  * @param mixed $pointX
  * @param mixed $pointY
  * 
  * @param mixed $circleX
  * @param mixed $circleY
  * @param mixed $circleR
  */
  function in_radius($pointX, $pointY, $circleX, $circleY, $circleR)
  {
	  return ( pow($pointX - $circleX, 2) + pow($pointY - $circleY, 2) <= pow($circleR, 2) );
  }
// -----------------------------------------------------------------------------
  function make_path_from_date($utime = null)
  {
	  if( empty($utime) ) {
		  $utime = time();
	  }
		
	  return date('Y', $utime) . DIR_SEP . date('m', $utime) . DIR_SEP . date('d', $utime);
  }
// -----------------------------------------------------------------------------
  function email_exists($email, $sender_email = null)
  {
	  
	  if( !is_email($email) ) {
		  return false;
	  }
	  
	  $code    = 0;
	  $data    = [];
	  $ehlo    = $_SERVER['SERVER_NAME'];
	  $from    = (!empty($sender_email) && is_email($sender_email)) ? $sender_email : "info@{$ehlo}";
	  $curdate = date('D, d F Y H:i:s O');

	  list($user, $domain) = explode('@', $email);

	  $records = dns_get_record($domain, DNS_MX);
	  
	  if( !is_array($records) ) {
		  return false;
	  }
	  
	  $records = reset( $records );
	 
	  if( !empty($records['target']) ) {
		  $host = $records['target'];
	  } else {
		  return false;
	  }

	  if( ($fp = @fsockopen($host, 25, $errno, $errstr, 2)) ) {
		  stream_set_timeout($fp, 2);
		  $data['CONNECT'] = fread($fp, 2048);
		  
		  fputs($fp, "EHLO {$ehlo}\r\n");
		  $data["EHLO {$ehlo}"] = fread($fp, 2048);

		  fputs($fp, "MAIL FROM: <{$from}>\r\n");
		  $data["MAIL FROM: <{$from}>"] = fread($fp, 2048);

		  $_ = substr($data["MAIL FROM: <{$from}>"], 0, 3);
		  
		  if( $_ != '250' && $_ != '251' ) {
			  fputs($fp, "QUIT\r\n");
			  fclose($fp);
			  
			  return false;
		  }
		  
		  fputs($fp, "RCPT TO: <{$email}>\r\n");
		  $data["RCPT TO: <{$email}>"] = fread($fp, 2048);

		  $_ = substr($data["RCPT TO: <{$email}>"], 0, 3);
		  
		  if( $_ != '250' && $_ != '251' ) {
			  fputs($fp, "QUIT\r\n");
			  fclose($fp);
			  
			  return false;
		  }
		  
		  fputs($fp, "DATA\r\n");
		  $data['DATA'] = fread($fp, 2048);
		  
		  fputs($fp, "Date: {$curdate}\r\nSubject: E-Mail address test message\r\nFrom: {$from}\r\nTo: {$email}\r\n\r\nThis email is the verification for your email address. Please do not reply to it.\r\nAlso, we apologize if this letter caused you inconvenience.\r\n.\r\n");
		  $data['DATA_END'] = fread($fp, 2048);
		  
		  fputs($fp, "QUIT\r\n");
		  $data['QUIT'] = fgets($fp);
		  
		  fclose($fp);
		  
		  $_ = substr($data['DATA_END'], 0, 3);
		  
		  if( $_ == '250' || $_ == '221' ) {
			  return true;
		  }
	  }
	  
	  return false;
  }
// -----------------------------------------------------------------------------
  function date_week_name($stamp, $full = false)
  {
	  $weeks = $full ? ['воскресенье', 'понедельник', 'вторник', 'среда', 'четверг', 'пятница', 'суббота']
					 : ['вс', 'пн', 'вт', 'ср', 'чт', 'пт', 'сб'];
		
	  if( is_numeric($stamp) ) {
		  return $weeks[ date('w', $stamp) ];
	  }
		
	  if( class_exists('\Carbon\Carbon') && $stamp instanceof \Carbon\Carbon ) {
		  return $weeks[ date('w', $stamp->timestamp) ];
	  }
		
	  return $weeks[ date('w', strtotime($stamp)) ]; 
  }
// -----------------------------------------------------------------------------
  function get_month_by_num($num)
  {
	  $num = (int)$num;
		
	  $months = [
		  1  => 'январь',
		  2  => 'февраль',
		  3  => 'март',
		  4  => 'апрель',
		  5  => 'май', 
		  6  => 'июнь',
		  7  => 'июль',
		  8  => 'август',
		  9  => 'сентябрь',
		  10 => 'октябрь',
		  11 => 'ноябрь',
		  12 => 'декабрь',
	  ];
		
	  if( array_key_isset($num, $months) ) {
		  return $months[ $num ];
	  }
		
	  return null;
  }

  /**
  * Calculate the percentage of $value relative to $quantity
  *
  * @param mixed $quantity
  * @param mixed $value
  */
  function percentage($quantity, $value)
  {
      return round( 100 * ($value / $quantity) );
  }
  
  function cors()
  {

      $origin = $_SERVER['HTTP_ORIGIN'] ?? null;

      if( $origin !== null ) {
          $allowed = [PROTOCOL . '://' . SELF_DOMAIN];

          if( $trusted = cfg('config', 'cors_origins') ) {
              $allowed = array_merge( $allowed, (array)$trusted );
          }

          if( in_array( $origin, $allowed, true ) ) {
              header("Access-Control-Allow-Origin: {$origin}");
              header('Access-Control-Allow-Credentials: true');

          } else {
              header('Access-Control-Allow-Origin: ' . $allowed[0]);
          }

      } else {
          header('Access-Control-Allow-Origin: *');
      }

      header('Access-Control-Max-Age: 86400');

      if( input()->isOptions() ) {
          if( !empty($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']) ) {
              header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
          }

          if( !empty($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']) ) {
              $allowedHeaders = ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept', 'X-CSRF-Token'];
              $requested      = array_map('trim', explode(',', (string)$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']));
              $valid          = array_intersect($requested, $allowedHeaders);

              if( $valid ) {
                  header('Access-Control-Allow-Headers: ' . implode(', ', $valid));
              }
          }

          http_response_code(204);
          exit;
      }
  }
  
  function execution_timeout($seconds = 180)
  {
      @set_time_limit($seconds);
      @ini_set('max_execution_time', $seconds);

      if( empty($seconds) ) {
          @ini_set('max_input_time', -1);

      } else {
          @ini_set('max_input_time', $seconds * 2);
      }
  }
  
  function make_controller(string $controller_name, $before = false)
  {
      $controller_class = "\\App\\Controllers\\{$controller_name}";
      
      if( !class_exists($controller_class) ) {
          throw new \Exception("Unknown controller name `{$controller_name}`");
      }
      
      $controller = new $controller_class();

      if( method_exists($controller, '_before') && $before ) {
          $controller->_before();
      }
      
      return $controller;
  }