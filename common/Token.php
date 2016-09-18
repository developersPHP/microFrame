<?php
namespace microFrame\common;

use microFrame\common\GetConfigInfo;
use microFrame\common\InnerHelper;
use microFrame\lavender\Dao\TokenRedisTable;

class Token
{
	/***
	 * @param $uid
	 * @param $token
	 * @param $appid
	 * @param bool $bol_quick 是否立即同步
	 * @return bool
	 */
	public static function set_token($uid, $token, $appid, $bol_quick = false)
	{
		$field = Token::get_field($appid);
		$redis_data = array('token'=>$token, 'updated'=>time());
		Dao\TokenRedisTable::instance()->bol_quick = $bol_quick;
		$shop_app_id = GetConfigInfo::get_config('const','shop_app_id');
// 		if ($appid == 3422 || (in_array($appid, $shop_app_id) && $uid == 516888)) {
		if ($appid == 3422 || (in_array($appid, $shop_app_id))) {
			$hget = Dao\TokenRedisTable::instance()->hget($uid, $field);
			$t = time() - (3600 * 8);
			if ($hget['updated'] < $t || empty($hget) || empty($hget['token'])) {
				Dao\TokenRedisTable::instance()->hset($uid, $field, $redis_data);
			}
		} else {
			Dao\TokenRedisTable::instance()->hset($uid, $field, $redis_data);
		}
		
		return true;
	}
	
	/**
	 * 设置用户token值,同时在用户所属区域设置
	 *
	 * @access public
	 * @package \Golo\Api\User
	 * @param array $route
	 * @param array $post_data
	 *
	 * @return mixed 设置成功返回true，失败返回false
	 */
	public static function set_user_token( $route, $uid, $token, $appid )
	{
		self::set_token( $uid, $token, $appid );

		$post_param['token'] = $token;
		$post_param['uid'] = $uid;
		$post_param['app_id'] = $appid;

		$r = InnerHelper::rpc_call( $route['url_inner'], 'token.set_token', array(), $post_param );
		$data = json_decode($r, true);

		if($data['code'] == 0){
			return true;
		}
		return false;
	}



	public static function get_token( $uid, $app_id )
	{
		$field = Token::get_field( $app_id );
		
		$redis_data = Dao\TokenRedisTable::instance()->hget( $uid, $field );
		
		return $redis_data['token'];
	}
	
	private static function get_field($appid)
	{
		$appid_plus = GetConfigInfo::get_config('const', 'appid_plus');
		
		if (!isset($appid_plus[$appid]) || !isset($appid_plus[$appid]['sson']) || $appid_plus[$appid]['sson'] != 1) {
			return  isset($appid_plus[$appid]['tk_field'])? $appid_plus[$appid]['tk_field'] : $appid;
		}
		
		return 'app1';
	}
	
	public static function set_client_ver( $uid, $ver)
	{
		if(empty($ver) || empty($uid)){
			return true;
		}
		
		$_ver = self::get_client_ver($uid);
		
		if($ver == $_ver){
			return true;
		}
		
		$field = 'ver';
		$redis_data = array('ver'=>$ver,'updated'=>time());
	
		$dao_token=Dao\TokenRedisTable::instance();
	
		$dao_token->hset( $uid, $field, $redis_data );
		
		User::set_base($uid,array('ver'=>$ver));
	
		return true;
	}
	
	public static function get_client_ver( $uid)
	{
		$redis_data = Dao\TokenRedisTable::instance()->hget( $uid, 'ver' );
	
		return $redis_data['ver'];
	}

}