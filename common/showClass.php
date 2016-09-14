<?php
namespace microFrame\common;
use microFrame\lavender\Core;
class showClass
{
    private static $end_time;
    private static $start_time;
    private static $_overtime;

    public function success($msg = '', $data = null, $arr_to_str = false)
    {
        if ($arr_to_str) {
            $data = $this->arr_to_str($data);
        }

        $result['code'] = 0;
        $result['msg'] = $msg;
        $result['data'] = $data;

        self::$end_time = microtime(true);
        $exec_time = self::$end_time - self::$start_time;

        $trace =  $exec_time;
        if (L_ENV == 'develop' || L_ENV == 'test') {
            $result['trace'] = $trace;
        }

        if ($exec_time > self::$_overtime) {
            Core::log('slow_success', date('Y-m-d H:i:s') . ', standard: ' . self::$_overtime . ' , uid: ' . $this->uid . ', app_ip: ' . $this->app_id . ', ver: ' . $this->ver . ', ' . $trace . "\n");
        }

        return $result;
    }
}