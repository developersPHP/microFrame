<?php
use Lavender\Validator;
?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>跳转提示</title>
    <style type="text/css">
        *{ padding: 0; margin: 0; }
        body{ background: #fff; font-family: '微软雅黑'; color: #333; font-size: 16px; }
        .system-message{ padding: 24px 48px; width: 420px;height: 200px;position: absolute;left: 50%;top: 50%;margin-left: -210px;margin-top: -100px;}
        .system-message h1{ font-size: 100px; font-weight: normal; line-height: 120px; margin-bottom: 12px; }
        .system-message .jump{ padding-top: 10px}
        .system-message .jump a{ color: #333;}
        .system-message .success,.system-message .error{ line-height: 1.8em; font-size: 20px; }
        .system-message .detail{ font-size: 12px; line-height: 20px; margin-top: 12px; display:none}
    </style>
</head>
<body>
<div class="system-message">
    <present name="message">
    </present>
    <p class="detail"></p>
    <p style="height: 100px; width: 300px; margin: 20px auto">
    	<?php echo $msg?>
    	<?php if (!empty($url)) { ?>
    		<a href="<?php echo $url?>">点击这里返回</a>
    	<?php }?>
    </p>
</div>
</body>
</html>