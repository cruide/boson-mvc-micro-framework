<?php namespace App\Controllers;

use App\Models\User;

class Index
{
	/**
	* Метод по умолчанию
	*/
	public function index($id = null)
	{
		$data = collect([
			['id' => 1, 'name' => 'Vasya', 'type' => 1],
			['id' => 2, 'name' => 'Nikolay', 'type' => 5],
			['id' => 3, 'name' => 'Nadeghda', 'type' => 1],
			['id' => 4, 'name' => 'Viktoriya', 'type' => 1],
			['id' => 5, 'name' => 'Dmitriy', 'type' => 3],
		]);
		
		$data = $data->filter(function($item, $key) {
			return $item['type'] != 1;
		});
		
        theme()->assign('is_auth', is_auth());
        
		return view('index/index');
	}
	
	public function register()
	{
		if( !input()->expectsJson() ) {
			abort('Некорректный запрос...');
		}
		
		if( auth()->check() ) {
			return json_response([
				'status' => 'success',
				'userId' => auth()->id(),
			]);
		}
		
		$json = input()->json();

		if( empty($json) ) {
			return json_response([
				'status' => 'error',
				'data'   => [
					'errorText' => 'Нет данных для обработки...',
				],
			]);
		}
		
		if( empty($json['login']) || !is_email($json['login']) ) {
			return json_response([
				'status' => 'error',
				'data'   => [
					'errorText' => 'Указан некорректный Email...',
				],
			]);
		}
		
		if( empty($json['name']) || !is_name($json['name']) ) {
			return json_response([
				'status' => 'error',
				'data'   => [
					'errorText' => 'Указано некорректное Имя...',
				],
			]);
		}
		
		if( empty($json['pwd1']) || empty($json['pwd2']) || $json['pwd1'] != $json['pwd2'] ) {
			return json_response([
				'status' => 'error',
				'data'   => [
					'errorText' => 'Проверьте правильность пароля...',
				],
			]);
		}
		
		$user = User::where('email', '=', $json['login'])
					->first();
		
		if( !empty($user->id) ) {
			return json_response([
				'status' => 'error',
				'data'   => [
					'errorText' => 'Пользователь с таким Email адресом уже зарегистрирован',
				],
			]);
		}
		
		$user = User::create([
			'email'    => $json['login'],
			'login'    => $json['name'],
			'password' => password_crypt($json['pwd1']),
		]);
		
		$user->profile()->create([
			'first_name' => $json['name'],
		]);
		
		if( !empty($user->id) && auth()->signin($user->email, $json['pwd1']) ) {
			return json_response([
				'status' => 'success',
				'userId' => auth()->id(),
			]);
		}
		
		return json_response([
			'status' => 'error',
			'data'   => [
				'errorText' => 'Регистрация не удалась...',
			],
		]);
	}
	
	/**
	* Метод авторизации
	*/
	public function login()
	{
		if( !input()->expectsJson() ) {
			abort('Некорректный запрос...');
		}

		if( auth()->check() ) {
			return json_response([
				'status' => 'success',
				'userId' => auth()->id(),
			]);
		}

		$json = input()->json();

		if( empty($json) || empty($json['login']) || empty($json['password']) ) {
			return json_response([
				'status' => 'error',
				'data'   => [
					'errorText' => 'Некорректные данные для обработки...',
				],
			]);
		}
		
		if( auth()->signin($json['login'], $json['password']) ) {
			if( !empty($json['rememberme']) ) {
				$token       = uuid();
				$user        = auth()->user();
				$user->token = $token;
				$user->save();
				
				cookies()->token = $token;
			}
			
			return json_response([
				'status' => 'success',
				'userId' => auth()->id(),
			]);
		}
		
		return json_response([
			'status' => 'error',
			'data'   => [
				'errorText' => 'Логин или пароль указан не правильно...',
			],
		]);
	}
	
	public function logout()
	{
		if( auth()->check() ) {
			auth()->signout();
			cookies()->token = null;
		}
		
		return json_response([
			'status' => 'success',
		]);
	}
	
    public function routes()
    {
        $routes = router()->getAllRoutes();
        $output = [];
        
        foreach($routes as $route) {
            $output[] = 
                '/' . $route['path'] . ' | ' .
                $route['type'] . ' | ' .
                $route['controller'] . '@' . $route['method'] . ' | ' .
                $route['name'];
        }
        
        return json_response($output);
    }
}
