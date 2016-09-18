<?php
namespace microFrame\lavender\Dao;

use microFrame\lavender\Core;
use microFrame\common\Error;

abstract class RedisTable
{
	protected $table = 'test';
	protected $cache = null;
	protected $prefix = '';
	public $is_sync = false; //默认关闭同步

	protected static $instances = array();

	public function __construct()
	{
		$this->cache = new \Redis;
		$config = Core::get_config('cache', $this->table);
		$config['timeout'] = empty($config['timeout']) ? 1 : $config['timeout'];
		
		// $this->cache->pconnect($config['host'], $config['port'], $config['timeout']);
		$this->cache->connect($config['host'], $config['port']);
		if (!empty($config['auth'])) {
			$this->cache->auth($config['auth']);
		}
	}

	public function __destruct()
	{
		if($this->cache) {
			$this->cache->close();
		}
	}

	public static function instance()
	{
		$class = get_called_class();
		if (empty(self::$instances[$class])) {
			self::$instances[$class] = new $class();
		}
		
		return self::$instances[$class];
	}

	// list all keys
	public function keys($id)
	{
		$key = $this->prefix.$id;
		return $this->cache->get($key);
	}
	
	//get all keys
	public function getKeys($id)
	{
		$key = $this->prefix.$id;
		return $this->cache->getKeys($key);
	}
	
	// set String
	public function set($id, $data, $time = null)
	{
		$key = $this->prefix.$id;
		$json_value = $this->pack($data);
		if($time !== null && is_numeric($time)){
			$result = $this->cache->set($key, $json_value, $time);
		}else{
			$result = $this->cache->set($key, $json_value);
		}
		//数据同步
		self::set_sync_data(get_called_class(),__FUNCTION__,func_get_args());
		return $result;
	}
	
	// get String
	public function get($id)
	{
		$key = $this->prefix.$id;
		$json_value = $this->cache->get($key);
		if ($json_value) {
			$value = $this->unpack($json_value, true);
			return $value;
		}
		return null;
	}

	// update String
	public function update($id, $data, $time = null)
	{
		$key = $this->prefix.$id;
		$json_value = $this->pack($data);
		$result = $this->cache->set($key, $json_value, $time);
		//数据同步
		self::set_sync_data(get_called_class(),__FUNCTION__,func_get_args());
		return $result;
	}

	
	public function delete($id, $field = null, $type = null)
	{
		$key = $this->prefix.$id;
		if(empty($type)) {
			$result = $this->cache->del($key);
		}else{
			switch ($type) {
				case 'H': // hash表删除
					$result = $this->cache->hdel($key, $field);
				//请根据自己的需求扩展
			}
		}
		//数据同步
		self::set_sync_data(get_called_class(),__FUNCTION__,func_get_args());
		return $result;
	}

	public function is_exists($id = '', $field = null, $type = null)
	{
		$key = $this->prefix.$id;
		if(empty($type)) {
			return $this->cache->exists($key);
		}else{
			switch ($type) {
				case 'H': // hash表检测
					return $this->cache->hexists($key, $field);
				//请根据自己的需求扩展
			}
		}
		return false;
	}
    public function type($key) {
        if(empty($key)) {
            throw new \Exception('param is empty', Error::PARAM_MISSED);
        }
        return $this->cache->type($key);
    }

	/**
	 * 队列入队
	 * 返回队列长度
	 */
	public function enqueue($id,$value)
	{
		$key = $this->prefix.$id;
		$value = $this->pack($value);
		$result = $this->cache->RPUSH($key,$value);
		//数据同步
		self::set_sync_data(get_called_class(),__FUNCTION__,func_get_args());
		return $result;
	}

	/**
	 * 队列出队
	 * 返回出队值
	 */
	public function dequeue($id)
	{
		$key = $this->prefix.$id;
		$value = $this->cache->LPOP($key);
		$result = $this->unpack($value,true);

		//数据同步
		self::set_sync_data(get_called_class(),__FUNCTION__,func_get_args());
		return $result;
	}

	//  队列长度
	public function queue_length($id)
	{
		$key = $this->prefix.$id;
		return $this->cache->LRANGE($key,0,-1);
	}

	/**
	 * list类型 左入栈
	 * 
	 * @param $id
	 * @param $value
	 */
	public function lpush($id, $value)
	{
		$key = $this->prefix;
		if(!is_null($id)){
			$key = $this->prefix.$id;
		}
		if(is_array($value)){
			foreach ($value as $v) {
				$result = $this->cache->lpush($key, $v);
			}	
		}else{
			$result = $this->cache->lpush($key, $value);
		}
		//数据同步
		self::set_sync_data(get_called_class(),__FUNCTION__,func_get_args());
		return $result;
	}

	/**
	 * list类型 右入栈
	 * 
	 * @param $id
	 * @param $value
	 */
	public function rpush($id, $value)
	{
		$key = $this->prefix;
		if(!is_null($id)){
			$key = $this->prefix.$id;
		}
		if(is_array($value)){
			foreach ($value as $v) {
				$result = $this->cache->rpush($key, $v);
			}	
		}else{
			$result = $this->cache->rpush($key, $value);
		}
		//数据同步
		self::set_sync_data(get_called_class(),__FUNCTION__,func_get_args());
		return $result;
	}
	
	/**
	 * list类型 左入栈
	 *
	 * @param $id
	 * @param array $value
	 */
	public function lpushx($id, array $value)
	{
		if(empty($id)){
			throw new \Exception('id is empty', Error::PARAM_MISSED);
		}
		$key = $this->prefix.$id;
		foreach ($value as $v) {
			$this->cache->lpushx($key, $v);
		}
		//数据同步
		self::set_sync_data(get_called_class(),__FUNCTION__,func_get_args());
	}
	
	/**
	 * 返回名称为key的list有多少个元素
	 */
	public function lsize($id)
	{
		if(empty($id)){
			throw new \Exception('id is empty', Error::PARAM_MISSED);
		}
		$key = $this->prefix.$id;
		return $this->cache->lSize($key);
	}

	/**
	 * list类型，删除链表类指定的值，返回实际被删除的数量。
	 */
	public function lrem($id, $value, $num = 1)
	{
		$key = $this->prefix;
		if(!is_null($id)){
			$key = $this->prefix.$id;
		}
		$result = $this->cache->lrem($key, $value, $num);
		//数据同步
		self::set_sync_data(get_called_class(),__FUNCTION__,func_get_args());
		return $result;
	}

	//list类型 从左至右统计
	public function lrange($id, $offset, $length)
	{
		$key = $this->prefix;
		if(!is_null($id)){
			$key = $this->prefix.$id;
		}
		return $this->cache->lrange($key, $offset, $length);
	}
	
	//裁剪指定之外的数据
	public function ltrim($id, $star, $end)
	{
		$key = $this->prefix;
		if(!is_null($id)){
			$key = $this->prefix.$id;
		}
		if(!is_numeric($star) || !is_numeric($end)){
			throw new \Exception('star or end not number', Error::PARAM_MISSED);
		}
		$result = $this->cache->ltrim($key, $star, $end);
		//数据同步
		self::set_sync_data(get_called_class(),__FUNCTION__,func_get_args());
		return $result;
	}

	/**
	 * Hmset
	 * 
	 * @param $key
	 * @param $arr
	 */
	public function hmset($key, array $arr)
	{
		$key = $this->prefix.$key;
		$fileds = array();
		
		foreach ($arr as $k => $v) {
			$fileds[$k] = $this->pack($v);
		}
		
		$result = $this->cache->hmset($key, $fileds);
		
		//数据同步
		self::set_sync_data(get_called_class(),__FUNCTION__,func_get_args());
		return $result;
	}

	/**
	 * Hmget
	 * 
	 * @param $key
	 * @param array $fields
	 * @return mixed
	 */
	public function hmget($key, array $fields)
	{
		$key = $this->prefix.$key;
		
		$res = $this->cache->hmget($key, $fields);
		$result = array();
		
		foreach ($res as $k => $v) {
			$result[$k] = $this->unpack($v);
		}
		
		return $result;
	}
	
	/**
	 * Hset
	 * 
	 * @param $key 键
	 * @param $field 字段
	 * @param $data 值
	 */
	public function hset($key,$field,$data)
	{
		if(empty($key) || empty($field)){
			throw new \Exception('param is empty', Error::PARAM_MISSED);
		}
		
		if(is_null($data)){
			throw new \Exception('value is null', Error::PARAM_INVALID);
		}
		
		$key = $this->prefix.$key;
		$result = $this->cache->hSet($key,$field,$this->pack($data));
		
		//数据同步
		self::set_sync_data(get_called_class(),__FUNCTION__,func_get_args());
		return $result;
	}

	/**
	 * hgetall
	 * 
	 * @param $key 键
	 */
	public function hgetall($key)
	{
		
		if(empty($key)){
			throw new \Exception('param is empty', Error::PARAM_MISSED);
		}
		
		$key = $this->prefix.$key;
		return $this->cache->hGetAll($key);
	}
	
	/**
	 * Hget
	 * 
	 * @param $key 键
	 * @param $field 字段
	 */
	public function hget($key,$field)
	{
		
		if(empty($key) || empty($field)){
			throw new \Exception('param is empty', Error::PARAM_MISSED);
		}
		
		$key = $this->prefix.$key;
		return $this->unpack($this->cache->hGet($key,$field));
	}
	
	/**
	 * 删除hash表的某个字段
	 * 
	 * @param $key
	 * @param $field
	 * @throws \Exception
	 * @return boolean
	 */
	public function hdel($key, $field)
	{
		if(!$key || !$field){
			throw new \Exception('param is empty', Error::PARAM_MISSED);
		}
		
		$key = $this->prefix . $key;
		
		$result = $this->cache->hDel($key, $field);
		
		//数据同步
		self::set_sync_data(get_called_class(),__FUNCTION__,func_get_args());
		return $result;
		
	}
	
	/**
	 * 获取hash表 key对应的field长度
	 * 
	 * @param $key
	 * @param $field
	 * @throws \Exception
	 * @return int
	 */
	public function hlen($key)
	{
		if(!$key){
			throw new \Exception('key is empty', Error::PARAM_MISSED);
		}
		
		$key = $this->prefix . $key;
		
		return $this->cache->hLen($key);
	}
	
	/**
	 * 获取hahs表的字段总数
	 * 
	 * @param $key
	 * @throws \Exception
	 * @return int
	 */
	public function hkeys($key)
	{
		if(!$key){
			throw new \Exception('key is empty', Error::PARAM_MISSED);
		}
		
		$key = $this->prefix . $key;
		
		return $this->cache->hKeys($key);
	}
	
	/**
	 * 获取hahs表的key对应的所有值
	 *
	 * @param $key
	 * @throws \Exception
	 * @return array
	 */
	public function hvals($key)
	{
		if(!$key){
			throw new \Exception('key is empty', Error::PARAM_MISSED);
		}
	
		$key = $this->prefix . $key;
	
		$res = $this->cache->hVals($key);
		if($res){
			foreach((array)$res as $k => $v){
				$res[$k] = $this->unpack($v);
			}
		}
		
		return $res;
	}
	
	//对某个键值设置过期时间
	public function expire($key, $time)
	{
		if(empty($time)) {
			throw new \Exception('param is empty', Error::PARAM_INVALID);
		}
	
		$key = $this->prefix.$key;
		$result = $this->cache->expire($key, $time);
	
		//数据同步
		self::set_sync_data(get_called_class(),__FUNCTION__,func_get_args());
		return $result;
	}

    /**
     * @param $key string
     * @param $value string
     * @return int  1 or 0
     */
    public function sadd($key,$value) {
        if(empty($key) || empty($value)){
            throw new \Exception('param is empty', Error::PARAM_MISSED);
        }
        $key = $this->prefix.$key;
        return $this->cache->sadd($key,$value);
    }

    /**
     * 功能:对集合就差集
     * @param $key1
     * @param $key2
     * @return array
     * @throws \Exception
     */
    public function sdiff($key1,$key2) {
        if(empty($key1) || empty($key2)) {
            throw new \Exception('param is empty', Error::PARAM_MISSED);
        }
        return $this->cache->sdiff($key1,$key2);
    }
    public function srem($key,$value) {
        if(empty($key) || empty($value)) {
            throw new \Exception('param is empty', Error::PARAM_MISSED);
        }
        return $this->cache->srem($key,$value);
    }
    //更换键名
    public function rename($old_key,$new_key) {
        if(empty($old_key) || empty($new_key)) {
            throw new \Exception('param is empty', Error::PARAM_MISSED);
        }
        return $this->cache->rename($old_key,$new_key);
    }
    //选择数据库
    public function select_database($database_index) {
        //数据库索引一定是数值
        if(empty($database_name) && !is_numeric($database_index)) {
            throw new \Exception('param is empty', Error::PARAM_MISSED);
        }
        return $this->cache->select($database_index);
    }
	
	// json encode
	protected function pack($data)
	{
		return json_encode($data, JSON_UNESCAPED_UNICODE);
	}

	// json decode
	protected function unpack($data)
	{
		return json_decode($data, true);
	}
	
	//同步
	protected function set_sync_data($called_class,$function,array $params)
	{
		//socket对象不为空，执行同步
		if(!empty($this->cache->socket)){
			if($this->is_sync){
				$sync_table=Core::get_config('const','sync_table');
				if ($sync_table && in_array($called_class,$sync_table)){
					\Golo\Api\DataSync::send(L_APP_NAME,$called_class,$function, $params);
				}
			}
		}
	}
	
}