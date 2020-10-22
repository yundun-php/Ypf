<?php
namespace Ypf\Lib;

use Symfony\Component\Yaml\Yaml;

/**
 * 
 * 配置
 *
 */
class Config
{
    /**
     * 配置数据
     * @var array
     */
    public static $config = array();
    
    public static $path = array();
    
    protected static $instances = null;
    

    public function __construct()
    {
     	$args = func_get_args();
     	if(!empty($args)) {
     		foreach($args as $path) {
     			$this->load($path);
     		}
     	}
     	self::$instances = &$this;
	}


	public function load( $path ) {
		if ( is_file( $path ) ) {
			return self::parseFile( $path );
		}

		//兼容musl libc
		if ( defined( 'GLOB_BRACE' ) && is_int( GLOB_BRACE ) ) {
			foreach ( glob( $path . '/{*.conf,*.yaml,*.json}', GLOB_BRACE ) as $config_file ) {
				self::$path[] = $path;
				self::parse( $config_file );
			}
		} else {
			$suffix = [ '*.conf', '*.yaml', '*.json' ];
			foreach ( $suffix as $sf ) {
				foreach ( glob( $path . "/$sf" ) as $config_file ) {
					self::$path[] = $path;
					self::parse( $config_file );
				}
			}
		}
	}

    /**
     * {
     *      ["common_config.php"],              //如果没有第二项或为空，则合并到第一维
     *      ["meal_config.php", "meal"],        //如果有第二项或为空，则合并到第二维, 如：{'meal' => {...}}
     *      ["order_config.php", "order"],
     * }
     */
    public static function loadPhps($files) {
        foreach($files as $row) {
            $cfg = require $row[0];
            if(isset($row[1]) && (is_string($row[1]) || is_numeric($row[1]))) {
                self::$config[$row[1]] = array_merge(self::$config[$row[1]], $cfg);
            } else {
                self::$config = array_merge(self::$config, $cfg);
            }
        }
    }


    protected static function parse($config_file){
        $suffix = pathinfo($config_file,PATHINFO_EXTENSION);
        $name = basename($config_file, ".$suffix");
        switch ($suffix){
            case "php":
                $cfg = self::parsePhp($config_file);
//                self::$config[$name] = $cfg;
                self::$config = array_merge(self::$config, $cfg);
                break;
            case "conf":
                self::$config[$name] = self::parseFile($config_file);
                break;
            case "yaml":
                $cfg = self::parseYaml($config_file);
                self::$config = array_merge(self::$config, $cfg);
                break;
            case "json":
                $cfg = self::parseJson($config_file);
                self::$config = array_merge(self::$config, $cfg);
                break;
        }

        return self::$config;
    }

    /**
     * @param $config_file
     * @return array|bool
     * @throws \Exception
     * @node_name parse
     * @link
     * @desc
     */
    protected static function parseFile($config_file)
    {
        $config = parse_ini_file($config_file, true);
        if (!is_array($config) || empty($config))
        {
            throw new \Exception('Invalid configuration format');
        }
        return $config;
    }

    protected static function parseJson($config_file){
        $content = file_get_contents($config_file);
        if(false === $content){
            throw new \Exception("read json file err");
        }
        $config = json_decode($content, true);

        return $config;
    }

    protected static function parseYaml($config_file){
        $config = [];
        //如果装了yaml扩展，优化使用扩展
        if(function_exists('yaml_parse_file')) {
            $config = yaml_parse_file($config_file);
        } else {
            $config = Yaml::parseFile($config_file);
        }
        return $config;
    }

    protected static function parsePhp($config_file){
        $config = require $config_file;

        return $config;
    }

    public static function getInstance() {
        return self::$instances;
    }
    /**
     * 获取配置
     * @param string $uri
     * @return mixed
     */
    public function get($uri)
    {
        $node = self::$config;
        $paths = explode('.', $uri);
        while (!empty($paths)) {
            $path = array_shift($paths);
            if (!isset($node[$path])) {
                return null;
            }
            $node = $node[$path];
        }
        return $node;
    }
    
    /**
     * @return array
     */
    public static function getAll()
    {
         $copy = self::$config;
         return $copy;
    }
	
	public static function clear()
	{
		 self::$config = array();
	}
	
	public function set($node, $data)
	{
	}
    
}
