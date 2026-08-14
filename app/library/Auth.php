<?php namespace App\Library;
/**
 * @name      Boson PHP framework
 * @author    Tishchenko Alexander (info@alex-tisch.ru)
 * @copyright Copyright (c) 2018 All rights reserved
 *
 * Modified: 2024
 * Description: User authentication class. Provides login, logout,
 * checking the current status and automatic authorization via session
 * or a long-term token ("Remember me").
 */

use Boson\Traits\SingletonTrait;
use App\Models\User;

final class Auth
{
    use SingletonTrait;

    /** @var bool Flag indicating whether the current user is authorized */
    private $authorized = false;
    /** @var User|null The current authorized user object */
    private $user = null;

    /**
     * Private constructor (singleton).
     * Tries to automatically authorize the user when the object is created.
     * Priority: 1. Active session -> 2. Long-term token in cookies.
     */
    public function __construct()
    {
        // --- 1. Attempt to authorize via active session ---
        $userFromSession = User::where('session', '=', session()->id(true))
                               ->where('ip', '=', get_ip_address())
                               ->first();

        if( $userFromSession ) {
            // Update the activity timestamp at most once per minute to avoid
            // an unnecessary DB write on every request.
            if( time() - (int)$userFromSession->unixtime >= 60 ) {
                $userFromSession->unixtime = time();
                $userFromSession->save();
            }

            $this->authorized = true;
            $this->user       = $userFromSession;

            return;
        }

        // --- 2. Attempt to authorize via long-term token (cookie) ---
        $token = cookies()->token;

        if( !empty($token) && is_uuid($token) ) {
            $userFromToken = User::where('token', '=', $token)->first();

            if( $userFromToken ) {
                // Token is valid. Update the session and IP for this user.
                $userFromToken->session  = session()->id(true);
                $userFromToken->unixtime = time();
                $userFromToken->ip       = get_ip_address();

                $userFromToken->save();

                // Update the cookie to extend its lifetime (reset the timer)
                cookies()->token = $token;

                $this->authorized = true;
                $this->user       = $userFromToken;

                return;
            }

            // Token exists in cookies, but not found in DB (expired, deleted) — remove it from the cookie
            unset( cookies()->token );
        }
    }

    /**
     * Authenticate a user by email and password.
     *
     * @param string $email    User email.
     * @param string $password Password (plain text).
     * @param bool   $remember "Remember me" flag. If true, a long-term token will be generated and saved
     *                         in cookies.
     * @return bool true on success, false on failure.
     */
    public function signin(string $email, string $password, bool $remember = false): bool
    {
        // Basic validation
        if( !is_email($email) ) {
            return false;
        }

        // Rate limiting: no more than 5 attempts per 15 minutes (check only, without increment)
        if( $this->isRateLimited($email) ) {
            return false;
        }

        // Find user by email
        $user = User::where('email', '=', $email)->first();

        if( !$user ) {
            $this->incrementRateLimit($email);
            return false;
        }

        // Password check
        if( password_verify($password, $user->password) ) {
            // bcrypt — ok
        } elseif( password_verify_legacy($password, $user->password) ) {
            // Rehash the old password to bcrypt
            $user->password = password_crypt($password);
        } else {
            $this->incrementRateLimit($email);
            return false;
        }

        // Regenerate session ID to prevent session fixation
        session()->regenerate();

        // Update session data
        $user->session  = session()->id(true);
        $user->unixtime = time();
        $user->ip       = get_ip_address();

        // Handle "Remember me"
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

        // Reset the attempt counter after a successful login
        $this->resetRateLimit($email);

        return true;
    }

    /**
     * Deauthorize the user (logout).
     * Clears the session, the token (if any) and destroys the PHP session.
     */
    public function signout(): void
    {
        if( $this->authorized && $this->user ) {
            // Clear user data
            $this->user->session  = '';
            $this->user->unixtime = 0;
            $this->user->ip       = '';

            // If there was a token, remove it (full logout)
            if( $this->user->token ) {
                $this->user->token = null;

                unset(cookies()->token);
            }

            $this->user->save();

            $this->user       = null;
            $this->authorized = false;

            // Destroy the PHP session
            session()->destroy();
        }
    }

    /**
     * Returns the current user object.
     *
     * @return User|null The User object or null if the user is not authorized.
     */
    public function user(): ?User
    {
        return $this->authorized ? $this->user : null;
    }

    /**
     * Returns the current user's ID.
     *
     * @return int|null The user ID or null.
     */
    public function id(): ?int
    {
        return $this->authorized ? (int)$this->user->id : null;
    }

    /**
     * Checks whether the user is authorized.
     *
     * @return bool true if authorized, otherwise false.
     */
    public function check(): bool
    {
        return $this->authorized;
    }

    /**
     * Checks whether the login attempt limit is exceeded.
     * No more than 5 attempts per 15 minutes from one IP/email.
     *
     * @param string $email
     * @return bool true if the limit is exceeded
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
     * Increments the counter of failed login attempts.
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
     * Resets the attempt counter after a successful login.
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
     * Cache key for rate limiting.
     *
     * @param string $email
     * @return string
     */
    private function rateLimitKey(string $email): string
    {
        return 'ratelimit_login_' . md5(get_ip_address() . $email);
    }

    /**
     * Checks cache availability.
     *
     * @return bool
     */
    private function cacheAvailable(): bool
    {
        return function_exists('cache') && function_exists('cacheRemember');
    }
}
