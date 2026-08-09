<?php namespace App\Models;

class Profile extends \Boson\Abstracts\EloquentModel
{
    const GENDER_MALE   = 0;
    const GENDER_FEMALE = 1;
    const GENDER_OTHER  = 2;
    
    protected $table    = 'user_profiles';
    protected $fillable = [
        'gender',
        'first_name',
        'middle_name',
        'last_name',
        'birthday',
        'user_id',
    ];
    
    protected $hidden = [
        'user_id',
        'updated_at',
        'created_at',
    ];
    
    protected $appends = [
        'gender_name',
    ];
    
    public static function getGenders($gender_id = null)
    {
        $_ = [
            self::GENDER_MALE   => i18n('male'),
            self::GENDER_FEMALE => i18n('female'),
            self::GENDER_OTHER  => i18n('gender_other'),
        ];
        
        if( $gender_id !== null ) {
            if( is_numeric($gender_id) && array_key_exists($gender_id, $_) ) {
                return $_[ $gender_id ];
            }
            
            return '';
        }
        
        return $_;
    }
    
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    public function getGenderNameAttribute()
    {
        return self::getGenders( $this->attributes['gender'] );
    }
}
