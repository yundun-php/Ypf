<?php

namespace Ypf\Lib;

class Request {
	public $get = array();
	public $post = array();
	public $cookie = array();
	public $files = array();
	public $server = array();

	public function __construct($clean_ignore = array()) {
		$this->get = (isset($clean_ignore['get']) && $clean_ignore['get']) ? $_GET : $this->clean($_GET);
		$this->post = (isset($clean_ignore['post']) && $clean_ignore['post']) ? $_POST :  $this->clean($_POST);
		$this->request = (isset($clean_ignore['request']) && $clean_ignore['request']) ? $_REQUEST :  $this->clean($_REQUEST);
		$this->cookie = (isset($clean_ignore['cookie']) && $clean_ignore['cookie']) ? $_COOKIE :  $this->clean($_COOKIE);
		$this->files = (isset($clean_ignore['files']) && $clean_ignore['files']) ? $_FILES :  $this->clean($_FILES);
		$this->server = (isset($clean_ignore['server']) && $clean_ignore['server']) ? $_SERVER :  $this->clean($_SERVER);
	}

	public function isPost() {
		return strtolower($this->server['REQUEST_METHOD']) == 'post';
	}

	public function get($name, $filter = null, $default = null) {
		$value = $default;
		if (isset($this->get[$name])) {
			if (!is_null($filter) && function_exists($filter)) {
				$value = $filter($this->get[$name]);
			} else {
				$value = $this->get[$name];
			}
		}
		return $value;
	}

	public function post($name, $filter = null, $default = null) {
		$value = $default;
		if (isset($this->post[$name])) {
			if (!is_null($filter) && function_exists($filter)) {
				$value = $filter($this->post[$name]);
			} else {
				$value = $this->post[$name];
			}
		}
		return $value;
	}

	public function clean($data) {
		if (is_array($data)) {
			foreach ($data as $key => $value) {
				unset($data[$key]);

				$data[$this->clean($key)] = $this->clean($value);
			}
		} else {
			$data = htmlspecialchars(trim($data), ENT_COMPAT, 'UTF-8');
		}

		return $data;
	}
}
