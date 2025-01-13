<?php

return [
	'configuration'     => [
		'host'     => 'localhost',
		'port'     => '5672',
		'user'     => '',
		'password' => '',
		'vhost'    => '',
	],
	'available_locales' => ['uz', 'ru'],
	
	"middleware" => 'web',
	"path"       => env('RABBIT_PATH', 'rabbitmq'),
	"domain"     => env('RABBITMQ_DOMAIN'),
];
