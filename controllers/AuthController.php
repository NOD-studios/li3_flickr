<?php
namespace li3_flickr\controllers;

use \lithium\data\Connections;
use \lithium\storage\Session;

class AuthController extends \lithium\action\Controller {

	public static $frobSession = 'Flickr.frob';
	public static $failRedirect = '/auth::failed';
	public static $connectionName = 'Flickr';
	public static $validPermissions = array('read', 'write', 'delete');
	protected static $_Flickr = false;

	protected function _init() {
		if(self::$_Flickr === false) {
			self::$_Flickr = Connections::get(self::$connectionName);
		}
		Session::config(array(
			'default' => array('adapter' => 'Php')
		));
		parent::_init();
	}

	public function get_auth($permission = 'read', $redirect = null) {
		$Flickr = self::$_Flickr;
		$params = compact(array('permission', 'redirect'));
		return $this->_filter(__METHOD__, $params, function($self, $params) use ($Flickr)  {
			if(!in_array($self::$connectionName, Connections::get())) {
				return false;
			}
			$authUrl = $Flickr->getAuthUrl($params = array(
				'perms' => in_array($params['permission'], $self::$validPermissions) ? $params['permission'] : 'read',
				'extra' => empty($params['redirect']) ? ($self->request->env('https') ? 'https://' : 'http://' ) . $self->request->env('http_host') . $self->request->env('base') : $params['redirect']
			));
			return $self->redirect($authUrl);
		});
	}

	public function write_frob() {
		$frob = empty($this->request->query['frob']) ? '' : "/{$this->request->query['frob']}";
		$extra = empty($this->request->query['extra']) ? '' : $this->request->query['extra'];
		$Flickr = self::$_Flickr;
		$params = compact(array('frob', 'extra'));
		return $this->_filter(__METHOD__, $params, function($self, $params) use ($Flickr) {
			if(Session::write($self::$frobSession, $params['frob'])) {
				return $self->redirect($params['extra']);
			}
			return $self->redirect($self::$failRedirect);
		});
	}

	public static function check_frob() {
		return Session::check(self::$frobSession);
	}
}
?>
