<?php namespace Boson;
/**
* @name      Boson PHP framework
* @author    Tishchenko Alexander (info@alex-tisch.ru)
* @copyright Copyright (c) 2018 All rights reserved
* @version   2.1
*
* Управление темами и рендерингом шаблонов.
* Поддерживает горячую смену движка (Smarty / Native PHTML) и темы оформления.
*/
            
final class Theme
{
    use \Boson\Traits\SingletonTrait;

    /** @var array Список CSS файлов для инжекции */
    protected $_css_list;

    /** @var array Список JS файлов для инжекции: ['head' => [...], 'body' => [...]] */
    protected $_js_list;

    /** @var string Имя текущей темы */
    protected $_theme_name;

    /** @var string Имя модуля (не используется, задел на будущее) */
    protected $_module_name;

    /** @var string Имя файла макета */
    protected $_layout;

    /** @var \Boson\Config|null Конфигурация */
    protected $_config;

    /** @var bool Флаг рендеринга макета */
    protected $render;

    /** @var array HTTP-заголовки для отправки */
    protected $_headers;

    /** @var string Тип движка: 'smarty' или 'native' */
    protected $_cover;

    /** @var string Тип движка: 'smarty' или 'native' */
    protected $_engine_type = 'native';

    /** @var mixed Экземпляр движка шаблонов (Smarty или Native) */
    protected $engine;

    /** @var bool Флаг: глобальные переменные уже установлены */
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
     * Возвращает экземпляр движка шаблонов (для обратной совместимости).
     */
    public function layout()
    {
        return $this->engine;
    }
// -------------------------------------------------------------------------------------
    /**
     * Возвращает экземпляр движка шаблонов (для обратной совместимости).
     */
    public function view()
    {
        return $this->engine;
    }
// -------------------------------------------------------------------------------------
    /**
     * Возвращает тип текущего движка: 'smarty' или 'native'.
     */
    public function engineType(): string
    {
        return $this->_engine_type;
    }
// -------------------------------------------------------------------------------------
    /**
     * Регистрирует плагин/функцию в движке шаблонов.
     * Для Smarty: тип 'function', имя коллбэка.
     * Для Native: сохраняется для вызова из шаблонов через function_exists.
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
     * Добавляет HTTP-заголовок.
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
     * Добавление внешнего JS скрипта.
     *
     * @param string $url
     * @param bool $head — в <head> (true) или перед </body> (false)
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
     * Добавление JS скрипта из темы.
     *
     * @param string $js_filename
     * @param bool $head — в <head> (true) или перед </body> (false)
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
     * Смена темы на лету. Обновляет пути шаблонов для текущего движка.
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
     * Назначает переменную для шаблона (доступна и в виде, и в макете).
     */
    public function assign($name, $value = ''): self
    {
        $this->engine->assign($name, $value);

        return $this;
    }
// -------------------------------------------------------------------------------------
    /**
     * Устанавливает глобальные переменные шаблона (base_url, js_url, css_url и т.д.).
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
     * Главный метод рендеринга. Оборачивает контент в макет и выводит результат.
     */
    public function display( $content )
    {
        // Глобальные переменные (если ещё не установлены)
        if( !$this->_globals_set ) {
            $this->setGlobals();
        }

        // Flash-сообщения
        if( session()->has('redirect_message') ) {
            $this->engine->assign('redirect_message', session()->redirect_message);
            session()->redirect_message = null;
        }

        if( session()->has('redirect_error') ) {
            $this->engine->assign('redirect_error', session()->redirect_error);
            session()->redirect_error = null;
        }

        // Статус авторизации (для Smarty-шаблонов, где нет прямого доступа к is_auth())
        if( function_exists('is_auth') ) {
            $this->engine->assign('is_auth', is_auth());
        }

        // Отправка заголовков
        if( !headers_sent() && array_count($this->_headers) > 0 ) {
            foreach($this->_headers as $val) {
                header($val);
            }
        }

        // Защитные заголовки (из конфига или значения по умолчанию)
        if( !headers_sent() ) {
            $this->sendSecurityHeaders();
        }

        // Без макета — просто выводим контент
        if( !$this->render ) {
            http_cache_off();
            send_header_app_info();

            echo $content;

            return;
        }

        // CSS/JS как переменные шаблона (движок сам решит, где их вставить)
        $this->engine->assign('boson_css'    , $this->_css_list ?? []);
        $this->engine->assign('boson_js_head', $this->_js_list['head'] ?? []);
        $this->engine->assign('boson_js_body', $this->_js_list['body'] ?? []);

        // Рендерим макет с контентом
        $this->engine->assign('content', $content);
        $out = $this->engine->fetch( $this->_layout );

        memory_clear();

        // Динамическая вставка CSS (regex-фоллбек для шаблонов без {$boson_css})
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

        // Динамическая вставка JS (regex-фоллбек для шаблонов без {$boson_js_head}/{$boson_js_body})
        if( array_count($this->_js_list) > 0 ) {
            foreach($this->_js_list as $pos => $items) {
                // Позиции head/body соответствуют тегам </head> и </body>
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
     * Отправляет защитные HTTP-заголовки. Значения берутся из config.ini.
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
