<?php
<?php
namespace App\Action;
define('L_APP_PATH',dirname(__DIR__).'/');
define('L_WORKSPACE_PATH',dirname(L_APP_PATH) . '/');

include_once L_WORKSPACE_PATH.'common/showClass.php';
use microFrame\common\showClass;
class test extends \microFrame\lavender\BaseClass
{
    public function test1_action() {
        $report = array(11);
        $show = new showClass();
        return $show->success('这是一个测试',$report);
    }
}
