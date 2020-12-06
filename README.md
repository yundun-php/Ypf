# ypf - 云盾PHP框架

## composer
	- composer require yd/ypf v1.0.4

## 异常日志相关说明

```
异常日志是指将程序执行过程中的 notice/warn/error/exception 等各种信息记录到日志中，以方便后期问题排查。
异常信息输出有三种：
日志文件，所有异常信息会强制记录到日志文件中。
命令行标准输出，命令行手动执行脚本时，异常信息会输出到标准输出，信息格式已做过定制做处理，可以用常量 ERROR_DISPLAY_CLI(true/false) 定义是否开启。
HTML页面，执行WEB请求时，将异常信息输出页面上，而且是HTML格式的，可以用常量 ERROR_DISPLAY_HTML(true/false) 定义是否开启。

日志级别及是否显示使用PHP自有的配置项：
error_reporting(E_ALL ^ E_WARNING ^ E_NOTICE ^ E_DEPRECATED ^ E_USER_NOTICE);
ini_set('display_errors', 'on');

异常信息按需应记录参数，参数是动态的，而且可能数据量很大，因此做了特殊的设定。
命令行及WEB请求，会全部输出参数。
日志文件默认不记录执行方法的参数，也可以用常量 ERROR_LOG_ARGS_SWITCH(true/false) 开启记录执行方法的参数。
当日志中不记录执行方法的参数时，如因业务需要，可以用常量 ERROR_LOG_ARGS_KEEP_CLASS(json字符串) 指明记录参数的类 或 ERROR_LOG_ARGS_KEEP_FUNC(json字符串) 指明记录参数的方法。
如果某些方法的参数比较敏感，可以用 ERROR_LOG_ARGS_IGNORE_FUNC(json字符串) 指明不记录方法的参数，而且所有的输出(日志文件/命令行/HTML)都受此影响。

日志文件可以用常是 ERROR_LOG_PREKEY 定义路经前綴，如果使用此框架的项目较多，日志文件可能重复，因此可以使用常是 SYS_KEY 指明项目标识。

系统长期迲行，日志文件会很大，可以使用常量 ERROR_LOG_AUTO_SPLIT 定义按日期切割, 默认不会自动切割
```

```
define('SYS_KEY',     'home-v4-cli');

error_reporting(E_ALL ^ E_WARNING ^ E_NOTICE ^ E_DEPRECATED ^ E_USER_NOTICE);
ini_set('display_errors',      'On');
define('ERROR_DISPLAY_CLI',   false);
define('ERROR_DISPLAY_HTML',   true);
define('ERROR_LOG_PREKEY', '/tmp/log_phperror_');
//需要记录参数的类, 仅为字符串
define('ERROR_LOG_ARGS_KEEP_CLASS', json_encode([
    'Ypf\Lib\DatabaseV5',
]));
//需要记录参数的方法，普通方法用字符，类方法用数组
define('ERROR_LOG_ARGS_KEEP_FUNC', json_encode([
    'getPrimaryDomain',
    ['Ypf\Lib\DatabaseV5','query'],
]));
//指明忽略参数的方法，避免敏感信息泄漏
define('ERROR_LOG_ARGS_IGNORE_FUNC', json_encode([
    'getPrimaryDomain',
    ['Ypf\Lib\DatabaseV5','query'],
]));
```
