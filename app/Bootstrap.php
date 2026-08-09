<?php

function auth()
{
    return \App\Library\Auth::getInstance();
}

function is_auth()
{
    return auth()->check();
} 

/**
 * Генерация CSRF токена.
 * Сохраняет токен в сессии и возвращает его.
 *
 * @return string
 */
function csrf_token()
{
    if( !session()->has('_csrf_token') ) {
        session()->_csrf_token = bin2hex(random_bytes(32));
    }

    return session()->_csrf_token;
}

/**
 * Возвращает HTML-поле с CSRF токеном для вставки в формы.
 *
 * @return string
 */
function csrf_field()
{
    return '<input type="hidden" name="_csrf_token" value="' . csrf_token() . '">';
}

/**
 * Проверка CSRF токена из запроса.
 * Возвращает true если токен валиден.
 *
 * @return bool
 */
function csrf_verify()
{
    $token = input()->_csrf_token
          ?? input()->header('X-CSRF-Token')
          ?? null;

    if( empty($token) || !session()->has('_csrf_token') ) {
        return false;
    }

    return hash_equals(session()->_csrf_token, $token);
}

cors();
