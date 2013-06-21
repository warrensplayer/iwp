<?php

/************************************************************
 * InfiniteWP Admin panel									*
 * Copyright (c) 2012 Revmakx								*
 * www.revmakx.com											*
 *															*
 ************************************************************/
 
@set_time_limit(300);
if(!defined('UPDATE_PAGE') && !defined('IS_AJAX_FILE')){
	@ob_start("ob_gzhandler");
}
 
include_once(dirname(dirname(__FILE__))."/config.php");
if(!defined('APP_ROOT')){
	header('Location: install/index.php');
	exit;		
}
include_once(APP_ROOT."/includes/db.php");
include_once(APP_ROOT."/includes/commonFunctions.php");
include_once(APP_ROOT."/includes/registry.php");
include_once(APP_ROOT."/includes/TPL.php");
include_once(APP_ROOT."/includes/file.php");
include_once(APP_ROOT."/controllers/appFunctions.php");
include_once(APP_ROOT."/controllers/manageClients.php");
include_once(APP_ROOT."/controllers/panelRequestManager.php");
include_once(APP_ROOT."/controllers/TPLFunctions.php");

//Static Data
include_once(APP_ROOT."/includes/httpErrorCodes.php");


define('APP_PHP_CRON_CMD', 'php -q -d safe_mode=Off ');


Reg::set('config', $config);
unset($config);
Reg::set('hooks', array());

protocolRedirect();


//session
@session_set_cookie_params(0, dirname($_SERVER['PHP_SELF']));
@session_start();


//DB connection starts here
DB::connect();

if(!defined('UPDATE_PAGE')){
//addons
loadActiveAddons();

}

//To prevent SQL Injection
$_REQUEST_ORIGINAL = $_REQUEST;
$_GET_ORIGINAL = $_GET;
$_POST_ORIGINAL = $_POST;

$_REQUEST = filterParameters($_REQUEST);
$_GET = filterParameters($_GET);
$_POST = filterParameters($_POST);


$settings = DB::getRow("?:settings", "*", 1);
Reg::set('settings', unserialize($settings['general']));

include_once(APP_ROOT."/controllers/processManager.php");

Reg::set('dateFormatLong', 'M d, Y @ h:ia');

clearUncompletedTask();

checkUserLoggedInAndRedirect();

?>