<?php
/**
* @name      Boson PHP framework
* @author    Tishchenko Alexander (info@alex-tisch.ru)
* @copyright Copyright (c) 2018 All rights reserved
*/

router()->whereNumber('id');
router()->whereAlphaNumeric('query');

router()->get([
	['/', 'Index@index'],
	['/install', 'Install@index'],
    ['/routes', 'Index@routes'],
]);
		
router()->any('/login', 'Index@login', 'index.login')
		->any('/logout', 'Index@logout', 'index.logout')
		->any('/register', 'Index@register', 'index.register');

router()->group(['prefix' => 'users', 'name' => 'users'], function($router) {
    $router->get('/', 'Users@index', 'index');
    $router->get('/{id}', 'Users@show', 'show');
    $router->post('/', 'Users@create', 'create');
    $router->put('/{id}', 'Users@update', 'update');
    $router->delete('/{id}', 'Users@remove', 'remove');
});

router()->group(['prefix' => 'mcp', 'name' => 'mcp'], function($router) {
    $router->post('/', 'Mcp@index', 'index');
    $router->get('/initialize', 'Mcp@initialize', 'initialize');
    $router->get('/tools/list', 'Mcp@tools_list', 'tools.list');
    $router->post('/tools/call', 'Mcp@tools_call', 'tools.call');
});
