<?php

return [
	'configuration'     => [
		'host'     => env('RABBIT_HOST', '127.0.0.1'),
		'port'     => env('RABBIT_PORT', '5672'),
		'user'     => env('RABBIT_USER'),
		'password' => env('RABBIT_PASSWORD'),
		'vhost'    => env('RABBIT_VHOST'),
	],
	'available_locales' => ['uz', 'ru'],
	
	"middleware" => 'web',
	"path"       => env('RABBIT_PATH', 'rabbitmq'),
	"domain"     => env('RABBIT_DOMAIN'),
];
