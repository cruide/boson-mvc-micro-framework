<?php namespace App\Controllers;

use App\Models\User;

class Users
{
    public function _before()
    {
        if( !is_auth() ) {
            abort_json(['message' => 'Unauthorized'], 401);
        }
    }

    /**
    * Просотр всех пользователей
    * GET /users
    */
    public function index()
    {
        $input = input()->all();
        $users = User::with('profile');
        
        if( !empty($input['name']) ) {
            $users = $users->whereLike('name', $input['name']);
        }

        if( !empty($input['email']) ) {
            $users = $users->whereLike('email', $input['email']);
        }
        
        return json_response(
            $users->get()
        );
    }
    
    /**
    * Просмотр данных кнкректного пользователя по его идентификатору
    * GET /users/{id}
    * 
    * @param mixed $user_id
    */
    public function show($user_id = null)
    {
        if( empty($user_id) || !is_numeric($user_id) || !($user = User::find($user_id)) ) {
            abort_json("Пользователь с идентификатором {$user_id} не найден", 404);
        }
        
        return json_response($user);
    }
    
    /**
    * Создание пользователя
    * POST /users
    *
    * @param mixed $user_id
    */
    public function create()
    {
        $input = input()->all();

        // Валидация входных данных
        $validator = validator($input, [
            'name'        => 'required|minlen:5|maxlen:254',
            'email'       => 'required|email|maxlen:255',
            'password'    => 'required|minlen:6|maxlen:64',
            'first_name'  => 'minlen:1|maxlen:254',
            'middle_name' => 'minlen:1|maxlen:254',
            'last_name'   => 'minlen:1|maxlen:254',
            'gender'      => 'integer|in:0,1,2',
            'birthday'    => 'date',
        ]);

        if( $validator->fails() ) {
            return abort_json([
                'status'  => false,
                'message' => 'Ошибка валидации данных',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        try {
            // Открываем транзакцию
            db()->beginTransaction();

            // Создаём пользователя
            $user = User::create([
                'name'     => $validated['name'],
                'email'    => $validated['email'],
                'password' => password_crypt($validated['password']),
            ]);

            // Собираем данные профиля (только переданные поля)
            $profileData   = ['user_id' => $user->id];
            $profileFields = ['first_name', 'middle_name', 'last_name', 'gender', 'birthday'];

            foreach( $profileFields as $field ) {
                if( array_key_exists($field, $validated) ) {
                    $profileData[ $field ] = $validated[ $field ];
                }
            }

            // Создаём профиль
            $user->profile()->create($profileData);

            db()->commit();
            
        } catch( \Exception $e ) {
            db()->rollBack();

            return abort_json([
                'status'  => false,
                'message' => 'Ошибка при создании пользователя: ' . $e->getMessage(),
            ], 500);
        }

        // Загружаем профиль и возвращаем результат
        $user->load('profile');

        return json_response($user);
    }
    
    /**
    * Обновление данных пользователя
    * PUT /users/{id}
    *
    * @param mixed $user_id
    */
    public function update($user_id = null)
    {
        if( empty($user_id) || !is_numeric($user_id) || !($user = User::find($user_id)) ) {
            abort_json("Пользователь с идентификатором {$user_id} не найден", 404);
        }

        $input         = input()->all();
        $profileFields = ['first_name', 'middle_name', 'last_name', 'gender', 'birthday'];

        // Отбираем только поля профиля из входных данных
        $profileInput  = [];
        
        foreach( $profileFields as $field ) {
            if( array_key_exists($field, $input) ) {
                $profileInput[ $field ] = $input[ $field ];
            }
        }

        // Если нет ни одного поля для обновления — возвращаем пользователя без изменений
        if( empty($profileInput) ) {
            $user->load('profile');

            return json_response($user);
        }

        // Валидация переданных полей
        $rules = [
            'first_name'  => 'minlen:1|maxlen:254',
            'middle_name' => 'minlen:1|maxlen:254',
            'last_name'   => 'minlen:1|maxlen:254',
            'gender'      => 'integer|in:0,1,2',
            'birthday'    => 'date',
        ];

        // Оставляем только правила для переданных полей
        $rules = array_intersect_key($rules, $profileInput);

        $validator = validator($profileInput, $rules);

        if( $validator->fails() ) {
            return abort_json([
                'status'  => false,
                'message' => 'Ошибка валидации данных',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // Обновляем профиль пользователя
        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );

        // Загружаем профиль и возвращаем результат
        $user->load('profile');

        return json_response($user);
    }

    /**
    * Удаления пользователя
    * DELETE /users/{id}
    *
    * @param mixed $user_id
    */
    public function remove($user_id = null)
    {
        if( empty($user_id) || !is_numeric($user_id) || !($user = User::find($user_id)) ) {
            abort_json("Пользователь с идентификатором {$user_id} не найден", 404);
        }

        // Удаляем пользователя
        $user->delete();

        return json_response([
            'status'  => true,
            'message' => "Пользователь с идентификатором {$user_id} успешно удалён",
        ]);
    }
}
