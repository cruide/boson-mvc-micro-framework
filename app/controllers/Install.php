<?php namespace App\Controllers;

use Illuminate\Database\Schema\Blueprint;
use App\Models\User;

class Install
{
	public function index()
	{
		$prefix = dbCfg('prefix');

		if( !orm()->hasTable('users') ) {
			schema()->create('users', function(Blueprint $table) {
				$table->increments('id');
				$table->string('name')->index()->default('');
				$table->string('email')->index()->default('');
				$table->string('password', 64)->default('');
				$table->string('token', 36)->index()->default('');
				$table->string('session', 32)->index()->default('');
				$table->string('ip', 15)->index()->default('');
				$table->unsignedBigInteger('unixtime')->index()->default(0);
				$table->timestamps();
				$table->index(['session', 'ip']);
			});
			
			schema()->create('user_profiles', function(Blueprint $table) {
				$table->increments('id');
				$table->unsignedBigInteger('user_id')->index()->default(0);
				$table->unsignedTinyInteger('gender')->index()->default(1);
				$table->string('first_name')->default('');
				$table->string('middle_name')->default('');
				$table->string('last_name')->default('');
				$table->date('birthday')->nullable();
				$table->timestamps();
			});
			
			$user = User::create([
				'name'     => 'superuser',
				'email'    => 'user@test.local',
				'password' => password_crypt('test'),
			]);
			
			$user->profile()->create([
				'first_name' => 'Superuser',
				'birthday'   => date(BOSON_SQL_DATE),
			]);
		}
		
		if( !orm()->hasTable('passwords') ) {
			schema()->create('passwords', function(Blueprint $table) {
				$table->increments('id');
				$table->string('name')->index()->default('');
				$table->string('url')->index()->default('');
				$table->string('username')->default('');
				$table->string('password')->default('');
				$table->timestamps();
			});
		}
		
		return view('install/success'); 
	}
}
