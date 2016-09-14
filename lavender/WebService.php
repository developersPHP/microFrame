<?php
namespace microFrame\lavender;
define('L_APP_PATH',dirname(__DIR__).'/');
define('L_WORKSPACE_PATH',dirname(L_APP_PATH) . '/');

include_once L_WORKSPACE_PATH.'lavender/Core.php';
use Lavender\Errno;
use Lavender\Filter;
use Lavender\Exception;
//use Lavender\WebService;
use Golo\ErrnoPlus;
use Golo\Signature;
use Golo\Pub\Factory;
use Golo\Init\Invoke\RequestInit;


use microFrame\lavender\BaseClass;
use microFrame\lavender\Core;

class WebService extends BaseClass
{
	private $system_sign;
	
	protected $uid = 0;
	protected $ver = '';
	protected $app_id = '';
	protected $lan = 'zh';
	protected $channel_id = 0;
	protected $public_id = 0;
	protected $shop_id = 0; //
	
	public static $user_id = 0;

	//the function is used to
	public function before_execute()
	{
		$route_options = Core::get_route_info();
		$action_name = $route_options['action'];
		
		// 签名4大参数
		$request = array(
			'user_id' => Filter::T_INT,
			'ver' => Filter::T_STRING,
			'sign' => Filter::T_STRING,
			'app_id' => Filter::T_STRING,
		);
		
		// *****************新增加全局参数*****************start****************
		$param = $this->parameters(array(
			'lan' => Filter::T_STRING,
			'user_id' => Filter::T_INT,
			'public_id' => Filter::T_INT,
			'custom_ver' => Filter::T_STRING, // 自定义版本，用于替换当前产品给出的版本ver
			'channel_id' => Filter::T_INT,
			'shop_id' => Filter::T_INT,       //总厂登录后将自己的id改为shop_id传进来
			), self::M_GET);
		
		if (empty($param['lan'])) {
			$param['lan'] = Core::get_config('const', 'lang');
		}

		$this->lan = $param['lan'] == 'ja' ? 'jp' : $param['lan'];
		$this->public_id = isset($param['public_id']) ? intval($param['public_id']) : 0;
		$this->channel_id = isset($param['channel_id']) ? intval($param['channel_id']) : 0;
		$this->shop_id = isset($param['shop_id']) ? intval($param['shop_id']) : 0;
		$custom_ver = isset($param['custom_ver']) ? $param['custom_ver'] : null;
		
		// $this->lan 升级为全局
		if (!isset($GLOBALS['lang'])) {
			global $lang;
		}
		$lang = $this->lan;
		
		if (!empty($param['user_id'])) {
			self::$user_id = $param['user_id'];
		}
		// *****************新增加全局参数*****************end******************
		
		// 非签名状态，保留赋值，避免action拿不到 this->ver, $this->app_id
		if (!empty($this->without_auth_actions) && (array_search($action_name, $this->without_auth_actions) !== false || array_search('*', $this->without_auth_actions) !== false)) {
			
			$request['user_id'] = Filter::T_RAW; //兼容老版本 "user_id=" 形式
			$request['lan'] = Filter::T_STRING;
			$request['channel_id'] = Filter::T_INT;
			$request['shop_id'] = Filter::T_INT;
			$param = $this->parameters($request, self::M_GET);
			
			$this->uid = intval($param['user_id']) ?: 0; // 避免报错
			$this->ver = $custom_ver ?: $param['ver'];
			$this->app_id = $param['app_id'];
			
			// $this->app_id 升级为全局
			if (!isset($GLOBALS['app_id'])) {
				global $app_id;
			}
			$app_id = $this->app_id;
			
			// 入口统计
			new RequestInit('http://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'], $this->uid, $this->app_id);
			
			return;
		}
		
		$param = $this->parameters($request, self::M_GET, true);

		if (empty($param['sign'])) {
			throw new Exception('sign missed', Errno::PARAM_MISSED);
		}

		if (empty($param['user_id']) || !is_numeric($param['user_id']) || $param['user_id'] < 1) {
			throw new Exception('user_id invalid , must be number', Errno::PARAM_MISSED);
		}
		
		$token = Api\Token::get_token($param['user_id'], $param['app_id']);
		if (empty($token)) {
			//状态码调整是为了兼容goloz发出去版本兼容问题， 由于goloz返回 Errno::AUTH_FAILED 状态码时， 不会重新登录。
// 			$err_code = $param['app_id'] == '1321' || $param['app_id'] == '1311'? ErrnoPlus::SIGN_INVALID : Errno::AUTH_FAILED;
			throw new Exception('token missed', ErrnoPlus::SIGN_INVALID);
		}
		
		//获取请求参数
		$request = $this->get_request_param();
		
		//验证签名是否正确
		if (!$this->is_valid_signature($token, $request, $param['sign'])) {
			throw new Exception('sign invalid', ErrnoPlus::SIGN_INVALID);
		}

		$this->uid = $param['user_id'];
		$this->ver = $custom_ver ?: $param['ver'];
		$this->app_id = $param['app_id'];
		
		// $this->app_id 升级为全局
		if (!isset($GLOBALS['app_id'])) {
			global $app_id;
		}
		$app_id = $this->app_id;
		
		// 入口统计
		new RequestInit('http://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'], $this->uid, $this->app_id);

		Api\Token::set_client_ver($this->uid, $this->ver);
	}
	
	// 总厂到分厂逻辑-重分配
	protected function set_shop2factory()
	{
		if ($this->shop_id <= 0) {
			return $this->uid;
		}
		
		// 总厂切换某个分厂
		if ($this->shop_id != $this->uid) {
			$this->uid = $this->shop_id;
		} else {
			// 总厂查看所有数据
			 $f = Factory::get_brand_id($this->shop_id);
			 if (!empty($f)) {
				$this->uid = $f;
			 }
		}
		
		return $this->uid;
	}

	/**
	 * 验证签名是否正确
	 * @param string $request_param
	 * @param string $sign
	 * @return boolean
	 */
	private function is_valid_signature($key, $request_param, $signature)
	{
		//根据提交的参数获取计算出来的签名
		// require L_EXT_PATH.'Signature.php';
		$signature_obj = new Signature($key, '', array('sign' => 1));
		$this->system_sign = $signature_obj->get_signature($request_param);
		if ($signature == $this->system_sign) {
			return true;
		}

		return false;
	}

	private function get_request_param()
	{
		$request = array();
		if (!empty($_GET)) {
			foreach ($_GET as $key => $value) {
				$request[$key] = $value;
			}
		}
		if (!empty($_POST)) {
			foreach ($_POST as $key => $value) {
				$request[$key] = $value;
			}
		}
		return $request;
	}
	//the execute function is rewirted
	public function execute($action_name)
	{
		$this->header_content_type(static::CONTENT_TYPE);

		try {
			$this->before_execute();
			$this->action_name = $action_name;
			$action_method = "{$action_name}_action";

			//action not found
			if (!method_exists($this, $action_method)) {
					$this->header_notfound();
					return ;
			}

			//call action
			$data = $this->$action_method();

			//render
			return $this->render($data);
		}
		catch (Exception $e) {
			return $this->render_error($e->getMessage(), $e->getCode());
		}
	}

	public function render($__data__ = null, $__view__ = null)
	{
		//get friend message
		if (isset($__data__['code']) && $__data__['code'] > 0) {
			//debug on error
			if (L_DEBUG) {
				$__data__['debug_msg'] = $__data__['msg'];
			}

			$__data__['msg'] = \Lavender\Core::get_lang_text($__data__['code']);
		}

		//json
		$response = json_encode($__data__, JSON_UNESCAPED_UNICODE);
		
		if (defined("L_FORMAT_JSON") && L_FORMAT_JSON === true) {
			echo $this->indent_json($response);
		} else {
			echo $response;
		}

		//write session flow
		$this->session_flow($__data__);
	}

	/**
	 * Indents a flat JSON string to make it more human-readable.
	 * @param string $json The original JSON string to process.
	 * @return string Indented version of the original JSON string.
	 */
	private function indent_json($json) 
	{

		$result = '';
		$pos = 0;
		$strLen = strlen($json);
		$indentStr = '  ';
		$newLine = "\r\n";
		$prevChar = '';
		$outOfQuotes = true;

		for ($i=0; $i<=$strLen; $i++) {

			// Grab the next character in the string.
			$char = substr($json, $i, 1);
			// Are we inside a quoted string?
			if ($char == '"' && $prevChar != '\\') {
				$outOfQuotes = !$outOfQuotes;
				// If this character is the end of an element,
				// output a new line and indent the next line.
			} else if(($char == '}' || $char == ']') && $outOfQuotes) {
				$result .= $newLine;
				$pos --;
				for ($j=0; $j<$pos; $j++) {
					$result .= $indentStr;
				}
			}
			// Add the character to the result string.
			$result .= $char;
			// If the last character was the beginning of an element,
			// output a new line and indent the next line.
			if (($char == ',' || $char == '{' || $char == '[') && $outOfQuotes) {
				$result .= $newLine;
				if ($char == '{' || $char == '[') {
					$pos ++;
				}
				for ($j = 0; $j < $pos; $j++) {
					$result .= $indentStr;
				}
			}
			$prevChar = $char;
		}

		return $result;

	}

	public static function sign_request($get_param, $post_param) {
		$token = \Golo\Api\Token::get_token($get_param['user_id'], $get_param['app_id']);
		$signature_obj = new \Golo\Signature($token, '', array('sign' => 1));

		$request = array();
		if (!empty($get_param)) {
			foreach ($get_param as $key => $value) {
				$request[$key] = $value;
			}
		}
		if (!empty($post_param)) {
			foreach ($post_param as $key => $value) {
				$request[$key] = $value;
			}
		}

		return $signature_obj->get_signature($request);
	}

	public function log($contents, $log_name = 'debug')
	{
		$old_mask = umask(0);

		//check & make dir
		$dir = L_WORKSPACE_PATH . 'log/' . L_APP_NAME . '/';
		if (!is_dir($dir)) {
			mkdir($dir, 0777, true);
		}

		//write file
		$file = $dir . $log_name;
		file_put_contents($file . '.log', $contents, FILE_APPEND | LOCK_EX);

		//keep small than 1G
		if (filesize($file . '.log') > 1000000000) {
			rename($file . '.log', $file . '.' . date('His') . '.log');
		}

		umask($old_mask);

	}
}
