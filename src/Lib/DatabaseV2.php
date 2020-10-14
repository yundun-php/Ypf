<?php
/**
 * public function delRecord($id, $domain_id) {
		return $this->db->table('records')
		->where("id=? and domain_id=?", array($id, $domain_id))
		->delete();
	}

	public function updateRecordById($id, $data = array()) {
		return $this->db->table('records')
		->data($data)
		->where("id=?", array($id))
		->update();
	}
$this->db->table('records')
		->data($data)
		->insert();
	等同于  $this->db->table('records')->save($data)
*/
namespace Ypf\Lib;

use \PDO;

class Database extends PDO
{

    protected $options = array();
    protected $params = array();
    protected $lastsql = "";
    // 链操作方法列表
    protected $methods = array('from', 'data', 'field', 'table','order','alias','having','group','lock','distinct','auto');
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
            throw new Exception($e);
        }
    }
    
	private function createdsn($options)
	{
		return $options['dbtype'] . ':host=' . $options['host'] . ';dbname=' . $options['dbname'] . ';port=' . $options['port'];
	}

    public function query($query, $data = array())
    {
        $this->lastsql = $this->setLastSql($query, $data);
        $stmt = parent::prepare($query);
    
        $stmt->execute($data);
        $this->options = $this->params = array();
        return $stmt;
    }
    
    public function insert($data = array())
    {
        $this->options['type'] = 'INSERT';
        return $this->save($data);
    }

    public function select($sql = null, $data = array())
    {
        $sql = $sql ? $sql : $this->getQuery();
        $data = empty($data) ? $this->params : $data;
        $stmt = $this->query($sql, $data);
		
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

        $stmt = $this->query($sql, $this->params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }    
    

    public function update($data = array())
    {
        $this->options['type'] = 'UPDATE';
        return $this->save($data);
    }

    public function delete()
    {
        $this->options['type'] = 'DELETE';
        return $this->save();
    }

    public function fetchOne($sql = null) {
        $this->options['limit'] = 1;
        $sql = $sql ? $sql : $this->getQuery();

        $stmt = $this->query($sql, $this->params);
        $result = $stmt->fetch(PDO::FETCH_NUM);

        if(isset($result[0])) return $result[0];
        return null;
    }

    public function save($data = array()) {
        if(!empty($data)) $this->data($data);
        if(!isset($this->options['type'])) {
            $this->options['type'] = isset($this->options['where']) ? 'UPDATE' : 'INSERT';
        }

        switch ($this->options['type']) {
            case 'INSERT':
                $keys = array_keys($this->options['data']);
                $fields = '`'.implode('`, `',$keys).'`';
                $placeholder = substr(str_repeat('?,',count($keys)),0,-1);
                $query = "INSERT INTO `" . $this->options['table'] . "`($fields) VALUES($placeholder)";

                return $this->query($query, array_values($data)) ? ( parent::lastInsertId() ? parent::lastInsertId() : true ) : false;
                break;
            case 'UPDATE':
                $update_field = array();
                $this->params = array_merge(array_values($this->options['data']), $this->params);
                foreach ($this->options['data'] as $key => $value) {
                    $update_field[] = "`$key`= ?";
                }
                $query = "UPDATE `" . $this->options['table'] . "` SET " . implode(",", $update_field) .  "WHERE " . implode(" AND ", $this->options['where']);
                $this->query($query, $this->params);
                break;
            case 'DELETE':
                $query = "DELETE FROM `" . $this->options['table'] . "`WHERE " . implode(" AND ", $this->options['where']);
                $this->query($query, $this->params);            
                break;
            default:
                # code...
                break;
        }
        return true;
    }

    private function getQuery() {
        $sql = "SELECT ";
        //parse field
        if(isset($this->options['field'])) {
            $sql .= " " . $this->options['field'] . " ";
        }else{
            $sql .= " * ";
        }
        //parse table
        if(isset($this->options['table'])) {
            $sql .= " FROM " . $this->options['table']. " ";
        }
        //parse join
        if(isset($this->options['join'])) {
            $sql .= $this->options['join'] . " ";
        }        
        //parse where
        if(isset($this->options['where'])) {
            $sql .= "WHERE " . implode(" AND ", $this->options['where']). " ";
        }
        //parse group
        if(isset($this->options['group'])) {
            $sql .= "GROUP BY " .  $this->options['group'] . " ";
        }        
        //parse having
        if(isset($this->options['having'])) {
            $sql .= "HAVING " . $this->options['having'] . " ";
        }
        //parse order
        if(isset($this->options['order'])) {
            $sql .= "ORDER BY " . $this->options['order'] . " ";
        }
        //parse limit
        if(isset($this->options['limit'])) {
            $sql .= "LIMIT " . $this->options['limit'];
        }
        return $sql;                    
    }

    public function __call($method,$args) {
        if(in_array(strtolower($method),$this->methods,true)) {
            $this->options[strtolower($method)] =   $args[0];
            return $this;
        }elseif(in_array(strtolower($method),array('count','sum','min','max','avg'),true)){
            $field =  (isset($args[0]) && !empty($args[0]))?$args[0]:'*';
            $as = '_' . strtolower($method);
            $this->options['field'] =  strtoupper($method) .'('.$field.') AS ' . $as;
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
    * $sql->where(array('uid'=>3, 'pid'=>2));
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
        $query_w = array();

        if(func_num_args() == 1 && is_array($args[0])) {
            foreach ($args[0] as $k => $v) {
                $query_w[] = "`$k` = ?";
            }
            $statement = implode(" AND ", $query_w);            
            $params = array_values($args[0]);
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

}

?>