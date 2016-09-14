<?php

namespace microFrame\lavender;


class Render {

    public $instance;
    public $options =array();
    public $module_name;
    public $action_name;
    public $session;


    public function __construct($module_name,$options,$sessoin,$action_name,$instance) {

        $this->module_name = $module_name;
        $this->options = $options;
        $this->session = $sessoin;
        $this->action_name = $action_name;
        $this->instance = $instance;

    }

    public function render($__data__ = null)
    {
        //get friend message
        if (isset($__data__['code']) && $__data__['code'] > 0) {
            $msg = Core::get_lang_text($__data__['code']);

            if(!empty($msg))
            {
                $__data__['msg'] = $msg;
            }
        }

        //extract data
        if ($__data__) {
            if (!is_array($__data__)) {
                throw new Exception("Data to render not an array", Errno::PARAM_INVALID);
            }

            extract($__data__);
        }
        //global data in view
        $gdata = array(
            'module' => $this->module_name,
            'action' => $this->action_name,
            'session' => $this->session,
            'options' => $this->options,
            'instance' => $this->instance,
        );

        //set view,
        if (empty($view)) {
            if (!empty($code)) {
                $view = 'common/error'; //this is a error page
            }
            else {
                $view = $this->module_name . '/' . $this->action_name;
            }
        }

        if (!file_exists(L_APP_PATH . "view/{$view}.php") ) {
            throw new Exception("View not found:{$view}", Errno::FILE_NOTFOUND);
        }

        include(L_APP_PATH . "view/{$view}.php");
    }

    public static function render_auth_error($msg, $code = 401)
    {
        $data = array(
            'code' => $code,
            'msg' => $msg,
            'view' => 'common/error', //this is a error page
        );

        return self::render($data);
    }
    public static function render_error($msg, $code = -100)
    {
        $data = array(
            'code' => $code,
            'msg' => $msg,
            'view' => 'common/error',
        );

        return self::render($data);
    }
}