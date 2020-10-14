<?php
namespace Ypf\Cli;
/**
 * 
 * 配置 
 *
 */
class Master
{
    
    /**
     * master进程pid
     * @var integer
     */
    protected static $masterPid = 0;
    
    /**
     * server统计信息 ['start_time'=>time_stamp, 'worker_exit_code'=>['worker_name1'=>[code1=>count1, code2=>count2,..], 'worker_name2'=>[code3=>count3,...], ..] ]
     * @var array
     */    
    protected static $serverStatusInfo = array(
        'start_time' => 0,
        'worker_exit_code' => array(),
    );
    
     public static function run() {
        self::installSignal();
        
        // 变成守护进程
        self::daemonize();
        // 保存进程pid
        self::savePid();
        
        self::runTask();
        // 主循环
        self::loop();        
    }
    
    protected static function runTask() {
        \Ypf\Cli\Task::init();
        foreach(\Ypf\Lib\Config::getAll() as $key => $worker) {
			if(false !== strpos(strtolower($key), 'action')){
				if(isset($worker['status']) && $worker['status']) {
					\Ypf\Cli\Task::add($worker['time_long'], $worker['action'], null, $worker['persistent']);
				}
			}
        }
    }

    /**
     * 安装相关信号控制器
     * @return void
     */
    protected static function installSignal()
    {
        // 设置终止信号处理函数
        pcntl_signal(SIGINT,  array('\Ypf\Cli\Master', 'signalHandler'), false);
        // 设置SIGUSR1信号处理函数,测试用
        pcntl_signal(SIGUSR1, array('\Ypf\Cli\Master', 'signalHandler'), false);
        // 设置SIGUSR2信号处理函数,平滑重启Server
        pcntl_signal(SIGHUP, array('\Ypf\Cli\Master', 'signalHandler'), false);
        // 设置子进程退出信号处理函数
        pcntl_signal(SIGCHLD, array('\Ypf\Cli\Master', 'signalHandler'), false);
    
        // 设置忽略信号
        pcntl_signal(SIGPIPE, SIG_IGN);
        pcntl_signal(SIGTTIN, SIG_IGN);
        pcntl_signal(SIGTTOU, SIG_IGN);
        pcntl_signal(SIGQUIT, SIG_IGN);
        pcntl_signal(SIGALRM, SIG_IGN);
    }
    
    /**
     * 忽略信号
     * @return void
     */
    protected static function ignoreSignal()
    {
        // 设置忽略信号
        pcntl_signal(SIGPIPE, SIG_IGN);
        pcntl_signal(SIGTTIN, SIG_IGN);
        pcntl_signal(SIGTTOU, SIG_IGN);
        pcntl_signal(SIGQUIT, SIG_IGN);
        pcntl_signal(SIGALRM, SIG_IGN);
        pcntl_signal(SIGINT, SIG_IGN);
        pcntl_signal(SIGUSR1, SIG_IGN);
        pcntl_signal(SIGHUP, SIG_IGN);
    }
    
    /**
     * 设置server信号处理函数
     * @param null $null
     * @param int $signal
     * @return void
     */
    public static function signalHandler($signal)
    {
        switch($signal)
        {
            // 停止server信号
            case SIGINT:
                echo("Server is shutting down");
                exit;
                break;
            // 测试用
            case SIGUSR1:
                break;
            // worker退出信号
            case SIGCHLD:
                // 不要在这里fork，fork出来的子进程无法收到信号
                // self::checkWorkerExit();
                break;
            // 平滑重启server信号
            case SIGHUP:
                echo("Server reloading\n");
                \Ypf\Cli\Task::delAll();
                \Ypf\Lib\Config::clear();
                \Ypf\Lib\Config::load(__CONF__);
                self::runTask();
                break;
        }
    }
    
    /**
     * 保存主进程pid
     * @return void
     */
    public static function savePid()
    {
        // 保存在变量中
        self::$masterPid = posix_getpid();
        
        // 保存到文件中，用于实现停止、重启
        if(false === @file_put_contents(YPF_PID_FILE, self::$masterPid))
        {
            exit("\033[31;40mCan not save pid to pid-file(" . YPF_PID_FILE . ")\033[0m\n\n\033[31;40mServer start fail\033[0m\n\n");
        }
        
        // 更改权限
        chmod(WORKERMAN_PID_FILE, 0644);
    }
    
    /**
     * 使之脱离终端，变为守护进程
     * @return void
     */
    protected static function daemonize()
    {
        // 设置umask
        umask(0);
        // fork一次
        $pid = pcntl_fork();
        if(-1 == $pid)
        {
            // 出错退出
            exit("Daemonize fail ,can not fork");
        }
        elseif($pid > 0)
        {
            // 父进程，退出
            exit(0);
        }
        // 子进程使之成为session leader
        if(-1 == posix_setsid())
        {
            // 出错退出
            exit("Daemonize fail ,setsid fail");
        }
    
        // 再fork一次
        $pid2 = pcntl_fork();
        if(-1 == $pid2)
        {
            // 出错退出
            exit("Daemonize fail ,can not fork");
        }
        elseif(0 !== $pid2)
        {
            // 结束第一子进程，用来禁止进程重新打开控制终端
            exit(0);
        }
    
        // 记录server启动时间
        self::$serverStatusInfo['start_time'] = time();
    }
    
    /**
     * 获取主进程pid
     * @return int
     */
    public static function getMasterPid()
    {
        return self::$masterPid;
    }
    
    /**
     * 主进程主循环 主要是监听子进程退出、服务终止、平滑重启信号
     * @return void
     */
    public static function loop()
    {
        $siginfo = array();
        while(1)
        {
            @pcntl_sigtimedwait(array(SIGCHLD), $siginfo, 1);
            // 初始化任务系统
            \Ypf\Cli\Task::tick();
            // 触发信号处理
            pcntl_signal_dispatch();
        }
    }    
}
