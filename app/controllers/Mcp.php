<?php namespace App\Controllers;

use App\Models\User;
use App\Models\Profile;

class Mcp
{
    public function _before()
    {
        if( !is_auth() ) {
            abort_json(['message' => 'Unauthorized'], 401);
        }
    }

    /**
     * POST /mcp
     */
    public function index()
    {
        $request = input()->json() ?: [];

        return json_response([
            'jsonrpc' => '2.0',
            'id'      => !empty($request['id']) ? $request['id'] : 0,
            'result'  => [
                'protocolVersion' => '2024-11-05',
                'capabilities'    => [
                    'tools'     => new \stdClass(),
                    'resources' => new \stdClass(),
                ],
                'serverInfo'      => [
                    'name'    => 'Boson MCP server',
                    'version' => '1.0.0',
                ],
            ],
        ]);
    }

    /**
     * GET /mcp
     * GET /mcp/initialize
     */
    public function initialize()
    {
        $request = input()->json() ?: [];

        return json_response([
            'jsonrpc' => '2.0',
            'id'      => !empty($request['id']) ? $request['id'] : 0,
            'result'  => [
                'protocolVersion' => '2024-11-05',
                'capabilities'    => [
                    'tools'     => new \stdClass(),
                    'resources' => new \stdClass(),
                ],
                'serverInfo'      => [
                    'name'    => 'Boson MCP server',
                    'version' => '1.0.0',
                ],
            ],
        ]);
    }

    /**
     * GET /mcp/tools/list
     */
    public function tools_list()
    {
        $request = input()->json() ?: [];

        return json_response([
            'jsonrpc' => '2.0',
            'id'      => !empty($request['id']) ? $request['id'] : 0,
            'result'  => [
                'tools' => [
                    [
                        'name'        => 'list_user',
                        'description' => 'Получение списка пользователей с возможностью фильтрации по имени и email',
                        'inputSchema' => [
                            'type'       => 'object',
                            'properties' => [
                                'name'  => ['type' => 'string', 'minLength' => 1, 'maxLength' => 128, 'description' => 'Фильтр по имени пользователя (поиск по подстроке)'],
                                'email' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 254, 'description' => 'Фильтр по email (поиск по подстроке)'],
                            ],
                            'required' => [],
                        ],
                    ],
                    [
                        'name'        => 'show_user',
                        'description' => 'Получение полных данных пользователя по его ID, включая профиль',
                        'inputSchema' => [
                            'type'       => 'object',
                            'properties' => [
                                'id' => ['type' => 'integer', 'description' => 'User ID'],
                            ],
                            'required' => ['id'],
                        ],
                    ],
                    [
                        'name'        => 'create_user',
                        'description' => 'Создание нового пользователя с профилем. Обязательные поля: name, email, password',
                        'inputSchema' => [
                            'type'       => 'object',
                            'properties' => [
                                'name'     => ['type' => 'string', 'minLength' => 5, 'maxLength' => 128, 'pattern' => '^[a-zA-Z0-9_]+$', 'description' => 'Уникальное имя пользователя (логин)'],
                                'email'    => ['type' => 'string', 'minLength' => 7, 'maxLength' => 254, 'format' => 'email', 'description' => 'Email адрес пользователя'],
                                'password' => ['type' => 'string', 'minLength' => 6, 'maxLength' => 64, 'description' => 'Пароль пользователя'],
                                'profile'  => [
                                    'type'       => 'object',
                                    'description' => 'Данные профиля пользователя',
                                    'properties' => [
                                        'first_name'  => ['type' => 'string', 'minLength' => 1, 'maxLength' => 128, 'description' => 'Имя'],
                                        'middle_name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 128, 'description' => 'Отчество'],
                                        'last_name'   => ['type' => 'string', 'minLength' => 1, 'maxLength' => 128, 'description' => 'Фамилия'],
                                        'birthday'    => ['type' => 'string', 'format' => 'date', 'description' => 'Дата рождения в формате YYYY-MM-DD'],
                                        'gender'      => ['type' => 'integer', 'minimum' => 0, 'maximum' => 2, 'description' => '0 — Мужской, 1 — Женский, 2 — Другой'],
                                    ],
                                    'required' => [],
                                ],
                            ],
                            'required' => ['name', 'email', 'password'],
                        ],
                    ],
                    [
                        'name'        => 'update_user',
                        'description' => 'Обновление данных профиля пользователя по его ID',
                        'inputSchema' => [
                            'type'       => 'object',
                            'properties' => [
                                'id'          => ['type' => 'integer', 'description' => 'User ID'],
                                'first_name'  => ['type' => 'string', 'minLength' => 1, 'maxLength' => 128, 'description' => 'Имя'],
                                'middle_name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 128, 'description' => 'Отчество'],
                                'last_name'   => ['type' => 'string', 'minLength' => 1, 'maxLength' => 128, 'description' => 'Фамилия'],
                                'birthday'    => ['type' => 'string', 'format' => 'date', 'description' => 'Дата рождения в формате YYYY-MM-DD'],
                                'gender'      => ['type' => 'integer', 'minimum' => 0, 'maximum' => 2, 'description' => '0 — Мужской, 1 — Женский, 2 — Другой'],
                            ],
                            'required' => ['id'],
                        ],
                    ],
                    [
                        'name'        => 'delete_user',
                        'description' => 'Удаление пользователя по его ID (вместе с профилем)',
                        'inputSchema' => [
                            'type'       => 'object',
                            'properties' => [
                                'id' => ['type' => 'integer', 'description' => 'User ID'],
                            ],
                            'required' => ['id'],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * POST /mcp/tools/call
     */
    public function tools_call()
    {
        // Для POST с JSON-телом используем input()->json(),
        // т.к. input()->all() не парсит JSON для POST-запросов
        $request   = input()->json() ?: input()->all();
        $arguments = $request['arguments'] ?? $request['params']['arguments'] ?? [];

        if (empty($request['name'])) {
            return json_response([
                'jsonrpc' => '2.0',
                'id'      => $request['id'] ?? 0,
                'error'   => [
                    'code'    => -32602,
                    'message' => 'Invalid params: tool name is required',
                ],
            ], 400);
        }

        try {
            switch ($request['name']) {
                case 'list_user':
                    $data = $this->listUser($arguments);
                    break;

                case 'show_user':
                    $data = $this->showUser($arguments);
                    break;

                case 'create_user':
                    $data = $this->createUser($arguments);
                    break;

                case 'update_user':
                    $data = $this->updateUser($arguments);
                    break;

                case 'delete_user':
                    $data = $this->deleteUser($arguments);
                    break;

                default:
                    return json_response([
                        'jsonrpc' => '2.0',
                        'id'      => $request['id'] ?? 0,
                        'error'   => [
                            'code'    => -32601,
                            'message' => "Tool not found: {$request['name']}",
                        ],
                    ], 404);
            }

            return json_response([
                'jsonrpc' => '2.0',
                'id'      => $request['id'] ?? 0,
                'result'  => [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                        ],
                    ],
                ],
            ]);

        } catch (\InvalidArgumentException $e) {
            return json_response([
                'jsonrpc' => '2.0',
                'id'      => $request['id'] ?? 0,
                'error'   => [
                    'code'    => $e->getCode() ?: -32602,
                    'message' => $e->getMessage(),
                ],
            ], 400);
        } catch (\Exception $e) {
            return json_response([
                'jsonrpc' => '2.0',
                'id'      => $request['id'] ?? 0,
                'error'   => [
                    'code'    => -32603,
                    'message' => 'Internal error: ' . $e->getMessage(),
                ],
            ], 500);
        }
    }

    // ========================================================================
    //  Методы-обработчики инструментов
    // ========================================================================

    /**
     * Получение списка пользователей с фильтрацией
     */
    protected function listUser(array $params): array
    {
        $users = User::with('profile');

        if (!empty($params['name'])) {
            $users = $users->where('name', 'like', '%' . $params['name'] . '%');
        }

        if (!empty($params['email'])) {
            $users = $users->where('email', 'like', '%' . $params['email'] . '%');
        }

        return $users->get()->toArray();
    }

    /**
     * Получение данных конкретного пользователя
     */
    protected function showUser(array $params): array
    {
        if (empty($params['id']) || !is_numeric($params['id'])) {
            throw new \InvalidArgumentException('Параметр id обязателен и должен быть числом', -32602);
        }

        $user = User::with('profile')->find($params['id']);

        if (!$user) {
            throw new \InvalidArgumentException("Пользователь с идентификатором {$params['id']} не найден", -32602);
        }

        return $user->toArray();
    }

    /**
     * Создание пользователя с профилем
     */
    protected function createUser(array $params): array
    {
        // Валидация
        $validator = validator($params, [
            'name'     => 'required|minlen:5|maxlen:128',
            'email'    => 'required|email|maxlen:254',
            'password' => 'required|minlen:6|maxlen:64',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            throw new \InvalidArgumentException(
                'Ошибка валидации: ' . implode('; ', array_map(fn($field, $msgs) => "$field: " . implode(', ', $msgs), array_keys($errors), $errors)),
                -32602
            );
        }

        $validated = $validator->validated();

        // Проверка уникальности email
        if (User::where('email', $validated['email'])->exists()) {
            throw new \InvalidArgumentException("Пользователь с email {$validated['email']} уже существует", -32602);
        }

        // Проверка уникальности name
        if (User::where('name', $validated['name'])->exists()) {
            throw new \InvalidArgumentException("Пользователь с именем {$validated['name']} уже существует", -32602);
        }

        try {
            db()->beginTransaction();

            $user = User::create([
                'name'     => $validated['name'],
                'email'    => $validated['email'],
                'password' => password_crypt($validated['password']),
            ]);

            // Собираем данные профиля (если переданы)
            $profileData   = ['user_id' => $user->id];
            $profileFields = ['first_name', 'middle_name', 'last_name', 'gender', 'birthday'];
            $profileInput  = $params['profile'] ?? [];

            foreach ($profileFields as $field) {
                if (array_key_exists($field, $profileInput)) {
                    $profileData[$field] = $profileInput[$field];
                }
            }

            // Валидация профиля, если есть что валидировать
            if (count($profileData) > 1) {
                $profileRules = [
                    'first_name'  => 'minlen:1|maxlen:128',
                    'middle_name' => 'minlen:1|maxlen:128',
                    'last_name'   => 'minlen:1|maxlen:128',
                    'gender'      => 'integer|in:0,1,2',
                    'birthday'    => 'date',
                ];
                $profileRules = array_intersect_key($profileRules, $profileData);

                $profileValidator = validator($profileData, $profileRules);
                if ($profileValidator->fails()) {
                    db()->rollBack();
                    $errors = $profileValidator->errors();
                    throw new \InvalidArgumentException(
                        'Ошибка валидации профиля: ' . implode('; ', array_map(fn($field, $msgs) => "$field: " . implode(', ', $msgs), array_keys($errors), $errors)),
                        -32602
                    );
                }
            }

            $user->profile()->create($profileData);

            db()->commit();
        } catch (\InvalidArgumentException $e) {
            throw $e; // Пробрасываем наши исключения валидации
        } catch (\Exception $e) {
            db()->rollBack();
            throw new \Exception('Ошибка при создании пользователя: ' . $e->getMessage(), -32603);
        }

        $user->load('profile');

        return $user->toArray();
    }

    /**
     * Обновление профиля пользователя
     */
    protected function updateUser(array $params): array
    {
        if (empty($params['id']) || !is_numeric($params['id'])) {
            throw new \InvalidArgumentException('Параметр id обязателен и должен быть числом', -32602);
        }

        $user = User::find($params['id']);

        if (!$user) {
            throw new \InvalidArgumentException("Пользователь с идентификатором {$params['id']} не найден", -32602);
        }

        // Отбираем поля профиля
        $profileFields = ['first_name', 'middle_name', 'last_name', 'gender', 'birthday'];
        $profileInput  = [];

        foreach ($profileFields as $field) {
            if (array_key_exists($field, $params)) {
                $profileInput[$field] = $params[$field];
            }
        }

        // Если нечего обновлять — возвращаем текущие данные
        if (empty($profileInput)) {
            $user->load('profile');

            return $user->toArray();
        }

        // Валидация
        $rules = [
            'first_name'  => 'minlen:1|maxlen:128',
            'middle_name' => 'minlen:1|maxlen:128',
            'last_name'   => 'minlen:1|maxlen:128',
            'gender'      => 'integer|in:0,1,2',
            'birthday'    => 'date',
        ];
        $rules = array_intersect_key($rules, $profileInput);

        $validator = validator($profileInput, $rules);
        if ($validator->fails()) {
            $errors = $validator->errors();
            throw new \InvalidArgumentException(
                'Ошибка валидации: ' . implode('; ', array_map(fn($field, $msgs) => "$field: " . implode(', ', $msgs), array_keys($errors), $errors)),
                -32602
            );
        }

        $validated = $validator->validated();

        // Обновляем/создаём профиль
        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );

        $user->load('profile');

        return $user->toArray();
    }

    /**
     * Удаление пользователя
     */
    protected function deleteUser(array $params): array
    {
        if (empty($params['id']) || !is_numeric($params['id'])) {
            throw new \InvalidArgumentException('Параметр id обязателен и должен быть числом', -32602);
        }

        $user = User::find($params['id']);

        if (!$user) {
            throw new \InvalidArgumentException("Пользователь с идентификатором {$params['id']} не найден", -32602);
        }

        $user->delete();

        return [
            'status'  => true,
            'message' => "Пользователь с идентификатором {$params['id']} успешно удалён",
        ];
    }
}
