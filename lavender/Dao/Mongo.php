<?php
namespace microFrame\lavender\Dao;

use microFrame\lavender\Core;
use microFrame\common\Error;

/**
 * Mongo Operater Class
 * based on MongoClient
 * @author Mervyn <tiezhu.tang@cnlaunch.com>
 * @author Norhon <zhenqiu.wu@cnlaunch.com>
 * @version 1.0.0
 */
class Mongo
{
	// 连接信息
	protected $_host = '127.0.0.1';
	protected $_port = 27017;
	protected $_username = '';
	protected $_password = '';
	protected $_options  = array();
	
	// 资源信息
	protected $_db = null;
	protected $_db_link = null;
	protected $_table   = null;
	protected $_database   = '';
	protected $_collection = null;

	// 配置信息
	private $reconn_num = 0;
	private $is_replica_set = false; // 是否复制集连接方式

	const SLEEP_TIME = 3; // 睡眠时间秒
	const TIME_OUT_MS = 3000; // 超时时间毫秒

	public function __construct($host, $database, $port = 27017, $options = array(), $username = '', $password = '')
	{
		// 超时设置. ms
		/*if (!isset($options['connectTimeoutMS'])) {
			$options['connectTimeoutMS'] = self::TIME_OUT_MS;
		}*/
		
		$this->_host = $host;
		$this->_port = $port;
		$this->_database = $database;
		$this->_options  = $options;
		$this->_username = $username;
		$this->_password = $password;
	}

	/**
	 * connect & selectdb
	 */
	public function open($table = null, $reconnect = false)
	{
		if (!empty($table) && $this->_table !== $table) {
			$this->_table = $table;
		}
		if ($this->_db_link && !$reconnect) {
			return;
		}
		try {
			if (is_array($this->_host)) {
				// 复制集多点连接
				$this->is_replica_set = true;
				$mongodb = 'mongodb://' . implode(',', $this->_host);
				$this->_db_link = new \MongoClient($mongodb, $this->_options);
			} else {
                // 单点连接
				$this->_db_link = new \MongoClient("mongodb://{$this->_host}:{$this->_port}", $this->_options);
			}
		} catch (\MongoConnectionException $e) {
			Core::log('db_connect', 'mongo_connect: ' . date('Y-m-d H:i:s') . '; message:' . $e->getMessage() . ', code:' . $e->getCode() . ', trace:' . var_export($e->getTrace()[0], true) . "\n");
			$this->_db_link === null;
		}
		
		if ($this->m_reconn()) {
			$this->open($table, $reconnect);
		}

		if ($this->_db_link === null) {
			return null;
		}
		try {
			$this->_db = $this->_db_link->selectDB($this->_database);

		} catch (Exception $e) {
			Core::log('db_select', 'mongo_selectDB: ' . date('Y-m-d H:i:s') . '; message:' . $e->getMessage() . ', code:' . $e->getCode() . ', trace:' . var_export($e->getTrace()[0], true) . "\n");
			return null;
		}
		if ($this->_table !== null) {
			$collections = $this->get_collections();
			if (!in_array($this->_table, $collections)) {
                throw new Exception('table is not existing', Error::PARAM_INVALID);
			}
			$this->_collection = new \MongoCollection($this->_db, $this->_table);

		}
	}

	// 管理重新连接
	public function m_reconn()
	{
		if ($this->is_replica_set && !$this->_db_link && $this->reconn_num < 3) {
			$this->reconn_num ++ ;
			sleep(self::SLEEP_TIME);
			return true;
		}
		
		return false;
	}

	/**
	 * select and return an array
	 *
	 * @param string $table   表名
	 * @param string $query   要搜索的字段
	 * @param string $fields  返回结果的字段 (eg. array('fieldname' => true, 'fieldname2' => true))
	 * @param string $order   要排序的字段(eg. array('id' => 1, 'name' => -1))
	 * @param int $offset limit offset
	 * @param int $length limit size
	 *
	 * @return array
	 */
	public function get($table, $query = array(), $fields = array(), $order = array(), $offset = '', $length = '', $is_res_id = false, $reconnect = false, $is_distribute=false)
	{
		$result = array();
		if ($is_distribute) {
			if (!$this->_check_date_name($table) ) {
				throw new Exception("table name invalid", Error::PARAM_INVALID);
			}
		} else {
			if (!$this->_check_name($table) ) {
				throw new Exception("table name invalid", Error::PARAM_INVALID);
			}
		}
		
		if (!$this->_check_query($query)) {
			throw new Exception("param query not an invalid", Error::PARAM_INVALID);
		}
		
		if (!$this->_check_fields($fields)) {
            throw new Exception("fields invalid", Error::PARAM_INVALID);
		}
		//连接数据库
		$this->open($table, $reconnect);

		try {
			$cursor = $this->_collection->find($query, $fields);
		} catch (\MongoConnectionException $e) {
			Core::log('db_sursor', 'mongo_connect: ' . date('Y-m-d H:i:s') . '; message:' . $e->getMessage() . ', code:' . $e->getCode() . ', trace:' . var_export($e->getTrace()[0], true) . "\n");
			return null;
		} catch (\MongoCursorException $e) {
			Core::log('db_connect', 'mongo_sursor: ' . date('Y-m-d H:i:s') . '; message:' . $e->getMessage() . ', code:' . $e->getCode() . ', trace:' . var_export($e->getTrace()[0], true) . "\n");
			return null;
		}
		
		//check it whether to order 
		if (!empty($order)) {
			if (!$this->_check_order($order)) {
				throw new Exception("order invalid", Error::PARAM_INVALID);
			}
			$cursor = $cursor->sort($order);
		}
		
		if (!empty($offset)) {
			if (!is_numeric($offset)) {
				throw new Exception("param offset not an invalid", Error::PARAM_INVALID);
			}
			
			$cursor = $cursor->skip($offset);
		}
		
		if (!empty($length)) {
			if (!is_numeric($length)) {
				throw new Exception("param length not an invalid", Error::PARAM_INVALID);
			}
			
			$cursor = $cursor->limit($length);
		}

		// return iterator_to_array($cursor);
		foreach ($cursor as $val) {
			//判断是否返回_id
			if (!$is_res_id) {
				unset($val['_id']);
			}
			$result[] = $val;
		}
		
		return $result;
	}

	/**
	 * 按条件查找一条记录
	 * @param string $table   表名
	 * @param string $query   要搜索的字段
	 * @param string $fields  返回结果的字段 (eg. array('fieldname' => true, 'fieldname2' => true))
	 *
	 * @return array
	 */
	public function get_single($table, $query = array(), $fields = array())
	{
		if (!$this->_check_name($table) ) {
			throw new Exception("table name invalid", Error::PARAM_INVALID);
		}
		
		if (!$this->_check_query($query)) {
			throw new Exception("param query not an invalid", Error::PARAM_INVALID);
		}
		
		if (!$this->_check_fields($fields)) {
			throw new Exception("field invalid", Error::PARAM_INVALID);
		}
		
		$this->open($table);
		
		try {
			$res = $this->_collection->findOne($query, $fields);
		} catch (\MongoCursorException $e) {
			Core::log('db_sursor', 'mongo_sursor: ' . date('Y-m-d H:i:s') . '; message:' . $e->getMessage() . ', code:' . $e->getCode() . ', trace:' . var_export($e->getTrace()[0], true) . "\n");
			return null;
		} catch (\MongoConnectionException $e) {
			Core::log('db_connect', 'mongo_connect: ' . date('Y-m-d H:i:s') . '; message:' . $e->getMessage() . ', code:' . $e->getCode() . ', trace:' . var_export($e->getTrace()[0], true) . "\n");
			return null;
		}
	
		return $res;
	}

	/**
	 * Count records by condition
	 *
	 * @param string $table  要统计的表名
	 * @param array  $query  要搜索的字段
	 * @param int    $offset limit offset
	 * @param int    $length limit size
	 *
	 * @return int
	 */
	public function count($table, $query = array(), $offset = null, $length = null)
	{
		if (!$this->_check_name($table) ) {
			throw new Exception("table name invalid", Error::PARAM_INVALID);
		}
		
		if (!$this->_check_query($query)) {
			throw new Exception("param query not an invalid", Error::PARAM_INVALID);
		}
		
		$this->open($table);
		
		$cursor = $this->_collection->find($query);
		
		try {
			$cursor = $this->_collection->find($query);
		} catch (\MongoCursorException $e) {
			Core::log('db_cursor', 'mongo_cursor: ' . date('Y-m-d H:i:s') . '; message:' . $e->getMessage() . ', code:' . $e->getCode() . ', trace:' . var_export($e->getTrace()[0], true) . "\n");
			return null;
		} catch (\MongoConnectionException $e) {
			Core::log('db_connect', 'mongo_connect: ' . date('Y-m-d H:i:s') . '; message:' . $e->getMessage() . ', code:' . $e->getCode() . ', trace:' . var_export($e->getTrace()[0], true) . "\n");
			return null;
		}
		
		if (!is_null($offset)) {
			if (!is_numeric($offset)) {
				throw new Exception("param offset not an invalid", Error::PARAM_INVALID);
			}
			
			$cursor = $cursor->skip($offset);
		}
		
		if (!is_null($length)) {
			if (!is_numeric($length)) {
				throw new Exception("param length not an invalid", Error::PARAM_INVALID);
			}
			
			$cursor = $cursor->limit($length);
		}
		
		try {
			$res = $cursor->count(true);
		} catch (\MongoCursorException $e) {
			Core::log('db_cursor', 'mongo_cursor: ' . date('Y-m-d H:i:s') . '; message:' . $e->getMessage() . ', code:' . $e->getCode() . ', trace:' . var_export($e->getTrace()[0], true) . "\n");
			return 0;
		} catch (\MongoConnectionException $e) {
			Core::log('db_connect', 'mongo_connect: ' . date('Y-m-d H:i:s') . '; message:' . $e->getMessage() . ', code:' . $e->getCode() . ', trace:' . var_export($e->getTrace()[0], true) . "\n");
			return 0;
		}
		
		return $res;
	}

	/**
	 * insert records
	 * @param  string $table   
	 * @param  array $content 插入的内容
	 * @param  array  $options Options for the insert
	 */
	public function insert($table, $content, $options = array())
	{
		if (!$this->_check_name($table) ) {
			throw new Exception("param invalid", Error::PARAM_INVALID);
		}
		
		$this->open($table);
		
		try {
			$res = $this->_collection->insert($content, $options);
		} catch (\MongoCursorException $e) {
			Core::log('db_cursor', 'mongo_cursor: ' . date('Y-m-d H:i:s') . '; message:' . $e->getMessage() . ', code:' . $e->getCode() . ', trace:' . var_export($e->getTrace()[0], true) . "\n");
			return 0;
		} catch (\MongoConnectionException $e) {
			Core::log('db_connect', 'mongo_connect: ' . date('Y-m-d H:i:s') . '; message:' . $e->getMessage() . ', code:' . $e->getCode() . ', trace:' . var_export($e->getTrace()[0], true) . "\n");
			return 0;
		}
		
		return $res;
	}

	/**
	 * update the objects 
	 * @param  string $table      要更新的集合名称
	 * @param  array $criteria   更新的条件
	 * @param  array $new_object 更新的内容
	 * @param  array  $options    更新的额外设置
	 */
	public function update($table, $criteria, $new_object, $is_reconnect = false, $options = array())
	{
		if (!$this->_check_name($table) ) {
			throw new Exception("param invalid", Error::PARAM_INVALID);
		}
		
		$this->open($table, $is_reconnect);
		
		try {
			$res = $this->_collection->update($criteria, $new_object, $options);
		} catch (\MongoCursorException $e) {
			Core::log('db_cursor', 'mongo_cursor: ' . date('Y-m-d H:i:s') . '; message:' . $e->getMessage() . ', code:' . $e->getCode() . ', trace:' . var_export($e->getTrace()[0], true) . "\n");
			return 0;
		} catch (\MongoConnectionException $e) {
			Core::log('db_connect', 'mongo_connect: ' . date('Y-m-d H:i:s') . '; message:' . $e->getMessage() . ', code:' . $e->getCode() . ', trace:' . var_export($e->getTrace()[0], true) . "\n");
			return 0;
		}
		
		return $res;
	}

	/**
	 * update_or_insert the objects
	 * @param  string $table      要更新的集合名称
	 * @param  array $criteria   更新的条件
	 * @param  array $new_object 更新的内容
	 * @param  $upsert    true则插，false则不插
	 * @param  $multi    false则更新找到的一条，ture则全部更新
	 */
	public function update_or_insert($table, $criteria, $new_object, $is_reconnect = false, $upsert,$multi)
	{
		if (!$this->_check_name($table) ) {
			throw new Exception("param invalid", Error::PARAM_INVALID);
		}
		
		$this->open($table, $is_reconnect);
		
		try {
			$res = $this->_collection->update($criteria, $new_object, $upsert, $multi);
		} catch (\MongoCursorException $e) {
			Core::log('db_cursor', 'mongo_cursor: ' . date('Y-m-d H:i:s') . '; message:' . $e->getMessage() . ', code:' . $e->getCode() . ', trace:' . var_export($e->getTrace()[0], true) . "\n");
			return 0;
		} catch (\MongoConnectionException $e) {
			Core::log('db_connect', 'mongo_connect: ' . date('Y-m-d H:i:s') . '; message:' . $e->getMessage() . ', code:' . $e->getCode() . ', trace:' . var_export($e->getTrace()[0], true) . "\n");
			return 0;
		}
		
		return $res;
	}

	/**
	 * delete the objects 
	 * @param  string $table      要操作的集合名称
	 * @param  array $criteria   删除的条件
	 * @param  array  $options    删除的额外设置
	 */
	public function delete($table, $criteria, $is_reconnect = false, $options = array())
	{
		if (!$this->_check_name($table) ) {
			throw new Exception("param invalid", Error::PARAM_INVALID);
		}
		
		$this->open($table, $is_reconnect);
		
		try {
			$res = $this->_collection->remove($criteria, $options);
		} catch (\MongoCursorException $e) {
			Core::log('db_cursor', 'mongo_cursor: ' . date('Y-m-d H:i:s') . '; message:' . $e->getMessage() . ', code:' . $e->getCode() . ', trace:' . var_export($e->getTrace()[0], true) . "\n");
			return 0;
		} catch (\MongoConnectionException $e) {
			Core::log('db_connect', 'mongo_connect: ' . date('Y-m-d H:i:s') . '; message:' . $e->getMessage() . ', code:' . $e->getCode() . ', trace:' . var_export($e->getTrace()[0], true) . "\n");
			return 0;
		}
		
		return $res;
	}

	/**
	 * batchinsert records
	 * @param  string $table   
	 * @param  array $content 插入的内容
	 * @param  array  $options Options for the insert
	 */
	public function batch_insert($table, $content, $options = array())
	{
		if (!$this->_check_name($table) ) {
			throw new Exception("param invalid", Error::PARAM_INVALID);
		}
		
		$this->open($table);
		
		try {
			$res = $this->_collection->batchInsert($content, $options);
		} catch (\MongoCursorException $e) {
			Core::log('db_cursor', 'mongo_cursor: ' . date('Y-m-d H:i:s') . '; message:' . $e->getMessage() . ', code:' . $e->getCode() . ', trace:' . var_export($e->getTrace()[0], true) . "\n");
			return null;
		} catch (\MongoConnectionException $e) {
			Core::log('db_connect', 'mongo_connect: ' . date('Y-m-d H:i:s') . '; message:' . $e->getMessage() . ', code:' . $e->getCode() . ', trace:' . var_export($e->getTrace()[0], true) . "\n");
			return null;
		}
		
		return $res;
	}

	/**
	 * 获取数据库中的集合名
	 * @param boolean $inc_sys_collections 是否包含系统的集合名称
	 * @return array
	 */
	public function get_collections($inc_sys_collections = false)
	{
		if (!$this->_db || !$this->_db_link) {
			$this->open();
		}
		
		try {
			$res = $this->_db->getCollectionNames($inc_sys_collections);
		} catch (\MongoConnectionException $e) {
			Core::log('db_connect', 'mongo_connect: ' . date('Y-m-d H:i:s') . '; message:' . $e->getMessage() . ', code:' . $e->getCode() . ', trace:' . var_export($e->getTrace()[0], true) . "\n");
			return null;
		} catch (\MongoCursorException $e) {
			Core::log('db_cursor', 'mongo_cursor: ' . date('Y-m-d H:i:s') . '; message:' . $e->getMessage() . ', code:' . $e->getCode() . ', trace:' . var_export($e->getTrace()[0], true) . "\n");
			return null;
		}
		
		return $res;
	}

	/**
	 * check table name
	 * @param string $string
	 * @return boolean
	 */
	protected function _check_name($string)
	{
		if (preg_match('/^[a-z0-9_\.]+$/i', $string)) {
			return true;
		}

		return false;
	}

	/**
	 * 检测查询条件数组
	 * @param array $query 查询的条件
	 * @return boolean
	 * 
	 */
	protected function _check_query($query = array())
	{
		if (!is_array($query)) {
			return false;
		}
		
		foreach ($query as $key => $val) {
			if (is_array($val)) {
				foreach ($val as $k => $v) {
					if (!preg_match('/(^\$[a-z0-9_]+$)/i', $k) && !is_numeric($k)) {
						return false;
					}
				}
			}
			
			if (!is_array($val) && !preg_match('/^[a-z0-9_\.]+$/i', $key)) {
				return false;
			}
		}
		
		return true;
	}

	/**
	 * 判断返回的字段列表是否合法 (应该形如 array('fields' => true, 'fields1' => false, ...))
	 * @param  array $fields
	 * @return boolean
	 */
	protected function _check_fields($fields)
	{
		if (!is_array($fields)) {
			return false;
		}

        foreach ($fields as $key => $val) {
			if (!is_bool($val)) {
				return false;
			}
		}
        return true;
	}

	/**
	 * 检查需要排序的字段是否合法 （应该形如 array('fields1' => 1, 'fields2' => -1, ....)）
	 */
	protected function _check_order($sort)
	{
		if (!is_array($sort)) {
			return false;
		}
		
		foreach ($sort as $key => $value) {
			if (abs($value) != 1 || !preg_match('/^[a-z0-9_]+$/i', $key)) {
				return false;
			}
		}
		
		return true;
	}

	protected function _check_date_name($string)
	{
		if (preg_match('/^[0-9]{4}[\-](1[0-2]|0[1-9])[\-](0[1-9]|[12][0-9]|3[01])$/', $string)) {
			return true;
		}

		return false;
	}

	/**
	 * 执行MongoDB原生函数
	 * @param  string $table   集合名称
	 * @param  array $command 要执行的函数操作
	 */
	public function command($command)
	{
		if (!is_array($command)) {
			throw new Exception("param must be array", Error::PARAM_INVALID);
		}
		
		if ($this->_db === null) {
			return null;
		}
		
		try {
			$res = $this->_db->command($command);
		} catch (\MongoCursorException $e) {
			Core::log('db_cursor', 'mongo_cursor: ' . date('Y-m-d H:i:s') . '; message:' . $e->getMessage() . ', code:' . $e->getCode() . ', trace:' . var_export($e->getTrace()[0], true) . "\n");
			return null;
		} catch (\MongoConnectionException $e) {
			Core::log('db_connect', 'mongo_connect: ' . date('Y-m-d H:i:s') . '; message:' . $e->getMessage() . ', code:' . $e->getCode() . ', trace:' . var_export($e->getTrace()[0], true) . "\n");
			return null;
		}
		
		return $res;
	}

	/**
	 * calculate aggregated values
	 * @param  array $pipeline   An array of pipeline operators, or just the first operator.
	 */
	public function aggregate($table, $pipeline, $is_reconnect = false)
	{
		if (!is_array($pipeline)) {
			throw new Exception("param must be array", Error::PARAM_INVALID);
		}

		$this->open($table, $is_reconnect);
		try {
			$res = $this->_collection->aggregate($pipeline);
		} catch (\MongoCursorException $e) {
			Core::log('db_cursor', 'mongo_cursor: ' . date('Y-m-d H:i:s') . '; message:' . $e->getMessage() . ', code:' . $e->getCode() . ', trace:' . var_export($e->getTrace()[0], true) . "\n");
			return null;
		} catch (\MongoConnectionException $e) {
			Core::log('db_connect', 'mongo_connect: ' . date('Y-m-d H:i:s') . '; message:' . $e->getMessage() . ', code:' . $e->getCode() . ', trace:' . var_export($e->getTrace()[0], true) . "\n");
			return null;
		}
		
		return $res;
	}
	
	public function __destruct()
	{
		try{
			if (empty($this->_db_link)) {
				return;
			}
			
			$conns = $this->_db_link->getConnections();
			foreach ($conns as $con) {
				// 关闭备份节点
				if ($con['connection']['connection_type_desc'] === 'SECONDARY') {
					$this->_db_link->close($con['hash']);
				}
			}
		} catch (\MongoConnectionException $e) {
			Core::log('db_connect', 'mongo_destruct: ' . date('Y-m-d H:i:s') . '; message:' . $e->getMessage() . ', code:' . $e->getCode() . ', trace:' . var_export($e->getTrace()[0], true) . "\n");
		}
	}

    /**receive change other collection to insert contents
     * @param $table
     * @param $content
     * @param bool $reconnection
     * @param array $options
     * @return int
     * @throws Exception
     */
    public function repeat_insert($table,$content,$reconnection = false,$options = array())
    {
        if (!$this->_check_name($table) ) {
            throw new Exception("param invalid", Error::PARAM_INVALID);
        }

        $this->open($table,$reconnection);

        try {
            $res = $this->_collection->insert($content, $options);
        } catch (\MongoCursorException $e) {
            Core::log('db_cursor', 'mongo_cursor: ' . date('Y-m-d H:i:s') . '; message:' . $e->getMessage() . ', code:' . $e->getCode() . ', trace:' . var_export($e->getTrace()[0], true) . "\n");
            return 0;
        } catch (\MongoConnectionException $e) {
            Core::log('db_connect', 'mongo_connect: ' . date('Y-m-d H:i:s') . '; message:' . $e->getMessage() . ', code:' . $e->getCode() . ', trace:' . var_export($e->getTrace()[0], true) . "\n");
            return 0;
        }

        return $res;
    }
}