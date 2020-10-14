<?php

namespace Ypf\Lib;
if (!defined('__ERROR_HANDLE_LEVEL__')) define('__ERROR_HANDLE_LEVEL__', E_ALL ^ E_WARNING ^ E_NOTICE);

/**
 * @node_name 命令行脚本错误异常处理默认类
 * Class ErrorCliHandle
 * @package   Ypf\Lib
 * User: 张鹏玄 | <zhangpengxuan@yundun.com>
 * Date: 2016-8-9 12:11:07
 */
class ErrorHandleCli {

    public function Error($type, $message, $file, $line) {
        if (($type & __ERROR_HANDLE_LEVEL__) !== $type) return;
        var_dump("ypf-cli error:");
        $error_info            = [];
        $error_info['type']    = $this->FriendlyErrorType($type);
        $error_info['message'] = $message;
        $error_info['file']    = $file;
        $error_info['line']    = $line;
        var_dump($error_info);
    }

    public function Exception($exception) {
        if(__ERROR_HANDLE_LEVEL__ & E_ERROR){

            var_dump("ypf-cli exception:");
            $exception_info            = [];
            $exception_info['message'] = $exception->getMessage();
            $exception_info['code']    = $exception->getCode();
            var_dump($exception_info);

        }
    }

    public function Shutdown() {
        var_dump("ypf-cli shutdown:");
        if ($error = error_get_last()) {
            if ( ($error['type'] & __ERROR_HANDLE_LEVEL__) !== $error['type']) return;
            $this->Error($error['type'], $error['message'], $error['file'], $error['line']);
        }
    }

    public static function FriendlyErrorType($type) {
        switch($type) {
            case E_ERROR: // 1 //
                return 'E_ERROR';
            case E_WARNING: // 2 //
                return 'E_WARNING';
            case E_PARSE: // 4 //
                return 'E_PARSE';
            case E_NOTICE: // 8 //
                return 'E_NOTICE';
            case E_CORE_ERROR: // 16 //
                return 'E_CORE_ERROR';
            case E_CORE_WARNING: // 32 //
                return 'E_CORE_WARNING';
            case E_CORE_ERROR: // 64 //
                return 'E_COMPILE_ERROR';
            case E_CORE_WARNING: // 128 //
                return 'E_COMPILE_WARNING';
            case E_USER_ERROR: // 256 //
                return 'E_USER_ERROR';
            case E_USER_WARNING: // 512 //
                return 'E_USER_WARNING';
            case E_USER_NOTICE: // 1024 //
                return 'E_USER_NOTICE';
            case E_STRICT: // 2048 //
                return 'E_STRICT';
            case E_RECOVERABLE_ERROR: // 4096 //
                return 'E_RECOVERABLE_ERROR';
            case E_DEPRECATED: // 8192 //
                return 'E_DEPRECATED';
            case E_USER_DEPRECATED: // 16384 //
                return 'E_USER_DEPRECATED';
        }
        return "";
    }
}
