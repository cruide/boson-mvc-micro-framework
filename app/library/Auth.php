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

        // Rate limiting: не более 5 попыток за 15 минут (только проверка, без инкремента)
        if( $this->isRateLimited($email) ) {
            return false;
        }

        // Поиск пользователя по email
        $user = User::where('email', '=', $email)->first();

        if( !$user ) {
            $this->incrementRateLimit($email);
            return false;
        }

        // Проверка пароля
        if( password_verify($password, $user->password) ) {
            // bcrypt — ок
        } elseif( password_verify_legacy($password, $user->password) ) {
            // Перехешируем старый пароль на bcrypt
            $user->password = password_crypt($password);
        } else {
            $this->incrementRateLimit($email);
            return false;
        }

        // Регенерируем ID сессии для предотвращения session fixation
        session()->regenerate();

        // Обновление данных сессии
        $user->session  = session()->id(true);
        $user->unixtime = time();
        $user->ip       = get_ip_address();

        // Обработка "Запомнить меня"
        if( $remember ) {
            if( empty($user->token) ) {
                $user->token = uuid();
            }

            cookies()->token = $user->token;

        } else {
            $user->token = null;

            unset(cookies()->token);
        }

        $user->save();

        $this->authorized = true;
        $this->user       = $user;

        // Сбрасываем счётчик попыток после успешного входа
        $this->resetRateLimit($email);

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
     * Проверяет, не превышен ли лимит попыток входа.
     * Не более 5 попыток за 15 минут с одного IP/email.
     *
     * @param string $email
     * @return bool true если лимит превышен
     */
    private function isRateLimited(string $email): bool
    {
        if( !$this->cacheAvailable() ) {
            return false;
        }

        $attempts = (int)cache($this->rateLimitKey($email));

        return $attempts >= 5;
    }

    /**
     * Инкрементирует счётчик неудачных попыток входа.
     *
     * @param string $email
     */
    private function incrementRateLimit(string $email): void
    {
        if( !$this->cacheAvailable() ) {
            return;
        }

        $key      = $this->rateLimitKey($email);
        $attempts = (int)cache($key);

        cache($key, $attempts + 1, 900);
    }

    /**
     * Сбрасывает счётчик попыток после успешного входа.
     *
     * @param string $email
     */
    private function resetRateLimit(string $email): void
    {
        if( !$this->cacheAvailable() ) {
            return;
        }

        cache($this->rateLimitKey($email), null);
    }

    /**
     * Ключ кеша для rate limiting.
     *
     * @param string $email
     * @return string
     */
    private function rateLimitKey(string $email): string
    {
        return 'ratelimit_login_' . md5(get_ip_address() . $email);
    }

    /**
     * Проверяет доступность кеша.
     *
     * @return bool
     */
    private function cacheAvailable(): bool
    {
        return function_exists('cache') && function_exists('cacheRemember');
    }
}
