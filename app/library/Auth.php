<?php namespace App\Library;
/**
 * @name      Boson PHP framework
 * @author    Tishchenko Alexander (info@alex-tisch.ru)
 * @copyright Copyright (c) 2018 All rights reserved
 *
 * Модифицировано: 2024
 * Описание: Класс аутентификации пользователей. Обеспечивает вход, выход,
 * проверку текущего статуса и автоматическую авторизацию через сессию
 * или долгосрочный токен ("Запомнить меня").
 */

use Boson\Traits\SingletonTrait;
use App\Models\User;

final class Auth
{
    use SingletonTrait;

    /** @var bool Флаг, авторизован ли текущий пользователь */
    private $authorized = false;
    /** @var User|null Объект текущего авторизованного пользователя */
    private $user = null;

    /**
     * Приватный конструктор (синглтон).
     * Пытается автоматически авторизовать пользователя при создании объекта.
     * Приоритет: 1. Активная сессия -> 2. Долгосрочный токен в куки.
     */
    public function __construct()
    {
        // --- 1. Попытка авторизации по активной сессии ---
        $userFromSession = User::where('session', '=', session()->id(true))
                               ->where('ip', '=', get_ip_address())
                               ->first();

        if( $userFromSession ) {
            // Обновляем временную метку активности
            $userFromSession->unixtime = time();
            $userFromSession->save();

            $this->authorized = true;
            $this->user       = $userFromSession;

            unset($userFromSession);

            // Успешно авторизовались по сессии, выходим
            return; 
        }

        // --- 2. Попытка авторизации по долгосрочному токену (куки) ---
        $token = cookies()->token;

        if( !empty($token) && is_uuid($token) ) {
            $userFromToken = User::where('token', '=', $token)->first();

            if( $userFromToken ) {
                // Токен валиден. Обновляем сессию и IP для этого пользователя.
                $userFromToken->session  = session()->id(true);
                $userFromToken->unixtime = time();
                $userFromToken->ip       = get_ip_address();

                $userFromToken->save();

                // Обновляем куки, чтобы продлить срок его жизни (сбросить таймер)
                cookies()->token = $token;

                $this->authorized = true;
                $this->user       = $userFromToken;

                unset($userFromToken);

                return;
            }

            // Токен есть в куках, но не найден в БД (устарел, удален) — удаляем его из куки
            unset( cookies()->token );
        }
    }

    /**
     * Аутентификация пользователя по email и паролю.
     *
     * @param string $email    Email пользователя.
     * @param string $password Пароль (в чистом виде).
     * @param bool   $remember Флаг "Запомнить меня". Если true, будет сгенерирован и сохранен
     *                         долгосрочный токен в куки.
     * @return bool true в случае успеха, false при неудаче.
     */
    public function signin(string $email, string $password, bool $remember = false): bool
    {
        // Базовая валидация
        if( !is_email($email) ) {
            return false;
        }

        // Rate limiting: не более 5 попыток за 15 минут
        if( !$this->checkRateLimit($email) ) {
            return false;
        }

        // Поиск пользователя по email (пароль пока не проверяем)
        $user = User::where('email', '=', $email)->first();

        // Проверка пароля с использованием password_verify (bcrypt)
        if( !$user || !password_verify($password, $user->password) ) {
            // Для обратной совместимости со старым хэшированием (MD5+salt)
            if( !$user || !password_verify_legacy($password, $user->password) ) {
                return false;
            }

            // Перехешируем пароль на bcrypt
            $user->password = password_crypt($password);
        }

        // Регенерируем ID сессии для предотвращения session fixation
        session()->regenerate();

        // Обновление данных сессии
        $user->session  = session()->id(true);
        $user->unixtime = time();
        $user->ip       = get_ip_address();

        // Обработка "Запомнить меня"
        if( $remember ) {
            // Если токена еще нет, генерируем новый UUID
            if( empty($user->token) ) {
                $user->token = uuid();
            }

            cookies()->token = $user->token;

        } else {
            // Если флаг "запомнить" не активен, удаляем токен и куки
            $user->token = null;

            unset(cookies()->token);
        }

        $user->save();

        $this->authorized = true;
        $this->user       = $user;

        return true;
    }

    /**
     * Деавторизация пользователя (выход).
     * Очищает сессию, токен (если есть) и уничтожает сессию PHP.
     */
    public function signout(): void
    {
        if( $this->authorized && $this->user ) {
            // Очищаем данные пользователя
            $this->user->session  = '';
            $this->user->unixtime = 0;
            $this->user->ip       = '';

            // Если был токен, удаляем его (полный выход)
            if( $this->user->token ) {
                $this->user->token = null;

                unset(cookies()->token);
            }

            $this->user->save();

            $this->user       = null;
            $this->authorized = false;

            // Уничтожаем PHP сессию
            session()->destroy();
        }
    }

    /**
     * Возвращает объект текущего пользователя.
     *
     * @return User|null Объект User или null, если пользователь не авторизован.
     */
    public function user(): ?User
    {
        return $this->authorized ? $this->user : null;
    }

    /**
     * Возвращает ID текущего пользователя.
     *
     * @return int|null ID пользователя или null.
     */
    public function id(): ?int
    {
        return $this->authorized ? (int)$this->user->id : null;
    }

    /**
     * Проверяет, авторизован ли пользователь.
     *
     * @return bool true, если авторизован, иначе false.
     */
    public function check(): bool
    {
        return $this->authorized;
    }

    /**
     * Проверка пароля, захешированного старым способом (MD5+crypt).
     * Использует глобальную функцию password_verify_legacy.
     *
     * @param string $password      Пароль в чистом виде.
     * @param string $storedHash    Хэш из базы данных.
     * @return bool                 Результат проверки.
     */
    private function legacyPasswordVerify(string $password, string $storedHash): bool
    {
        return password_verify_legacy($password, $storedHash);
    }

    /**
     * Rate limiting для попыток входа.
     * Не более 5 попыток за 15 минут с одного IP/email.
     *
     * @param string $email
     * @return bool true если лимит не превышен
     */
    private function checkRateLimit(string $email): bool
    {
        $ip    = get_ip_address();
        $key   = 'ratelimit_login_' . md5($ip . $email);

        if( function_exists('cache') && function_exists('cacheRemember') ) {
            $attempts = (int)cache($key);

            if( $attempts >= 5 ) {
                return false;
            }

            // Увеличиваем счётчик, TTL 15 минут
            cache($key, $attempts + 1, 900);
        }

        return true;
    }
}
