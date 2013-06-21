<?php
/************************************************************
 * InfiniteWP Admin panel									*
 * Copyright (c) 2012 Revmakx								*
 * www.revmakx.com											*
 *															*
 ************************************************************/
 
 
function processCallReturn($Array){
	return $Array;
}

function isPluginResponse($data){ // Checking if we got the rite data / it didn't time out
 
	if(stripos($data,'IWPHEADER') === false){
		return false;
	}
	return true;
}
function signData($data, $isOpenSSLActive, $privateKey, $randomSignature){
	if( function_exists('openssl_verify') && $isOpenSSLActive ){
		openssl_sign($data, $signature, base64_decode($privateKey));
	}
	elseif(!$isOpenSSLActive){
		$signature =  md5($data . $randomSignature);
	}
	else{
		return false;	
	}
	return $signature;
}

function secureData($data, $isOpenSSLActive, $privateKey, $randomSignature){
	if(!empty($data) && $isOpenSSLActive && !empty($privateKey)){
	   $secureData = serialize($data);		 
	   $secureDataArray = str_split($secureData, 96); //max length 117
		   
	   $secureDataEncrypt = array();
	   
		foreach($secureDataArray as $secureDataPart){				 			  
			openssl_private_encrypt($secureDataPart, $secureDataEncryptPart, base64_decode($privateKey));
			$secureDataEncrypt[] = $secureDataEncryptPart;
		}
		$data = $secureDataEncrypt;
	}	
	return $data;	
}

function addHistory($historyData, $historyAdditionalData=''){
	$historyData['userID'] = $_SESSION['userID'];
	$historyData['microtimeAdded']=microtime(true);
	$historyID =  DB::insert('?:history', $historyData);
	if(!empty($historyAdditionalData)){
		foreach($historyAdditionalData as $row){			
			$row['historyID'] = $historyID;
			DB::insert("?:history_additional_data", $row);
			
		}
	}
	
	return $historyID;
}

function updateHistory($historyData, $historyID, $historyAdditionalData=false){
	DB::update('?:history', $historyData, 'historyID='.$historyID);
	
	if(!empty($historyAdditionalData)){
		DB::update('?:history_additional_data', $historyAdditionalData, 'historyID='.$historyID);
	}
}

function getHistory($historyID, $getAdditional=false){
	$historyData = DB::getRow('?:history', '*', 'historyID='.$historyID);
	if($getAdditional){
		$historyData['additionalData'] = DB::getArray('?:history_additional_data', '*', 'historyID='.$historyID);
	}
	return $historyData;
}

function getSiteData($siteID){
	return DB::getRow("?:sites","*","siteID=".$siteID."");
}

function getSitesData($siteIDs=false, $where='1'){//for current user
	if(!empty($siteIDs)){
		$where = "siteID IN (". implode(', ', $siteIDs) .")";
	}
	
	return DB::getArray("?:sites", "*", $where, "siteID");
}

function prepareRequestAndAddHistory($PRP){	

	$defaultPRP = array('doNotExecute' 		=> false,
						'exitOnComplete' 	=> false,
						'doNotShowUser' 	=> false,//hide in history
						'directExecute' 	=> false,
						'signature' 		=> false,
						'timeout' 			=> DEFAULT_MAX_CLIENT_REQUEST_TIMEOUT,
						'runCondition'		=> false,
						'status'			=> 'pending',
						'isPluginResponse'	=> 1,
						'sendAfterAllLoad'	=> false,
						'callOpt'			=> array()
						);
						
	$PRP = array_merge($defaultPRP, $PRP);
	@extract($PRP);
	
	if(empty($historyAdditionalData)){
		echo 'noHistoryAdditionalData';
		return false;	
	}
	
	if( ($siteData['connectURL'] == 'default' && defined('CONNECT_USING_SITE_URL') && CONNECT_USING_SITE_URL == 1) || $siteData['connectURL'] == 'siteURL'){
		$URL = $siteData['URL'];
	}
	else{//if($siteData['connectURL'] == 'default' || $siteData['connectURL'] == 'adminURL')
		$URL = $siteData['adminURL'];
	}
	
	$historyData = array('siteID' 	=> $siteData['siteID'], 
						 'actionID' => Reg::get('currentRequest.actionID'),
						 'userID' 	=> $_SESSION['userID'],
						 'type' 	=> $type,
						 'action' 	=> $action,
						 'events' 	=> $events,
						 'URL' 		=> $URL,
						 'timeout' 	=> $timeout,
						 'isPluginResponse' => $isPluginResponse);	
	if($doNotShowUser){
		$historyData['showUser'] = 'N';
	}
	
	if(!empty($siteData['callOpt'])){
		$callOpt = @unserialize($siteData['callOpt']);
	}
	
	if(!empty($siteData['httpAuth'])){
		$callOpt['httpAuth'] = @unserialize($siteData['httpAuth']);
	}
	if(!empty($runCondition)){
		$historyData['runCondition'] = $runCondition;
	}
	if(!empty($timeScheduled)){
		$historyData['timeScheduled'] = $timeScheduled;
	}
	
	$historyData['callOpt'] = serialize($callOpt);
	$historyID = addHistory($historyData, $historyAdditionalData);
		
	if($signature === false){
		$signature = signData($requestAction.$historyID, $siteData['isOpenSSLActive'], $siteData['privateKey'], $siteData['randomSignature']);
	}
	
	$requestParams['username'] =  $siteData['adminUsername'];
	
	if(isset($requestParams['secure'])){
		$requestParams['secure'] = secureData($requestParams['secure'], $siteData['isOpenSSLActive'], $siteData['privateKey'], $siteData['randomSignature']);
	}
	
	$requestData = array('iwp_action' => $requestAction, 'params' => $requestParams, 'id' => $historyID, 'signature' => $signature, 'iwp_admin_version' => APP_VERSION);
		
	$updateHistoryData = array('status' => $status);
	
	updateHistory($updateHistoryData, $historyID);
	
	DB::insert("?:history_raw_details", array('historyID' => $historyID, 'request' => base64_encode(serialize($requestData)), 'panelRequest' => serialize($_REQUEST) ) );
	
	if($directExecute){
		set_time_limit(0);
		echo 'direct_execute<br />';
		executeRequest($historyID, $type, $action, $siteData['URL'], $requestData, $timeout, true, $callOpt);
	}
	else{
		echo 'async_call_it_should_be<br />';
		if($exitOnComplete){ set_time_limit(0); echo "async_call_it_should_be_working"; Reg::set('currentRequest.exitOnComplete', true); }
		elseif($sendAfterAllLoad){ Reg::set('currentRequest.sendAfterAllLoad', true); }
	}
	return $historyID;
}

function executeRequest($historyID, $type='', $action='', $URL='', $requestData='', $timeout='', $isPluginResponse=true, $callOpt=array()){	
	
	//get responseProcessor
	$responseProcessor = array();
	$responseProcessor['plugins']['install'] = $responseProcessor['themes']['install'] = 'installPluginsThemes';
	$responseProcessor['plugins']['manage'] = $responseProcessor['themes']['manage'] = 'managePluginsThemes';	
	$responseProcessor['stats']['getStats'] = 'getStats';
	$responseProcessor['PTC']['update'] = 'updateAll';
	$responseProcessor['plugins']['get'] = $responseProcessor['themes']['get'] = 'getPluginsThemes';
	$responseProcessor['backup']['now'] = 'backup';
	$responseProcessor['backup']['restore'] = 'restoreBackup';
	$responseProcessor['backup']['remove'] = 'removeBackup';
	$responseProcessor['site']['add'] = 'addSite';
	$responseProcessor['site']['remove'] = 'removeSite';
	$responseProcessor['clientPlugin']['update'] = 'updateClient';

	setHook('responseProcessors', $responseProcessor);
	
	$historyData = getHistory($historyID);
	$actionResponse = $responseProcessor[$historyData['type']][$historyData['action']];
	
	if(manageClients::methodPreExists($actionResponse)){
		manageClients::executePre($actionResponse, $historyID);
		$historyDataTemp = getHistory($historyID);
		if($historyDataTemp['status'] != 'pending'){
			return false;	
		}
		unset($historyDataTemp);	
	}	
	
	if(empty($type) || empty($action) || empty($URL) || empty($requestData)){
		$historyData = getHistory($historyID);
		$historyRawDetails = DB::getRow("?:history_raw_details", "*", "historyID=".$historyID);
		$type = $historyData['type'];
		$action = $historyData['action'];
		$URL = $historyData['URL'];
		$timeout =  $historyData['timeout'];
		$requestData = unserialize(base64_decode($historyRawDetails['request']));
		$isPluginResponse =  $historyData['isPluginResponse'];
		$callOpt = @unserialize($historyData['callOpt']);
	}
	updateHistory(array('microtimeInitiated' => microtime(true), 'status' => 'running'), $historyID);

	$updateHistoryData = array();
	list($rawResponseData, $updateHistoryData['microtimeStarted'], $updateHistoryData['microtimeEnded'], $curlInfo)  = doCall($URL, $requestData, $timeout, $callOpt);
	

	DB::update("?:history_raw_details", array('response' => addslashes($rawResponseData), 'callInfo' => serialize($curlInfo)), "historyID = ".$historyID);
		
	//checking request http executed
	if($curlInfo['info']['http_code'] != 200 || !empty($curlInfo['errorNo'])){
		$updateHistoryAdditionalData = array();
		$updateHistoryAdditionalData['status'] = $updateHistoryData['status'] = 'netError';
		
		if($curlInfo['info']['http_code'] != 0 && $curlInfo['info']['http_code'] != 200){
			$updateHistoryAdditionalData['error'] = $updateHistoryData['error'] = $curlInfo['info']['http_code'];
			$updateHistoryAdditionalData['errorMsg'] = 'HTTP Error '.$curlInfo['info']['http_code'].': '.$GLOBALS['httpErrorCodes'][ $curlInfo['info']['http_code'] ].'.';
		}
		elseif($curlInfo['errorNo']){
			$updateHistoryAdditionalData['error'] = $updateHistoryData['error'] = $curlInfo['errorNo'];
			$updateHistoryAdditionalData['errorMsg'] = htmlspecialchars($curlInfo['error']);
		}
		
		if(!isPluginResponse($rawResponseData)){//sometimes 500 error with proper IWP Client Response, so if it not proper response continue set error and exit
			return updateHistory($updateHistoryData, $historyID, $updateHistoryAdditionalData);
		}
	}
	
	if($isPluginResponse){//$isPluginResponse is set to true then expecting result should be pluginResponse
		
		//checking response is the plugin data
		if(!isPluginResponse($rawResponseData)){ //Checking the timeout		
			$updateHistoryAdditionalData = array();
			$updateHistoryAdditionalData['status'] = $updateHistoryData['status'] = 'error';	
			$updateHistoryAdditionalData['error'] = $updateHistoryData['error'] = 'main_plugin_connection_error';
			$updateHistoryAdditionalData['errorMsg'] = 'IWP Client plugin connection error.';
			return updateHistory($updateHistoryData, $historyID, $updateHistoryAdditionalData);		
		}		
		
		removeResponseJunk($rawResponseData);
		$responseData = processCallReturn( unserialize(base64_decode($rawResponseData)) );
	}
	else{
		$responseData = $rawResponseData;
	}
	
	$updateHistoryData['status'] = 'processingResponse';
	updateHistory($updateHistoryData, $historyID);
	
	//handling reponseData below
	
	if(manageClients::methodResponseExists($actionResponse)){
		manageClients::executeResponse($actionResponse, $historyID, $responseData);
		//call_user_func('manageClients::'.$funcName, $historyID, $responseData);
		updateHistory(array('status' => 'completed'), $historyID);
		return true;
	}
	else{
		updateHistory(array('status' => 'completed'), $historyID);
		echo '<br>no_response_processor';
		return 'no_response_processor';
	}	
}

function onAsyncFailUpdate($historyID, $info){
	
	if($info['status'] == true){ return false; }
	
	$errorMsg = 'Fsock error: ';
	if(!empty($info['error'])){
		$errorMsg .= $info['error'];
	}
	
	if($info['writable'] === false){
		$errorMsg .= 'Unable to write request';
	}	
	
	$updateHistoryAdditionalData = array();
	$updateHistoryAdditionalData['status'] = $updateHistoryData['status'] = 'error';	
	$updateHistoryAdditionalData['error'] = $updateHistoryData['error'] = 'fsock_error';
	$updateHistoryAdditionalData['errorMsg'] = $errorMsg;
	updateHistory($updateHistoryData, $historyID, $updateHistoryAdditionalData);
	return true;
	
}

function removeResponseJunk(&$response){
	$headerPos = stripos($response, '<IWPHEADER');
	if($headerPos !== false){
		$response = substr($response, $headerPos);
		$response = substr($response, strlen('<IWPHEADER>'), stripos($response, '<ENDIWPHEADER'));
	}
}

function getServiceResponseToArray($response){
	removeResponseJunk($response);
	$response = @base64_decode($response);
	$response = @unserialize($response);
	return $response;
}

function responseDirectErrorHandler($historyID, $responseData){
	
	if(!empty($responseData['error']) && is_string($responseData['error'])){
		
		DB::update("?:history_additional_data", array('status' => 'error', 'errorMsg' => $responseData['error']), "historyID=".$historyID."");
		return true;
	}
	return false;
	
}

function exitOnComplete(){
	$maxTimeoutSeconds = 60;//1 min
	if(!empty($_SESSION['offline'])){
		$maxTimeoutSeconds = 60 * 15;//15 min
	}
	$maxTimeoutTime = time() + $maxTimeoutSeconds;
	while(DB::getExists("?:history", "historyID", "actionID = '".Reg::get('currentRequest.actionID')."' AND status NOT IN('completed', 'error', 'netError')") && $maxTimeoutTime > time()){
		usleep(100000);//1000000 = 1 sec
	}
}

function sendAfterAllLoad(&$requiredData){//changing exit on complete technique

	$_requiredData = array();
	if(!empty($requiredData)){
		$exceptionRequireData = array('getHistoryPanelHTML');
		foreach($requiredData as $key => $value){
			if(!in_array($key, $exceptionRequireData)){
				$_requiredData[$key] = $requiredData[$key];
				unset($requiredData[$key]);
			}
		}
	}
	
	if(!isset($_SESSION['waitActions'])) $_SESSION['waitActions'] = array();
	$actionID = Reg::get('currentRequest.actionID');
	$currentTime = time();
	$_SESSION['waitActions'][$actionID] = array('timeInitiated' => $currentTime,
												'timeExpiresFromSession' => $currentTime + (20 * 60),
												'requiredData' => $_requiredData
												);	
}

function clearUncompletedTask(){
	$addtionalTime = 30;//seconds
	$time = time();
	$updateData = array( 'H.status' => 'netError',
						 'H.error' => 'timeoutClear',
						 'HAD.status' => 'netError',
						 'HAD.error' => 'timeoutClear',
						 'HAD.errorMsg' => 'Timeout'
						);
	$where = "H.historyID = HAD.historyID";
	$where .= " AND H.status IN('initiated', 'running')";
	$where .= " AND (H.microtimeInitiated + H.timeout + ".$addtionalTime.") < ".$time."";
	$where .= " AND H.microtimeInitiated > 0";//this will prevent un-initiated task from clearing
	DB::update("?:history H, ?:history_additional_data HAD", $updateData, $where);
}
//Login .
function userLogin($params){
	$userName = DB::getRow("?:users", "userID", "email = '".trim($params["email"])."' AND password = '".sha1($params["password"])."'" );
	$isUserExists = !empty($userName["userID"]) ? true : false;	
	
	$allowedLoginIPs = DB::getFields("?:allowed_login_ips", "IP", "1", "IP");
	$allowedLoginIPsClear = 1;
	if($isUserExists && !empty($allowedLoginIPs)){
		$allowedLoginIPsClear = 0;
		foreach($allowedLoginIPs as $IP){
			if(IPInRange($_SERVER['REMOTE_ADDR'], $IP)){
				$allowedLoginIPsClear = 1;
				break;
			}
		}
	}
	
	if($isUserExists && $allowedLoginIPsClear == 1){
		//session_start();
		$_SESSION["userID"] = $userName['userID'];
		header('Location: '.APP_URL);//'Location: ' => index.php
		exit;
	}
	else{
		$_SESSION = array();
		$_SESSION['errorMsg'] = 'Invalid credentials.';
		if($allowedLoginIPsClear == 0){
			$_SESSION['errorMsg'] = 'Access restricted.';
		}
		header('Location: login.php');
		exit;
	}
}

function userLogout($userRequest=false){
	$_SESSION = array();
	if($userRequest){
		$_SESSION['successMsg'] = 'You have successfully logged out.';
	}
	else{
		//$_SESSION['errorMsg'] = 'Please login to access Admin panel.';
	}
	if(!defined('IS_AJAX_FILE')){
		header('Location: login.php');
		exit;
	}
}

function checkUserLoggedIn(){
	
	$return = false;
	if(!empty($_SESSION['userID'])){
		$return = DB::getExists("?:users", "userID", "userID = ".$_SESSION['userID']);
	}

	if($return == false){
		userLogout();
	}
	return $return;
}

function checkUserLoggedInAndRedirect(){
	//check session, if not logged in redirect to login page
	if(!defined('USER_SESSION_NOT_REQUIRED') && !defined('IS_EXECUTE_FILE')){
		if(!checkUserLoggedIn()){
			echo json_encode(array('logout' => true));
			exit;
		}
	}
}

function anonymousDataRunCheck(){

	if(!Reg::get('settings.sendAnonymous')){ return false; }
	
	$currentTime = time();	
	$nextSchedule = getOption('anonymousDataNextSchedule');
	
	if($currentTime > $nextSchedule){		
		//execute
		$fromTime = getOption('anonymousDataLastSent');
		$isSent = anonymousDataSend($fromTime, $currentTime);
		if(!$isSent){
			return false;	
		}
		//if sent execute below code
		updateOption('anonymousDataLastSent', $currentTime);
		
		$installedTime = getOption('installedTime');
		
		$weekDay = @date('w', $installedTime);
		$currWeekDay = @date('w', $currentTime);
	    $nextNoOfDays = 7 - $currWeekDay + $weekDay;		
		$nextSchedule = mktime(@date('H', $installedTime), @date('i', $installedTime), @date('s', $installedTime), @date('m', $currentTime), @date('d', $currentTime)+$nextNoOfDays, @date('Y', $currentTime));
		updateOption('anonymousDataNextSchedule', $nextSchedule);
		
	}
}

function anonymousDataSend($fromTime, $toTime){

	$fromTime = (int)$fromTime;
	
	$anonymousData = array();
	$addons = DB::getFields("?:addons", "slug", "1", 'slug');	
	if(!empty($addons)){
		foreach($addons as $slug => $v){
			$addons[$slug] = getAddonVersion($slug);
		}
	}	
	$anonymousData['addonsBought'] = $addons;
	$anonymousData['sites'] 			= DB::getField("?:sites", "count(siteID)", "1");
	$anonymousData['groups']			= DB::getField("?:groups", "count(groupID)", "1");
	$anonymousData['groupMaxSites']		= DB::getField("?:groups_sites", "count(siteID) as maxSiteCount", "1 GROUP BY groupID ORDER BY maxSiteCount DESC LIMIT 1", "maxSiteCount");
	$anonymousData['hiddenCount']		= DB::getField("?:hide_list", "count(ID)", "1");
	$anonymousData['favourites']		= DB::getField("?:favourites", "count(ID)", "1");
	$anonymousData['settings']			= DB::getArray("?:settings", "*", "1");
	$anonymousData['users']				= DB::getField("?:users", "count(userID)", "1");
	$anonymousData['allowedLoginIps']	= DB::getField("?:allowed_login_ips", "count(IP)", "1");
		
	$anonymousData['siteNameMaxLength']	= DB::getField("?:sites", "length(name) as siteNameLength", "1 ORDER BY siteNameLength DESC LIMIT 1");	
	$anonymousData['groupNameMaxLength']= DB::getField("?:groups", "length(name) as groupNameLength", "1 ORDER BY groupNameLength DESC LIMIT 1");
	
	//$anonymousData['appVersion'] 				= APP_VERSION;
	$anonymousData['appInstalledTime'] 			= getOption('installedTime');
	$anonymousData['lastHistoryActivityTime'] 	= DB::getField("?:history", "microtimeAdded", "1 ORDER BY historyID DESC");	
	$anonymousData['updatesNotificationMailLastSent'] = getOption('updatesNotificationMailLastSent');
	
	
	//to find hostType
	$anonymousData['hostType'] = 'unknown';
	
	if(!empty($_SERVER['SERVER_ADDR'])){
		$SERVER_ADDR = $_SERVER['SERVER_ADDR'];
	}
	else{
		$SERVER_ADDR = gethostbyname($_SERVER['HTTP_HOST']);
	}
	
	if(!empty($SERVER_ADDR)){
		if(IPInRange($SERVER_ADDR, '127.0.0.0-127.255.255.255')){
			$anonymousData['hostType'] = 'local';
		}
		elseif(IPInRange($SERVER_ADDR, '10.0.0.0-10.255.255.255') || IPInRange($SERVER_ADDR, '172.16.0.0-172.31.255.255') || IPInRange($SERVER_ADDR, '192.168.0.0-192.168.255.255')){
			$anonymousData['hostType'] = 'private';
		}
		else{
			$anonymousData['hostType'] = 'public';
		}
	}
	
	//history stats
	$anonymousData['historyStatusStats']= DB::getFields("?:history", "count(status) as statusCount, status", "microtimeAdded > '".$fromTime."' AND microtimeAdded <= '".$toTime."' GROUP BY status", "status");
	$tempHistoryData					= DB::getRow("?:history", "count(historyID) as historyCount, count(DISTINCT actionID) as historyActions", "microtimeAdded > '".$fromTime."' AND microtimeAdded <= '".$toTime."'");
	$anonymousData['historyCount']		= $tempHistoryData['historyCount'];
	$anonymousData['historyActions']	= $tempHistoryData['historyActions'];
	
	$anonymousData['historyAdditionalStatusStats']	= DB::getFields("?:history H, ?:history_additional_data HAD", "count(HAD.status) as statusCount, HAD.status", "H.historyID = HAD.historyID AND H.microtimeAdded > '".$fromTime."' AND H.microtimeAdded <= '".$toTime."' GROUP BY HAD.status", "status");
	$anonymousData['historyAdditionalCount']		= DB::getField("?:history H, ?:history_additional_data HAD", "count(HAD.historyID) as historyCount", "H.historyID = HAD.historyID AND H.microtimeAdded > '".$fromTime."' AND H.microtimeAdded <= '".$toTime."'");
	
	$historyEventStatusStatsArray	= DB::getArray("?:history H, ?:history_additional_data HAD", "H.type, H.action, HAD.detailedAction, HAD.status, COUNT(H.historyID) as events", "H.historyID = HAD.historyID GROUP BY H.type, H.action, HAD.detailedAction ORDER BY H.type, H.action, HAD.detailedAction, HAD.status");

	$historyEventStatusStats= array();
	if(!empty($historyEventStatusStatsArray)){
		foreach($historyEventStatusStatsArray as $v){
			
			if(!isset($historyEventStatusStats[$v['type']][$v['action']][$v['detailedAction']][$v['status']])){
				$historyEventStatusStats[$v['type']][$v['action']][$v['detailedAction']]['total'] = 0;
			}
			$historyEventStatusStats[$v['type']][$v['action']][$v['detailedAction']][$v['status']] = $v['events'];			
			$historyEventStatusStats[$v['type']][$v['action']][$v['detailedAction']]['total'] += $v['events'];
		}
	}	
	
	$anonymousData['historyEventStatusStats'] = $historyEventStatusStats;
		
	$anonymousData['siteStats']					= DB::getArray("?:sites", "WPVersion, pluginVersion, isOpenSSLActive", "1");	
	
	$anonymousData['server']['PHP_VERSION'] 	= phpversion();
	$anonymousData['server']['PHP_CURL_VERSION']= curl_version();
	$anonymousData['server']['MYSQL_VERSION'] 	= DB::getField("select version() as V");
	$anonymousData['server']['OS'] =  php_uname('s');
	$anonymousData['server']['OSVersion'] =  php_uname('v');
	$anonymousData['server']['Machine'] =  php_uname('m');
	
	$anonymousData['server']['PHPDisabledFunctions'] = explode(',', ini_get('disable_functions'));	
	array_walk($anonymousData['server']['PHPDisabledFunctions'], 'trimValue');
	
	$anonymousData['server']['PHPDisabledClasses'] = explode(',', ini_get('disable_classes'));	
	array_walk($anonymousData['server']['PHPDisabledClasses'], 'trimValue');
	
	$anonymousData['browser'] = $_SERVER['HTTP_USER_AGENT'];
	
	$requestData = array('anonymousData' => serialize($anonymousData), 'appInstallHash' => APP_INSTALL_HASH, 'appVersion' => APP_VERSION);
		
	list($result) = doCall(getOption('serviceURL').'anonymous.php', $requestData);
	
	$result = getServiceResponseToArray($result);
	
	if($result['status'] == 'true'){
		return true;	
	}
	return false;
	
}

function getReportIssueData($actionID, $reportType='historyIssue'){
	
	$reportIssue = array();
	
	//collecting data about the action
	
	$addons = DB::getFields("?:addons", "slug", "1", 'slug');
	
	if(!empty($addons)){
		foreach($addons as $slug => $v){
			$addons[$slug] = getAddonVersion($slug);
		}
	}	
	
	$reportIssue['addonsBought'] = $addons;
	
	if(!empty($actionID) && $reportType='historyIssue'){
	
		$reportIssue['history'] = DB::getArray("?:history H", "*", "H.actionID ='".$actionID."'");		
		$siteIDs = DB::getFields("?:history H", "H.siteID, H.actionID", "H.actionID ='".$actionID."'");					
		if(empty($reportIssue['history'])){
			return false;
		}		
		$reportIssue['historyAdditional'] = DB::getArray("?:history_additional_data HAD, ?:history H", "HAD.*", "H.actionID = '".$actionID."' AND HAD.historyID = H.historyID");
		$reportIssue['historyRaw'] = DB::getArray("?:history_raw_details HRD, ?:history H", "HRD.*", "H.actionID = '".$actionID."' AND HRD.historyID = H.historyID");
		$reportIssue['siteDetails'] = DB::getArray("?:sites", "siteID, URL, WPVersion, pluginVersion, network, parent, IP", "siteID IN (".implode(',',$siteIDs).")", "siteID");
	
	}
	
	$reportIssue['settings'] = DB::getRow("?:settings", "general", "1");
	$reportIssue['settings']['general'] = unserialize($reportIssue['settings']['general']);
	
	$reportIssue['fsockSameURLConnectCheck'] = fsockSameURLConnectCheck(APP_URL.'execute.php');
	//$siteID = DB::getArray("?:");
		
	$reportIssue['server']['PHP_VERSION'] 	= phpversion();
	$reportIssue['server']['PHP_CURL_VERSION']= curl_version();
	$reportIssue['server']['PHP_WITH_OPEN_SSL'] = function_exists('openssl_verify');
	$reportIssue['server']['PHP_MAX_EXECUTION_TIME'] =  ini_get('max_execution_time');
	$reportIssue['server']['MYSQL_VERSION'] 	= DB::getField("select version() as V");
	$reportIssue['server']['OS'] =  php_uname('s');
	$reportIssue['server']['OSVersion'] =  php_uname('v');
	$reportIssue['server']['Machine'] =  php_uname('m');
	
	$reportIssue['server']['PHPDisabledFunctions'] = explode(',', ini_get('disable_functions'));	
	array_walk($reportIssue['server']['PHPDisabledFunctions'], 'trimValue');
	
	$reportIssue['server']['PHPDisabledClasses'] = explode(',', ini_get('disable_classes'));	
	array_walk($reportIssue['server']['PHPDisabledClasses'], 'trimValue');
	
	$reportIssue['browser'] = $_SERVER['HTTP_USER_AGENT'];
	$reportIssue['reportTime'] = time();
	
	//removing unwanted data
	if(!empty($reportIssue['historyRaw']) && is_array($reportIssue['historyRaw'])){
	foreach($reportIssue['historyRaw'] as $key => $value){
		$datas = unserialize(base64_decode($value['request']));
		unset($datas['signature']);
		unset($datas['params']['secure']);
		$reportIssue['historyRaw'][$key]['request'] = base64_encode(serialize($datas));
	}
	}
	
	//to find hostType
	$reportIssue['hostType'] = 'unknown';
	
	if(!empty($_SERVER['SERVER_ADDR'])){
		$SERVER_ADDR = $_SERVER['SERVER_ADDR'];
	}
	else{
		$SERVER_ADDR = gethostbyname($_SERVER['HTTP_HOST']);
	}
	
	$reportIssue['serverIP'] = $SERVER_ADDR;
	
	if(!empty($SERVER_ADDR)){
		if(IPInRange($SERVER_ADDR, '127.0.0.0-127.255.255.255')){
			$reportIssue['hostType'] = 'local';
		}
		elseif(IPInRange($SERVER_ADDR, '10.0.0.0-10.255.255.255') || IPInRange($SERVER_ADDR, '172.16.0.0-172.31.255.255') || IPInRange($SERVER_ADDR, '192.168.0.0-192.168.255.255')){
			$reportIssue['hostType'] = 'private';
		}
		else{
			$reportIssue['hostType'] = 'public';
		}
	}
	//to find hostType - Ends
	
	if(!empty($actionID) && $reportType='historyIssue'){
		$_SESSION['reportIssueTemp'][$actionID] = $reportIssue;
	}	
	
	return array('actionID' =>$actionID, 'report' => $reportIssue);
}

function sendReportIssue($params){
	if(!empty($params)){
		
		
		if($params['type'] == 'historyIssue' && !empty($params['report'])){
			$actionID = $params['actionID'];	
			$params['reportBase64'] = base64_encode(serialize($_SESSION['reportIssueTemp'][$actionID]));
			unset($params['report']);
			unset($_SESSION['reportIssueTemp'][$actionID]);
		}
		elseif($params['type'] == 'userIssue'){//user Issue
			$temp = getReportIssueData('', 'userIssue');
			$params['reportBase64'] = base64_encode(serialize($temp['report']));
			unset($temp);
		}
		
		$data = array('reportData' => $params);
		if(function_exists('gzcompress')){
			$data['reportDataCompressed'] = gzcompress(serialize($data['reportData']));
			unset($data['reportData']);
		}
		
		$temp = doCall(getOption('serviceURL').'report.php', $data);
		list($result) = $temp;
		
		$result = getServiceResponseToArray($result);
		
		if($result['status'] == 'true'){
			return true;
		}
	}
	return false;
}

function addOption($optionName, $optionValue){
	return DB::insert("?:options", array('optionName' => $optionName, 'optionValue' => $optionValue));
}

function updateOption($optionName, $optionValue){
	
	if(isExistOption($optionName)){
		return DB::update("?:options", array('optionName' => $optionName, 'optionValue' => $optionValue), "optionName = '".$optionName."'");
	}
	else{
		return addOption($optionName, $optionValue);
	}	
	
}

function getOption($optionName){
	return DB::getField("?:options", "optionValue", "optionName = '".$optionName."'");
}

function deleteOption($optionName){
	return DB::delete("?:options", "optionName = '".$optionName."'");
}

function isExistOption($optionName){
	return DB::getExists("?:options", "optionID", "optionName = '".$optionName."'");
}

function sendMail($from, $fromName, $to, $toName, $subject, $message, $options=array()){
	
	
	require_once(APP_ROOT.'/lib/phpmailer.php');

	$mail = new PHPMailer(); // defaults to using php "mail()"
	
	$body = $message;
	
	$mail->SetFrom($from, $fromName);
	
	$mail->AddAddress($to, $toName);
	
	$mail->Subject = $subject;
	
	$mail->MsgHTML($body);
		
	if(!$mail->Send()) {
	  addNotification($type='E', $title='Mail Error', $message=$mail->ErrorInfo, $state='U');	  
	  return false;
	} else {
	  //echo "Message sent!";
	  return true;
	}
}

function sendAppMail($params, $contentTPL, $options=array()){
	
	$content = TPL::get($contentTPL, $params);
		
	$content = explode("+-+-+-+-+-+-+-+-+-+-+-+-+-updatesNotificationsMail+-+-+-+-+-+-+-+-+-+-+-", $content);
	
	$subject = $content[0];
	$message = $content[1];
	
	$user = DB::getRow("?:users", "email, name", "1 ORDER BY userID ASC LIMIT 1");
	
	$from = $to = $user['email'];
	$fromName = $toName = $user['name'];
	
	if(defined('APP_MAIL_FROM_EMAIL_NAME') && APP_MAIL_FROM_EMAIL_NAME){
		$emailName = explode('|', APP_MAIL_FROM_EMAIL_NAME, 2);
		$from = $emailName[0];
		$fromName = $emailName[1];
	}
	
	return sendMail($from, $fromName, $to, $toName, $subject, $message, $options);	
	
}


function cronRun(){
	
	ignore_user_abort(true);
	set_time_limit(0);
	
	$userID = DB::getField("?:users", "userID", "1 ORDER BY userID ASC LIMIT 1");
	if(empty($userID)){
		return false;
	}
	$time = time();
	updateOption('cronLastRun', $time);//sometime cron may take long time to complete, so updating first last cron run
	
	$_SESSION['userID'] = $userID;
	$_SESSION['offline'] = true;
	
	
	updatesNotificationMailRunCheck();
	$temp = array();
	setHook('cronRun', $temp);
	
	$settings = Reg::get('settings');
	if($settings['executeUsingBrowser'] == 1){
		do{
			$status = executeJobs();
		}
		while($status['requestInitiated'] > 0 && $status['requestPending'] > 0);
	}	
	$_SESSION = array();
}

function updatesNotificationMailRunCheck(){
	
	$settings = panelRequestManager::getSettings();
	$updatesNotificationMail = $settings['notifications']['updatesNotificationMail'];
	
	//check setting
	if($updatesNotificationMail['frequency'] == 'never' || (empty($updatesNotificationMail['coreUpdates']) && empty($updatesNotificationMail['pluginUpdates']) && empty($updatesNotificationMail['themeUpdates'])) ){
		return false;//	updatesNotificationMail is disabled in the settings
	}
	
	//get updates Notification Mail Last Sent
	$updatesNotificationMailLastSent = getOption('updatesNotificationMailLastSent');
	
	//check last run falls within the frequency
	if($updatesNotificationMailLastSent > 0){
		if($updatesNotificationMail['frequency'] == 'daily'){
			$frequencyStartTime = mktime(0, 0, 0, @date('m'), @date('d'), @date('Y'));
		}
		elseif($updatesNotificationMail['frequency'] == 'weekly'){//mon to sun week
			$day = @date('w', $now);
			$frequencyStartTime = mktime(0, 0, 1, @date('m'), @date('d') - ($day > 0 ? $day: 7) + 1, @date('Y'));
		}
		else{
			return false;	
		}
		if($updatesNotificationMailLastSent > $frequencyStartTime){
			return false;//already sent
		}
	}
	return updatesNotificationMailSend();	
	
}

function updatesNotificationMailSend($force=false){	
	
	$settings = panelRequestManager::getSettings();
	$updatesNotificationMail = $settings['notifications']['updatesNotificationMail'];
	
	//check setting
	if($force == false){
		if($updatesNotificationMail['frequency'] == 'never' || (empty($updatesNotificationMail['coreUpdates']) && empty($updatesNotificationMail['pluginUpdates']) && empty($updatesNotificationMail['themeUpdates'])) ){
			return false;//	updatesNotificationMail is disabled in the settings
		}
		updateOption('updatesNotificationMailLastSent', time());//AUTO MODE($force == false) This is to avoid re-tries for Mail sending which creates lot of getStats
	}
	
	if($force == false){//for test mail(i.e $force == true dont need to get new data
		//reloading stats from all the sites
		$requestData = array();
		$requestData['action'] = 'getStats';
		$requestData['args']['siteIDs'] = '';
		$requestData['args']['params'] = array('forceRefresh' => 1);
		$requestData['args']['extras'] = array('doNotShowUser' => true, 'exitOnComplete' => true, 'sendAfterAllLoad' => false);
		
		if($settings['general']['executeUsingBrowser'] == 1){ //this will fix "Everything up to date" when do not use fsock is used
			$requestData['args']['extras']['directExecute'] = true;
			$requestData['args']['extras']['exitOnComplete'] = false;
		}		
		
		panelRequestManager::handler($requestData);
	}
	
	//getting updateInformation
	$sitesUpdates = panelRequestManager::getSitesUpdates();
	
	$hiddenUpdates = panelRequestManager::getHide();
	
	foreach($hiddenUpdates as $siteID => $value){
		foreach($value as $d){
			unset($sitesUpdates['siteView'][$siteID][$d['type']][$d['URL']]);
			
			//this will fix the site name shows even all update items are hidden
			if(empty($sitesUpdates['siteView'][$siteID][$d['type']])){
				unset($sitesUpdates['siteView'][$siteID][$d['type']]);
			}
			if(empty($sitesUpdates['siteView'][$siteID])){
				unset($sitesUpdates['siteView'][$siteID]);
			}
		}
	}
	
	
	if(!empty($sitesUpdates['siteView']) && ( empty($updatesNotificationMail['coreUpdates']) ||  empty($updatesNotificationMail['pluginUpdates']) || empty($updatesNotificationMail['themeUpdates']) )){//this will fix  when the "plugin" not selected in settings. Site name shows with empty list when plugin update is available
		foreach($sitesUpdates['siteView'] as $siteID => $value){
			if(empty($updatesNotificationMail['coreUpdates'])){
				unset($sitesUpdates['siteView'][$siteID]['core']);
			}
			if(empty($updatesNotificationMail['pluginUpdates'])){
				unset($sitesUpdates['siteView'][$siteID]['plugins']);				
			}
			if(empty($updatesNotificationMail['themeUpdates'])){
				unset($sitesUpdates['siteView'][$siteID]['themes']);
			}
			if(empty($sitesUpdates['siteView'][$siteID])){
				unset($sitesUpdates['siteView'][$siteID]);
			}
		}
	}
	
	$params = array('sitesUpdates' => $sitesUpdates,  'updatesNotificationMail' => $updatesNotificationMail, 'updateNotificationDynamicContent' => getOption('updateNotificationDynamicContent'));
	$isSent = sendAppMail($params, '/templates/email/updatesNotification.tpl.php');
	
	if($isSent){
		if(!$force){
			updateOption('updatesNotificationMailLastSent', time());
		}
		return true;
	}
	else{
		if(!$force){
			updateOption('updatesNotificationMailLastSent', time());//even mail sending failed mark as sent this will avoid re-trying, customer will be notified if mail not sent using offline notification
		}
	}
	return false;
	
}

function checkUpdate($force=false, $checkForUpdate=true){
	
	$currentTime = time();
	$updateLastChecked = getOption('updateLastCheck');
	if(!$force){
		if( ($currentTime - $updateLastChecked) < 86400 ){ //86400 => 1 day in seconds
				$updateAvailable = getOption('updateAvailable');
				if(!empty($updateAvailable)){
					$updateAvailable = @unserialize($updateAvailable);
					if($updateAvailable == 'noUpdate'){
						return false;
					}
					return $updateAvailable;
				}
			return false;
		}
	}
	
	if(!$checkForUpdate){
		return false;
	}
	
	$updateLastTried = getOption('updateLastTry');
	if(!$force && $updateLastTried > 0 && $updateLastChecked > 0 && ($currentTime - $updateLastTried) < 600){//600 => 10 mins
		return false;//auto checkUpdate after 600 secs
	}

	$URL = getOption('serviceURL').'checkUpdate.php';	
	$URL .= '?appVersion='.APP_VERSION.'&appInstallHash='.APP_INSTALL_HASH.'&installedHash='.getInstalledHash();
	
	$installedAddons = getInstalledAddons(false);
	if(!empty($installedAddons)){
		foreach($installedAddons as $addonSlug => $addon){
			$URL .=  '&checkAddonsUpdate['.$addonSlug.']='.$addon['version'];		
		}
	}
	if($force){
		$URL .= '&force=1';
	}
	
	updateOption('updateLastTry', $currentTime);

	$temp = doCall($URL, '');
	list($result, , , $curlInfo) = $temp;

	$result = getServiceResponseToArray($result);//from 2.0.8 follows <IWPHEADER><IWPHEADEREND> wrap in return value	
		
	if($curlInfo['info']['http_code'] != 200 || empty($result)){
		//response error
		return false;	
	}	 
	
	setHook('updateCheck', $result);
	
	if(!empty($result['updateNotificationDynamicContent'])){
		updateOption('updateNotificationDynamicContent', $result['updateNotificationDynamicContent']);
	}
	
	updateOption('updateLastCheck', $currentTime);
	
	if(isset($result['registerd'])){
		updateAppRegistered($result['registerd']);
	}
	
	if(isset($result['addons'])){
		processCheckAddonsUpdate($result['addons']);
	}
	
	if(isset($result['promos'])){
		updateOption('promos', serialize($result['promos']));
	}
	
	if($result['checkUpdate'] == 'noUpdate'){
		updateOption('updateAvailable', '');
		return false;
	}
	else{
		updateOption('updateAvailable', serialize($result['checkUpdate']));
		return $result['checkUpdate'];
	}
}

function processAppUpdate(){//download and install update
	$updateAvailable = checkUpdate(false, false);
	if(empty($updateAvailable)){
		return false;	
	}
	
	$newVersion = $updateAvailable['newVersion'];
	if(version_compare(APP_VERSION, $newVersion) !== -1){
		return false;
	}
	
	$updateDetails = $updateAvailable['updateDetails'][$newVersion];
	
	if(!empty($updateDetails['downloadLink']) && !empty($updateDetails['fileMD5'])){
		
		$updateZip = getTempName('appUpdate.zip');
	
		appUpdateMsg('Downloading package..');
		
		$isDone = downloadURL($updateDetails['downloadLink'], $updateZip);		
		
		if(!$isDone){ //download failed
			appUpdateMsg('Download Failed.', true);
			return false;
		}
				
		if(md5_file($updateZip) != $updateDetails['fileMD5']){
			appUpdateMsg('File MD5 mismatch(damaged package).', true);
			return false;	
		}
		
		if(!initFileSystem()){
			appUpdateMsg('Unable to initiate file system.', true);
			return false;
		}
		
		$unPackResult = unPackToWorkingDir($updateZip);
		if(!empty($unPackResult) && is_array($unPackResult)){
			$source = $unPackResult['source'];
			$remoteSource = $unPackResult['remoteSource'];
		}
		else{
			return false;	
		}
		
		$destination = APP_ROOT;
		if(!copyToRequiredDir($source, $remoteSource, $destination)){
			return false;	
		}
		
		appUpdateMsg('Finalizing update..');	
		if(file_exists(APP_ROOT.'/updateFinalize_v'.$newVersion.'.php')){
			//$updateAvailable variable should be live inside the following file
			include(APP_ROOT.'/updateFinalize_v'.$newVersion.'.php');//run the update file
			
			if($updateFinalizeStatus == true){
				
				@unlink($updateZip);
				updateOption('updateAvailable', '');
				
				appUpdateMsg('Updated successfully.', false);	
				return true;
			}
			else{
				appUpdateMsg('Update failed.', true);
			}
		}
		else{
			//updateFinalize file not found	
			appUpdateMsg('Update finalizing file missing.', true);
		}
		@unlink($updateZip);
		return false;	
	}	
}

function unPackToWorkingDir($updateZip){
	
	if(empty($updateZip)) return false;
	
	$workingDir = APP_ROOT.'/updates';
	
	$updatesFolder = $GLOBALS['FileSystemObj']->findFolder($workingDir);
		
	//Clean up contents of upgrade directory beforehand.
	$updatesFolderFiles = $GLOBALS['FileSystemObj']->dirList($updatesFolder);
	if ( !empty($updatesFolderFiles) ) {
		$temp = basename($updateZip);
		foreach ( $updatesFolderFiles as $file ){
			if($temp != $file['name'])
			$GLOBALS['FileSystemObj']->delete($updatesFolder . $file['name'], true);
		}
	}

	//We need a working directory
	//removing the extention
	$updateZipParts = explode('.', basename($updateZip));
	if(count($updateZipParts) > 1) array_pop($updateZipParts);
	$tempFolderName = implode('.', $updateZipParts);
	if(empty($tempFolderName)) return false;
	
	$remoteWorkingDir = $updatesFolder . $tempFolderName;
	$workingDir = addTrailingSlash($workingDir). $tempFolderName;

	// Clean up working directory
	if ( $GLOBALS['FileSystemObj']->isDir($remoteWorkingDir) )
		$GLOBALS['FileSystemObj']->delete($remoteWorkingDir, true);

	// Unzip package to working directory
	$result = $GLOBALS['FileSystemObj']->unZipFile($updateZip, $remoteWorkingDir); //TODO optimizations, Copy when Move/Rename would suffice?

	// Once extracted, delete the package.
	@unlink($updateZip);
	
	if ( $result == false ) {
		$GLOBALS['FileSystemObj']->delete($remoteWorkingDir, true);
		return false;
	}
	
	return array('source' => $workingDir, 'remoteSource' => $remoteWorkingDir);
}

function copyToRequiredDir($source, $remoteSource, $destination){
	
	$remoteSourceFiles = array_keys( $GLOBALS['FileSystemObj']->dirList($remoteSource) );
	if(empty($remoteSourceFiles)){
		appUpdateMsg('Invalid zip file.', true);
		return false;
	}
	
	
	@set_time_limit( 300 );
	//$destination = APP_ROOT;
	$remoteDestination = $GLOBALS['FileSystemObj']->findFolder($destination);

	//Create destination if needed
	if ( !$GLOBALS['FileSystemObj']->exists($remoteDestination) )
		if ( !$GLOBALS['FileSystemObj']->mkDir($remoteDestination, FS_CHMOD_DIR) ){
			//return new WP_Error('mkdir_failed', $this->strings['mkdir_failed'], $remoteDestination);
			appUpdateMsg('Unable to create directory '.$remoteDestination, true);
			return false;
		}

	// Copy new version of item into place.
	$result = $GLOBALS['FileSystemObj']->copyDir($remoteSource, $remoteDestination);
	if ( !$result ) {
		$GLOBALS['FileSystemObj']->delete($remoteSource, true);
		return $result;
	}

	//Clear the Working folder?
	$GLOBALS['FileSystemObj']->delete($remoteSource, true);	
	
	return true;
}

function appUpdateMsg($msg, $isError=0){
	echo '<br>'.$msg;
	if($isError === true){
		?>
        <br />Try again by refreshing the panel or contact <a href="mailto:help@infinitewp.com">help@infinitewp.com</a>
         <script>
		window.parent.$("#updates_centre_cont .btn_loadingDiv").remove();
		</script>
		<?php
	}
	elseif($isError === false){
		?>
        <script>
		window.parent.$("#updates_centre_cont .btn_loadingDiv").remove();
		window.parent.$(".updateActionBtn").attr({'btnaction':'reload','target':'_parent', 'href':'<?php echo APP_URL; ?>'}).text('Reload App').removeClass('disabled');
		</script>
		<?php
	}
	?>
	<script>
	window.scrollTo(0, document.body.scrollHeight);
	</script>
    <?php
	ob_flush();
	flush();
}

function runOffBrowserLoad(){
	checkUpdate();
	anonymousDataRunCheck();
	autoSelectConnectionMethod();
	$temp = array();
	setHook('runOffBrowserLoad', $temp);
}

function getResponseMoreInfo($historyID){
	
	$return = DB::getField("?:history_raw_details", "response", "historyID = '".$historyID."'");
	
	$startStr = '<IWPHEADER>';
    $endStr = '<ENDIWPHEADER';

	$response_start = stripos($return, $startStr);	
	if($response_start === false){
		return $return;
	}
	$str = substr($return, 0, $response_start);
	
	$response_End = stripos($return, $endStr);
	$Estr = substr($return, $response_End + strlen($endStr));
	$Estr = (substr($Estr, 0, 1) == '>') ? substr($Estr, 1) : $Estr;
	return $str.$Estr;
}

/*
$type = 'N' -> notice, 'W' -> Warning, 'E' -> Error
$state = 'T' -> Timer, 'U' -> user should close it
*/
function addNotification($type, $title, $message, $state='T', $callbackOnClose='', $callbackReference=''){	

	if(!empty($_SESSION['userID']) && empty($_SESSION['offline'])){//if user logged session
		if(!isset($_SESSION['notifications'])){ $_SESSION['notifications'] = array(); }
		$notifications = &$_SESSION['notifications'];
	}
	else{//offline may be run by a cronjob
		$offlineNotifications = getOption('offlineNotifications');
		$offlineNotifications = (!empty($offlineNotifications)) ? @unserialize($offlineNotifications) : array();
		$notifications = &$offlineNotifications;
	}
	
	$key =  md5($type.$title.$message);
	if(empty($notifications[$key])){
		$notifications[$key] = array('key' => $key,
									 'type' => $type,
									 'title' => $title, 
									 'message' => $message, 
									 'state' => empty($callbackOnClose) ? $state : 'U', 
									 'callbackOnClose' => $callbackOnClose, 
									 'callbackReference' => $callbackReference, 
									 'counter' => 1,
									 'time' => time(),
									 'notified' => false);
	}
	else{
		$notifications[$key]['counter']++;
	}
	
	if(!empty($offlineNotifications)){
		//save in db
		updateOption('offlineNotifications', serialize($offlineNotifications));
	}
}

function getNotifications($reloaded=false){
	
	if(empty($_SESSION['userID'])){ return false; }//No session, dont send any notifications
	
	$notifications = array();
	
	if(!isset($_SESSION['notifications'])){ $_SESSION['notifications'] = array(); }
	
	$offlineNotifications = getOption('offlineNotifications');
	
	if(!empty($offlineNotifications)){
		$offlineNotifications = @unserialize($offlineNotifications);	
		$_SESSION['notifications'] = @array_merge($_SESSION['notifications'], (array)$offlineNotifications);
	}	
	
	foreach($_SESSION['notifications'] as $key => $_notification){
		if(!empty($_notification['callbackOnClose'])){
			if($reloaded || !$_notification['notified']){
				$_SESSION['notifications'][$key]['notified'] = true;
				$notifications[] = $_notification;
			}
		}
		else{
			unset($_SESSION['notifications'][$key]);
			$notifications[] = $_notification;
		}		
	}
	
	//Null the offlineNotifications option
	updateOption('offlineNotifications', NULL);
	
	return $notifications;	
}

function closeNotification($key){
	//only happens when user logged in
	if(!empty($_SESSION['notifications'][$key])){
		if(function_exists($_SESSION['notifications'][$key]['callbackOnClose'])){
			@call_user_func($_SESSION['notifications'][$key]['callbackOnClose'], $_SESSION['notifications'][$key]['callbackReference']);
		}
		unset($_SESSION['notifications'][$key]);
	}
}

function installFolderAlert(){
	if(is_dir(APP_ROOT.'/install')){
		addNotification($type='E', $title='Security Warning!', $message='Please remove or rename the install folder.', $state='U', $callbackOnClose='', $callbackReference='');
	}
}

function setHook($hook, &$arg1 = NULL, &$arg2 = NULL, &$arg3 = NULL, &$arg4 = NULL, &$arg5 = NULL, &$arg6 = NULL, &$arg7 = NULL, &$arg8 = NULL, &$arg9 = NULL){
	
	$num = func_num_args();
	$args = array();
	for ($i = 1; $i < 10; $i++) {//$i = 1, skiping first arg which is always a hook name
		$argName = 'arg' . $i;
		if ($i < $num) {
			$args[$i] = &$$argName;
		}
		unset($$argName, $argName);
	}
	
	$hooks = Reg::get('hooks');
	if(empty($hooks[$hook])){
		return false;
	}
	foreach($hooks[$hook] as $func){
		if(function_exists($func)){
			call_user_func_array($func, $args);
		}
	}
}


function regHooks(){
	
	$args = func_get_args();
	
	$hooks = Reg::get('hooks');
	
	$backtrace = debug_backtrace();
	
	$addonSlug = basename(dirname($backtrace[0]['file']));
	
	foreach($args as $arg){
		$arg = trim($arg);
		if(empty($arg) || !is_string($arg)){
			continue;
		}
		if(!isset($hooks[$arg])){
			$hooks[$arg] = array();
		}
		
		$hooks[$arg][] = $addonSlug.ucfirst($arg);
		
		$hooks[$arg] = array_unique($hooks[$arg]);		
	}	
	Reg::set('hooks', $hooks);
}

function getInstalledAddons($withUpdates=true){
	$addons = DB::getArray("?:addons", "*", "1", 'slug');
	foreach($addons as $slug => $addon){
		$addons[$slug]['version'] = getAddonVersion($slug);
	}
	
	$updateAddonsAvailable = @unserialize(getOption('updateAddonsAvailable'));
		
	if(!empty($updateAddonsAvailable)){
		foreach($updateAddonsAvailable as $slug => $updateAddon){
			if(!empty($addons[$slug])){
				if(!empty($updateAddon['updateAvailable'])){				
					$addons[$slug]['updateAvailable'] = $updateAddon['updateAvailable'];				
				}
				$addons[$slug]['isValidityExpired'] = $updateAddon['isValidityExpired'];
			}
		}
	}
	
	return $addons;
}

function getAddonUpdateCount(){
	$i = 0;
	$addons = getInstalledAddons();	
	if(!empty($addons)){
		foreach($addons as $slug => $addon){
			if(!empty($addon['updateAvailable'])) $i++;
		}
	}
	return $i;
}
function getAddonVersion($slug){
	$matches = array();
	if(file_exists(APP_ROOT.'/addons/'.$slug.'/addon.'.$slug.'.php')){
				
		$fp = fopen(APP_ROOT.'/addons/'.$slug.'/addon.'.$slug.'.php', 'r');
		$fileData = fread($fp, 8192);
		fclose($fp);
		preg_match( '/^[ \t\/*#@]*' . preg_quote( 'version', '/' ) . ':(.*)$/mi', $fileData, $matches);
		return trim($matches[1]);
	}
	return false;
}


function loadActiveAddons(){
	$activeAddons = DB::getArray("?:addons", "slug, status, addon", "1");
	
	$installedAddons = @unserialize(getOption('updateAddonsAvailable'));
	$newAddons = getNewAddonsAvailable();
	
	$purchasedAddons = array();
	if(!empty($installedAddons)){ $purchasedAddons = array_merge($purchasedAddons, array_keys($installedAddons));  }
	if(!empty($newAddons)){ $purchasedAddons = array_merge($purchasedAddons, array_keys($newAddons));  }
	
	$uninstallAddons = $uninstall = $activeAddonsSlugs = array();
	foreach($activeAddons as $key => $addon){
		if(!in_array($addon['slug'], $purchasedAddons)){
			$uninstall[] = $addon['slug'];
			$uninstallAddons[]['slug'] = $addon['slug'];
		}
		if($addon['status'] == 'active'){
			$activeAddonsSlugs[] = $addon['slug'];
			if(file_exists(APP_ROOT.'/addons/'.$addon['slug'].'/addon.'.$addon['slug'].'.php')){
				include_once(APP_ROOT.'/addons/'.$addon['slug'].'/addon.'.$addon['slug'].'.php');
				
				if(method_exists('addon'.ucfirst($addon['slug']),'init')){
					call_user_func(array('addon'.ucfirst($addon['slug']),'init'));
				}
			}
			else{//file not found deactivate the addon
				unset($activeAddons[$key]);
				DB::delete("?:addons", "slug='".$addon['slug']."'");
				addNotification($type='E', $title='Addon file missing', $message='The "'.$addon['addon'].'" addon has been removed, since a file is missing.', $state='U', $callbackOnClose='', $callbackReference='');
			}
		}
	}
	if(!empty($uninstallAddons)){
		addNotification($type='E', $title='Addon error', $message='Addon(s) are not legitimate.', $state='U', $callbackOnClose='', $callbackReference='');
		uninstallAddons($uninstallAddons);		
	}
	Reg::set('activeAddons', $activeAddonsSlugs);
}


function downloadAndInstallAddons($addons){
	$installedHash = getInstalledHash();
	foreach($addons as $key => $addon){
		appUpdateMsg('Checking download for '.$addon['slug'].'...');
		$downloadCheckLink = getOption('serviceURL').'download.php?appInstallHash='.APP_INSTALL_HASH.'&installedHash='.$installedHash.'&appVersion='.APP_VERSION.'&type=addon&download='.$addon['slug'].'&downloadToken='.$_SESSION['downloadToken'].'&downloadType=install';
				
		$temp = doCall($downloadCheckLink, '');
		list($result, , , $curlInfo) = $temp;
		
		//$result = base64_decode($result);
		//$result = @unserialize($result);
		$result = getServiceResponseToArray($result);
		
		if($curlInfo['info']['http_code'] == "200"){
			if($result['status'] == 'success'){
				$addons[$key]['downloadLink'] = $result['downloadLink'];//$downloadCheckLink.'&checked=true';
			}
			elseif($result['status'] == 'error'){
				unset($addons[$key]);
				appUpdateMsg('Error while downloading addon '.$addon['slug'].': '.$result['errorMsg']);
			}
		}
		else{
			unset($addons[$key]);
			appUpdateMsg('Error while downloading addon '.$addon['slug'].': Unable to communicate with server.');
		}	
		
	}
	downloadAndUnzipAddons($addons);
	installAddons($addons);
	appUpdateMsg('<br>Please <a href="'.APP_URL.'" target="_top">click here</a> to reload the app.'); 
	updateOption('updateLastCheck', 0);//to trigger checkUpdate in next page load
}


function updateAddons($addons){
	$installedHash = getInstalledHash();
	foreach($addons as $key => $addon){
		appUpdateMsg('Checking download for '.$addon['slug'].'...');
		$downloadCheckLink = getOption('serviceURL').'download.php?appInstallHash='.APP_INSTALL_HASH.'&installedHash='.$installedHash.'&appVersion='.APP_VERSION.'&type=addon&downloadType=update&download='.$addon['slug'];
		
		$temp = doCall($downloadCheckLink, '');
		list($result, , , $curlInfo) = $temp;
		//$result = base64_decode($result);
		//$result = @unserialize($result);
		$result = getServiceResponseToArray($result);
		
		if($curlInfo['info']['http_code'] == "200"){
			if($result['status'] == 'success'){
				$addons[$key]['downloadLink'] = $result['downloadLink'];//$downloadCheckLink.'&checked=true';
			}
			elseif($result['status'] == 'error'){
				unset($addons[$addon['slug']]);
				appUpdateMsg('Error while downloading addon '.$addon['slug'].': '.$result['errorMsg']);
			}
		}
		else{
			unset($addons[$addon['slug']]);
			appUpdateMsg('Error while downloading addon '.$addon['slug'].': Unable to communicate with server.');
		}		
	}
	downloadAndUnzipAddons($addons);
	
	foreach($addons as $addon){
		if($addon['process']['unzipDone']){			
			//$addon['process']['updated'] = true;
			include(APP_ROOT.'/addons/'.$addon['slug'].'/addon.'.$addon['slug'].'.php');
			
			if(method_exists('addon'.ucfirst($addon['slug']),'update')){
				call_user_func(array('addon'.ucfirst($addon['slug']),'update'));
			}
			appUpdateMsg('Addon '.$addon['slug'].' successfully updated.');		
		}
	}
	appUpdateMsg('<br>Please <a href="'.APP_URL.'" target="_top">click here</a> to reload the app.');
	updateOption('updateLastCheck', 0);//to trigger checkUpdate in next page load 	
}

function downloadAndUnzipAddons(&$addons){
	foreach($addons as $key => $addon){
		if(!empty($addon['downloadLink'])/* && !empty($updateDetails['fileMD5'])*/){
		
			$downloadLink = $addon['downloadLink'];
			//$fileMD5 = $updateDetails['fileMD5'];
		
			$zip = getTempName('addon_'.$addon['slug'].'.zip');
			
			
			appUpdateMsg('Downloading '.$addon['slug'].' package...');
			$isDone = downloadURL($downloadLink, $zip);			
			
			if(!$isDone){ //download failed
				appUpdateMsg($addon['slug'].' package download failed.', true);
				continue;
			}
			
			if(!initFileSystem()){
				appUpdateMsg('Unable to initiate file system.', true);
				return false;
			}
			
			$unPackResult = unPackToWorkingDir($zip);
			if(!empty($unPackResult) && is_array($unPackResult)){
				$source = $unPackResult['source'];
				$remoteSource = $unPackResult['remoteSource'];
			}
			else{
				return false;	
			}
			
			$destination = APP_ROOT;
			if(!copyToRequiredDir($source, $remoteSource, $destination)){
				return false;	
			}
			

			appUpdateMsg('Unziped '.$addon['slug'].' package.');
			$addons[$key]['process']['unzipDone'] = true;

			@unlink($zip);
					
				
		}
	}
	//return $addons;
}

function installAddons($addons){//install and activate the addon

	foreach($addons as $addon){
		if(empty($addon['process']['unzipDone'])){
			continue;	
		}
		$ok=false;
		appUpdateMsg('Installing '.$addon['slug'].' addon...');
		$isExist = DB::getField("?:addons", "slug", "slug='". $addon['slug'] ."'");
		if($isExist){
			appUpdateMsg('The '.$addon['slug'].' addon is already installed.');
			continue;
		}		
		
		if(file_exists(APP_ROOT.'/addons/'.$addon['slug'].'/addon.'.$addon['slug'].'.php')){
			//activating the addon
			$isDone = DB::insert("?:addons", array('slug' => $addon['slug'], 'addon' => $addon['addon'], 'status' => 'active', 'validityExpires' => $addon['validityExpires'], 'initialVersion' => $addon['version']));
			if($isDone){
				$ok=true;
				include_once(APP_ROOT.'/addons/'.$addon['slug'].'/addon.'.$addon['slug'].'.php');
				if(method_exists('addon'.ucfirst($addon['slug']), 'install')){

					$isSuccess = call_user_func(array('addon'.ucfirst($addon['slug']),'install'));
					if(!$isSuccess){
						$ok=false;
						DB::delete("?:addons", "slug = '".$addon['slug']."'");
						appUpdateMsg('An error occured while installing the '.$addon['slug'].' addon.');
					}
				}
				if($ok){ appUpdateMsg($addon['slug'].' addon successfully installed.'); }
			}
		}
		else{
			appUpdateMsg('A file was found missing while installing the '.$addon['slug'].' addon.');
		}
	}
	//force update to make new addons to show as installed
	checkUpdate(true, true);
}

function activateAddons($addons){
	$success = array();
	foreach($addons as $key => &$addon){
		$isExist = DB::getField("?:addons", "slug", "slug='". $addon['slug'] ."'");
		if(!$isExist){
			addNotification($type='E', $title='Addon activation failed', $message='The '.$addon['slug'].' addon does not exist in the database.', $state='U', $callbackOnClose='', $callbackReference='');
			$addons[$key]['activate'] = false;
			continue;
		}		
		
		if(file_exists(APP_ROOT.'/addons/'.$addon['slug'].'/addon.'.$addon['slug'].'.php')){
			//activating the addon
			$isDone = DB::update("?:addons", array( 'status' => 'active'), "slug = '".$addon['slug']."'");
			if($isDone){
				$addons[$key]['activate'] = true;
				$success[$addon['slug']] = DB::getField("?:addons", "addon", "slug = '".$addon['slug']."'");
				include_once(APP_ROOT.'/addons/'.$addon['slug'].'/addon.'.$addon['slug'].'.php');
				if(method_exists('addon'.ucfirst($addon['slug']),'activate')){
					call_user_func(array('addon'.ucfirst($addon['slug']),'activate'));
				}
			}
		}
		else{
			$addons[$key]['activate'] = false;
			addNotification($type='E', $title='Addon activation failed', $message='A file was found missing while activating the '.$addon['slug'].' addon.', $state='U', $callbackOnClose='', $callbackReference='');
		}
	}
	if(!empty($success)){
		addNotification($type='N', $title='Addon has been activated', $message=implode('<br>', $success), $state='U', $callbackOnClose='', $callbackReference='');
	}
	return $addons;
}

function deactivateAddons($addons){
	foreach($addons as $key => $addon){
		$isExist = DB::getField("?:addons", "slug", "slug='". $addon['slug'] ."'");
		if(!$isExist){
			$addons[$key]['deactivate'] = false;
			addNotification($type='E', $title='Addon deactivation failed', $message='The '.$addon['slug'].' addon does not exist in the database.', $state='U', $callbackOnClose='', $callbackReference='');
			continue;
		}		
		
		if(file_exists(APP_ROOT.'/addons/'.$addon['slug'].'/addon.'.$addon['slug'].'.php')){
			//activating the addon
			$isDone = DB::update("?:addons", array( 'status' => 'inactive'), "slug = '".$addon['slug']."'");
			if($isDone){
				$addons[$key]['deactivate'] = true;
				$success[$addon['slug']] = DB::getField("?:addons", "addon", "slug = '".$addon['slug']."'");
				include_once(APP_ROOT.'/addons/'.$addon['slug'].'/addon.'.$addon['slug'].'.php');
				if(method_exists('addon'.ucfirst($addon['slug']),'deactivate')){
					call_user_func(array('addon'.ucfirst($addon['slug']),'deactivate'));
				}
			}
		}
		else{
			$addons[$key]['deactivate'] = false;
			addNotification($type='E', $title='Addon deactivation failed', $message='A file was found missing while deactivating the '.$addon['slug'].' addon.', $state='U', $callbackOnClose='', $callbackReference='');
		}
	}
	if(!empty($success)){
		addNotification($type='N', $title='Addon has been deactivated', $message=implode('<br>', $success), $state='U', $callbackOnClose='', $callbackReference='');
	}
	return $addons;
}

function uninstallAddons($addons){
	foreach($addons as $addon){
	
		if(file_exists(APP_ROOT.'/addons/'.$addon['slug'].'/addon.'.$addon['slug'].'.php')){
			
			if($isDone){
				include_once(APP_ROOT.'/addons/'.$addon['slug'].'/addon.'.$addon['slug'].'.php');
				if(method_exists('addon'.ucfirst($addon['slug']),'uninstall')){
					call_user_func(array('addon'.ucfirst($addon['slug']),'uninstall'));
				}
				
			}
		}
		
		if(is_object($GLOBALS['FileSystemObj']) || initFileSystem()){
			$GLOBALS['FileSystemObj']->delete(APP_ROOT.'/addons/'.$addon['slug'], true);
		}		
		$isDone = DB::delete("?:addons", "slug = '".$addon['slug']."'");
		addNotification($type='N', $title='Addon uninstalled', $message='The '.$addon['slug'].' addon has been uninstalled.', $state='U', $callbackOnClose='', $callbackReference='');	
	}
}

function getAddonsHTMLHead(){
	$head = '';
	$activeAddons = Reg::get('activeAddons');//loadActiveAddons() should be called before it
	$installedAddons = getInstalledAddons(false);
	foreach($activeAddons as $key => $addon){
		$addon_version = '';
		if(file_exists(APP_ROOT.'/addons/'.$addon.'/initHTMLHead.php')){
			$addon_version = $installedAddons[$addon]['version'];
			ob_start();
			echo "\n";
			include(APP_ROOT.'/addons/'.$addon.'/initHTMLHead.php');
			$head .= ob_get_clean();
		}
	}
	return $head;
}

function getInstalledHash(){
	return sha1(APP_DOMAIN_PATH.'|--|'.APP_ROOT);
}


function updateAppRegistered($user){//$user => username or email registered with IWP Site
	updateOption('appRegisteredUser', $user);
}

function isAppRegistered(){
	$appRegisteredUser = getOption('appRegisteredUser');
	if(!empty($appRegisteredUser)){
		return true;
	}
	return false;
}

function processCheckAddonsUpdate($addonsUpdate){
	//get updates
	updateOption('updateAddonsAvailable', serialize($addonsUpdate['updateAddons']));
	updateOption('newAddonsAvailable', serialize($addonsUpdate['newAddons']));
	updateOption('promoAddons', serialize($addonsUpdate['promoAddons']));
}

function getNewAddonsAvailable(){
	return @unserialize(getOption('newAddonsAvailable'));
}

function getPromoAddons(){
	return @unserialize(getOption('promoAddons'));
}


function initMenus(){
	
	$menus['manage'] 	= array();
	$menus['protect'] 	= array();
	$menus['monitor'] 	= array();
	$menus['maintain'] 	= array();
	$menus['tools'] 	= array();
	
	$menus['manage']['displayName'] 	= 'Manage';
	$menus['manage']['subMenus'][] 		= array('page' => 'updates', 'displayName' => '<span class="float-left">Updates</span><span class="update_count float-left droid700" id="totalUpdateCount">0</span><div class="clear-both"></div>');
	$menus['manage']['subMenus'][] 		= array('page' => 'items', 'displayName' => 'Plugins &amp; Themes');
	
	$menus['protect']['displayName'] 	= 'Protect';
	$menus['protect']['subMenus'][] 	= array('page' => 'backups', 'displayName' => 'Backups');
	
	$menus['monitor']['displayName'] 	= 'Monitor';	
	$menus['maintain']['displayName'] 	= 'Maintain';	
	$menus['tools']['displayName'] 		= 'Tools';
	
	setHook('addonMenus', $menus);
	
	$hooks = Reg::get('hooks');
	
	Reg::set('menus', $menus);
}

function printMenus(){
	$menus = Reg::get('menus');
	$tempAddons = array();
	$tempAddons['displayName']	= 'Addons';
	$tempAddons['subMenus']		= array();
	
	foreach($menus as $key => $group){	
		if(isset($menus[$key]) && !empty($menus[$key]['subMenus']) && is_array($menus[$key]['subMenus'])){	
			$groupName = $menus[$key]['displayName'];
			$singleGroupMenu = $menus[$key]['subMenus'];
			$HTML = TPL::get('/templates/menu/menu.tpl.php', array('groupName' => $groupName, 'singleGroupMenu' => $singleGroupMenu));
			echo $HTML;
		}
		/*elseif(isset($group['displayName'])){
			
		}*/
		elseif(is_string($group)){
			$tempAddons['subMenus'][] = $group;
		}
	}
	
	if(!empty($tempAddons['subMenus'])){
		
		$groupName = $tempAddons['displayName'];
		$singleGroupMenu = $tempAddons['subMenus'];
		$HTML = TPL::get('/templates/menu/menu.tpl.php', array('groupName' => $groupName, 'singleGroupMenu' => $singleGroupMenu));
		echo $HTML;
		
	}
}

function getAddonMenus(){
	$menus = array();
	
	
	
		
	return implode("\n", $menus);
}


function repositoryTestConnection($account_info){
	if(isset($account_info['iwp_ftp']) && !empty($account_info)) {
	  $return = repositoryFTP($account_info['iwp_ftp']);
	}
	  
	if(isset($account_info['iwp_amazon_s3']) && !empty($account_info['iwp_amazon_s3'])) {
	  $return = repositoryAmazons3($account_info['iwp_amazon_s3']);
	}
	  
	if (isset($account_info['iwp_dropbox']) && !empty($account_info['iwp_dropbox'])) {
	  $return = repositoryDropbox($account_info['iwp_dropbox']);
	}	
	  
	return $return;	
}
	
function repositoryFTP($args){
	extract($args);	
	$ftp_hostname = $ftp_hostname ? $ftp_hostname : $hostName;
	$ftp_username = $ftp_username ? $ftp_username : $hostUserName;
	$ftp_password = $ftp_password ? $ftp_password : $hostPassword;
	
	if(empty($ftp_hostname)){
		return array('status' => 'error',
			'errorMsg' => 'Inavlid FTP host',
			);	
	}
	
	
	$port = $ftp_port ? $ftp_port : 21; //default port is 21
	if ($ftp_ssl) {
		if (function_exists('ftp_ssl_connect')) {
			$conn_id = ftp_ssl_connect($ftp_hostname, $port);
			if ($conn_id === false) {
				return array('status' => 'error',
						'errorMsg' => 'Failed to connect to ' . $ftp_hostname,
				);
			}
		}
		else {
			return array('status' => 'error',
			'errorMsg' => 'Your server doesn\'t support FTP SSL',
			);
		}
	} else {
		if (function_exists('ftp_connect')) {
			$conn_id = ftp_connect($ftp_hostname, $port);
			if ($conn_id === false) {
				return array('status' => 'error',
				'errorMsg' => 'Failed to connect to ' . $ftp_hostname,
				);
			}
		}
		else {
			return array('status' => 'error',
			'errorMsg' => 'Your server doesn\'t support FTP',
			);
		}
	}
	$login = @ftp_login($conn_id, $ftp_username, $ftp_password);
	if ($login === false) {
		return array('status' => 'error', 
		'errorMsg' => 'FTP login failed for ' . $ftp_username . ', ' . $ftp_password,
		);
	}
	else{
		return array('status' => 'success');
	}
}

function repositoryAmazons3($args){
	require_once(APP_ROOT."/lib/s3.php");
	extract($args);
	
	$endpoint = isset($as3_bucket_region) ? $as3_bucket_region : 's3.amazonaws.com';
    $s3 = new S3(trim($as3_access_key), trim(str_replace(' ', '+', $as3_secure_key)), false, $endpoint);
	
	try{
		$s3->getBucket($as3_bucket, S3::ACL_PUBLIC_READ);
		return array('status' => 'success');
	}
	catch (Exception $e){
         return array('status' => 'error', 'errorMsg' => $e->getMessage());
		 //$e->getMessage();
	}
}

function repositoryDropbox($args){
	extract($args);

	if(isset($consumer_secret) && !empty($consumer_secret)){
		require_once(APP_ROOT.'/lib/dropbox.oauth.php');
		$dropbox = new Dropbox($consumer_key, $consumer_secret);				
		$dropbox->setOAuthToken($oauth_token);
		$dropbox->setOAuthTokenSecret($oauth_token_secret);
		try{
			$dropbox->accountinfo();
			return array('status' => 'success');			
		}
		catch(Exception $e){
			return array('status' => 'error', 'errorMsg' => $e->getMessage());
		}
	}
	return array('status' => 'error', 'errorMsg' => 'Consumer Secret not available');
}

function getAddonHeadJS(){
	$headJS = array();
	setHook('addonHeadJS', $headJS);	
	return implode("\n", $headJS);
}

function cronNotRunAlert(){
	//cron job should run every 20 mins
	$cronLastRun = getOption('cronLastRun');
	
	if( $cronLastRun > (time() - (40 * 60)) ){//checking for 40 mins instead of 20 mins here
		return;	
	}	
	
	$requiredFor = array();
	
	$settings = panelRequestManager::getSettings();
	$updatesNotificationMail = $settings['notifications']['updatesNotificationMail'];

	//check setting
	if(!($updatesNotificationMail['frequency'] == 'never' || (empty($updatesNotificationMail['coreUpdates']) && empty($updatesNotificationMail['pluginUpdates']) && empty($updatesNotificationMail['themeUpdates']))) ){
		$requiredFor[] = 'Email Update Notification';
	}
	
	setHook('cronRequiredAlert', $requiredFor);
	
	if(!empty($requiredFor)){
		addNotification($type='E', $title='CRON JOB IS NOT RUNNING', $message='Please set the cron job to run every 20 minutes.<div class="droid700" style="white-space: pre; word-wrap: break-word;">'.APP_PHP_CRON_CMD.APP_ROOT.'/cron.php &gt;/dev/null 2&gt;&1</div><br>It is required for the following -<br>'.@implode('<br>', $requiredFor), $state='U', $callbackOnClose='', $callbackReference='');
	}	
}

function onBrowserLoad(){
	installFolderAlert();
	cronNotRunAlert();	
}

function autoSelectConnectionMethod(){
	$settings = Reg::get('settings');
	if($settings['autoSelectConnectionMethod'] == 1){				
		$result = fsockSameURLConnectCheck(APP_URL.'execute.php');
		if($result['status'] && $settings['executeUsingBrowser'] == 1){
			$settings['executeUsingBrowser'] = 0;
			DB::update("?:settings", array('general' => serialize($settings)), "1");
			addNotification($type='N', $title='FSOCK HAS BEEN ENABLED', $message='Your server supports fsock and has been enabled.', $state='U', $callbackOnClose='', $callbackReference='');
		}
		elseif($result['status'] == false && $result['errorNo'] != 'authentication_required' && $settings['executeUsingBrowser'] == 0){	
			$settings['executeUsingBrowser'] = 1;
			DB::update("?:settings", array('general' => serialize($settings)), "1");
			addNotification($type='N', $title='FSOCK HAS BEEN DISABLED', $message='Your server doesn\'t support fsock. An alternative method is being used.', $state='U', $callbackOnClose='', $callbackReference='');
		}		
	}
}
?>