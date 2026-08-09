<?php namespace App\Models;

class User extends \Boson\Abstracts\EloquentModel
{
    protected $table    = 'users';
    protected $fillable = [
        'name',
        'email',
        'password',
        'session',
    ];
    
    protected $hidden = [
        'password',
        'session',
        'updated_at',
    ];
    
    public static function boot()
    {
        parent::boot();
        
        self::deleting(function(User $obj) {
            $obj->profile()->delete();
        });
    }
    
    public function profile()
    {
        return $this->hasOne(Profile::class, 'user_id');
    }
}
