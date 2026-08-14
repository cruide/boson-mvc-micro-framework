<?php namespace App\Models;

class Password extends \Boson\Abstracts\EloquentModel
{
    protected $table    = 'passwords';
    protected $fillable = [
        'name',
        'url',
        'username',
        'password',
    ];

    protected $casts = [
        'password' => \Boson\Casts\EncryptedCast::class,
    ];
}
