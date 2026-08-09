<?php namespace Boson;
/**
 * I18n – система интернационализации микрофреймворка Boson.
 *
 * Данный класс адаптирован под PHP 8.0 без включения строгой типизации
 * (`declare(strict_types=1)` не используется). Типы указаны в сигнатурах
 * и свойствах как рекомендация, но их отсутствие не приводит к ошибкам.
 *
 * Основные улучшения по сравнению с оригиналом:
 *  1. Подробные PHPDoc‑комментарии для всех публичных и приватных методов.
 *  2. Кеширование уже загруженных языковых файлов в свойстве `$loaded`,
 *     чтобы избежать повторных `include`.
 *  3. Более эффективная подстановка плейсхолдеров через `strtr`.
 *  4. Fallback к строкам из языка‑по‑умолчанию, если ключ отсутствует в текущем.
 *  5. Читаемый код с ранними возвратами и минимумом вложенности.
 *
 * Пример использования:
 *
 * ```php
 * // Получаем единственный экземпляр
 * $i18n = I18n::getInstance();
 *
 * // Выводим строку с подстановкой
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
 * Финальный класс, отвечающий за загрузку и выдачу переводов.
 *
 * Класс реализует паттерн «Одиночка» с помощью трейта
 * `Boson\Traits\SingletonTrait`.
 */
final class I18n
{
    /** @use SingletonTrait обеспечивает один глобальный экземпляр класса */
    use \Boson\Traits\SingletonTrait;

    /**
     * Хранилище всех строк текущего (и, при необходимости, базового) языка.
     *
     * @var BosonObject
     */
    private BosonObject $strings;

    /**
     * Список поддерживаемых языков.
     *
     * Ключ – код языка (двухбуквенный, в нижнем регистре),
     * значение – человекочитаемое название.
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
     * Язык по умолчанию. Файл `en.php` в каталоге `LANG_DIR` будет загружен
     * в качестве базового набора строк.
     *
     * @var string
     */
    private string $default = 'en';

    /**
     * Текущий язык, выбранный пользователем (cookie) или конфигурацией.
     *
     * @var string
     */
    private string $current = 'en';

    /**
     * Флаги уже загруженных файлов языков.
     *
     * Ключ – код языка, значение – `true`, если файл уже был включён.
     *
     * @var array<string,bool>
     */
    private array $loaded = [];

    /**
     * Конструктор.
     *
     * Инициализирует список доступных языков, определяет текущий язык
     * (по cookie → конфиг → язык по умолчанию) и загружает необходимые файлы.
     */
    public function __construct()
    {
        // Оставляем только те языки, для которых реально существуют файлы.
        $this->filterAvailableLanguages();

        // Пытаемся взять язык из cookie.
        $cookieLang = cookies()->lang ?? null;
        // Если в конфиге указано предпочтительное значение.
        $configLang = cfg('config', 'lang');

        if ($cookieLang && array_key_exists($cookieLang, $this->languages)) {
            $this->current = $cookieLang;
        } elseif ($configLang && array_key_exists($configLang, $this->languages)) {
            $this->current = $configLang;
        }

        // Если список поддерживаемых языков пуст, просто создаём пустой объект.
        if (empty($this->languages)) {
            $this->strings = new BosonObject();
            return;
        }

        $this->strings = new BosonObject();

        // Загружаем базовый язык и, при необходимости, текущий язык.
        $this->loadLanguageFile($this->default);
        if ($this->current !== $this->default) {
            $this->loadLanguageFile($this->current);
        }
    }

    /**
     * Оставляет в `$this->languages` только те языки, для которых
     * действительно существует файл в каталоге `LANG_DIR`.
     *
     * Файлы ожидаются в виде `LANG_DIR/<code>.php` и должны возвращать массив
     * переводов.
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
     * Загружает файл переводов указанного языка и добавляет строки в объект
     * `$this->strings`. Файл загружается только один раз в течение жизни
     * экземпляра.
     *
     * @param string $lang Код языка (например, `en`, `ru`).
     *
     * @return void
     */
    private function loadLanguageFile(string $lang): void
    {
        // Если файл уже был загружен – выходим.
        if (!empty($this->loaded[$lang])) {
            return;
        }

        $langFile = LANG_DIR . DIR_SEP . $lang . '.php';
        if (!is_file($langFile)) {
            // Файла нет – всё равно помечаем как загруженный, чтобы не пытаться снова.
            $this->loaded[$lang] = true;
            return;
        }

        $strings = include $langFile;

        if (is_array($strings) && $strings !== []) {
            foreach ($strings as $key => $value) {
                if ($this->isVariableName($key) && is_scalar($value)) {
                    // При конфликте (один и тот же ключ в нескольких файлах)
                    // последняя загрузка переопределит предыдущее значение.
                    $this->strings->set($key, $value);
                }
            }
        }

        $this->loaded[$lang] = true;
    }

    /**
     * Проверка, может ли строка быть валидным именем переменной.
     *
     * Правило: первая буква или подчёркивание, далее любые буквы, цифры,
     * подчёркивания.
     *
     * @param string $name Проверяемое имя.
     *
     * @return bool `true`, если имя соответствует паттерну.
     */
    private function isVariableName(string $name): bool
    {
        return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name) === 1;
    }

    /**
     * Возвращает код языка‑по‑умолчанию.
     *
     * @return string Код языка (по умолчанию `en`).
     */
    public function getDefaultLang(): string
    {
        return $this->default;
    }

    /**
     * Возвращает текущий активный язык.
     *
     * @return string Текущий язык (например, `ru`).
     */
    public function getCurrentLang(): string
    {
        return $this->current;
    }

    /**
     * Устанавливает текущий язык.
     *
     * При успешном изменении сохраняет выбранный язык в cookie `lang`
     * и загружает его строки (если они ещё не были загружены).
     *
     * @param string $langId Код языка (регистр не важен).
     *
     * @return $this Текущий объект для цепочечного вызова.
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
     * Возвращает массив поддерживаемых языков.
     *
     * @return array<string,string> Ключи – коды, значения – названия.
     */
    public function getLanguages(): array
    {
        return $this->languages;
    }

    /**
     * Проверяет наличие строки по ключу в текущем наборе переводов.
     *
     * @param string $key Ключ строки.
     *
     * @return bool `true`, если строка существует.
     */
    public function has(string $key): bool
    {
        return $key !== '' && isset($this->strings->$key);
    }

    /**
     * Получает строку перевода по ключу.
     *
     * Если строка не найдена в текущем языке, проверяется наличие в базовом
     * языке (по умолчанию). Если и там её нет — возвращается маркер
     * `::key::`.
     *
     * Плейсхолдеры вида `:name` заменяются значениями из массива `$values`.
     *
     * @param string $key    Ключ строки.
     * @param array  $values Ассоциативный массив замен (key => value).
     *
     * @return string Переведённая строка или маркер отсутствия.
     */
    public function get(string $key, array $values = []): string
    {
        // Попытка получить строку из текущего набора.
        if ($this->has($key)) {
            $str = $this->strings->get($key);
        } else {
            // Если текущий язык не содержит строку – пробуем базовый язык.
            if (!isset($this->loaded[$this->default])) {
                $this->loadLanguageFile($this->default);
            }
            $str = $this->has($key) ? $this->strings->get($key) : null;
        }

        // Если ничего не найдено – возвращаем маркер.
        if ($str === null) {
            return '::' . $key . '::';
        }

        // Замена плейсхолдеров, если переданы значения.
        if (!empty($values)) {
            $replace = [];
            foreach ($values as $k => $v) {
                if (is_scalar($v)) {
                    $replace[':' . $k] = (string) $v;
                }
            }
            // strtr быстрее, чем многократный str_replace.
            $str = strtr($str, $replace);
        }

        return $str;
    }
}
