<?php
namespace li3_flickr\controllers;

use \lithium\data\Connections;
use \lithium\storage\Session;

class AuthController extends \lithium\action\Controller {

	public $frobSession = 'Flickr.frob';

	public function get_auth($permission = 'read', $redirect = null) {
		$validPermissions = array('read', 'write', 'delete');
		$connectionName = 'Flickr';
		$params = compact(array('permission', 'redirect', 'validPermissions', 'connectionName', 'baseUrl'));
		return $this->_filter(__METHOD__, $params, function($self, $params) {
			if(!in_array($params['connectionName'], Connections::get())) {
				return false;
			}
			$Flickr = Connections::get($params['connectionName']);
			$authUrl = $Flickr->getAuthUrl($params = array(
				'perms' => in_array($params['permission'], $params['validPermissions']) ? $params['permission'] : 'read',
				'extra' => empty($params['redirect']) ? ($self->request->env('https') ? 'https://' : 'http://' ) . $self->request->env('http_host') . $self->request->env('base') : $params['redirect']
			));
			return $self->redirect($authUrl);
		});
	}

	public function write_frob() {
		$frob = !isset($this->request->query['frob']) ? '' : "/{$this->request->query['frob']}";
		$extra = !isset($this->request->query['extra']) ? '' : $this->request->query['extra'];
		$params = compact(array('frob', 'extra'));
		return $this->_filter(__METHOD__, $params, function($self, $params) {
			Session::write($self->frobSession, $params['frob']);
			return $self->redirect($params['extra']);
		});
	}

	public static function check_frob() {
		return Session::check($this->frobSession);
	}
}
?>
