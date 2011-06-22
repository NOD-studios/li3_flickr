<?php
use \lithium\storage\Session;

lithium\util\collection\Filters::apply('lithium\action\Dispatcher', '_callable', function($self, $params, $chain) {
	$controller = $chain->next($self, $params, $chain);
	if(isset($controller->request->query['frob'])) {
		$controller->flickrAuthStatus = false;
		$flickr = \lithium\data\Connections::get('flickr');
		if($flickr !== false) {
			$controller->flickrAuthStatus = $flickr->afterAuth($controller->request->query['frob']) ?: 0;
		}
	}
	return $controller;
});
?>