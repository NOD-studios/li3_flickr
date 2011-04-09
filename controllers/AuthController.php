<?php
namespace li3_flickr\controllers;

use \lithium\net\http\Router;
use \lithium\data\Connections;
use \lithium\storage\Session;

class AuthController extends \lithium\action\Controller {

	public $authError = '';

	public function get_auth($permission = 'read', $redirect = null) {
		if(!in_array('Flickr', Connections::get())) {
			return false;
		}
		$Flickr = Connections::get('Flickr');
		$validPermissions = array('read', 'write', 'delete');
		$authUrl = $Flickr->getAuthUrl($params = array(
			'perms' => in_array($permission, $validPermissions) ? $permission : 'read',
			'extra' => $redirect ? $redirect : ($this->request->env('https') ? 'https://' : 'http://' ) . $this->request->env('http_host') . $this->request->env('base')
		));
		return $this->redirect($authUrl);
	}

	public function write_frob() {
		$frob = !isset($this->request->query['frob']) ? '' : "/{$this->request->query['frob']}";
		$extra = !isset($this->request->query['extra']) ? '' : $this->request->query['extra'];
		$params = compact(array('frob', 'extra'));
		$redirect = $this->_filter(__METHOD__, $params, function($self, $params) {
			Session::write('Flickr.frob', $params['frob']);
			$chain->next($self, $params, $chain);
			return $params['extra'];
		});
		$this->redirect($redirect);
	}

	public static function check_frob() {
		return Session::check('Flickr.frob');
	}
}
?>
