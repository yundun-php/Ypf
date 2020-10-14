<?php
/*
error_reporting(E_ALL ^ E_WARNING ^ E_NOTICE ^ E_DEPRECATED);
ini_set("display_errors","On");
define('ERROR_DISPLAY_CLI', true);
define('ERROR_DISPLAY_HTML', false);
define('SYS_KEY', 'apiv4');


register_shutdown_function(array(new \Ypf\Lib\ErrorHandleV1(),'Shutdown'));
set_error_handler(array(new \Ypf\Lib\ErrorHandleV1(), 'Error'));
set_exception_handler(array(new \Ypf\Lib\ErrorHandleV1(),'Exception'));
 */
namespace Ypf\Lib;

if(!defined('ERROR_DISPLAY_CLI'))   define('ERROR_DISPLAY_CLI',  false);
if(!defined('ERROR_DISPLAY_HTML'))  define('ERROR_DISPLAY_HTML',  true);
if(!defined('ERROR_TRACELOG_JSON')) define('ERROR_TRACELOG_JSON', true);
if(!defined('ERROR_LOG_PREKEY')) define('ERROR_LOG_PREKEY', '/tmp/log_phperror_');
if(!defined('SYS_KEY')) define('SYS_KEY', 'default');

if(in_array(ini_get('display_errors'), ["On", "on", "1"])) ini_set("display_errors","On");
if(in_array(ini_get('display_errors'), ["Off", "off", "0"])) ini_set("display_errors","Off");

define('ERROR_LOGFILE', ERROR_LOG_PREKEY.SYS_KEY.'.log');

//按日期切割
if(!file_exists(ERROR_LOGFILE)) touch(ERROR_LOGFILE);
$cDate = date('Ymd', ErrorHandleV1::fctime(ERROR_LOGFILE));
if(date('Ymd') != $cDate) ErrorHandleV1::cutLog(ERROR_LOGFILE);

class ErrorHandleV1 {

    static public $logDate = null;

    static private $errnoMap = [
        1     => 'E_ERROR',
        2     => 'E_WARNING',
        4     => 'E_PARSE',
        8     => 'E_NOTICE',
        16    => 'E_CORE_ERROR',
        32    => 'E_CORE_WARNING',
        64    => 'E_CORE_ERROR',
        128   => 'E_CORE_WARNING',
        256   => 'E_USER_ERROR',
        512   => 'E_USER_WARNING',
        1024  => 'E_USER_NOTICE',
        2048  => 'E_STRICT',
        4096  => 'E_RECOVERABLE_ERROR',
        8192  => 'E_DEPRECATED',
        16384 => 'E_USER_DEPRECATED',
    ];

    static private $_tplTrace = <<<EOF
<!DOCTYPE html><html><head><title>Error Occurred</title>
<meta http-equiv="content-type" content="text/html;charset=utf-8"/><style>
    body{font-family: 'Microsoft Yahei', Verdana, arial, sans-serif; font-size:14px; }
    a{text-decoration:none;color:#174B73;}
    a:hover{ text-decoration:none;color:#FF6600;}
    .title{margin:4px 0; color:#F60; font-weight:bold;}
    .message,#trace{padding:1em;border:solid 1px #000;margin:10px 0;background:#FFD;line-height:150%;}
    .message{background:#FFD;color:#2E2E2E;border:1px solid #E0E0E0;}
    #trace{background:#E7F7FF; border:1px solid #E0E0E0; color:#535353;}
    .code{overflow:auto;padding:5px;background:#EEE;border:1px solid #ddd;}
    .notice{padding:10px;margin:5px;color:#666;background:#FCFCFC;border:1px solid #E0E0E0;}
    .red{color:red;font-weight:bold;}
    code{font-size:14px!important;padding:0 .2em!important;border-bottom:1px solid #DEDEDE !important}
</style></head><body><div class="notice">
    <p><strong>[ Location ]</strong>　FILE: <span class="red">#FILE#</span>　LINE: <span class="red">#LINE#</span></p>
    <div class="code">#CODE#</div>
    <p class="title">[ Info ]</p>
    <p class="message"><strong>#TYPE#</strong> :  #MSG#</p>
    <p class="title">[ Trace ]</p>
    <p id="trace">#TRACE#</p>
</div></body></html>
EOF;
    static private $_tplCliDefault = "the system have error, please check log to fixed bug!!!\r\n";
    static private $_tplHtmlDefault = <<<EOF
<html><head><meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
<title>页面提示</title><style type="text/css">
*{margin:0px;padding:0px;font-size:12px;font-family:Arial,Verdana;}
#wrapper{width:450px;height:200px;background:#F5F5F5;border:1px solid #D2D2D2;position:absolute;top:40%;left:50%;margin-top:-100px;margin-left:-225px;}
p.msg-title{width:100%;height:30px;line-height:30px;text-align:center;color:#EE7A38;margin-top:40px;font:14px Arial,Verdana;font-weight:bold;}
p.message{width:100%;height:40px;line-height:40px;text-align:center;color:blue;margin-top:5px;margin-bottom:5px;}
p.error{width:100%;height:40px;line-height:40px;text-align:center;color:red;margin-top:5px;margin-bottom:5px;}
p.notice{width:100%;height:25px;line-height:25px;text-align:center;}
</style></head><body><div id="wrapper">
<p class="msg-title">系统错误</p>
<p class="notice"><a href="/index">返回首页</a></p>
</div></body></html>
EOF;

    public function __construct() {
        if(!self::$logDate) self::$logDate = date('Ymd');
    }

    public function Error($errno, $msg, $file, $line) {
        $trace    = debug_backtrace();
        unset($trace[0]);
        $errorRaw = [
            "create_at" => date('Y-m-d H:i:s'),
            "type"      => self::$errnoMap[$errno],
            "file"      => $file,
            "line"      => $line,
            "msg"       => $msg,
            "trace"     => self::formatTrace(self::filterTrace($trace)),
            "syskey"    => SYS_KEY,
        ];
        self::writeLog($errorRaw);

        if(!($errno & ini_get('error_reporting'))) return;
        //if($errno != E_ERROR) return;
        if(substr(php_sapi_name(), 0, 3) != 'cli') ob_clean();
        self::display($errorRaw, ini_get('display_errors'));
    }

    public  function Exception($exception) {
        $trace = array_reverse($exception->getTrace());
        krsort($trace);
        $file = $exception->getFile();
        $line = $exception->getLine();
        $errorRaw = [
            "create_at" => date('Y-m-d H:i:s'),
            "type"      => get_class($exception),
            "file"      => $file,
            "line"      => $line,
            "msg"       => $exception->getMessage(),
            "trace"     => self::formatTrace(self::filterTrace($trace)),
            "syskey"    => SYS_KEY,
        ];
        self::writeLog($errorRaw);

        if(E_ERROR & ini_get('error_reporting')) {
            self::display($errorRaw, ini_get('display_errors'));
        } else {
            self::display($errorRaw, 'Off', 0);
        }
        exit;
    }

    public function Shutdown() {
        if($error = error_get_last()) {
            if(($error['type'] & ini_get('error_reporting')) !== $error['type']) return;
            $this->Error($error['type'], $error['message'], $error['file'], $error['line']);
        }
    }

    static public function writeLog($errorRaw = []) {
        if(self::$logDate != date('Ymd')) {
            ErrorHandleV1::cutLog();
            self::$logDate = date('Ymd');
        }

        if(ERROR_TRACELOG_JSON) {
            file_put_contents(ERROR_LOGFILE, date("Y-m-d H:i:s")."\t".json_encode($errorRaw)."\n", FILE_APPEND);
        } else {
            file_put_contents(ERROR_LOGFILE, date("Y-m-d H:i:s")."\t".self::formatCli($errorRaw)."\n\n", FILE_APPEND);
        }
    }

    static public function display($errorRaw = [], $displayErrors = 'On', $isExit = 1) {
        if(ini_get('display_errors') == 'On') {
            if(ERROR_DISPLAY_CLI)  echo self::formatCli($errorRaw)."\r\n";
            if(ERROR_DISPLAY_HTML) echo self::formatHtml($errorRaw);
        } else {
            if(ERROR_DISPLAY_CLI)  echo self::$_tplCliDefault;
            if(ERROR_DISPLAY_HTML) echo self::$_tplHtmlDefault;
        }
        if($isExit) exit();
    }

    static public function errorCode($filePath, $lineno, $lineLimit = 5) {
        if(!is_file($filePath)) return [];
        $start = $lineno - 1 - $lineLimit;
        $rows = array_slice(file($filePath), $start, $lineLimit * 2);
        array_walk($rows, function(&$line, $key, $start) {$line = ($start + $key + 1).":\t".rtrim($line);}, $start);
        $rows[$lineLimit] = rtrim($rows[$lineLimit]) . " //!!!please fix bug in here!!!";
        return $rows;
    }

    static public function filterTrace($traces) {
        $rows = [];
        foreach($traces as $trace) {
            if(!isset($trace['file'])) continue;
            $rows[] = [
                "file"     => $trace["file"],
                "line"     => $trace["line"],
                "class"    => isset($trace["class"]) ? $trace["class"] : '',
                //"object" => isset($trace["object"]) ? $trace["object"] : '',          //对象太大，不存储
                "type"     => isset($trace["type"]) ? $trace["type"] : '',
                "function" => isset($trace["function"]) ? $trace["function"] : '',
                "args"     => isset($trace["args"]) ? $trace["args"] : '',            //对象太大，不存储
            ];
        }
        return $rows;
    }

    static public function formatTrace($traces = []) {
        array_walk($traces, function(&$v, $k) {
            //格式化args
            array_walk($v['args'], function(&$arg, $k) {
                if(is_array($arg)) {
                    foreach($arg as &$item) {
                        if(is_object($item)) $item = "obj:".get_class($item);
                    }
                }
                if(is_object($arg))$arg = "obj:".get_class($arg);
            });
            //格式化trace
            $v = "#{$k} {$v['file']}({$v['line']}): {$v['class']}{$v['type']}{$v['function']}(".json_encode($v['args']).")";
        });
        return $traces;
    }

    static public function formatCli($info) {
        $lines = [];
        $lines[] = "{$info['type']}: {$info['msg']} in {$info['file']} {$info['line']}";
        $lines[] = implode("\r\n", self::errorCode($info['file'], $info['line']))."...";
        $lines[] = "Trace:";
        $lines[] = implode("\r\n", $info['trace']);
        return implode("\r\n", $lines);
    }

    static public function formatHtml($info) {
        $code = highlight_string("<?php \n". implode("\n", self::errorCode($info['file'], $info['line'])) . "...", true);
        $html = str_replace(
            ["#TYPE#",      "#FILE#",      "#LINE#",      "#MSG#",     '#CODE#', '#TRACE#'], 
            [$info['type'], $info['file'], $info['line'], $info['msg'], $code,    implode("<br/>\n",$info['trace'])], 
            self::$_tplTrace);
        return $html;
    }

    static public function cutLog() {
        $cDate = date('Ymd', self::fctime(ERROR_LOGFILE));
        $toFile = ERROR_LOGFILE.".{$cDate}";
        rename(ERROR_LOGFILE, $toFile);
        touch(ERROR_LOGFILE);
    }

    static public function fctime($fname) {
        $info  = stat($fname);
        $ctime = $info['atime'];
        if($ctime > $info['mtime']) $ctime = $info['mtime'];
        if($ctime > $info['ctime']) $ctime = $info['ctime'];
        return $ctime;
    }

}

