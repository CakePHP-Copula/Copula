<?php
/**
 * A [MyApi] API Method Map
 *
 * Refer to the apis plugin for how to build a method map
 * @link https://github.com/ProLoser/CakePHP-Api-Datasources
 */
$config['Apis']['[MyApi]']['hosts'] = array(
	'oauth' => 'github.com/login/oauth', // Main domain+path for OAuth requests
	'rest' => 'api.github.com', // Main domain+path for REST requests
);
// http://developer.github.com/v3/oauth/
$config['Apis']['[MyApi]']['oauth'] = array(
	'version' => '2.0', // 1.0, 2.0 or null
	// These paths are appended to the end of the Host-OAuth value
	'authorize' => 'authorize', // Example URI: https://github.com/login/oauth/authorize
	'request' => 'requestToken', //client_id={$this->config['login']}&redirect_uri
	'access' => 'access_token', 
	'login' => 'authenticate', // Like authorize, just auto-redirects
	'logout' => 'invalidateToken', 
);
$config['Apis']['[MyApi]']['read'] = array(
	// The 'fields' param should be set to this (the name of the resource)
	'repos' => array(
		// This path is appended to the end of the Host-REST value
		'repos/:user/:repo' => array(
			// required conditions (optional, can be an empty array)
			'user',
			'repo',
		),
		// api url
		'users/:user/repos' => array(			
			// required conditions (optional, can be an empty array)
			'user',
			// optional conditions (optional key)
			'optional' => array(
				'type' // all, owner, member. Default: public
			),
		),
	),
	'users' => array(
		'user/:user' => array(
			'user',
		),
		'user' => array(),
	),
	...
);
// Refer to READ block
$config['Apis']['[MyApi]']['create'] = array();
// Refer to READ block
$config['Apis']['[MyApi]']['update'] = array();
// Refer to READ block
$config['Apis']['[MyApi]']['delete'] = array();