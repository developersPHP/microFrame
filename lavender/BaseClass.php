<?php

namespace microFrame\lavender;

define('L_APP_PATH',dirname(__DIR__).'/');
define('L_WORKSPACE_PATH',dirname(L_APP_PATH) . '/');

include_once L_WORKSPACE_PATH.'lavender/Core.php';
include_once L_WORKSPACE_PATH.'lavender/CookieClass.php';
include_once L_WORKSPACE_PATH.'lavender/SessionClass.php';
include_once L_WORKSPACE_PATH.'lavender/Render.php';
include_once L_WORKSPACE_PATH.'lavender/Filter.php';

use microFrame\lavender\Exception;
use microFrame\lavender\Core;
use microFrame\lavender\CookieClass;
use microFrame\lavender\SessionClass;
use microFrame\lavender\Render;
use microFrame\lavender\Filter;

class BaseClass {

    /**
     * session cookie name
     * @const
     */
    const SESSION_KEY_NAME = 'sk';
    const TOKEN_KEY = 'token';
    const CONTENT_TYPE = 'text/html';

    private static $instance;
    protected $without_auth_actions = array();
    protected $options =array();
    protected $module_name;

    protected $session;


    public function __construct($module_name,$options) {

        $this->module_name = $module_name;
        $this->options = $options;

    }

    //run programe
    public static function run($web_options) {
        if(!is_array($web_options['action_modules'])) {
            throw new Exception('action_modules not setted in $options', Errno::PARAM_INVALID);
        }
        //gain url module
        $route_info = Core::get_route_info();
        $module_name = empty($route_info['module']) ? 'index' : $route_info['module'];
        $action_name = empty($route_info['action']) ? 'index' : $route_info['action'];

        //abnormal condition handle
        if(!in_array($module_name,$web_options['action_modules'])) {
            Core::header_status(404);
        }
        $class_name = "App\\Action\\{$module_name}";
        self::$instance = new $class_name($module_name,$web_options);
        //这里体现了excute的多态性，如果接口类型继承webService类,执行该类的excute，如果是页面继承webPage类,执行该类的excute方法.
        self::$instance->excute($action_name);
    }

    //before_excute
    private function before_excute() {

        //check whether the cookie is setted
        if(!CookieClass::get_cookie(self::TOKEN_KEY)) {
            $cookie_value = CookieClass::set_cookie_vaule();
            CookieClass::set_cookie(self::TOKEN_KEY,$cookie_value);
        }
    }

    //excute
    private function excute($action_name) {
        try {
            //check whether the cookie is setted
            $this->before_excute();
            $this->action_name = $action_name;
            $action_method = "{$action_name}_action";

            //action not found
            if (!method_exists($this, $action_method)) {
                Core::header_status(404);
                return ;
            }

            //create session object
            $this->session = SessionClass::createSessionObject(self::SESSION_KEY_NAME,$this->options); //创建session实例

            //need auth
            /*if (empty($this->without_auth_actions) || (array_search($action_name, $this->without_auth_actions) === false && array_search('*', $this->without_auth_actions) === false) ) {

                //check auth
                if (!$this->session->check_valid() ) {
                    throw new Exception('auth verify failed', Errno::AUTH_FAILED);
                }
            }*/

            //call action
            $data = $this->$action_method();
            //print_r($data);exit;

            //view render
            $render = new Render($this->module_name,$this->options,$this->session,$this->action_name,$this);
            return $render->render($data);
        }
        catch (Exception\Auth $e) {
            CookieClass::set_cookie(self::SESSION_KEY_NAME, '');
            return Render::render_auth_error($e->getMessage(), $e->getCode());
        }
        catch (Exception $e) {
            return Render::render_error($e->getMessage(), $e->getCode());
        }

    }
    /******************************parameters function*******************************************************/
    protected function parameters($definition, $method = WebPage::M_GET, $required = false, $prefix = null)
    {
        switch ($method) {
            case null:
            case self::M_GET:
                $source = $_GET;
                break;

            case self::M_POST:
                $source = $_POST;
                break;

            case self::M_COOKIE:
                $source = $_COOKIE;
                break;
//
//			 case self::M_REQUEST:
//			 	$source = $_REQUEST;
//			 	break;

            case self::M_FILE:
                $source = $_FILES;
                break;

            case self::M_ENV:
                $source = $_ENV;
                break;

            case self::M_SERVER:
                $source = $_SERVER;
                break;

            default:
                return false;
        }
        $parameters = array();
        foreach ($definition as $key => $filter) {
            if (isset($source[$key]) ) {
                //gain some query parmas on here
                $result = Filter::filter($source[$key], $filter);
                if ($result === false) {
                    throw new Exception\Filter("Parameter '{$key}' is invalid", Errno::INPUT_PARAM_INVALID);
                }

                //string filter
                if ($filter & Filter::T_STRING_STRIC) {
                    if ( \Golo\Api\SensitiveWord::check( $source[$key] ) ) {
                        throw new Exception\Filter("Parameter '{$key}' contain sensitive word", Errno::INPUT_SENSITIVE_WORD_INVALID);
                    }
                }
            }
            else {
                if ($required) {
                    //judge that whether params is required
                    throw new Exception\Filter("Parameter '{$key}' is required", Errno::INPUT_PARAM_MISSED);
                }
                continue;
            }

            //parameter key prefix
            if ($prefix) {
                $key =  $prefix . $key;
            }

            $parameters[$key] = $result;
        }

        return $parameters;
    }

}