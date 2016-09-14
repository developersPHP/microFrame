<?php
namespace microFrame\lavender;

class Filter
{
	const T_RAW			 = 0x0;    //0
	const T_STRING		 = 0x1;   //1*16^0=1    o
	const T_INT			 = 0x2;   //2*16^0=2    10
	const T_FLOAT		 = 0x4;   //4*16^0=4    100
	const T_BOOL		 = 0x8;   //8*16^0=8    1000
	const T_MAP			 = 0x10;  //1*16^1+0*16^0=16  10000
	const T_STRIP_ER	 = 0x20; //strip \r 2*16^1+0*16^0=32   100000
	const T_STRIP_NL	 = 0x40; //strip \n and \r  4*16^1+0*16^0=64   1000000
	const T_STRIP_TAGS	 = 0x80; //strip html tags  8*16^1+0*16^0=128   10000000
	const T_STRING_STRIC = 0x100; //敏感词检查       1*16^2+0*16^1+0*16^0=256   100000000
	const T_JSON		 = 0x200;  //               2*16^2+0*16^1+0*16^0=512   1000000000
	const T_EMAIL		 = 0x400;//4*16^2+0*16^1+0*16^0=1024                   10000000000
	const T_URL			 = 0x800;//8*16^2+0*16^1+0*16^0=2048                   100000000000
	const T_PHONE		 = 0x1000;//1*16^3+0*16^2+0*16^1+0*16^0=4096           1000000000000
	const T_MOBILE		 = 0x2000;//2*16^3+0*16^2+0*16^1+0*16^0=8192           10001011010000


	/**
	 * filter from souce data by types
	 *
	 * @param $source source data list
	 * @param $definitions filter type list
	 * @param $prefix output keys prefix
	 *
	 * @return array
	 */
	public static function filter_map(array $source, array $definitions, $prefix = '')
	{
		$output = array();
		foreach ($definitions as $_k => $type) {
			if (!isset($source[$_k]) ) {
				continue;
			}
			$output[$prefix . $_k] = self::filter($source[$_k], $type);
		}

		return $output;
	}

	/**
	 * filter from souce data by type
	 *
	 * @param $var source data 类型名的值
	 * @param $type filter type list  类型数值
	 *
	 * @return array
	 */
	public static function filter($var, $type)
	{
        //if type =T_RAW,all type is valid
		if ($type === self::T_RAW) {
			return $var;
		}

		//map
		if ($type & self::T_MAP) { //与位运算  T_MAP=10000,数组xq
			if (is_array($var) ) {
				$tmp_type = $type ^ self::T_MAP;   //或位运算,数组里类型只能为1000,100,10,0即是string,int,boole,float
				if ($tmp_type) {
					//filter to every item
					foreach ($var as $tmp_key => $tmp_value) {
						$var[$tmp_key] = self::filter($tmp_value, $tmp_type);
					}
				}

				return $var;
			}

			if (!empty($var)) {//如果不是数组
				return false;
			}

			return array();
		}

		//int
		if ($type & self::T_INT) {
			if (!is_numeric($var)) {
				return false;
			}

			$var = intval($var);
			return $var;
		}

		//float
		if ($type & self::T_FLOAT) {
			if (!is_numeric($var)) {
				return false;
			}

			$var = doubleval($var);
			return $var;
		}

		//boolean
		if ($type & self::T_BOOL) {
			$var = empty($var) ? 0 : 1;
			return $var;
		}
		
		//json
		if ($type & self::T_JSON) {
			$jd = json_decode($var, true);
			if (empty($jd) || $jd === $var) {
				return false;
			}
			
			return $jd;
		}
		
		//string filter
		if ($type & self::T_STRING_STRIC) {
			if (is_string($var)) {
				return $var;
			}
		
			return false;
		}
		
		//string above
		if ($type & self::T_STRING) {
			if (is_string($var)) {
				return $var;
			}

			return false;
		}

		if ($type & self::T_EMAIL) {
			$var = filter_var($var, FILTER_VALIDATE_EMAIL);
			if ($var === false) {
				return false;
			}

			return $var;
		}

		if ($type & self::T_URL) {
			$var = filter_var($var, FILTER_VALIDATE_URL);
			if ($var === false) {
				return false;
			}

			return $var;
		}

		if ($type & self::T_PHONE) {
			if (preg_match('/^(\+?[0-9]{2,3})?[0-9]{3,7}\-[0-9]{6,8}(\-[0-9]{2,6})?$/', $var)) {
				return false;
			}

			return $var;
		}

		if ($type & self::T_MOBILE) {
			if (preg_match('/^(\+?[0-9]{2,3})?[0-9]{6,11}$/', $var)) {
				return false;
			}

			return $var;
		}

		//strip \r
		if ($type & self::T_STRIP_ER) {
			$var = str_replace("\r", '', $var);
		}

		//strip \n & \r
		if ($type & self::T_STRIP_NL) {
			$var = str_replace("\r", '', $var);
			$var = str_replace("\n", '', $var);
		}

		//strip html tags
		if ($type & self::T_STRIP_TAGS) {
			$var = strip_tags($var);
		}

		return $var;
	}
}



