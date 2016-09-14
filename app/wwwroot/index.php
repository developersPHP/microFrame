<?php
error_reporting(1);
define('L_APP_PATH',dirname(__DIR__).'/');
define('L_WORKSPACE_PATH',dirname(L_APP_PATH) . '/');

include_once L_WORKSPACE_PATH . 'lavender/Core.php';
include_once L_WORKSPACE_PATH.'lavender/BaseClass.php';
//microFrame\lavender\Core::register_autoload('Golo', L_WORKSPACE_PATH.'golo/');

$WEB_OPTIONS = require L_APP_PATH . 'conf/web_options.php';

microFrame\lavender\BaseClass::run($WEB_OPTIONS);
