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
     * Shared server-info payload for the initial handshake.
     */
    protected function serverInfo(): array
    {
        return [
            'protocolVersion' => '2024-11-05',
            'capabilities'    => [
                'tools'     => new \stdClass(),
                'resources' => new \stdClass(),
            ],
            'serverInfo'      => [
                'name'    => 'Boson MCP server',
                'version' => '1.0.0',
            ],
        ];
    }

    /**
     * Build a JSON-RPC success response.
     */
    protected function jsonrpcResult(array $request, array $result): string
    {
        return json_response([
            'jsonrpc' => '2.0',
            'id'      => !empty($request['id']) ? $request['id'] : 0,
            'result'  => $result,
        ]);
    }

    /**
     * Build a JSON-RPC error response.
     */
    protected function jsonrpcError(array $request, int $code, string $message, int $status = 200): string
    {
        return json_response([
            'jsonrpc' => '2.0',
            'id'      => !empty($request['id']) ? $request['id'] : 0,
            'error'   => [
                'code'    => $code,
                'message' => $message,
            ],
        ], $status);
    }

    /**
     * POST /mcp
     */
    public function index()
    {
        $request = input()->json() ?: [];

        return $this->jsonrpcResult($request, $this->serverInfo());
    }

    /**
     * GET /mcp
     * GET /mcp/initialize
     */
    public function initialize()
    {
        $request = input()->json() ?: [];

        return $this->jsonrpcResult($request, $this->serverInfo());
    }

    /**
     * GET /mcp/tools/list
     */
    public function tools_list()
    {
        $request = input()->json() ?: [];

        return $this->jsonrpcResult($request, [
            'tools' => [
                    [
                        'name'        => 'list_user',
                        'description' => 'Get a list of users with filtering by name and email',
                        'inputSchema' => [
                            'type'       => 'object',
                            'properties' => [
                                'name'  => ['type' => 'string', 'minLength' => 1, 'maxLength' => 128, 'description' => 'Filter by user name (substring search)'],
                                'email' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 254, 'description' => 'Filter by email (substring search)'],
                            ],
                            'required' => [],
                        ],
                    ],
                    [
                        'name'        => 'show_user',
                        'description' => 'Get full user data by their ID, including the profile',
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
                        'description' => 'Create a new user with a profile. Required fields: name, email, password',
                        'inputSchema' => [
                            'type'       => 'object',
                            'properties' => [
                                'name'     => ['type' => 'string', 'minLength' => 5, 'maxLength' => 128, 'pattern' => '^[a-zA-Z0-9_]+$', 'description' => 'Unique user name (login)'],
                                'email'    => ['type' => 'string', 'minLength' => 7, 'maxLength' => 254, 'format' => 'email', 'description' => 'User email address'],
                                'password' => ['type' => 'string', 'minLength' => 6, 'maxLength' => 64, 'description' => 'User password'],
                                'profile'  => [
                                    'type'       => 'object',
                                    'description' => 'User profile data',
                                    'properties' => [
                                        'first_name'  => ['type' => 'string', 'minLength' => 1, 'maxLength' => 128, 'description' => 'First name'],
                                        'middle_name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 128, 'description' => 'Middle name'],
                                        'last_name'   => ['type' => 'string', 'minLength' => 1, 'maxLength' => 128, 'description' => 'Last name'],
                                        'birthday'    => ['type' => 'string', 'format' => 'date', 'description' => 'Date of birth in YYYY-MM-DD format'],
                                        'gender'      => ['type' => 'integer', 'minimum' => 0, 'maximum' => 2, 'description' => '0 — Male, 1 — Female, 2 — Other'],
                                    ],
                                    'required' => [],
                                ],
                            ],
                            'required' => ['name', 'email', 'password'],
                        ],
                    ],
                    [
                        'name'        => 'update_user',
                        'description' => 'Update user profile data by their ID',
                        'inputSchema' => [
                            'type'       => 'object',
                            'properties' => [
                                'id'          => ['type' => 'integer', 'description' => 'User ID'],
                                'first_name'  => ['type' => 'string', 'minLength' => 1, 'maxLength' => 128, 'description' => 'First name'],
                                'middle_name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 128, 'description' => 'Middle name'],
                                'last_name'   => ['type' => 'string', 'minLength' => 1, 'maxLength' => 128, 'description' => 'Last name'],
                                'birthday'    => ['type' => 'string', 'format' => 'date', 'description' => 'Date of birth in YYYY-MM-DD format'],
                                'gender'      => ['type' => 'integer', 'minimum' => 0, 'maximum' => 2, 'description' => '0 — Male, 1 — Female, 2 — Other'],
                            ],
                            'required' => ['id'],
                        ],
                    ],
                    [
                        'name'        => 'delete_user',
                        'description' => 'Delete a user by their ID (along with the profile)',
                        'inputSchema' => [
                            'type'       => 'object',
                            'properties' => [
                                'id' => ['type' => 'integer', 'description' => 'User ID'],
                            ],
                            'required' => ['id'],
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
        // For POST with a JSON body, use input()->json(),
        // since input()->all() does not parse JSON for POST requests
        $request   = input()->json() ?: input()->all();
        $arguments = $request['arguments'] ?? $request['params']['arguments'] ?? [];

        if (empty($request['name'])) {
            return $this->jsonrpcError($request, -32602, 'Invalid params: tool name is required', 400);
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
                    return $this->jsonrpcError($request, -32601, "Tool not found: {$request['name']}", 404);
            }

            return $this->jsonrpcResult($request, [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                    ],
                ],
            ]);

        } catch (\InvalidArgumentException $e) {
            return $this->jsonrpcError($request, $e->getCode() ?: -32602, $e->getMessage(), 400);
        } catch (\Exception $e) {
            error_log('MCP internal error: ' . (string)$e);
            return $this->jsonrpcError($request, -32603, 'Internal error', 500);
        }
    }

    // ========================================================================
    //  Tool handler methods
    // ========================================================================

    /**
     * Get a list of users with filtering
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
     * Get data of a specific user
     */
    protected function showUser(array $params): array
    {
        if (empty($params['id']) || !is_numeric($params['id'])) {
            throw new \InvalidArgumentException('The id parameter is required and must be a number', -32602);
        }

        $user = User::with('profile')->find($params['id']);

        if (!$user) {
            throw new \InvalidArgumentException("User with identifier {$params['id']} not found", -32602);
        }

        return $user->toArray();
    }

    /**
     * Create a user with a profile
     */
    protected function createUser(array $params): array
    {
        // Validation
        $validator = validator($params, [
            'name'     => 'required|minlen:5|maxlen:128',
            'email'    => 'required|email|maxlen:254',
            'password' => 'required|minlen:6|maxlen:64',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            throw new \InvalidArgumentException(
                'Validation error: ' . implode('; ', array_map(fn($field, $msgs) => "$field: " . implode(', ', $msgs), array_keys($errors), $errors)),
                -32602
            );
        }

        $validated = $validator->validated();

        // Check email uniqueness
        if (User::where('email', $validated['email'])->exists()) {
            throw new \InvalidArgumentException("User with email {$validated['email']} already exists", -32602);
        }

        // Check name uniqueness
        if (User::where('name', $validated['name'])->exists()) {
            throw new \InvalidArgumentException("User with name {$validated['name']} already exists", -32602);
        }

        try {
            db()->beginTransaction();

            $user = User::create([
                'name'     => $validated['name'],
                'email'    => $validated['email'],
                'password' => password_crypt($validated['password']),
            ]);

            // Collect profile data (if passed)
            $profileData   = ['user_id' => $user->id];
            $profileFields = ['first_name', 'middle_name', 'last_name', 'gender', 'birthday'];
            $profileInput  = $params['profile'] ?? [];

            foreach ($profileFields as $field) {
                if (array_key_exists($field, $profileInput)) {
                    $profileData[$field] = $profileInput[$field];
                }
            }

            // Validate the profile if there is anything to validate
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
                        'Profile validation error: ' . implode('; ', array_map(fn($field, $msgs) => "$field: " . implode(', ', $msgs), array_keys($errors), $errors)),
                        -32602
                    );
                }
            }

            $user->profile()->create($profileData);

            db()->commit();
        } catch (\InvalidArgumentException $e) {
            throw $e; // Rethrow our validation exceptions
        } catch (\Exception $e) {
            db()->rollBack();
            error_log('MCP create_user error: ' . (string)$e);
            throw new \Exception('Error creating user', -32603);
        }

        $user->load('profile');

        return $user->toArray();
    }

    /**
     * Update user profile
     */
    protected function updateUser(array $params): array
    {
        if (empty($params['id']) || !is_numeric($params['id'])) {
            throw new \InvalidArgumentException('The id parameter is required and must be a number', -32602);
        }

        $user = User::find($params['id']);

        if (!$user) {
            throw new \InvalidArgumentException("User with identifier {$params['id']} not found", -32602);
        }

        // Pick profile fields
        $profileFields = ['first_name', 'middle_name', 'last_name', 'gender', 'birthday'];
        $profileInput  = [];

        foreach ($profileFields as $field) {
            if (array_key_exists($field, $params)) {
                $profileInput[$field] = $params[$field];
            }
        }

        // If there is nothing to update — return the current data
        if (empty($profileInput)) {
            $user->load('profile');

            return $user->toArray();
        }

        // Validation
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
                'Validation error: ' . implode('; ', array_map(fn($field, $msgs) => "$field: " . implode(', ', $msgs), array_keys($errors), $errors)),
                -32602
            );
        }

        $validated = $validator->validated();

        // Update or create the profile
        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );

        $user->load('profile');

        return $user->toArray();
    }

    /**
     * Delete user
     */
    protected function deleteUser(array $params): array
    {
        if (empty($params['id']) || !is_numeric($params['id'])) {
            throw new \InvalidArgumentException('The id parameter is required and must be a number', -32602);
        }

        $user = User::find($params['id']);

        if (!$user) {
            throw new \InvalidArgumentException("User with identifier {$params['id']} not found", -32602);
        }

        $user->delete();

        return [
            'status'  => true,
            'message' => "User with identifier {$params['id']} successfully deleted",
        ];
    }
}
