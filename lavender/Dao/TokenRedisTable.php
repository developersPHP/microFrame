<?php
namespace microFrame\lavender\Dao;

class TokenRedisTable extends \Lavender\Dao\RedisTable
{
	protected $table = 'token';
	protected $prefix = 'token_';
}