<?php
/************************************************************
 * InfiniteWP Admin panel									*
 * Copyright (c) 2012 Revmakx								*
 * www.revmakx.com											*
 *															*
 ************************************************************/
$executeFileTimeStart = $timeStart = microtime(true);
@ignore_user_abort(true);
define('IS_EXECUTE_FILE', true);

$isExecuteJobs = false;

if(@$_REQUEST['check'] == 'sameURL'){
	echo 'same_url_connection';
	exit;
}

require_once('includes/app.php');

if(@$_REQUEST['check'] == 'sameURLUsingDB'){
	echo 'same_url_connection';
	updateOption('fsockSameURLCheckUsingDBValue', $_REQUEST['fsockSameURLCheckUsingDBValue']);
	exit;
}

set_time_limit(3600);//3600 = 1hr, this is only for safety, we are controlling timeout in CURL 

if($_REQUEST['runOffBrowserLoad'] == 'true'){
	runOffBrowserLoad();
	exit;
}
elseif(!empty($_POST['historyID']) && !empty($_POST['actionID'])){

	$historyID = $_POST['historyID'];
	$actionID = $_POST['actionID'];
	
	//if(empty($historyID) || empty($actionID)){ echo 'invalidRequest'; exit; }
	//fix: add some security 
	
	$isValid = DB::getExists("?:history", "historyID", "historyID = ".$historyID." AND actionID = '".$actionID."'");
	if($isValid){
		
		if(empty($_SESSION['userID'])){
			//setting userID of the task to session, because when this file running by fsock, it will not have the same session IWP Admin Panel
			$userID = DB::getField("?:history", "userID", "historyID = ".$historyID." AND actionID = '".$actionID."'");
			$_SESSION['userID'] = $userID;
			$_SESSION['offline'] = true;
		}
		echo 'executingRequest';
		executeRequest($historyID);
		
		$isExecuteJobs = true; 	
		
	}
}

if($isExecuteJobs || $_REQUEST['executeJobs'] == 'true'){
	$noNewTaskAfterNSecs = 60;
	//do additional jobs
	if(($GLOBALS['executeFileTimeStart'] + $noNewTaskAfterNSecs) > time()){
		do{
			$status = executeJobs();
		}
		while($status['requestInitiated'] > 0 && $status['requestPending'] > 0 && ($GLOBALS['executeFileTimeStart'] + $noNewTaskAfterNSecs) > time());
	}	
	exit;
}
	
?>