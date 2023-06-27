<?php
if (!defined('BOOTSTRAP')) { die('Access denined'); }

if($mode == 'modal') {
	
	Tygh::$app['view']->assign('modal_url', $_REQUEST['modal_url']);
	Tygh::$app['view']->assign('cancel_url', fn_url('checkout.checkout'));
	Tygh::$app['view']->display('addons/paygine/views/paygine/modal.tpl');
	
} elseif($mode == 'redirect') {
	
	$args = $_REQUEST;
	$action = $_REQUEST['action'];
	unset($args['dispatch'], $args['modal'], $args['action']);
	$uri = $action . "?" . urldecode(http_build_query($args));
	Tygh::$app['view']->assign('url', fn_url($uri));
	Tygh::$app['view']->display('addons/paygine/views/paygine/redirect.tpl');
	
}
exit;