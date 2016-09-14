<?php
namespace mircoFrame\common;

class Errno
{
	//系统错误代码，action中不引用
	//base 10**
	const UNKNOW = 1000;
	const VAR_UNDEFINED = 1001;
	const INDEX_UNDEFINED = 1002; //for array
	const CONST_UNDEFINED = 1003;

	const PARAM_INVALID = 1015;
	const PARAM_MISSED = 1016;
	const DEFINED_INVALID = 1017;

	const INPUT_PARAM_INVALID = 1022;
	const INPUT_PARAM_MISSED = 1023;
	const INPUT_PARAM_NONUNIQUE = 1024;// input param not unique
	const INPUT_VERIFY_CODE_MISSED = 1025;  // input verify code missed
	
	const INPUT_SENSITIVE_WORD_INVALID = 1026; // input sensitive word.

	//auth 12**
	const AUTH_FAILED = 1201; //401
	const SESSION_INVALID = 1211;
	const SESSION_TIMEOUT = 1212;
	const SESSION_ID_INVALID = 1213;
	const SIGN_FAILED = 1214;
	
	//file 13**
	const FILE_NOTFOUND = 1301;

	const DB_FAILED = 1401;
	const DB_CONNECT_FAILED = 1402;

	const FILE_FAILED = 1500;
	const MKDIR_FAILED = 1510;

	const NETWORK_FAILED = 1600;

	//logic 17**
	const CONFIG_TYPE_INVALID = 1701;
	const CONFIG_ITEM_INVALID = 1702;

	const SERIALIZE_FAILED = 1731;
	const UNSERIALIZE_FAILED = 1732;
	const PACK_FAILED = 1733;
	const UNPACK_FAILED = 1734;

	const ITEM_NOT_FOUND = 1741;

	const IMAGE_TYPE_INVALID = 1751;
	const IMAGE_SAVE_FAILED = 1752;

	const TOKEN_VERIFY_FAILED = 1761;
	
	// mongo exception errno
	const MONGO_CONNECT = 1801;
	const MONGO_CURSOR = 1802;
	const MONGO_CURSOR_TIMEOUT = 1803;
	const MONGO_RESULT = 1804;
	const MONGO_GRIDFS = 1805;
	const MONGO_WRITE_CONCERN = 1806;
	const MONGO_PROTOCOL = 1807;
	const MONGO_DUPLICATE = 1808;
	const MONGO_EXCEPTION_TIMEOUT = 1809;
}