<?php

namespace li3_flickr\extensions\adapter\data\source\http;

/**
 * li3_flickr
 *
 * Flickr API Datasource Wrapper extension for Lithium
 *
 *
 * @see \lithium\data\source\Http
 *
 * @link http://www.flickr.com/services/api/
 */

use lithium\action\Controller;

class Flickr extends \lithium\data\source\Http {

	/**
	 * List of predined exceptional methods and their corresponding HTTP method and path. 
	 * You can pre(or after) define api methods in this variable, otherwise it'll be automaticly created and will be holded in this variable.
	 *
	 * @var array
	 */
	protected $_methods = array();

	/**
	 * Holds unserialized last Flickr response & error
	 *
	 * @var Array
	 */
	public $last = array('response', 'error', 'unserialized', 'params');

	/**
	 * Constructor
	 *
	 * You should override the api_key and api_secret in your application's connection settings via Connection::add(). Default api parameters only for testing.
	 * Be aware, using defaults is not good for your application's security and privacy.
	 *
	 * @param array $config
	 * @return void
	 */
	public function __construct(array $config = array()) {
		$defaults = array(
			'scheme'			=> 'http',
			'callback_url'		=> null,
			'host'				=> 'api.flickr.com',
			'auth_url'			=> 'http://flickr.com/services/auth/',
			'api_service'		=> '/services/rest/',
			'api_key'			=> 'b039bb83c2a8e1a59cb093cdd889d230',
			'api_secret'		=> '71403385dd9bc04a',
			'api_sig'			=> null,
			'perms'				=> 'delete',
			'api_method'		=> 'GET',
			'permission_code'	=> '99',
			'auto_auth'			=> true,
			'encoding'			=> 'UTF-8',
			'socket'     		=> 'Context',
			'unserialize'		=> 'php'
		);
		$config += $defaults;
		parent::__construct($config);
	}

	/**
	 * Filtering required variables for authentication signing, and returns them.
	 *
	 * @access protected
	 * @param array $params
	 * @return array $authParams
	*/
	protected function _filterAuthParams($params = array()) {
		$params += $this->connection->_config;
		$authParams = array(
			'api_key' => empty($params['api_key']) ? $this->connection->_config['api_key'] : $params['api_key'],
			'perms' => empty($params['perms']) ? $this->connection->_config['perms'] : $params['perms'],
			'extra' => empty($params['extra']) ? $this->connection->_config['extra'] : $params['extra']
		);
		ksort($authParams);
		return $authParams;
	}

	/**
	 * Makes flickr compatible md5 hashed api sign which is required for getting permission (and frob).
	 *
	 * @access public
	 * @param array $params
	 * @return string $apiSig
	*/
	public function makeSign($params = array()) {
		$apiSig = empty($params['api_secret']) ? $this->connection->_config['api_secret'] : $params['api_secret'];
		$authParams = $this->_filterAuthParams($params);
		ksort($authParams);
		foreach($authParams as $key => $value) {
			$apiSig .= $key . $value;
		}
		echo "{$apiSig}\n <br />";
		return $this->last['params']['api_sig'] = md5($apiSig);
	}

	/**
	 * Makes flickr auth url for getting permission. 
	 * You can override settings and permission (i.e: 'perms' => 'delete') level with passing $params argument, otherwise it'll use connection defaults.
	 *
	 * @access public
	 * @param array $params
	 * @return string $url
	*/
	public function getAuthUrl($params = array()) {
		$params += $this->connection->_config;
		$authUrl = $params['auth_url'];
		$authParams = $this->_filterAuthParams($params);
		$apiSig = $this->makeSign($authParams);
		foreach($authParams as $key => $value) {
			$authParams[] = urlencode($key) . '=' . urlencode($value);
			unset($authParams[$key]);
		}
		$url = $authUrl . '?'. implode('&', $authParams) . '&api_sig=' . $apiSig;
		return $url;
	}

	/**
	 * Checks for errors at the end of service call. 
	 * If it'll find error, putting them $this->last['error'] variable as array.
	 * If permission is not enough to operate action, will return permission denied string.
	 * 
	 * @access protected
	 * @param mixed $response
	 * @return mixed
	*/
	protected function _checkForErrors($response = false) {
		$config = $this->connection->_config;
		if($config['unserialize'] != 'php' || !isset($response['stat']) || $response['stat'] == 'ok') {
			return $response;
		}

		if(isset($response['message'])) {
			$this->last['error'] = $response['message'];
		}

		if(isset($response['code']) && $response['code'] == $config['permission_code']) {
			return 'permission_denied';
		}
		return false;
	}

	/**
	 * Setting service response format depending on $this->connection->_config['unserialize']
	 * Avaible options are json, simplexml, rest, php serialize(recommended)
	 * 
	 * @access protected
	 * @return mixed
	*/
	protected function _setResponseFormat() {
		switch($this->connection->_config['unserialize']) {
			case 'json':
				return array('format' => 'json', 'nojsoncallback' => 1);
				break;
			case 'simplexml':
				return array();
				break;
			case 'rest':
				return array();
				break;
			case 'php':
				return array('format' => 'php_serial');
				break;
			default:
				$this->connection->_config['unserialize'] = 'php';
				return array('format' => 'php_serial');
		}
	}

	/**
	 * Converting service response format depending on $this->connection->_config['unserialize']
	 * Avaible options are json, simplexml, rest, php serialize(recommended)
	 * 
	 * @access protected
	 * @param mixed $response
	 * @return mixed
	*/
	protected function _filterResponse($response = false) {
		switch($this->connection->_config['unserialize']) {
			case 'json':
				return json_decode($response);
				break;
			case 'simplexml':
				return simplexml_load_string($response);
				break;
			case 'rest':
				return $response;
				break;
			case 'php':
				return unserialize($response);
				break;
			default:
				return $response;
		}
	}

	/**
	 * Organizing and encoding request params
	 * 
	 * @access protected
	 * @param array $params
	 * @return array $encoded_params
	*/
	protected function _setParams($params = array()) {
		$params = array_merge(array(
			'api_key'		=> $this->connection->_config['api_key'],
			'perms'			=> $this->connection->_config['perms']
		), $params);
		$this->last['params'] = $params = $this->_setResponseFormat() + $params;

		$encoded_params = array();
		foreach ($params as $key => $value) {
			if(is_string($value) || is_numeric($value)) {
				$encoded_params[] = urlencode($key) . '=' . urlencode($value);
			}
		}

		return $encoded_params;
	}

	/**
	 * Sets the service call method when it's not in $_methods variable.
	 * 
	 * @access protected
	 * @param array $params
	 * @return array $encoded_params
	*/
	protected function _setMethod($method = null, $params = array()) {
		if(!$method) {
			return false;
		}

		$params = $this->_setParams($params);

		$path = $this->connection->_config['api_service'] . '?method=flickr.' . str_replace('_', '.', $method);
		$path .= empty($params) ? '' : '&'. implode('&', $params);

		$this->_config['methods'][$method] = $this->connection->_config['methods'][$method] = $this->_methods[$method] = array(
			'path' => empty($params['path']) ? $path : $params['path'],
			'method' => empty($params['api_method']) ? $this->connection->_config['api_method'] : $params['api_method']
		);
	}

	/**
	 * Controlling the hole api request and returns response.
	 * 
	 * @access protected
	 * @param array $params
	 * @return array $encoded_params
	*/
	public function getResponse($method = null) {
		if(!$method) { 
			return false; 
		}
		
		return $this->_filter(__METHOD__, $method, function($self, $method) {
			$response = $this->last['unserialized'] = parent::__call($method, array());
			$response = $this->_filterResponse($response);
			if($this->_checkForErrors($response)) {
				return $this->last['response'] = $response;
			}
			return false;
		});
	}

	/**
	 * Catches methods.
	 * If it's not a class method or defined api method (which is stored $_methods variable) it'll convert the method as a API call, end after that it  will request it
	 * If returns false assigning error to $last
	 * If returns (string) permission_requeired than you should get permission. Check AuthController::get_auth()
	 *
	 * @param string $method
	 * @param array $params
	 * @return array,bool
	 */
	public function __call($method, Array $params = array()) {
		if(is_callable($this, $method)) {
			return $this->invokeMethod($this, $method);
		}

		$params = !empty($params[0]) ? $params[0] : array();
		$params = !is_array($params) ? array($params) : $params;

		if(!in_array($method, $this->_methods)) {
			$this->_setMethod($method, $params);
		}

		return $this->getResponse($method);
	}
}
?>
