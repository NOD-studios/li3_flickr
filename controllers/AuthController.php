<?php
namespace li3_flickr\controllers;

use \lithium\data\Connections;
use \lithium\storage\Session;

class AuthController extends \lithium\action\Controller {

	public $authError = '';

	public function get_auth($permission = 'read', $redirect = null) {
		$validPermissions = array('read', 'write', 'delete');
		$connectionName = 'Flickr';
		$baseUrl = ($this->request->env('https') ? 'https://' : 'http://' ) . $this->request->env('http_host') . $this->request->env('base');
		$params = compact(array('permission', 'redirect', 'validPermissions', 'connectionName', 'baseUrl'));

		$authUrl = $this->_filter(__METHOD__, $params, function($self, $params) {
			if(!in_array($params['connectionName'], Connections::get())) {
				return false;
			}
			$Flickr = Connections::get($params['connectionName']);
			return $authUrl = $Flickr->getAuthUrl($params = array(
				'perms' => in_array($params['permission'], $params['validPermissions']) ? $params['permission'] : 'read',
				'extra' => empty($params['redirect']) ? $params['baseUrl'] : $params['redirect']
			));
		});
		return $this->redirect($authUrl);
	}

	public function write_frob() {
		$frob = !isset($this->request->query['frob']) ? '' : "/{$this->request->query['frob']}";
		$extra = !isset($this->request->query['extra']) ? '' : $this->request->query['extra'];
		$params = compact(array('frob', 'extra'));
		$redirect = $this->_filter(__METHOD__, $params, function($self, $params) {
			Session::write('Flickr.frob', $params['frob']);
			return $params['extra'];
		});
		$this->redirect($redirect);
	}

	public static function check_frob() {
		return Session::check('Flickr.frob');
	}
}
?>
