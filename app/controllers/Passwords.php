<?php namespace App\Controllers;

use App\Models\Password;

class Passwords
{
	public function _before()
	{
		if( !is_auth() ) {
			abort_json(['message' => 'Unauthorized'], 401);
		}
	}

	public function index()
	{
		$query = input()->query;

		if( !empty($query) ) {
			$items = Password::where('name', 'LIKE', "%{$query}%")
							 ->orWhere('url', 'LIKE', "%{$query}%")
							 ->get();
		} else {
			$items = Password::all();
		}
		
		return json_response(
			$items
		);
	}
	
	public function update()
	{
		$csvPath = cfg('passwords', 'csv_path') ?: (ROOT . DIR_SEP . 'passwords.csv');

		if( !file_exists($csvPath) ) {
			return json_response([
				'status'  => false,
				'message' => 'Файл passwords.csv не найден.',
			], 400);
		}
		
			$csv = file($csvPath);
		$all = 0;
		
		unset( $csv[0] );
		
		foreach($csv as $line) {
			list($name, $url, $username, $pwd) = explode(',', $line);
			
			if( !empty($username) && !empty($pwd) ) {
				Password::create([
					'name'     => $name, 
					'url'      => $url, 
					'username' => $username, 
					'password' => trim($pwd), 
				]);
				
				$all += 1;
				
				usleep(1);
			}
		}
		
		return json_response([
			'status' => true,
			'items'  => $all,
		]);
	}
}
