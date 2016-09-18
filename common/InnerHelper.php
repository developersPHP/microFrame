<?php

namespace microFrame\common;
use microFrame\common\GetConfigInfo;
use microFrame\lavender\Core;

class InnerHelper {
	private static $sign_param = array (
			'communicate_id' => 'a2ad9b4babbba7e9',
			'version' => '1.0.0' 
	);
	
	/**
	 * 服务器间通信签名
	 * @param string $action action
	 * @param string $get_param get参数, k=v&k=v
	 * @param array $post_param post参数
	 * @return string
	 */
	public static function sign($action, $get_param = '', $post_param = array()) {
		if (! empty ( $get_param )) {
			$get = array_filter ( explode ( '&', $get_param ) );
			foreach ( $get as $v ) {
				$get_p = explode ( '=', $v );
				$param [$get_p [0]] = $get_p [1];
			}
		}
		$param = array_merge ( $param, $post_param );
		
		$param ['action'] = $action;
		$comunicata_id = GetConfigInfo::get_config ( 'const', self::$sign_param ['communicate_id'] );
		
		ksort ( $param );
		$url_param = '';
		foreach ( $param as $key => $value ) {
			$url_param .= $key . '=' . $value . "&";
		}
		$url_param = trim ( $url_param, "&" );
		$url_param . $comunicata_id;
		return md5 ( $url_param . $comunicata_id );
	}
	
	// post上传
	/**
	 * post 请求
	 * @param string $url 请求地址
	 * @param array $post_data post 参数
	 * @return mixed
	 */
	public function post_invoke($url, $post_data = array()) {
		$curl = curl_init ();
		
		curl_setopt ( $curl, CURLOPT_URL, $url );
		curl_setopt ( $curl, CURLOPT_POST, 1 );
		curl_setopt ( $curl, CURLOPT_HEADER, 0 );
		curl_setopt ( $curl, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt ( $curl, CURLOPT_POSTFIELDS, $post_data );
		curl_setopt ( $curl, CURLOPT_BINARYTRANSFER, 1 );
		
		$result = curl_exec ( $curl );
		
		curl_close ( $curl );
		
		Core::log ( 'inner_call', "get_data : {$url}  " ."post_data : " . json_encode ( $post_data ) . "   result : " . json_encode ( $result ) . "\r\n" );
		
		return $result;
	}
	
	/**
	 * 文件上传
	 * @param string $file_url 上传文件, path.file_name
 	 * @param string $filename 文件名
	 * @param string $url 上传地址
	 * @param string $action 请求action
	 * @param array $get_param get参数
	 * @param array $post_param post参数
	 * @return unknown
	 */
	public static function upload_file($file_url, $filename, $url, $action, $get_param = array(), $post_param = array()) {
		if (empty ( $post_param )) {
			$post_param = array ();
		}
		
		if(empty($post_param)){
			$post_param = array();
		}
		
		$req_get_param = '';
		if (! empty ( $get_param )) {
			foreach ( $get_param as $key => $value ) {
				$req_get_param .= $key . '=' . $value . "&";
			}
			$req_get_param = rtrim ( $req_get_param, '&' );
		}
		
		$req_get_param .= '&communicate_id=' . self::$sign_param ['communicate_id'] . '&version=' . self::$sign_param ['version'];
		$req_get_param .= "&filename={$filename}";
		
		$req_get_param = ltrim ( $req_get_param, '&' );
		
		$sign = self::sign ( $action, $req_get_param, $post_param );
		$req_get_param .= "&sign={$sign}";
		
		if(!preg_match('/\/\?/', $url)){
			$url .= '/?';
		}
		
		$url .= 'action=' . $action  .'&' . $req_get_param;
		$url = rtrim ( $url, '&' );
		
		$post_param ['file'] = "@{$file_url}"; 
		
		$res = self::post_invoke ( $url, $post_param );
		
		return $res;
	}
	
	/**
	 *  rpc call
	 * @param string $url 请求地址
	 * @param string $action 请求action
	 * @param array $get_param get参数
	 * @param array $post_param post参数
	 * @return unknown
	 */
	public static function rpc_call($url, $action, $get_param = array(), $post_param = array()) {
		if (empty ( $post_param )) {
			$post_param = array ();
		}
		
		$req_get_param = '';
		if (! empty ( $get_param )) {
			foreach ( $get_param as $key => $value ) {
				$req_get_param .= $key . '=' . $value . "&";
			}
			$req_get_param = rtrim ( $req_get_param, '&' );
		}
		
		$req_get_param .= '&communicate_id=' . self::$sign_param ['communicate_id'] . '&version=' . self::$sign_param ['version'];
		
		$sign = self::sign ( $action, $req_get_param, $post_param );
		$req_get_param .= "&sign={$sign}";
		
		if(!preg_match('/\/\?/', $url)){
			$url .= '/?';
		}
		
		$url .= 'action=' . $action .'&' . $req_get_param;
		
		$url = rtrim ( $url, '&' );
		
		$res = self::post_invoke ( $url, $post_param );
		
		return $res;
	}
}
?>