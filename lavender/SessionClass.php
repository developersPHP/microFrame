<?php
namespace microFrame\lavender;

define('L_APP_PATH',dirname(__DIR__).'/');
define('L_WORKSPACE_PATH',dirname(L_APP_PATH) . '/');
include_once L_WORKSPACE_PATH.'lavender/Dao/SessionKvTable.php';
include_once L_WORKSPACE_PATH.'lavender/CookieClass.php';
use microFrame\lavender\CookieClass;


class SessionClass
{
    protected $key;
    protected $request_time;
    protected $timeout;
    protected $updated;
    protected $dao_name;

    protected $id;
    protected $secret;

    protected $data = array();
    protected $inited = false;
    protected $changed = false;

    public function __construct($key, $request_time, $timeout, $dao_name = null) {
        $this->key = $key;
        $this->request_time = $request_time;
        $this->timeout = $timeout;
        $this->dao_name = $dao_name;

    }

    public function __destruct() {
        $this->save_data();

    }

    //thes session is based on cookie,if customer cut down cookie from browser it will be useless
    public static function createSessionObject($cookieKey,array $options) {

        $token = CookieClass::get_cookie($cookieKey);
        $timeOut = isset($options['session_timeout']) ? $options['session_timeout'] : 0;
        $daoName = isset($options['session_dao']) ? $options['session_dao'] : '';
        return new SessionClass($token, $_REQUEST['REQUEST_TIME'], $timeOut, $daoName);
    }

 /*************************************************create session*******************************************************/
    //create session
    public function create($id,$time) {
        //because param of id is global
        $this->id = $id;
        $this->data['secret'] = $this->secret = $this->createSecret($time);
        $this->changed = true;
        $this->inited = true;

        return $this->createKey();
    }

    private function createSecret($time)
    {
        return mt_rand(1000000000, 9999999999) . $time;
    }

    private function createKey()
    {
        if (!$this->id) {
            throw new Exception('session id undefined.', Errno::SESSION_ID_INVALID);
        }

        //signature code
        $signature = $this->createSignature();

        return "{$this->id}_{$this->secret}_{$signature}";
    }

    private function createSignature()
    {
        //get hash key
        //$hash_key = Core::get_config('const', 'hash_key');
        $hash_key = 'fj)(3ldfjLUuiro4io98I#Ji';
        //signature code
        return substr(md5("{$this->id}|{$this->secret}|" . $hash_key), 5, 10);
    }
/**********************************check auth*****************************************************************/
    //check auth
    public function check_valid() {

        //check that whether a session is created
        if (!$this->inited) {
            $this->check_auth_valid();
        }

        return empty($this->data) ? false : true;
    }

    private function check_auth_valid()
    {
        if ($this->inited) {
            return $this->id;
        }

        $this->inited = true;

        //check session id & key
        if (empty($this->key) ) {
            return null;
        }

        //get session key info
        $tmp = explode('_', $this->key);
        $this->id = $tmp[0];
        $this->secret = trim($tmp[1]);
        $signature = trim($tmp[2]);
        if (empty($this->secret) || empty($signature)) {
            throw new Exception('session key invalid.', Errno::SESSION_INVALID);
        }

        //check signature
        if ($this->createSignature() !== $signature) {
            throw new Exception('session key invalid.', Errno::SESSION_INVALID);
        }

        //read & check session data
        $this->data = $this->read_data();
        if (empty($this->data) ) {
            throw new Exception\Auth('session record not found on server.', Errno::SESSION_INVALID);
        }

        if (empty($this->data['secret']) || $this->data['secret'] != $this->secret) {
            throw new Exception\Auth('session secret verify failed.', Errno::SESSION_INVALID);
        }

        return $this->id;
    }
    private function read_data()
    {
        if (!$this->id) {
            return array();
        }

        $item = $this->get_handle()->get_raw_record($this->id);
        if ($item && $this->timeout && $item['updated'] < ($this->request_time - $this->timeout) ) {
            throw new Exception\Auth('session timeout on server side.', Errno::SESSION_TIMEOUT);
        }

        $this->updated = $item['updated'];

        return $item ? $item['data'] : array();
    }

    private function get_handle()
    {
        //return Dao\SessionKvTable::instance();
        static $dao;
        if (!$dao) {
            $dao_name = $this->dao_name ? $this->dao_name : 'Dao\SessionKvTable';
            $dao = new $dao_name();
        }

        return $dao;
    }
    /********************************************save check data***************************************************************/

    private function save_data()
    {
        if (!$this->id) {
            return true;
        }

        $dao = $this->get_handle();

        //destory
        if (empty($this->data) ) {
            return $dao->delete($this->id);
        }

        //update modify time only
        if (!$this->changed) {
            //update session time interval: 5min
            if ($this->request_time > $this->updated + 300) {
                return $dao->update_time($this->id, $this->request_time);
            }
            return true;
        }

        //update or add
        return $dao->set($this->id, $this->data, $this->request_time);
    }

    /************************************************save check data after destructiuon*********************************************************/
    public function destroy()
    {
        if (!$this->inited) {
            $this->check_auth_valid();
        }
        $this->data = array();
        $this->changed = true;
        $this->save_data();
    }
}