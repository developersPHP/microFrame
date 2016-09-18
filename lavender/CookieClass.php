<?php
namespace microFrame\lavender;

class CookieClass
{
    //here you must careful because you can not hava anything information sended out before you set cookies,so you can set error_reporting=1
    public static function set_cookie($name, $value, $expire = 0, $path = null, $domain = null, $secure = false, $httponly = false) {


        return setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);
    }

    public static function get_cookie($name) {

        $token = isset($_COOKIE[$name]) ? $_COOKIE[$name] : null;
        //print_r($name);exit;
        return $token;
    }

    public static function set_cookie_vaule() {

        return mt_rand(1000000000, 9999999999);
    }
}