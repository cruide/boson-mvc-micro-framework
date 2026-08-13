<?php namespace Boson;
/**
* @name      Boson PHP framework
* @author    Tishchenko Alexander (info@alex-tisch.ru)
* @copyright Copyright (c) 2018 All rights reserved
* @version   2.1
*
* Theme management and template rendering.
* Supports hot-swapping the engine (Smarty / Native PHTML) and the design theme.
*/
            
final class Theme
{
    use \Boson\Traits\SingletonTrait;

    /** @var array List of CSS files for injection */
    protected $_css_list;

    /** @var array List of JS files for injection: ['head' => [...], 'body' => [...]] */
    protected $_js_list;

    /** @var string Name of the current theme */
    protected $_theme_name;

    /** @var string Module name (unused, reserved for the future) */
    protected $_module_name;

    /** @var string Layout file name */
    protected $_layout;

    /** @var \Boson\Config|null Configuration */
    protected $_config;

    /** @var bool Layout rendering flag */
    protected $render;

    /** @var array HTTP headers to send */
    protected $_headers;

    /** @var string Engine type: 'smarty' or 'native' */
    protected $_cover;

    /** @var string Engine type: 'smarty' or 'native' */
    protected $_engine_type = 'native';

    /** @var mixed Template engine instance (Smarty or Native) */
    protected $engine;

    /** @var bool Flag: global variables are already set */
    protected $_globals_set = false;

// -------------------------------------------------------------------------------------
    public function __construct()
    {
        $this->_config     = cfg('config');
        $this->_theme_name = $this->_config->theme;
        $this->_layout     = $this->_config->layout;
        $this->_cover      = $this->_config->cover;
        $this->_headers    = [];

        if( empty($this->_theme_name) ) {
            $this->_theme_name = 'default';
        }

        if( empty($this->_layout) ) {
            $this->_layout = 'layout';
        }

        $this->setHeader( CONTENT_TYPE_HTML );
        $this->enableLayout();

        $viewsPath = path_correct( THEMES_DIR . DIR_SEP . $this->_theme_name . DIR_SEP . 'views' );

        if( $this->_cover == 'smarty' && class_exists('\Smarty\Smarty') ) {

            $this->_engine_type = 'smarty';

            if( !preg_match('#\.tpl$#', $this->_layout) ) {
                $this->_layout .= '.tpl';
            }

            $this->engine = new \Smarty\Smarty();

            $this->engine->setTemplateDir( $viewsPath );
            $this->engine->setCompileDir( SMARTY_TEMP_DIR );
            $this->engine->setCacheDir( SMARTY_TEMP_DIR . DIR_SEP . 'cache' );

            $this->engine->registerPlugin('function', 'i18n', 'smarty_function_i18n');
            $this->engine->registerPlugin('function', 'num2word', 'smarty_function_num2word');

        } else {

            $this->_engine_type = 'native';
            $this->engine = new Native( $viewsPath );
        }
    }
// -------------------------------------------------------------------------------------
    /**
     * Returns the template engine instance (for backward compatibility).
     */
    public function layout()
    {
        return $this->engine;
    }
// -------------------------------------------------------------------------------------
    /**
     * Returns the template engine instance (for backward compatibility).
     */
    public function view()
    {
        return $this->engine;
    }
// -------------------------------------------------------------------------------------
    /**
     * Returns the current engine type: 'smarty' or 'native'.
     */
    public function engineType(): string
    {
        return $this->_engine_type;
    }
// -------------------------------------------------------------------------------------
    /**
     * Registers a plugin/function in the template engine.
     * For Smarty: type 'function', callback name.
     * For Native: stored for calls from templates via function_exists.
     */
    public function addPlugin(string $type, string $name, callable $callback): self
    {
        if( $this->_engine_type === 'smarty' ) {
            $this->engine->registerPlugin($type, $name, $callback);
        }

        return $this;
    }
// -------------------------------------------------------------------------------------
    /**
     * Adds an HTTP header.
     *
     * @param string|array $header
     * @return Theme
     */
    public function setHeader($header)
    {
        if( array_count($header) > 0 ) {
            foreach($header as $val) {
                if( !empty($val) && is_string($val) && !in_array($val, $this->_headers) ) {
                    if( preg_match('/^Content\-type/i', $val) ) {
                        $this->_contentTypeClear();
                    }

                    $this->_headers[] = $val;
                }
            }

            return $this;
        }

        if( !empty($header) && is_string($header) && !in_array($header, $this->_headers) ) {
            if( preg_match('/^Content\-type/i', $header) ) {
                $this->_contentTypeClear();
            }

            $this->_headers[] = $header;
        }

        return $this;
    }
// -------------------------------------------------------------------------------------
    protected function _contentTypeClear()
    {
        $tmp = [];

        foreach($this->_headers as $val) {
            if( !preg_match('/^Content\-type/i', $val) ) {
                $tmp[] = $val;
            }
        }

        $this->_headers = $tmp;
    }
// -------------------------------------------------------------------------------------
    public function setLayoutName($name)
    {
        $ext  = $this->_engine_type === 'smarty' ? '.tpl' : '.phtml';
        $file = $name;

        if( !preg_match('#\.(tpl|phtml)$#', $file) ) {
            $file .= $ext;
        }

        if( !empty($name) && is_file($this->getThemeViewsPath() . DIR_SEP . $file) ) {
            $this->_layout = $file;
        }

        return $this;
    }
// -------------------------------------------------------------------------------------
    public function useExternalCss($url)
    {
        if( !is_array($this->_css_list) ) {
            $this->_css_list = [];
        }

        if( is_url_exists($url) ) {
            $this->_css_list[] = $url;
        }

        return $this;
    }
// -------------------------------------------------------------------------------------
    public function useThemeCss($css_filename)
    {
        $css_file_path = path_correct( $this->getThemePath() . DIR_SEP . 'css' . DIR_SEP . $css_filename );

        if( is_file($css_file_path) ) {
            $this->_css_list[] = $css_filename;
        }

        return $this;
    }
// -------------------------------------------------------------------------------------
    /**
     * Add an external JS script.
     *
     * @param string $url
     * @param bool $head — in <head> (true) or before </body> (false)
     * @return Theme
     */
    public function useExternalJs($url, $head = true)
    {
        if( !is_array($this->_js_list) ) {
            $this->_js_list = [];
        }

        $position = $head ? 'head' : 'body';

        if( !isset($this->_js_list[ $position ]) || !is_array($this->_js_list[ $position ]) ) {
            $this->_js_list[ $position ] = [];
        }

        if( is_url_exists($url) ) {
            $this->_js_list[ $position ][] = $url;
        }

        return $this;
    }
// -------------------------------------------------------------------------------------
    /**
     * Add a JS script from the theme.
     *
     * @param string $js_filename
     * @param bool $head — in <head> (true) or before </body> (false)
     * @return Theme
     */
    public function useThemeJs($js_filename, $head = true)
    {
        $js_file_path = path_correct( $this->getThemePath() . DIR_SEP . 'js' . DIR_SEP . $js_filename );

        if( is_file($js_file_path) ) {
            $position = $head ? 'head' : 'body';

            if( !isset($this->_js_list[ $position ]) || !is_array($this->_js_list[ $position ]) ) {
                $this->_js_list[ $position ] = [];
            }

            $this->_js_list[ $position ][] = $js_filename;
        }

        return $this;
    }
// -------------------------------------------------------------------------------------
    /**
     * Change the theme on the fly. Updates template paths for the current engine.
     */
    public function setTheme($theme_name): self
    {
        if( !empty($theme_name) && is_dir(THEMES_DIR . DIR_SEP . $theme_name) ) {
            $this->_theme_name = $theme_name;

            $viewsPath = path_correct( THEMES_DIR . DIR_SEP . $theme_name . DIR_SEP . 'views' );
            $this->engine->setTemplateDir( $viewsPath );
        }

        return $this;
    }
// -------------------------------------------------------------------------------------
    public function disableLayout(): self
    {
        $this->render = false;
        return $this;
    }
// -------------------------------------------------------------------------------------
    public function enableLayout(): self
    {
        $this->render = true;
        return $this;
    }
// -------------------------------------------------------------------------------------
    public function getThemeUrl(): string
    {
        return THEMES_URL . '/' . $this->_theme_name;
    }
// -------------------------------------------------------------------------------------
    public function getThemeName(): string
    {
        return $this->_theme_name;
    }
// -------------------------------------------------------------------------------------
    public function getThemePath(): string
    {
        return THEMES_DIR . DIR_SEP . $this->_theme_name;
    }
// -------------------------------------------------------------------------------------
    public function getThemeViewsPath(): string
    {
        return THEMES_DIR . DIR_SEP . $this->_theme_name . DIR_SEP . 'views';
    }
// -------------------------------------------------------------------------------------
    /**
     * Assigns a variable for the template (available both in the view and the layout).
     */
    public function assign($name, $value = ''): self
    {
        $this->engine->assign($name, $value);

        return $this;
    }
// -------------------------------------------------------------------------------------
    /**
     * Sets the global template variables (base_url, js_url, css_url, etc.).
     */
    public function setGlobals(): self
    {
        $this->engine->assign('base_url'   , BASE_URL);
        $this->engine->assign('content_url', CONTENT_URL);
        $this->engine->assign('js_url'     , THEMES_URL . "/{$this->_theme_name}/js");
        $this->engine->assign('css_url'    , THEMES_URL . "/{$this->_theme_name}/css");
        $this->engine->assign('images_url' , THEMES_URL . "/{$this->_theme_name}/images");

        $this->_globals_set = true;

        return $this;
    }
// -------------------------------------------------------------------------------------
    /**
     * Main rendering method. Wraps the content in a layout and outputs the result.
     */
    public function display( $content )
    {
        // Global variables (if not yet set)
        if( !$this->_globals_set ) {
            $this->setGlobals();
        }

        // Flash messages (new session()->flash() API + old one for compatibility)
        foreach(['message', 'error'] as $key) {
            $flash = session()->flash($key);
            if( $flash !== null ) {
                $this->engine->assign('redirect_' . $key, $flash);
            } elseif( session()->has('redirect_' . $key) ) {
                $this->engine->assign('redirect_' . $key, session()->get('redirect_' . $key));
                session()->remove('redirect_' . $key);
            }
        }

        // Authorization status (for Smarty templates without direct access to is_auth())
        if( function_exists('is_auth') ) {
            $this->engine->assign('is_auth', is_auth());
        }

        // Sending headers
        if( !headers_sent() && array_count($this->_headers) > 0 ) {
            foreach($this->_headers as $val) {
                header($val);
            }
        }

        // Security headers (from config or default values)
        if( !headers_sent() ) {
            $this->sendSecurityHeaders();
        }

        // Without layout — just output the content
        if( !$this->render ) {
            http_cache_off();
            send_header_app_info();

            echo $content;

            return;
        }

        // CSS/JS as template variables (the engine decides where to insert them)
        $this->engine->assign('boson_css'    , $this->_css_list ?? []);
        $this->engine->assign('boson_js_head', $this->_js_list['head'] ?? []);
        $this->engine->assign('boson_js_body', $this->_js_list['body'] ?? []);

        // Render the layout with the content
        $this->engine->assign('content', $content);
        $out = $this->engine->fetch( $this->_layout );

        memory_clear();

        // Dynamic CSS injection (regex fallback for templates without {$boson_css})
        if( array_count($this->_css_list) > 0 ) {
            $_ = "\n\t\t<!-- BOSON DYNAMIC CSS -->\n";

            foreach($this->_css_list as $val) {
                if( preg_match('/^http/is', $val) ) {
                    $_ .= "\t\t<link href=\"{$val}\" rel=\"stylesheet\" type=\"text/css\" />\n";
                } else {
                    $url = $this->getThemeUrl() . '/css/' . $val;
                    $_ .= "\t\t<link href=\"{$url}\" rel=\"stylesheet\" type=\"text/css\" />\n";
                }
            }

            $out = preg_replace('#\<\/head\>#is', $_ . "</head>\n", $out);
        }

        // Dynamic JS injection (regex fallback for templates without {$boson_js_head}/{$boson_js_body})
        if( array_count($this->_js_list) > 0 ) {
            foreach($this->_js_list as $pos => $items) {
                // head/body positions correspond to the </head> and </body> tags
                if( !in_array($pos, ['head', 'body'], true) ) {
                    continue;
                }

                $_ = str_replace(':position', str_upper($pos), "\n\t\t<!-- :position BOSON DYNAMIC JS -->\n");

                if( array_count($items) > 0 ) {
                    foreach($items as $val) {
                        if( preg_match('/^http/is', $val) ) {
                            $_ .= "\t\t<script type=\"text/javascript\" src=\"{$val}\"></script>\n";
                        } else {
                            $url = $this->getThemeUrl() . '/js/' . $val;
                            $_ .= "\t\t<script type=\"text/javascript\" src=\"{$url}\"></script>\n";
                        }
                    }

                    $out = preg_replace("#\<\/{$pos}\>#is", $_ . "</{$pos}>\n", $out);
                }
            }
        }

        send_header_app_info();

        echo $out;
    }
// -------------------------------------------------------------------------------------
    /**
     * Sends security HTTP headers. Values are taken from config.ini.
     */
    protected function sendSecurityHeaders(): void
    {
        $frameOptions = cfg('config', 'x_frame_options');
        if( $frameOptions !== null && $frameOptions !== '' && $frameOptions !== '0' ) {
            header("X-Frame-Options: {$frameOptions}");
        } else if( $frameOptions === null ) {
            header('X-Frame-Options: DENY');
        }

        $contentTypeOptions = cfg('config', 'x_content_type_options');
        if( $contentTypeOptions !== null && $contentTypeOptions !== '' && $contentTypeOptions !== '0' ) {
            header("X-Content-Type-Options: {$contentTypeOptions}");
        } else if( $contentTypeOptions === null ) {
            header('X-Content-Type-Options: nosniff');
        }

        $referrerPolicy = cfg('config', 'referrer_policy');
        if( $referrerPolicy !== null && $referrerPolicy !== '' && $referrerPolicy !== '0' ) {
            header("Referrer-Policy: {$referrerPolicy}");
        } else if( $referrerPolicy === null ) {
            header('Referrer-Policy: strict-origin-when-cross-origin');
        }
    }
}
