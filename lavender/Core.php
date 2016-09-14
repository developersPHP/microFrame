<?php
namespace microFrame\lavender;

/******************************environment configurate***********************************
 * the environment configurate is very important,it can help you distinguish some different*
 * environment                                                                             *
 *                                                                                         *
 *****************************************************************************************/

//you can get some configuration from PHP.ini configurateion file
$env = get_cfg_var('golo_env');

if (!defined('L_ENV') ) {
	define('L_ENV', $env ? $env : 'work'); //develop,test,work
}
//foreign or inland
$idc = get_cfg_var('golo_idc');
if ($idc && !defined('L_IDC')) {
	define('L_IDC', $idc);
}

//init
Core::init();

final class Core
{
	/**
	 * server env
	 * @const string
	 */
	const ENV_DEVELOP = 'develop';
	const ENV_TEST = 'test';
	const ENV_WORK = 'work';

	private static $namespace_map = array();
	private static $config_cache = array();
	private static $db_instances = array();
	private static $language_cache;

	/**
	 * init Lavender
	 * running on load this file
	 *
	 * @return void
	 */
	public static function init()
	{
		if (!defined('L_WORKSPACE_PATH')) {
			throw new \Exception('L_WORKSPACE_PATH undefined,please define it in init.php', Errno::CONST_UNDEFINED);
		}

		if (!defined('L_APP_PATH')) {
			throw new \Exception('L_APP_PATH undefined,please define it in init.php', Errno::CONST_UNDEFINED);
		}

		if (!defined('L_ENV')) {
			throw new \Exception('L_ENV undefined,please define it in init.php', Errno::CONST_UNDEFINED);
		}

		//auto define consts
		define('L_EXT_PATH', L_WORKSPACE_PATH . 'ext/');
		define('L_APP_NAME', basename(L_APP_PATH) );

		//register autoload
		self::$namespace_map['Lavender'] = __DIR__ . '/'; //framework
		self::$namespace_map['App'] = L_APP_PATH; //app


        //this function can guide all class object to autoload function
		spl_autoload_register('\microFrame\lavender\Core::autoload');
	}

	/**
	 * autoload register
	 * warring: can not cover the registered
	 *
	 * @param string $namespace 	root namespace
	 * @param string $path 			the root namespace path
	 *
	 * @return void
	 */
	public static function register_autoload($namespace, $path)
	{
		if (empty(self::$namespace_map[$namespace])) {
			self::$namespace_map[$namespace] = $path;
			return true;
		}

		return false;
	}

	/**
	 * Lavender autoload function
	 * call this function on not found the class
	 *
	 * @param string $class_name
	 *
	 * @return void
	 */
	public static function autoload($class_name)
	{
		$tmp = explode('\\', $class_name);
		if (isset(self::$namespace_map[$tmp[0]])) {
			$space = array_shift($tmp);

			//convert class name to file name
			$file = implode('/', $tmp);

			//include class file
			include self::$namespace_map[$space] . $file . '.php';
		}
	}

	/**
	 * get all in type
	 *
	 * @param string $type
	 *
	 * @return mixed 	return the config,and false on error
	 */
	public static function get_config($type, $key)
	{
		//load
		$cache_key = $type;
		if (empty(self::$config_cache[$cache_key]) ) {
			if (strpos('/', $type) !== false || strpos('\\', $type) !== false) {
				throw new Exception("config '{$type}' is invalid", Errno::CONFIG_TYPE_INVALID);
			}

			//load from file
			if (defined('L_IDC') && L_IDC && L_ENV != 'develop'){
				$file = L_APP_PATH . 'conf/' . L_IDC . '.' . L_ENV . '.' . $type . '.php';
			}
			else {
				$file = L_APP_PATH . 'conf/' . L_ENV . '.' . $type . '.php';
			}
			self::$config_cache[$cache_key] = include $file;
			if (self::$config_cache[$cache_key] === false)	{
				throw new Exception("config {$type} not exists,file path:{$file}", Errno::CONFIG_TYPE_INVALID);
			}
		}

		//get all in type
		if (is_null($key)) {
			return self::$config_cache[$cache_key];
		}

		//get by key
		if (isset(self::$config_cache[$cache_key][$key])) {
			return self::$config_cache[$cache_key][$key];
		}

		//key not found
		throw new Exception("config key not found,type:{$type},key:{$key}", Errno::CONFIG_ITEM_INVALID);
	}

	/**
	 * get database instance by driver & config name
	 *
	 * @param string $driver
	 * @param string $name
	 * @param string $index
	 *
	 * @return Lavender\Db\Interface
	 */
	public static function get_database($driver, $name, $index = null)
	{
		$conf = self::get_config('db', $name);

		//if distributed
		if ($index !== null) {
			$conf['database'] .= $index;
		}

		$cache_key = "$driver|$name|$index";

		//check process cache
		if (isset(self::$db_instances[$cache_key])) {
			return self::$db_instances[$cache_key];
		}

		//create instance
		switch ($driver) {
			case 'mysql':
				$instance = new Db\Mysql($conf['host'], $conf['user'], $conf['password'], $conf['database'], $conf['port'], $conf['charset']);
				break;
			case 'mongodb':
				$instance = new Db\Mongo($conf['host'], $conf['database'], $conf['port'], $conf['options']);
				break;
			default:
				throw new Exception("database driver invalid,driver:{$driver}", Errno::PARAM_INVALID);
		}

		//return & cache to process
		return self::$db_instances[$cache_key] = $instance;
	}

	/**
	 * get language text by code
	 *
	 * @param int $code
	 *
	 * @return string
	 */
	public static function get_lang_text($code,$lan = null)
	{
		$lang = self::get_config('const', 'lang');
		//多语言提示语BUG修复
		if(!empty($lan)){
			$lang = $lan;
		}else{
			if (defined('L_IDC') && L_IDC=='us') {
				$lang = 'en';
			} else {
				$lang = 'zh';
			}
		}
        //包含语言包文件进来
		if (empty(self::$language_cache)) {
			//load from file
			$file = L_APP_PATH . 'lang/' . $lang . '.php';
			self::$language_cache = include $file;
		}

		if (!isset(self::$language_cache[$code])) {
			trigger_error("language item \"{$code}\" undefined", E_USER_WARNING);
			return null;
		}
		return self::$language_cache[$code];
	}
	/**
	 * get language text by key
	 *
	 * @param String $key
	 *
	 * @return string
	 */
	public static function get_text($key, $lanCode = null, $arguments = array())
	{
		if($lanCode == null){
			$lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'],0,2);
		}else{
			$lang = $lanCode;
		}

		if ($lang == null) {			
			if (defined('L_IDC') && L_IDC=='us') {
				$lang = 'en';
			} else {
				$lang = 'zh';
			}
		}
		
		if (empty(self::$language_cache[$lang])) {
			self::$language_cache[$lang] = include L_APP_PATH . "lang/$lang.php";
		}

		if (!isset(self::$language_cache[$lang][$key])) {
			// trigger_error("language item \"{$key}\" undefined", E_USER_WARNING);
			// return null;
		}
		$val = self::$language_cache[$lang][$key];
		if(empty($val)){
			if (defined('L_IDC') && L_IDC=='us') {
				$lang = 'en';
				$val = self::$language_cache[$lang][$key];
			} else {
				$lang = 'zh';
				$val = self::$language_cache[$lang][$key];
			}
		}
		$str_ary = explode('{?}', $val);
		if(count($arguments)>0){
			$newstr = "";
			for($i = 0; $i <count($str_ary); $i++)
			{
				if ($i <count($str_ary ) -1)
				{
					if ($newstr  != "" )
						$newstr =$newstr . $arguments[$i-1].$str_ary[$i];
					else{
						$newstr=$newstr . $str_ary[$i];
					}
				}else{
					$newstr = $newstr .$arguments[$i-1].$str_ary[$i];
				}
			}
			return trim($newstr);
		}else{
			return $val;
		}
	}

	public static function log($dir_name, $content, $file_name = '')
	{
		$old_mask = umask(0);

		//check & make dir
		$dir = L_WORKSPACE_PATH . 'log/' . $dir_name . '/';
		if (!is_dir($dir)) {
			mkdir($dir, 0777, true);
		}

		//write file
		$file_name = empty($file_name) ? date('Ymd') : $file_name;
		$file = $dir . $file_name;
		file_put_contents($file . '.log', $content, FILE_APPEND | LOCK_EX);

		//keep small than 1G
		if (filesize($file . '.log') > 1000000000) {
			rename($file . '.log', $file . '.' . date('YmdHis') . '.log');
		}

		umask($old_mask);
	}
    public static function get_route_info()
    {
        $route_info = empty($_GET['action']) ? '' : $_GET['action'];
        $route_info = strpos($route_info, '/') ? explode('/', $route_info) : explode('.', $route_info);

        return array(
            'module' => trim($route_info[0]),
            'action' => empty($route_info[1]) ? '' : trim($route_info[1]),
        );
    }
}
