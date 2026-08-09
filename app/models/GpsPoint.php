<?php namespace App\Models;

class GpsPoint extends \Boson\Abstracts\EloquentModel
{
    protected $table = 'gps_points';
    
    public function users()
    {
        return $this->belongsToMany(User::class, 'point_user', 'gps_point_id', 'user_id');
    }
}
