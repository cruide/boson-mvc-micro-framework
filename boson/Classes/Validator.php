<?php namespace Boson;
/**
 * @name      Boson PHP micro framework
 * @author    Tishchenko Alexander
 * @copyright Copyright © 2025
 * @version   2.1
 *
 * Универсальный валидатор входных данных.
 *
 * Поддерживает:
 * - строковые правила, например: `required|trim|email|maxlen:100`
 * - массивы правил
 * - кастомные `Closure`-валидаторы
 * - регистрацию собственных правил через addRule()
 * - множественные сообщения об ошибках для каждого поля
 * - локализацию ошибок через функцию `i18n()`, если она определена
 *
 * Пример использования:
 *
 * ```php
 * $validator = validator($values, [
 *     'email' => 'required|trim|email|maxlen:100',
 *     'age'   => 'required|int|min:18|max:99',
 * ]);
 *
 * if ($validator->fails()) {
 *     return json_response(['errors' => $validator->errors()], 422);
 * }
 *
 * $data = $validator->validated();
 * ```
 *
 * Поддерживаемые строковые правила:
 * - `required`, `nullable`, `trim`
 * - `int`, `integer` (синоним), `float`, `bool`, `boolean` (синоним), `numeric`
 * - `email`, `url`, `json`
 * - `alpha`, `alphanum`
 * - `date`, `date:Y-m-d`
 * - `min:<N>`, `max:<N>`, `minlen:<N>`, `maxlen:<N>`
 * - `in:a,b,c`, `not_in:x,y,z`
 * - `same:field`, `confirmed`
 * - `regexp:/.../`
 * - `validator:name` — вызывает `is_name($value)`
 *
 * Формат ошибок:
 *
 * ```php
 * [
 *     'email' => [
 *         'Некорректный email'
 *     ],
 *     'password' => [
 *         'Минимальная длина — 6 символов'
 *     ]
 * ]
 * ```
 */
class Validator
{
    /**
     * Входные значения для валидации.
     *
     * @var array<string, mixed>
     */
    protected array $values = [];

    /**
     * Нормализованные правила валидации.
     *
     * @var array<string, array<string, mixed>|Closure>
     */
    protected array $rules = [];

    /**
     * Ошибки валидации.
     *
     * @var array<string, array<int, string>>
     */
    protected array $messages = [];

    /**
     * Останавливать ли проверку поля после первой ошибки.
     *
     * @var bool
     */
    protected bool $stopOnFirstError = true;

    /**
     * Пользовательские сообщения об ошибках.
     *
     * @var array<string, string|array>
     */
    protected array $customMessages = [];

    /**
     * Зарегистрированные кастомные правила.
     *
     * @var array<string, Closure>
     */
    protected array $customRules = [];

    /**
     * Карта типов ошибок к ключам локализации.
     *
     * @var array<string, string>
     */
    protected array $errorKeys = [
        'required'  => 'validator_required',
        'nullable'  => 'validator_nullable',
        'minlen'    => 'validator_minlen',
        'maxlen'    => 'validator_maxlen',
        'regexp'    => 'validator_regexp',
        'min'       => 'validator_min',
        'max'       => 'validator_max',
        'numeric'   => 'validator_numeric',
        'email'     => 'validator_email',
        'url'       => 'validator_url',
        'int'       => 'validator_int',
        'float'     => 'validator_float',
        'bool'      => 'validator_bool',
        'in'        => 'validator_in',
        'not_in'    => 'validator_not_in',
        'same'      => 'validator_same',
        'confirmed' => 'validator_confirmed',
        'date'      => 'validator_date',
        'json'      => 'validator_json',
        'alpha'     => 'validator_alpha',
        'alphanum'  => 'validator_alphanum',
        'custom'    => 'validator_invalid',
    ];

    /**
     * Validator constructor.
     *
     * @param array<string, mixed> $values Входные данные для проверки
     * @param array<string, mixed> $rules  Набор правил валидации
     * @param bool $stopOnFirstError       Если `true`, проверка каждого поля останавливается на первой ошибке
     */
    public function __construct(array $values = [], array $rules = [], bool $stopOnFirstError = true)
    {
        $this->values = $values;
        $this->stopOnFirstError = $stopOnFirstError;

        foreach ($rules as $field => $rule) {
            $this->rules[$field] = $this->normalizeRules($rule);
        }
    }

    /**
     * Статический фабричный метод.
     *
     * @param array<string, mixed> $values
     * @param array<string, mixed> $rules
     * @return self
     */
    public static function make(array $values, array $rules): self
    {
        return new static($values, $rules);
    }

    /**
     * Заменяет входные данные для проверки.
     */
    public function setValues(array $values): self
    {
        $this->values = $values;
        return $this;
    }

    /**
     * Заменяет набор правил валидации.
     */
    public function setRules(array $rules): self
    {
        $this->rules = [];

        foreach ($rules as $field => $rule) {
            $this->rules[$field] = $this->normalizeRules($rule);
        }

        return $this;
    }

    /**
     * Устанавливает пользовательские сообщения об ошибках.
     *
     * Формат:
     * ```php
     * $validator->setMessages([
     *     'email.required' => 'Укажите email',
     *     'email.email'    => 'Некорректный email',
     * ]);
     * ```
     */
    public function setMessages(array $messages): self
    {
        $this->customMessages = array_merge($this->customMessages, $messages);
        return $this;
    }

    /**
     * Регистрирует кастомное правило валидации.
     *
     * Сигнатура callback:
     * `function(mixed $value, array $params, array $allValues): bool|string`
     *
     * @param string $name Имя правила
     * @param Closure $callback Функция проверки
     * @return $this
     */
    public function addRule(string $name, Closure $callback): self
    {
        $this->customRules[ $name ] = $callback;
        return $this;
    }

    /**
     * Возвращает `true`, если ошибок нет.
     */
    public function passes(): bool
    {
        return $this->checkAll();
    }

    /**
     * Возвращает `true`, если есть хотя бы одна ошибка.
     */
    public function fails(): bool
    {
        return !$this->checkAll();
    }

    /**
     * Выполняет полную валидацию всех полей.
     */
    public function checkAll(): bool
    {
        $this->messages = [];
        $result = true;

        foreach ($this->rules as $field => $ruleSet) {
            if (!$this->validateField($field, $ruleSet)) {
                $result = false;
            }
        }

        return $result;
    }

    /**
     * Проверяет только одно поле.
     */
    public function check(string $field): bool
    {
        $this->clearFieldMessages($field);

        if (!array_key_exists($field, $this->rules)) {
            return false;
        }

        return $this->validateField($field, $this->rules[$field]);
    }

    /**
     * Все сообщения об ошибках.
     *
     * @return array<string, array<int, string>>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Алиас getMessages().
     *
     * @return array<string, array<int, string>>
     */
    public function getErrors(): array
    {
        return $this->messages;
    }

    /**
     * Короткий алиас для getMessages().
     *
     * @return array<string, array<int, string>>
     */
    public function errors(): array
    {
        return $this->messages;
    }

    /**
     * Возвращает первое сообщение об ошибке.
     *
     * @param string|null $field Если указано — первая ошибка поля, иначе первая ошибка вообще.
     * @return string|null
     */
    public function first($field = null): ?string
    {
        if ($field !== null) {
            return $this->messages[$field][0] ?? null;
        }

        foreach ($this->messages as $msgs) {
            if (!empty($msgs[0])) {
                return $msgs[0];
            }
        }

        return null;
    }

    /**
     * Возвращает первое сообщение об ошибке для указанного поля.
     */
    public function getFirstMessage(string $field): ?string
    {
        return $this->first($field);
    }

    /**
     * Проверяет, есть ли ошибки у указанного поля.
     */
    public function hasError(string $field): bool
    {
        return !empty($this->messages[$field]);
    }

    /**
     * Возвращает список полей, не прошедших валидацию.
     *
     * @return array<int, string>
     */
    public function failed(): array
    {
        return array_keys($this->messages);
    }

    /**
     * Возвращает только валидированные данные (поля, для которых есть правила).
     *
     * @return array<string, mixed>
     */
    public function validated(): array
    {
        $result = [];

        foreach (array_keys($this->rules) as $field) {
            if (array_key_exists($field, $this->values)) {
                $result[$field] = $this->values[$field];
            }
        }

        return $result;
    }

    /**
     * Возвращает часть валидированных данных по указанным ключам.
     *
     * @param array<int, string> $keys
     * @return array<string, mixed>
     */
    public function only($keys): array
    {
        $validated = $this->validated();
        $result    = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $validated)) {
                $result[$key] = $validated[$key];
            }
        }

        return $result;
    }

    /**
     * Возвращает валидированные данные или выбрасывает исключение при ошибках.
     *
     * @throws \RuntimeException
     * @return array<string, mixed>
     */
    public function validateOrFail(): array
    {
        if ($this->fails()) {
            $msg = $this->first() ?: 'Ошибка валидации';
            throw new \RuntimeException($msg);
        }

        return $this->validated();
    }

    // ---------------------------------------------------------------------
    //  Внутренняя логика валидации
    // ---------------------------------------------------------------------

    /**
     * Выполняет валидацию одного поля.
     */
    protected function validateField(string $field, $ruleSet): bool
    {
        $exists = array_key_exists($field, $this->values);
        $value  = $exists ? $this->values[$field] : null;

        if ($ruleSet instanceof Closure) {
            if (!$exists) {
                return true;
            }

            return $this->validateClosure($field, $value, $ruleSet);
        }

        if (!is_array($ruleSet)) {
            return true;
        }

        $required = (bool)($ruleSet['required'] ?? false);
        $nullable = (bool)($ruleSet['nullable'] ?? false);
        $trim     = (bool)($ruleSet['trim'] ?? false);

        if (!$exists) {
            if ($required) {
                $this->addError($field, $this->getErrorMessage($field, 'required', $ruleSet));
                return false;
            }

            return true;
        }

        if ($trim && is_string($value)) {
            $value = trim($value);
            $this->values[$field] = $value;
        }

        if ($value === null) {
            if ($required && !$nullable) {
                $this->addError($field, $this->getErrorMessage($field, 'required', $ruleSet));
                return false;
            }

            return true;
        }

        if (is_string($value)) {
            if ($required && trim($value) === '') {
                $this->addError($field, $this->getErrorMessage($field, 'required', $ruleSet));
                return false;
            }

            if (!$required && trim($value) === '') {
                return true;
            }
        }

        $valid = true;

        foreach ($ruleSet as $rule => $ruleValue) {
            if (in_array($rule, ['required', 'nullable', 'trim', 'message', 'messages'], true)) {
                continue;
            }

            $rulePassed = $this->applyRule($field, $value, $rule, $ruleValue, $ruleSet);

            if (!$rulePassed) {
                $valid = false;

                if ($this->stopOnFirstError) {
                    break;
                }
            }
        }

        return $valid;
    }

    /**
     * Выполняет кастомную проверку через `Closure`.
     */
    protected function validateClosure(string $field, $value, Closure $closure): bool
    {
        $result = $closure($field, $value, $this->values);

        if ($result === true) {
            return true;
        }

        if ($result === false) {
            $this->addError($field, $this->getErrorMessage($field, 'custom', []));
            return false;
        }

        if (is_string($result) && $result !== '') {
            $this->addError($field, Str::ucfirst($result));
            return false;
        }

        $this->addError($field, $this->getErrorMessage($field, 'custom', []));
        return false;
    }

    /**
     * Применяет одно правило к значению поля.
     */
    protected function applyRule(string $field, $value, string $rule, $ruleValue, array $allRules): bool
    {
        // Кастомные правила пользователя
        if (isset($this->customRules[$rule])) {
            $result = $this->customRules[$rule]($value, (array)$ruleValue, $this->values);

            if ($result === true) {
                return true;
            }

            $msg = is_string($result) ? $result : $this->getErrorMessage($field, $rule, $allRules);
            $this->addError($field, $msg);
            return false;
        }

        switch ($rule) {
            case 'regexp':
                if (!is_scalar($value) || !preg_match((string)$ruleValue, (string)$value)) {
                    $this->addError($field, $this->getErrorMessage($field, 'regexp', $allRules));
                    return false;
                }
                return true;

            case 'minlen':
                if (!is_scalar($value) || Str::length((string)$value) < (int)$ruleValue) {
                    $this->addError($field, $this->getErrorMessage($field, 'minlen', $allRules, [
                        'minlen' => (int)$ruleValue
                    ]));
                    return false;
                }
                return true;

            case 'maxlen':
                if (!is_scalar($value) || Str::length((string)$value) > (int)$ruleValue) {
                    $this->addError($field, $this->getErrorMessage($field, 'maxlen', $allRules, [
                        'maxlen' => (int)$ruleValue
                    ]));
                    return false;
                }
                return true;

            case 'min':
                if (!is_numeric($value)) {
                    $this->addError($field, $this->getErrorMessage($field, 'numeric', $allRules));
                    return false;
                }

                if ((float)$value < (float)$ruleValue) {
                    $this->addError($field, $this->getErrorMessage($field, 'min', $allRules, [
                        'min' => $ruleValue
                    ]));
                    return false;
                }
                return true;

            case 'max':
                if (!is_numeric($value)) {
                    $this->addError($field, $this->getErrorMessage($field, 'numeric', $allRules));
                    return false;
                }

                if ((float)$value > (float)$ruleValue) {
                    $this->addError($field, $this->getErrorMessage($field, 'max', $allRules, [
                        'max' => $ruleValue
                    ]));
                    return false;
                }
                return true;

            case 'numeric':
                if (!is_numeric($value)) {
                    $this->addError($field, $this->getErrorMessage($field, 'numeric', $allRules));
                    return false;
                }
                return true;

            case 'email':
                if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                    $this->addError($field, $this->getErrorMessage($field, 'email', $allRules));
                    return false;
                }
                return true;

            case 'url':
                if (filter_var($value, FILTER_VALIDATE_URL) === false) {
                    $this->addError($field, $this->getErrorMessage($field, 'url', $allRules));
                    return false;
                }
                return true;

            case 'int':
            case 'integer':
                if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $this->addError($field, $this->getErrorMessage($field, 'int', $allRules));
                    return false;
                }
                return true;

            case 'float':
                if (filter_var($value, FILTER_VALIDATE_FLOAT) === false) {
                    $this->addError($field, $this->getErrorMessage($field, 'float', $allRules));
                    return false;
                }
                return true;

            case 'bool':
            case 'boolean':
                if (!is_bool($value) && !in_array($value, [0, 1, '0', '1', true, false], true)) {
                    $this->addError($field, $this->getErrorMessage($field, 'bool', $allRules));
                    return false;
                }
                return true;

            case 'json':
                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() !== JSON_ERROR_NONE || ($decoded === null && trim($value) !== 'null')) {
                        $this->addError($field, $this->getErrorMessage($field, 'json', $allRules));
                        return false;
                    }
                } else {
                    $this->addError($field, $this->getErrorMessage($field, 'json', $allRules));
                    return false;
                }
                return true;

            case 'alpha':
                if (!is_string($value) || !preg_match('/^[\pL\pM]+$/u', $value)) {
                    $this->addError($field, $this->getErrorMessage($field, 'alpha', $allRules));
                    return false;
                }
                return true;

            case 'alphanum':
                if (!is_string($value) || !preg_match('/^[\pL\pM\pN]+$/u', $value)) {
                    $this->addError($field, $this->getErrorMessage($field, 'alphanum', $allRules));
                    return false;
                }
                return true;

            case 'date':
                if (is_string($value) && $value !== '') {
                    $format = is_string($ruleValue) && $ruleValue !== '' ? $ruleValue : null;

                    if ($format !== null) {
                        $dt = \DateTime::createFromFormat($format, $value);
                        if ($dt === false || $dt->format($format) !== $value) {
                            $this->addError($field, $this->getErrorMessage($field, 'date', $allRules));
                            return false;
                        }
                    } else {
                        if (strtotime($value) === false) {
                            $this->addError($field, $this->getErrorMessage($field, 'date', $allRules));
                            return false;
                        }
                    }
                } else {
                    $this->addError($field, $this->getErrorMessage($field, 'date', $allRules));
                    return false;
                }
                return true;

            case 'confirmed':
                $confirmField = $field . '_confirmation';
                $confirmValue = $this->values[$confirmField] ?? null;

                if ((string)$value !== (string)$confirmValue) {
                    $this->addError($field, $this->getErrorMessage($field, 'confirmed', $allRules, [
                        'field' => $confirmField
                    ]));
                    return false;
                }
                return true;

            case 'in':
                $allowed = is_array($ruleValue) ? $ruleValue : explode(',', (string)$ruleValue);
                if (!in_array((string)$value, array_map('strval', $allowed), true)) {
                    $this->addError($field, $this->getErrorMessage($field, 'in', $allRules, [
                        'values' => implode(', ', $allowed)
                    ]));
                    return false;
                }
                return true;

            case 'not_in':
                $denied = is_array($ruleValue) ? $ruleValue : explode(',', (string)$ruleValue);
                if (in_array((string)$value, array_map('strval', $denied), true)) {
                    $this->addError($field, $this->getErrorMessage($field, 'not_in', $allRules, [
                        'values' => implode(', ', $denied)
                    ]));
                    return false;
                }
                return true;

            case 'same':
                $otherField = (string)$ruleValue;
                $otherValue = $this->values[$otherField] ?? null;

                if ($value !== $otherValue) {
                    $this->addError($field, $this->getErrorMessage($field, 'same', $allRules, [
                        'field' => $otherField
                    ]));
                    return false;
                }
                return true;

            case 'validator':
                $validator = 'is_' . $ruleValue;

                if (!function_exists($validator) || !$validator($value)) {
                    $this->addError($field, $this->getErrorMessage($field, 'custom', $allRules));
                    return false;
                }
                return true;
        }

        return true;
    }

    // ---------------------------------------------------------------------
    //  Сообщения
    // ---------------------------------------------------------------------

    /**
     * Добавляет сообщение об ошибке для поля.
     */
    protected function addError(string $field, string $message): void
    {
        $this->messages[$field] ??= [];
        $this->messages[$field][] = $message;
    }

    /**
     * Удаляет все ошибки указанного поля.
     */
    protected function clearFieldMessages(string $field): void
    {
        unset($this->messages[$field]);
    }

    /**
     * Возвращает текст сообщения об ошибке для указанного типа правила.
     *
     * Приоритет источников:
     * 1. `$this->customMessages['field.rule']`
     * 2. `$rule['messages'][$type]`
     * 3. `$rule['message']`
     * 4. `i18n($key, $params)`
     * 5. Ключ локализации как fallback
     */
    protected function getErrorMessage(string $field, string $type, array $rule, array $params = []): string
    {
        // 1. Пользовательские сообщения через setMessages()
        $dotKey = $field . '.' . $type;
        if (!empty($this->customMessages[$dotKey])) {
            return Str::ucfirst((string)$this->customMessages[$dotKey]);
        }

        // 2. messages[type] внутри правил поля
        if (!empty($rule['messages'][$type])) {
            return Str::ucfirst((string)$rule['messages'][$type]);
        }

        // 3. message внутри правил поля
        if (!empty($rule['message']) && is_string($rule['message'])) {
            return Str::ucfirst($rule['message']);
        }

        // 4. i18n / ключ локализации
        $key = $this->errorKeys[$type] ?? 'validator_invalid';

        if (function_exists('i18n')) {
            return Str::ucfirst(i18n($key, $params));
        }

        return Str::ucfirst($key);
    }

    // ---------------------------------------------------------------------
    //  Нормализация правил
    // ---------------------------------------------------------------------

    /**
     * Нормализует правила в единый формат.
     */
    protected function normalizeRules($rules)
    {
        if ($rules instanceof Closure) {
            return $rules;
        }

        if (is_array($rules)) {
            return $rules;
        }

        if (is_string($rules)) {
            return $this->parseRules($rules);
        }

        return [];
    }

    /**
     * Разбирает строку правил в массив.
     *
     * Пример: `required|trim|email|maxlen:100`
     *
     * преобразуется в:
     *
     * ```php
     * [
     *     'required' => true,
     *     'trim'     => true,
     *     'email'    => true,
     *     'maxlen'   => 100,
     * ]
     * ```
     */
    protected function parseRules(string $rules): array
    {
        if ($rules === '') {
            return [];
        }

        $rules  = str_replace('\|', '___PIPE___', $rules);
        $parts  = explode('|', $rules);
        $result = [];

        foreach ($parts as $part) {
            $part = str_replace('___PIPE___', '\|', $part);
            $raw  = trim($part);
            $key  = strtolower($raw);

            // Правила без параметров
            if (in_array($key, ['required', 'nullable', 'trim'], true)) {
                $result[$key] = true;
                continue;
            }

            // Типы и форматы без параметров
            if (in_array($key, ['email', 'url', 'int', 'integer', 'float', 'bool', 'boolean', 'numeric', 'json', 'alpha', 'alphanum'], true)) {
                if ($key === 'integer') {
                    $result['int'] = true;
                } elseif ($key === 'boolean') {
                    $result['bool'] = true;
                } else {
                    $result[$key] = true;
                }
                continue;
            }

            // date (опционально с форматом)
            if ($key === 'date' || strpos($key, 'date:') === 0) {
                if (strpos($raw, ':') !== false) {
                    $result['date'] = substr($raw, 5);
                } else {
                    $result['date'] = true;
                }
                continue;
            }

            // confirmed
            if ($key === 'confirmed') {
                $result['confirmed'] = true;
                continue;
            }

            // regexp:/pattern/
            if (strpos($key, 'regexp:') === 0) {
                $result['regexp'] = substr($raw, 7);
                continue;
            }

            // message:Текст
            if (strpos($key, 'message:') === 0) {
                $result['message'] = substr($raw, 8);
                continue;
            }

            // Правила с параметрами (ключ:значение)
            if (strpos($key, ':') !== false) {
                [$k, $v] = explode(':', $raw, 2);
                $k = strtolower(trim($k));
                $v = trim($v);

                switch ($k) {
                    case 'min':
                        $result['min'] = is_numeric($v) ? $v + 0 : $v;
                        break;
                    case 'max':
                        $result['max'] = is_numeric($v) ? $v + 0 : $v;
                        break;
                    case 'minlen':
                        $result['minlen'] = (int)$v;
                        break;
                    case 'maxlen':
                        $result['maxlen'] = (int)$v;
                        break;
                    case 'in':
                        $result['in'] = array_map('trim', explode(',', $v));
                        break;
                    case 'not_in':
                        $result['not_in'] = array_map('trim', explode(',', $v));
                        break;
                    case 'same':
                        $result['same'] = $v;
                        break;
                    case 'validator':
                        $result['validator'] = strtolower($v);
                        break;
                    case 'date':
                        $result['date'] = $v;
                        break;
                }

                continue;
            }

            // Неизвестное правило — пробуем как валидатор is_*
            if (function_exists('is_' . $key)) {
                $result['validator'] = $key;
                continue;
            }

            // Кастомное зарегистрированное правило
            if (isset($this->customRules[$key])) {
                $result[$key] = true;
                continue;
            }
        }

        return $result;
    }
}
