<?php

namespace li3_flickr\extensions\adapter\data\source\http;

use \lithium\util\String;
use \lithium\core\ConfigException;
use \lithium\storage\Cache;
use \lithium\storage\Session;


/**
 * li3_Flickr
 *
 * Flickr API Datasource Wrapper extension for Lithium
 *
 *
 * @see \lithium\data\source\Http
 *
 * @link www.flickr.com/services/api/
 */

class Flickr extends \lithium\data\source\Http {


	/**
	 * Classes required
	 *
	 * @access protected
	 * @var array
	 */
	protected $_classes = array(
		'service' 			=> 'lithium\net\http\Service',
		'session'			=> 'lithium\storage\Session',
		'cache'				=> 'lithium\storage\Cache',
		'entity' 			=> 'lithium\data\entity\Document',
		'set' 				=> 'lithium\data\collection\DocumentSet',
		'configException'	=> 'lithium\core\ConfigException'
	);

	/**
	 * List of defined methods and their corresponding HTTP method and path.
	 * You can pre(or after) define api methods in this variable with setMethod() function,
	 * otherwise it'll be created from Flickr API with magic (if exists) and will be holded in this variable.
	 *
	 * @access protected
	 * @var array
	 */
	protected $_methods = array(
		'flickr.auth.getToken' => array(
			'params'	=> array('method', 'api_key', 'frob', 'api_sig'),
			'method'	=> 'post',
		    'domain'	=> 'api',
		    'path'		=> '/services/{:service}',
		    'login'		=> 0,
		    'sign'		=> 1,
		    'perms'		=> 0
		),
	);

	/**
	 * Host configurations
	 * You can access them with getDomain() function.
	 *
	 * @access protected
	 * @var array
	 */
	protected $_domains = array(
		'api' 			=> 'api.flickr.com',
		'secure' 		=> 'secure.flickr.com/services/{:service}',

		'auth'			=> 'http://flickr.com/services/{:service}/{:authParams}',
		'after_upload'	=> 'http://www.flickr.com/photos/upload/edit',
		'short_url'		=> 'http://flic.kr/p/{:base58PhotoId}',
		'photo'			=> 'http://farm{:farm}.static.flickr.com/{:server}/{:id}_{:secret}_{:size}.{:extension}',
		'profile'		=> 'http://www.flickr.com/people/{:userId}',
		'buddyicon'		=> 'http://farm{:icon-farm}.static.flickr.com/{:icon-server}/buddyicons/{:nsid}.jpg',
		'photo_page' 		=> 'http://www.flickr.com/photos/{:userId}/{:photo-id}',
		'photosets'		=> 'http://www.flickr.com/photos/{:userId}',
		'photoset'		=> 'http://www.flickr.com/photos/{:userId}/sets/{:photoset-id}'
	);

	/**
	 * Flickr Api permission levels
	 *
	 * @access protected
	 * @var Array
	*/
	protected $_permissions = array(
		'default' => 0,
		'read' => 1,
		'write' => 2,
		'delete' => 3
	);


	/**
	 * The Connection
	 *
	 * @access public
	 * @var object
	*/
	public $connection;

	/**
	 * Some important response codes for  purposes
	 *
	 * @access protected
	 * @var array
	 */
	protected $_codes = array(
		96	=> 'Invalid signature',
		97	=> 'Missing signature',
		98	=> 'Login failed / Invalid auth token',
		99	=> 'User not logged in / Insufficient permissions',
		100	=> 'Invalid API Key',
		105	=> 'Service currently unavailable',
		112	=> 'Method {:method} not found',
		116	=> 'Bad URL found',
	);


	/**
	 * Constructor
	 *
	 * @param array $config
	 */
	public function __construct(array $config = array()) {
		$defaults = array(
			'host'				=> $this->_domains['api'],
			'scheme'			=> 'http',
			'method'			=> 'post',
			'service'			=> 'rest',
			'format'			=> 'json',
			'method_prefix'		=> 'flickr',
			'encoding'			=> 'UTF-8',
			'socket'     		=> 'Context',
			'lazy_method_fetch'	=> true,
			'skip_method_fetch'	=> false,
			'skip_method_check'	=> false,
			'load_methods'		=> array(),
			'session'			=> 'default',
			'cache'				=> 'default',
			'cache_expire'		=> '+2 days'
		);

 		$config += $defaults;

		if(!isset($config['api_secret'])) {
			throw new ConfigException("Api key is not configured.");
		}
		if(!isset($config['api_key'])) {
			throw new ConfigException("Api secret is not configured.");
		}

		parent::__construct($config);
	}


	/**
	 * Initialize
	 *
	 * @access protected
	 */
	protected function _init() {
	    parent::_init();

	    $this->_checkAdapter('Session', $this->connection->_config['session']);
	    $this->_checkAdapter('Cache', $this->connection->_config['cache']);

		$cachedMethods = Cache::read($this->connection->_config['cache'], "li3_Flickr_methods");
		if($cachedMethods && !empty($cachedMethods)) {
			foreach($cachedMethods as $method => $params) {
				$this->setMethod($method, $params);
			}
		}

	    if(!$this->connection->_config['skip_method_fetch']) {
    		$this->setMethod('flickr.reflection.getMethodInfo', array(
	    		'params' => array('method_name', 'api_key')
			));
	    	if(!$this->connection->_config['lazy_method_fetch']) {
	    		$this->_fetchAllMethods();
    		}
	    }
	}

	/**
	 * Checks the required storage adapter setting is configured properly.
	 *
	 * @access protected
	 * @param string $storage
	 * @param string $name
	 * @return bool
	 */
	protected function _checkAdapter($storage = false, $name = false) {

		if($storage === false || $name === false) {
			return false;
		}

		switch($storage) {
			case 'Session':
				$check = Session::check('li3_Flickr', array('name' => $name));
				$permission = $this->_permissions['default'];
				$write = function() use($permission, $name) {
					return Session::write('li3_Flickr', array(
						'permission' 			=> $permission,
						'frob' 					=> null,
						'auth_token'			=> null,
						'user'					=> null
					), array('name' => $name));
				};
			break;

			case 'Cache':
				$check = Cache::read($name, 'li3_Flickr');
				$expire = $this->connection->_config['cache_expire'];
				$write = function() use($name, $expire) {
					return Cache::write($name, 'li3_Flickr', array('status' => 'ok'), $expire);
				};
			break;

			default:
				return false;
			break;
		}

		$params = compact('storage', 'name');
		return $this->_filter(__METHOD__, $params, function($self, $params) use($check, $write) {
			extract($params);
			if(!$check) {
				if(!$write()) {
					throw new ConfigException(" '{$name}' named {$storage} adapter is not properly configured.");
				}
			}
			return true;
		});
	}

	/**
	 * Checks the current permission level is enough
	 *
	 * @access public
	 * @param mixed $level
	 * @param array $config
	 * @return bool
	 */
	 public function checkPermission($level = 0, $config = array()) {

	 	if(is_numeric($level)) {
	 		$level = !$level ? 0 : $level;
	 	}
	 	
	 	if(is_string($level)) {
	 		$level = empty($this->_permissions[$level]) ? 0 : $this->_permissions[$level];
	 	}

	 	$config += $this->connection->_config;
	 	$session = Session::read('li3_Flickr', array('name' => $config['session']));
	 	$permissions = $this->_permissions;

	 	$params = compact('level', 'permissions');
	 	return $this->_filter(__METHOD__, $params, function($self, $params) use($session) {
	 		extract($params);

	 		if(!isset($params['level']) || !$session || empty($session)) {
	 			return false;
	 		}

	 		$currentPermission = (integer) $session['permission'];
	 		
	 		if($currentPermission < $level) {
	 			return false;
	 		}
	 		
	 		if($level > 0 && (empty($session['auth_token']) || !$session['auth_token'])) {
	 			return false;
	 		}

	 		return true;
	 	});
	 }

	/**
	 * Checks the current permission level is enough for the specific method
	 *
	 * @access public
	 * @param string $by
	 * @param array $params
	 * @return bool
	 */
	 public function checkPermissionByMethod($method = false, array $params = array()) {
	 	
	 	if($method === false || !$this->checkMethod($method)) {
	 		return false;
	 	}
	 	
	 	$params = $params + $this->_methods[$method];
	 	return $this->_filter(__METHOD__, $params, function($self, $params) use($params, $method) {
	 		$perms = empty($params['perms']) || !$params['perms'] ? 0 : $params['perms'];
			
	 		if(!$self->checkPermission($perms)) {
	 			return false;
	 		}
			
	 		return true;
	 	});
	 }

	/**
	 * Checks the api method is defined, and if not defines it from method cache or Flickr api
	 *
	 * @access public
	 * @param string $method
	 * @return bool
	 */
	public function checkMethod($method = null) {

		if($this->connection->_config['skip_method_check']) {
			return true;
		}

		if($method) {
			if(isset($this->_methods[$method])) {
				return true;
			}

			if(!$this->connection->_config['skip_method_fetch']) {
				$response = $this->_fetchMethod($method);

				if(!$response) {
					return false;
				}

				$response = $this->connection->last->response;
				$domains = $this->_domains;

				$params = array();
				return $this->_filter(__METHOD__, $params, function($self, $params) use($domains, $response) {
					return $self->setMethod($response->method->name, array(
						'login'	=> $response->method->needslogin,
						'sign'	=> $response->method->needssigning,
						'perms'	=> $response->method->requiredperms,
						'params' => array_map(function($param) {
							return isset($param->name) ? $param->name : false;
						}, $response->arguments->argument)
					));
				});
			}
		}
		return false;
	}

	/**
	 * Removes the method cache
	 *
	 * @access protected
	 * @param string $method
	 * @return object, bool
	 */
	public function clearMethods() {
		return Cache::delete($this->connection->_config['cache'], 'li3_Flickr_methods');
	}
	
	/**
	 * Removes the authentication session
	 *
	 * @access protected
	 * @param string $method
	 * @return object, bool
	 */
	public function clearSession() {
		return Session::delete('li3_Flickr', array('name' => $this->connection->_config['session']));
	}

	/**
	 * Fetches the method info from api
	 *
	 * @access protected
	 * @param string $method
	 * @return object, bool
	*/
	protected function _fetchMethod($method = null) {
		if(!$method) {
			return false;
		}

		$response = $this->_request('flickr.reflection.getMethodInfo', array(
			'query' => array(
				'method_name' => $method,
				'format' => 'json'
			),
			'options' => array('format' => 'json')
		)) ? $this->connection->last->response : false;

		return isset($response->method) ? $response : false;
	}

	/**
	 * Fetches all method infos from api
	 *
	 * @access protected
	*/
	protected function _fetchAllMethods() {

		$response = $this->reflection_getMethods(array(), array('format' => 'json'));

		if(!$response) {
			return false;
		}

		$methods = empty($this->connection->_config['load_methods']) ? array_map(function($method) {
			return $method->_content;
		}, $response->methods->method) : $this->connection->_config['load_methods'];

		$this->connection->_config = $currentSettings + $this->connection->_config;

		if(empty($methods)) {
			return false;
		}

		foreach($methods as $method) {
			$this->checkMethod($method);
		}

		return true;
	}

	/**
	 * Defines a new method
	 *
	 * @param string $method
	 * @param array $params
	 * @return array, bool
	 */
	public function setMethod($method = null, array $params = array()) {
		$config = $this->connection->_config;
		$return = !$method ? false : $this->_methods[$method] = $params += array(
			'method' => 'post',
			'domain' => 'api',
			'params' => array('api_key'),
			'path'	 => '/services/{:service}',
			'login'	 => 0,
			'sign'	 => 0,
			'perms'	 => 0
		);

		if(isset($params['params']) && in_array('frob', $params['params'])) {
			//flickr reflection bug
			$return['sign'] = 1;
		}

		$cachedMethods = Cache::read($config['cache'], "li3_Flickr_methods");
		$cachedMethods = empty($cachedMethods) ? array() : $cachedMethods;
		$cachedMethods[$method] = $return;
		Cache::write($config['cache'], "li3_Flickr_methods", $cachedMethods, $config['cache_expire']);
		return $return;
	}

	/**
	 * Entities
	 *
	 * @access public
	 * @param object $class
	 * @return void
	 */
	public function entities($class = null) {}

	/**
	 * Describe data source.
	 *
	 * @param string $entity
	 * @param string $meta
	 * @return void
	 */
	public function describe($describefentity = null, array $meta = array()) {}


	/**
	 * Returns replaced proper params in the specified domain
	 *
	 * @access public
	 * @param array $params, string $domain
	 * @return string
	*/
	public function getDomain($params = array(), $domain = 'api') {
		$params = $domain == 'photo' ? (array) $params + array(
			'extension' => 'jpg',
			'size'		=> 'b'
		) : (array) $params;

		if(!$domain || empty($params) || !isset($this->_domains[$domain])) {
			return false;
		}
		return String::insert($this->_domains[$domain], $params);
	}

	/**
	 * Makes flickr compatible md5 hashed api sign which is required for getting token and frob.
	 *
	 * @access public
	 * @param array $params
	 * @return string $apiSig
	*/
	public function makeSign(array $params = array(), $options = array()) {
		$options = empty($options) ? $this->connection->_config : $options + $this->connection->_config;
		 if(empty($options['api_secret'])) {
			return false;
		}

		ksort($params);
		$apiSig = $options['api_secret'];
		foreach($params as $param => $value) {
			$apiSig .= $param . $value;
		}

		return $this->connection->last->api_sig = md5($apiSig);
	}

	/**
	 * Makes flickr auth url for getting permission.
	 * You can override settings a with passing $params key otherwise it'll use connection defaults.
	 *
	 * @access public
	 * @param string $permissions
	 * @param array $params
	 * @return string
	*/
	public function getAuthUrl($permission = 'read', $params = array()) {
		$authParams = $params = array(
			'perms' => $permission
		) + $this->_filterParams($params, array('api_key', 'perms', 'api_sig', 'extra'));
		$apiSig = $this->makeSign($params);
		$authParams['api_sig'] = $apiSig;
		ksort($authParams);
		foreach($authParams as $key => $value) {
			$authParams[] = urlencode($key) . '=' . urlencode($value);
			unset($authParams[$key]);
		}

		$sessionAdapter = array('name' => $this->connection->_config['session']);

		if(!isset($params['perms']) || !in_array($params['perms'], $this->_permissions)) {
			$params['perms'] = 'read';
		}

		$params = array(
			'service'		=> 'auth',
			'authParams'	=> '?'. implode('&', $authParams)
		);

		return $this->_filter(__METHOD__, $params, function($self, $params) {
			return $self->getDomain($params, 'auth');
		});
	}


	/**
	 * Preparares the session and token for given frob.
	 *
	 * @access public
	 * @param string $frob
	 * @return bool
	*/
	public function afterAuth($frob = null) {
		$sessionAdapter = array('name' => $this->connection->_config['session']);
		$currentSession = Session::read('li3_Flickr', $sessionAdapter);

		$params = compact('sessionAdapter', 'currentSession', 'frob');
		$permissions = $this->_permissions;

		$this->_filter(__METHOD__, $params, function($self, $params) use($permissions) {

			extract($params);

			if(!$frob || !$sessionAdapter || !$currentSession) {
				return false;
			}

			$response = $self->auth_getToken(array(
				'frob'	=> $frob
			), array('format' => 'json'));
			
			if(!isset($response->auth)) {
				return false;
			}

			$response = $response->auth;
			$permission = (bool) !$response->perms->_content ? 1 : $permissions[$response->perms->_content];

			return Session::write('li3_Flickr', array(
				'permission'	=> $permission,
				'frob'			=> $frob,
				'auth_token'	=> $response->token->_content,
				'user'			=> $response->user
			) + $currentSession, $sessionAdapter);
		});
	}

	/**
	 * Filtering the sended params
	 *
	 * @access protected
	 * @param array $params, array $against
	 * @return array
	*/
	protected function _filterParams($params = array(), $against = array()) {
		extract($this->connection->_config);
		$params += compact('api_key', 'api_secret', 'format');

		return array_intersect_key($params, array_fill_keys($against, null));
	}

	/**
	 * Key function that organazing params for request, makes API call and returns response for the configured format.
	 *
	 * @access protected
	 * @param array $apiMethod, array $requestParams
	 * @return object
	*/
	protected function _request($apiMethod = null, $requestParams = array()) {

		if(!$apiMethod) {
			return false;
		}
		
		extract($this->_methods[$apiMethod], EXTR_OVERWRITE);
		extract($requestParams);

		if(!isset($query) || !$this->checkPermissionByMethod($apiMethod, $query)) {
			return false;
		}

		$method = isset($this->_methods[$apiMethod]['method']) ? $this->_methods[$apiMethod]['method'] : false;
		$domain = isset($domain) ? $domain : $this->_domains['api'];
		$path = isset($path) ? $path : '/services/{:service}';

		$params = isset($params) ? $params : array('api_key');
		$options = isset($options) ? (
			$options + array('method' => $method) + $this->connection->_config
		) : $this->connection->_config;

		$method = $options['method'];

		if(!empty($options['format']) && $options['format']!='xml') {
			$params[] = 'format';
			if($options['format'] == 'json') {
				$params[] = 'nojsoncallback';
				$data['nojsoncallback'] = 1;
			}
		}

		$data['method'] = $apiMethod;
		$data += $this->_filterParams($query, $params);

		if($this->_methods[$apiMethod]['sign']) {
			$session = Session::read('li3_Flickr', array('name' => $options['session']));
			if($session && !empty($session['auth_token'])) {
				$params[] = 'auth_token';
				$data['auth_token'] = $session['auth_token'];
			}

			$data['api_sig'] = $this->makeSign($data, $options);
			$params[] = 'api_sig';
		}

		$options['host'] = $this->getDomain($options, $domain);
		$path = String::insert($path, $options);

		$this->connection = $this->_instance('service', $options);

		$response = $this->connection->{$method}($path, $data, $options);

		$params = compact('data', 'options', 'path');
		$filter = $this->_filter(__METHOD__, $params, function($self, $params) use($response) {

			$bare = $response;

			$response = isset($response->body[0]) ? $response->body[0] : $response;
			switch($params['options']['format']) {

				case 'json' :
					$return = json_decode($response);
				break;

				case 'php_serial' :
					$return = unserialize($response);
				break;

				case 'xml' :
					$return = simplexml_load_string($response);
				break;

				default :
					$return = $response;
				break;
			}

			$return->bare = $bare;
			return $return;
		});

		return $this->connection->last->response = $filter;
	}


	/**
	 * Catches all context method calls and if it's proper to call, starting the API request process. Otherwise invoking the method.
	 *
	 * @access public
	 * @param string $method
	 * @param array $params
	 * @return mixed
	 */
	public function __call($method, Array $params = array()) {
		$flickrPrefix = $this->connection->_config['method_prefix'];
		$apiMethod = preg_match("/{$flickrPrefix}/", $method) ? '' : "{$flickrPrefix}.";
		$apiMethod .= str_replace('_', '.', $method);

		$query = isset($params[0]) ? $params[0] : array();
		$options = isset($params[1]) ? $params[1] + $this->connection->_config : $this->connection->_config;

		if($options['skip_method_check'] || $this->checkMethod($apiMethod)) {
			$params = compact('query','options');

			return  $this->_filter(__METHOD__, $params, function($self, $params) use($apiMethod) {
				return $self->invokeMethod('_request', array($apiMethod, $params));
			});
		}

		return $this->connection->invokeMethod($method, $params);
	}
}
?>
