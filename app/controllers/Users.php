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
    * View all users
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
    * View data of a specific user by their identifier
    * GET /users/{id}
    * 
    * @param mixed $user_id
    */
    public function show($user_id = null)
    {
        if( empty($user_id) || !is_numeric($user_id) || !($user = User::find($user_id)) ) {
            abort_json("User with identifier {$user_id} not found", 404);
        }
        
        return json_response($user);
    }
    
    /**
    * Create user
    * POST /users
    *
    * @param mixed $user_id
    */
    public function create()
    {
        $input = input()->all();

        // Validate input data
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
                'message' => 'Data validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        try {
            // Begin transaction
            db()->beginTransaction();

            // Create user
            $user = User::create([
                'name'     => $validated['name'],
                'email'    => $validated['email'],
                'password' => password_crypt($validated['password']),
            ]);

            // Collect profile data (only passed fields)
            $profileData   = ['user_id' => $user->id];
            $profileFields = ['first_name', 'middle_name', 'last_name', 'gender', 'birthday'];

            foreach( $profileFields as $field ) {
                if( array_key_exists($field, $validated) ) {
                    $profileData[ $field ] = $validated[ $field ];
                }
            }

            // Create profile
            $user->profile()->create($profileData);

            db()->commit();
            
        } catch( \Exception $e ) {
            db()->rollBack();

            return abort_json([
                'status'  => false,
                'message' => 'Error creating user: ' . $e->getMessage(),
            ], 500);
        }

        // Load the profile and return the result
        $user->load('profile');

        return json_response($user);
    }
    
    /**
    * Update user data
    * PUT /users/{id}
    *
    * @param mixed $user_id
    */
    public function update($user_id = null)
    {
        if( empty($user_id) || !is_numeric($user_id) || !($user = User::find($user_id)) ) {
            abort_json("User with identifier {$user_id} not found", 404);
        }

        $input         = input()->all();
        $profileFields = ['first_name', 'middle_name', 'last_name', 'gender', 'birthday'];

        // Pick only profile fields from the input data
        $profileInput  = [];
        
        foreach( $profileFields as $field ) {
            if( array_key_exists($field, $input) ) {
                $profileInput[ $field ] = $input[ $field ];
            }
        }

        // If there are no fields to update — return the user unchanged
        if( empty($profileInput) ) {
            $user->load('profile');

            return json_response($user);
        }

        // Validate the passed fields
        $rules = [
            'first_name'  => 'minlen:1|maxlen:254',
            'middle_name' => 'minlen:1|maxlen:254',
            'last_name'   => 'minlen:1|maxlen:254',
            'gender'      => 'integer|in:0,1,2',
            'birthday'    => 'date',
        ];

        // Keep only the rules for the passed fields
        $rules = array_intersect_key($rules, $profileInput);

        $validator = validator($profileInput, $rules);

        if( $validator->fails() ) {
            return abort_json([
                'status'  => false,
                'message' => 'Data validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // Update the user profile
        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );

        // Load the profile and return the result
        $user->load('profile');

        return json_response($user);
    }

    /**
    * Delete user
    * DELETE /users/{id}
    *
    * @param mixed $user_id
    */
    public function remove($user_id = null)
    {
        if( empty($user_id) || !is_numeric($user_id) || !($user = User::find($user_id)) ) {
            abort_json("User with identifier {$user_id} not found", 404);
        }

        // Delete user
        $user->delete();

        return json_response([
            'status'  => true,
            'message' => "User with identifier {$user_id} successfully deleted",
        ]);
    }
}
