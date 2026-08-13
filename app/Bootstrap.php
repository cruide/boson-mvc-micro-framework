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
 * CSRF token generation.
 * Saves the token in the session and returns it.
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
 * Returns an HTML field with a CSRF token for embedding in forms.
 *
 * @return string
 */
function csrf_field()
{
    return '<input type="hidden" name="_csrf_token" value="' . csrf_token() . '">';
}

/**
 * CSRF token verification from the request.
 * Returns true if the token is valid.
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
