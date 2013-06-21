<?php
/************************************************************
 * InfiniteWP Admin panel									*
 * Copyright (c) 2012 Revmakx								*
 * www.revmakx.com											*
 *															*
 ************************************************************/
 
$ajaxPageTimeStart = $timeStart = microtime(true);
define('IS_AJAX_FILE', true);
require('includes/app.php');

$result = panelRequestManager::handler($_REQUEST);
if(Reg::get('settings.executeUsingBrowser') != 1){
	if(!headers_sent()){
		@ob_start("ob_gzhandler");
	}
	echo $result;
}
else{
	outputStringAndCloseConnection($result);
}

function outputStringAndCloseConnection($stringToOutput) 
{    
    @set_time_limit(3600);
    @ignore_user_abort(true);

	$stringToOutput = gzencode($stringToOutput, 9, FORCE_GZIP);
   	@ob_start();
    echo $stringToOutput;
    
    $size = ob_get_length();
   
    header("Content-Encoding: gzip");
    header("Content-Length: $size");
    header('Connection: close');
   
    @ob_end_flush();
   	@ob_flush();
    @flush(); 

    if (session_id()) session_write_close();
	//start your offfline work here
	
	if(Reg::get('currentRequest.runOffBrowserLoad') === 'true'){//this will be first ajax call after Panel loaded in browser
		runOffBrowserLoad();
	}
	
	$noNewTaskAfterNSecs = 60;
	if(($GLOBALS['ajaxPageTimeStart'] + $noNewTaskAfterNSecs) > time()){
		do{
			$status = executeJobs();
		}
		while($status['requestInitiated'] > 0 && $status['requestPending'] > 0 && ($GLOBALS['ajaxPageTimeStart'] + $noNewTaskAfterNSecs) > time());
	}
	exit;
} 


?>