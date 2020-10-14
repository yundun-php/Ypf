<?php

namespace Ypf\Lib;

use \PDO;

class Database extends PDO
{

    protected $options = array();
    protected $params = array();
    protected $lastsql = "";
    // 链操作方法列表
    protected $methods = array('from', 'field', 'table','order','alias','having','group','lock','distinct','auto');
    public function __construct($options = array())
    {
        $default_options = array(
        	'dbtype' => 'mysql',
        	'host' => '127.0.0.1',
        	'port' => 3306,
        	'dbname' => 'test',
        	'username' => 'root',
        	'password' => '',
        	'charset' => 'utf8',
            'timeout' => 3,
        );
        $options = array_merge($default_options, $options);
        $dsn = $this->createdsn($options);
        try {
            $option = $options['charset'] ? array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES '.$options['charset']) : null;
            $option[PDO::ATTR_TIMEOUT] = $options['timeout'];
            parent::__construct(
                $dsn,
                $options['username'],
                $options['password'],
                $option
            );
            parent::setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(Exception $e) {
            echo 'Error : '.$e->getMessage().'<br />';
            echo 'No : '.$e->getCode();
        }
    }
    
	private function createdsn($options)
	{
		return $options['dbtype'] . ':host=' . $options['host'] . ';dbname=' . $options['dbname'] . ';port=' . $options['port'];
	}

    public function executeQuery($query, $data = array())
    {
        $this->lastsql = $this->setLastSql($query, $data);
        $stmt = parent::prepare($query);
    
        $stmt->execute($data);
		$this->options = array();//clear
		$this->params = array();//clear
        return $stmt;
    }
    
    public function insert($query, $data = array())
    {
        return $this->executeQuery($query, $data) ? ( parent::lastInsertId() ? parent::lastInsertId() : true ) : false;
    }

    public function select($sql = null, $data = array())
    {
        $sql = $sql ? $sql : $this->getQuery();
		$data = empty($data) ? $this->params : $data;
        $stmt = $this->executeQuery($sql, $data);
		
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    private function setLastSql($string,$data) {
        $indexed=$data==array_values($data);
        foreach($data as $k=>$v) {
            if(is_string($v)) $v="'$v'";
            if($indexed) $string=preg_replace('/\?/',$v,$string,1);
            else $string=str_replace(":$k",$v,$string);
        }
        return $string;        
    }
    public function getLastSql() {
        return $this->lastsql;
    }

    public function fetch($sql = null)
    {
        $sql = $sql ? $sql : $this->getQuery();

        $stmt = $this->executeQuery($sql, $this->params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }    
    
    public function update($query, $data = array())
    {
        return $this->executeQuery($query, $data);
    }

    public function delete($query,$data=array())
    {
        $stmt = $this->executeQuery($query, $data);
		return $stmt===false ? false : $stmt->rowCount();
    }

    public function fetchOne($sql = null) {
        $this->options['limit'] = 1;
        $sql = $sql ? $sql : $this->getQuery();

        $stmt = $this->executeQuery($sql, $this->params);
        $result = $stmt->fetch(PDO::FETCH_NUM);

        if(isset($result[0])) return $result[0];
        return null;
    }

    public function save($data = array()) {
        if(empty($data)) return null;
        $keys = array_keys($data);
        $fields = '`'.implode('`, `',$keys).'`';
        $placeholder = substr(str_repeat('?,',count($keys)),0,-1);
        $query = "INSERT INTO `" . $this->options['table'][0] . "`($fields) VALUES($placeholder)";

        return $this->executeQuery($query, array_values($data)) ? ( parent::lastInsertId() ? parent::lastInsertId() : true ) : false;
    }

    private function getQuery() {
        $sql = "SELECT ";
        //parse field
        if(isset($this->options['field'])) {
            $sql .= " " . implode(", ", $this->options['field']). " ";
        }else{
            $sql .= " * ";
        }
        //parse table
        if(isset($this->options['table'])) {
            $sql .= " FROM " . implode(", ", $this->options['table']). " ";
        }
        //parse join
        if(isset($this->options['join'])) {
            $sql .= implode(", ", $this->options['join']). " ";
        }        
        //parse where
        if(isset($this->options['where'])) {
            $sql .= "WHERE " . implode(" AND ", $this->options['where']). " ";
        }
        //parse group
        if(isset($this->options['group'])) {
            $sql .= "GROUP BY " . implode(", ", $this->options['group']). " ";
        }        
        //parse having
        if(isset($this->options['having'])) {
            $sql .= "HAVING " . implode(", ", $this->options['having']). " ";
        }
        //parse order
        if(isset($this->options['order'])) {
            $sql .= "ORDER BY " . implode(", ", $this->options['order']). " ";
        }
        //parse limit
        if(isset($this->options['limit'])) {
            $sql .= "LIMIT " . $this->options['limit'];
        }
        return $sql;                    
    }

    public function __call($method,$args) {
        if(in_array(strtolower($method),$this->methods,true)) {
            $this->options[strtolower($method)][] =   $args[0];
            return $this;
        }elseif(in_array(strtolower($method),array('count','sum','min','max','avg'),true)){
            $field =  (isset($args[0]) && !empty($args[0]))?$args[0]:'*';
            $as = '_' . strtolower($method);
            $this->options['field'] = array( strtoupper($method) .'('.$field.') AS ' . $as);
            return $this->fetchOne();
        }else{
            return null;
        }
    }
    
    public function addParams($params) {
        if (is_null($params)) {
            return;
        }

        if(!is_array($params)) {
            $params = array($params);
        }

        $this->params = array_merge($this->params, $params);
    }

   /**
    * Add statement for where - ... WHERE [?] ...
    *
    * Examples:
    * $sql->where("user_id = ?", $user_id);
    * $sql->where("u.registered > ? AND (u.is_active = ? OR u.column IS NOT NULL)", array($registered, 1));
    *
    * @param string $statement
    * @param mixed $params
    * @return Query
    */
    public function where()
    {
        $args = func_get_args();
        $statement = $params = null;

        if(func_num_args() == 1 && is_array($args[0])) {
            return $this;// todo
        }else{
            $statement = array_shift($args);

            $params = isset($args[0]) && is_array($args[0]) ? $args[0] : $args;
        }        
        if(!empty($statement)) {
            $this->options['where'][] = $statement;
            $this->addParams($params);
        }
        return $this;
    }

    public function limit($offset,$length=null){
        $this->options['limit'] =   is_null($length)?$offset:$offset.','.$length;
        return $this;
    } 
	
	public function create($data,$tbl=''){
		if($data){
			$fields = array_keys($data);
			$fieldsvals =  array(implode(",",$fields),":" . implode(",:",$fields));
			$query = "INSERT INTO ".($tbl?$tbl:$this->options['table'][0])." (".$fieldsvals[0].") VALUES (".$fieldsvals[1].")";
			return $this->insert($query,$data);
		}
		return false;
	}
	
	public function update_where($where,$data,$tbl=''){
		if($where && $data){
			$set = $this->parseSet($data);
			$wh = $this->parseWhere($where);
			$param = array();
			$param = array_merge($param,array_values($data),array_values($where));
			$query = "update `" . ($tbl?$tbl:$this->options['table'][0]) . "` $set $wh";
			$stmt = $this->executeQuery($query, $param);
			return $stmt===false ? false : $stmt->rowCount();
		}
		return false;
	}
	
	public function del_where($where,$tbl=''){
		if($where){
			$wh = $this->parseWhere($where);
			$param = array();
			$param = array_merge($param,array_values($where));
			$query = "delete from `".($tbl?$tbl:$this->options['table'][0])."` $wh";
			$stmt = $this->executeQuery($query,$param);
			return $stmt===false ? false : $stmt->rowCount();
		}
		return false;
	}
	
	public function fetch_where($where,$tbl=''){
		if($where){
			$wh = $this->parseWhere($where);
			$param = array();
			$param = array_merge($param,array_values($where));
			$tbl = $tbl?$tbl:$this->options['table'][0];
			$query = "select * from $tbl $wh limit 1"; 
			$stmt = $this->executeQuery($query, $param);
			$res = $stmt->fetch(PDO::FETCH_ASSOC);
			return $res?$res:false;
		}
		return false;
	}
	
	public function count_where($where,$tbl=''){
		if($where){
			$wh = $this->parseWhere($where);
			$param = array();
			$param = array_merge($param,array_values($where));
			$tbl = $tbl?$tbl:$this->options['table'][0];
			$query = "select count(*)  from $tbl $wh";
			$stmt = $this->executeQuery($query, $param);
			$res = $stmt->fetch(PDO::FETCH_NUM);
			if(isset($res[0])){
				return (int)$res[0];
			}else{
				return false;
			}
		}
		return  false;
	}
	
	public function getField($field,$where,$tbl=''){
		if($field && $where){
			$wh = $this->parseWhere($where);
			$param = array();
			$param = array_merge($param,array_values($where));
			$tbl = $tbl?$tbl:$this->options['table'][0];
			$query = "select $field from $tbl $wh limit 1";
			$stmt = $this->executeQuery($query, $param);
			
			if(strpos($field,',') !== false){//多字段 返回一维关联数组
				$res = $stmt->fetch(PDO::FETCH_ASSOC);
				return $res?$res:array();
			}else{
				$res = $stmt->fetch(PDO::FETCH_NUM);
				return $res?$res[0]:'';
			}
		}
		return "";
	}
	
	private function parseSet($data){
		$set = array();
		foreach($data as $key=>$val){
			if(is_scalar($val)){
				$set[] = "`$key`=?";
			}
		}
		return ' set '.implode(',',$set).'';
	}
	
	private function parseWhere(&$where){
		$w = array();
		foreach($where as $key=>$val){
			if(is_scalar($val)){
				if($key == '_string'){
					$w[] = $val;
					unset($where[$key]);
				}else{
					$w[] = "`$key`=?";
				}
			}
		}
		return ' where '.implode(' and ',$w).'';
	}

}

?>