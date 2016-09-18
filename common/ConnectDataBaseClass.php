<?php
namespace microFrame\common;

use microFrame\lavender\Dao\Mysql;
use microFrame\lavender\Dao\Mongo;
use microFrame\common\GetConfigInfo;
class ConnectDataBaseClass
{
    private static $db_instances = array();

    /**
     * get database instance by driver & config name
     * @param string $driver
     * @param string $name
     * @param string $index
     *
     * @return Lavender\Db\Interface
     */
    public static function get_database($driver, $name, $index = null)
    {
        $conf = GetConfigInfo::get_config('db', $name);

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
                $instance = new Mysql($conf['host'], $conf['user'], $conf['password'], $conf['database'], $conf['port'], $conf['charset']);
                break;
            case 'mongodb':
                $instance = new Mongo($conf['host'], $conf['database'], $conf['port'], $conf['options']);
                break;
            default:
                throw new Exception("database driver invalid,driver:{$driver}", Errno::PARAM_INVALID);
        }

        //return & cache to process
        return self::$db_instances[$cache_key] = $instance;
    }
}