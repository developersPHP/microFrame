<?php
namespace Lavender\Dao;
define('L_APP_PATH',dirname(__DIR__).'/');
define('L_WORKSPACE_PATH',dirname(L_APP_PATH) . '/');
include_once L_WORKSPACE_PATH.'lavender/Dao/KvTable.php';
use Lavender\Errno;

class SessionKvTable extends KvTable
{
	const DATA_MAX_LENGTH = 4096;

	/**
	 * database config name
	 * @var string
	 */
	protected $database = 'session';

	/**
	 * table
	 * @var string
	 */
	protected $table = 'kv_session';

	//session dao interface
	// public function set($id, $data, $time)
	// public function update_time($id, $time)
	// public function delete($id)
	// public function get_raw_record($id)

	protected function pack($data)
	{
		$json = json_encode($data, JSON_UNESCAPED_UNICODE);

		if (strlen($json) > self::DATA_MAX_LENGTH) {
			throw new Exception("session data over than the max length", Errno::PACK_FAILED);
		}

		return $json;
	}
}
