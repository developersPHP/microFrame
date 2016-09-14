<?php

define('L_URL_BASE', '/');
define('L_SITE_NAME', 'LAVENDER DEMO');
define('L_SITE_DOMAIN', 'lvd-demo.com');

//pass phrase
define('PASS_PHRASE', 'cnlaunch');
return array(
    'root_domain' => L_SITE_DOMAIN,
    'domain' => L_SITE_DOMAIN,
    'uri_path' => '/',
    'session_timeout' => 60 * 60, //20 min
    'session_dao' => '\Lavender\Dao\SessionKvTable',
    'action_modules' => array(
        'index',
        'download',
        'repair_service',
        'diagnostics_service',
        'clouds',
        'clouds_service',
        'zhongka_service',
        'test'
    ),
);