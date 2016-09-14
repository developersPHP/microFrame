<?php
namespace microFrame\common;
use microFrame\lavender\Exception;
use microFrame\common\Errno;
class GetConfigInfo
{
    private static $config_cache = array();

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
}
