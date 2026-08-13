<?php namespace Boson;
/**
 * I18n — internationalization system of the Boson micro-framework.
 *
 * This class is adapted for PHP 8.0 without strict typing
 * (`declare(strict_types=1)` is not used). Types are specified in signatures
 * and properties as a recommendation, but their absence does not lead to errors.
 *
 * Main improvements over the original:
 *  1. Detailed PHPDoc comments for all public and private methods.
 *  2. Caching of already loaded language files in the `$loaded` property,
 *     to avoid repeated `include` calls.
 *  3. More efficient placeholder substitution via `strtr`.
 *  4. Fallback to default-language strings if a key is missing in the current one.
 *  5. Readable code with early returns and minimal nesting.
 *
 * Usage example:
 *
 * ```php
 * // Get the single instance
 * $i18n = I18n::getInstance();
 *
 * // Output a string with substitution
 * echo $i18n->get('greeting', ['name' => 'Иван']);
 * ```
 *
 * @package Boson
 * @author  Tishchenko Alexander <info@alex-tisch.ru>
 * @link    http://alex-tisch.ru
 * @license MIT
 */

use Boson\BosonObject;

/**
 * Final class responsible for loading and returning translations.
 *
 * The class implements the Singleton pattern using the
 * `Boson\Traits\SingletonTrait` trait.
 */
final class I18n
{
    /** @use SingletonTrait provides a single global instance of the class */
    use \Boson\Traits\SingletonTrait;

    /**
     * Storage of all strings of the current (and, if necessary, base) language.
     *
     * @var BosonObject
     */
    private BosonObject $strings;

    /**
     * List of supported languages.
     *
     * Key is the language code (two-letter, lowercase),
     * value is the human-readable name.
     *
     * @var array<string,string>
     */
    private array $languages = [
        'en' => 'English',
        'ru' => 'Русский',
        'ua' => 'Український',
        'be' => 'Беларускі',
        'de' => 'Deutsch',
        'fr' => 'Français',
    ];

    /**
     * Default language. The `en.php` file in the `LANG_DIR` directory will be loaded
     * as the base set of strings.
     *
     * @var string
     */
    private string $default = 'en';

    /**
     * Current language, selected by the user (cookie) or by configuration.
     *
     * @var string
     */
    private string $current = 'en';

    /**
     * Flags of already loaded language files.
     *
     * Key is the language code, value is `true` if the file has already been included.
     *
     * @var array<string,bool>
     */
    private array $loaded = [];

    /**
     * Constructor.
     *
     * Initializes the list of available languages, determines the current language
     * (via cookie → config → default language) and loads the required files.
     */
    public function __construct()
    {
        // Keep only the languages that actually have files.
        $this->filterAvailableLanguages();

        // Try to get the language from the cookie.
        $cookieLang = cookies()->lang ?? null;
        // If a preferred value is specified in the config.
        $configLang = cfg('config', 'lang');

        if ($cookieLang && array_key_exists($cookieLang, $this->languages)) {
            $this->current = $cookieLang;
        } elseif ($configLang && array_key_exists($configLang, $this->languages)) {
            $this->current = $configLang;
        }

        // If the list of supported languages is empty, just create an empty object.
        if (empty($this->languages)) {
            $this->strings = new BosonObject();
            return;
        }

        $this->strings = new BosonObject();

        // Load the base language and, if necessary, the current language.
        $this->loadLanguageFile($this->default);
        if ($this->current !== $this->default) {
            $this->loadLanguageFile($this->current);
        }
    }

    /**
     * Keeps in `$this->languages` only the languages for which
     * a file actually exists in the `LANG_DIR` directory.
     *
     * Files are expected to be named `LANG_DIR/<code>.php` and must return an array
     * of translations.
     *
     * @return void
     */
    private function filterAvailableLanguages(): void
    {
        foreach ($this->languages as $code => $name) {
            $langFile = LANG_DIR . DIR_SEP . $code . '.php';
            if (!is_file($langFile)) {
                unset($this->languages[$code]);
            }
        }
    }

    /**
     * Loads the translation file of the specified language and adds the strings to
     * the `$this->strings` object. The file is loaded only once during the lifetime
     * of the instance.
     *
     * @param string $lang Language code (e.g. `en`, `ru`).
     *
     * @return void
     */
    private function loadLanguageFile(string $lang): void
    {
        // If the file has already been loaded, exit.
        if (!empty($this->loaded[$lang])) {
            return;
        }

        $langFile = LANG_DIR . DIR_SEP . $lang . '.php';
        if (!is_file($langFile)) {
            // The file does not exist — still mark it as loaded to avoid retrying.
            $this->loaded[$lang] = true;
            return;
        }

        $strings = include $langFile;

        if (is_array($strings) && $strings !== []) {
            foreach ($strings as $key => $value) {
                if ($this->isVariableName($key) && is_scalar($value)) {
                    // On conflict (the same key in several files),
                    // the last load will override the previous value.
                    $this->strings->set($key, $value);
                }
            }
        }

        $this->loaded[$lang] = true;
    }

    /**
     * Checks whether a string can be a valid variable name.
     *
     * Rule: first letter or underscore, then any letters, digits,
     * underscores.
     *
     * @param string $name Name to check.
     *
     * @return bool `true` if the name matches the pattern.
     */
    private function isVariableName(string $name): bool
    {
        return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name) === 1;
    }

    /**
     * Returns the default language code.
     *
     * @return string Language code (`en` by default).
     */
    public function getDefaultLang(): string
    {
        return $this->default;
    }

    /**
     * Returns the current active language.
     *
     * @return string Current language (e.g. `ru`).
     */
    public function getCurrentLang(): string
    {
        return $this->current;
    }

    /**
     * Sets the current language.
     *
     * On successful change, stores the selected language in the `lang` cookie
     * and loads its strings (if they have not been loaded yet).
     *
     * @param string $langId Language code (case-insensitive).
     *
     * @return $this Current object for chained calls.
     */
    public function setCurrentLang(string $langId): self
    {
        $langId = strtolower($langId);
        if (array_key_exists($langId, $this->languages)) {
            $this->current = $langId;
            cookies()->lang = $langId;
            $this->loadLanguageFile($langId);
        }

        return $this;
    }

    /**
     * Returns the array of supported languages.
     *
     * @return array<string,string> Keys are codes, values are names.
     */
    public function getLanguages(): array
    {
        return $this->languages;
    }

    /**
     * Checks whether a string with the given key exists in the current set of translations.
     *
     * @param string $key String key.
     *
     * @return bool `true` if the string exists.
     */
    public function has(string $key): bool
    {
        return $key !== '' && isset($this->strings->$key);
    }

    /**
     * Returns the translated string by key.
     *
     * If the string is not found in the current language, the base language
     * (default) is checked. If it is not there either, the marker
     * `::key::` is returned.
     *
     * Placeholders like `:name` are replaced with values from the `$values` array.
     *
     * @param string $key    String key.
     * @param array  $values Associative array of replacements (key => value).
     *
     * @return string Translated string or the missing marker.
     */
    public function get(string $key, array $values = []): string
    {
        // Try to get the string from the current set.
        if ($this->has($key)) {
            $str = $this->strings->get($key);
        } else {
            // If the current language does not contain the string, try the base language.
            if (!isset($this->loaded[$this->default])) {
                $this->loadLanguageFile($this->default);
            }
            $str = $this->has($key) ? $this->strings->get($key) : null;
        }

        // If nothing is found, return the marker.
        if ($str === null) {
            return '::' . $key . '::';
        }

        // Replace placeholders if values were passed.
        if (!empty($values)) {
            $replace = [];
            foreach ($values as $k => $v) {
                if (is_scalar($v)) {
                    $replace[':' . $k] = (string) $v;
                }
            }
            // strtr is faster than repeated str_replace.
            $str = strtr($str, $replace);
        }

        return $str;
    }
}
