<?php
use \lithium\net\http\Router;

Router::connect('/flickr/{:controller}/{:action}/{:args}', array('library' => 'li3_flickr'));
?>
