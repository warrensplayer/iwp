<?php
/************************************************************
 * InfiniteWP Admin panel									*
 * Copyright (c) 2012 Revmakx								*
 * www.revmakx.com											*
 *															*
 ************************************************************/
 
class manageClientsBackup{
	
	public static function backupProcessor($siteIDs, $params){
					
		$accountInfo = array('account_info' => $params['accountInfo']);
		$config = $params['config'];
		$timeout = (20 * 60);//20 mins
		$type = "backup";
		$action = $params['action'];
		$requestAction = "scheduled_backup";
		
		if(empty($config['taskName'])){
			$config['taskName'] = 'Backup Now';
		}
		
		foreach($siteIDs as $siteID){
			$siteData = getSiteData($siteID);
			
			$exclude = explode(',', $config['exclude']);
			$include = explode(',', $config['include']);	
		   	array_walk($exclude, 'trimValue');
			array_walk($include, 'trimValue');
			
			$requestParams = array('task_name' => $config['taskName'], 'args' => array('what' => $config['what'], 'optimize_tables' => $config['optimizeDB'], 'exclude' => $exclude, 'include' => $include, 'del_host_file' => $config['delHostFile'], 'disable_comp' => $config['disableCompression'], 'fail_safe_db' => $config['failSafeDB'], 'fail_safe_files' => $config['failSafeFiles'], 'limit' => $config['limit'], 'backup_name' => $config['backupName']), 'secure' => $accountInfo);
		   			
			$historyAdditionalData = array();
			$historyAdditionalData[] = array('uniqueName' => $config['taskName'], 'detailedAction' => $type);
			  		
			$events=1;
			$PRP = array();
			$PRP['requestAction'] 	= $requestAction;
			$PRP['requestParams'] 	= $requestParams;
			$PRP['siteData'] 		= $siteData;
			$PRP['type'] 			= $type;
			$PRP['action'] 			= $action;
			$PRP['events'] 			= $events;
			$PRP['historyAdditionalData'] 	= $historyAdditionalData;
			$PRP['timeout'] 		= $timeout;
		   
		   prepareRequestAndAddHistory($PRP);
		  }
	}
	
	public static function backupResponseProcessor($historyID, $responseData){
		
		responseDirectErrorHandler($historyID, $responseData);
		
		if(empty($responseData['success'])){
			return false;
		}
		
		if(!empty($responseData['success']['error'])){
			DB::update("?:history_additional_data", array('status' => 'error', 'errorMsg' => $responseData['success']['error']), "historyID=".$historyID);	
			return false;
		}
		
		$historyData = DB::getRow("?:history", "*", "historyID=".$historyID);
		$siteID = $historyData['siteID'];
		
		if(!empty($responseData['success']['task_results']) && $historyData['action'] == 'now'){
			DB::update("?:history_additional_data", array('status' => 'success'), "historyID=".$historyID);
			
			if(!empty($responseData['success']['task_args']['account_info'])){
						
				foreach($responseData['success']['task_args']['account_info'] as $repoTypeKey => $repoTypeValue){
					$repoTypeKey = $repoTypeKey;
				}
										
				if($repoTypeKey == "iwp_ftp") $repoTypeKey = "FTP";
				if($repoTypeKey == "iwp_amazon_s3") $repoTypeKey = "AmazonS3";
				if($repoTypeKey == "iwp_dropbox") $repoTypeKey = "Dropbox";
				
				$newParams = array('action' => 'backupRepository',
				'args' => array(
				'params' => array("repository" => $repoTypeKey), 
				'siteIDs' => array($siteID)
				)
				);
				panelRequestManager::handler($newParams);
			}
		}
		
		//---------------------------post process------------------------>
		$siteID = DB::getField("?:history", "siteID", "historyID=".$historyID);
	
		$allParams = array('action' => 'getStats', 'args' => array('siteIDs' => array($siteID), 'extras' => array('sendAfterAllLoad' => false, 'doNotShowUser' => true)));
		
		panelRequestManager::handler($allParams);
	}
	
	public static function restoreBackupProcessor($siteIDs, $params){
		
		$type = "backup";
		$action = "restore";
		$requestAction = "restore";
		$timeout = (20 * 60);//20 mins
		
		$requestParams = array('task_name' => $params['taskName'], 'result_id' => $params['resultID']);
		
		$historyAdditionalData = array();
		$historyAdditionalData[] = array('uniqueName' => $params['taskName'], 'detailedAction' => $action);
		
		foreach($siteIDs as $siteID){
			$siteData = getSiteData($siteID);		
			
			$events=1;
			
			$PRP = array();
			$PRP['requestAction'] 	= $requestAction;
			$PRP['requestParams'] 	= $requestParams;
			$PRP['siteData'] 		= $siteData;
			$PRP['type'] 			= $type;
			$PRP['action'] 			= $action;
			$PRP['events'] 			= $events;
			$PRP['historyAdditionalData'] 	= $historyAdditionalData;
			$PRP['timeout'] 		= $timeout;
			
			prepareRequestAndAddHistory($PRP);
		}	
	}
	
	public static function restoreBackupResponseProcessor($historyID, $responseData){
		
		responseDirectErrorHandler($historyID, $responseData);
		
		if(empty($responseData['success'])){
			return false;
		}
		
		if(!empty($responseData['success'])){
			DB::update("?:history_additional_data", array('status' => 'success'), "historyID=".$historyID."");
		}
		
		//---------------------------post process------------------------>
		$siteID = DB::getField("?:history", "siteID", "historyID=".$historyID);
	
		$allParams = array('action' => 'getStats', 'args' => array('siteIDs' => array($siteID), 'extras' => array('sendAfterAllLoad' => false, 'doNotShowUser' => true)));
		
		panelRequestManager::handler($allParams);
		
	}
	
	public static function removeBackupProcessor($siteIDs, $params){
		
		$type = "backup";
		$action = "remove";
		$requestAction = "delete_backup";
		
		$requestParams = array('task_name' => $params['taskName'], 'result_id' => $params['resultID']);
		
		$historyAdditionalData = array();
		$historyAdditionalData[] = array('uniqueName' => $params['taskName'], 'detailedAction' => $action);
		
		foreach($siteIDs as $siteID){
			$siteData = getSiteData($siteID);	
			$events=1;	
			
			$PRP = array();
			$PRP['requestAction'] 	= $requestAction;
			$PRP['requestParams'] 	= $requestParams;
			$PRP['siteData'] 		= $siteData;
			$PRP['type'] 			= $type;
			$PRP['action'] 			= $action;
			$PRP['events'] 			= $events;
			$PRP['historyAdditionalData'] 	= $historyAdditionalData;
			
			prepareRequestAndAddHistory($PRP);
		}	
	}
	
	public static function removeBackupResponseProcessor($historyID, $responseData){
		
		responseDirectErrorHandler($historyID, $responseData);
		if(empty($responseData['success'])){
			return false;
		}
		
		if(!empty($responseData['success'])){
			DB::update("?:history_additional_data", array('status' => 'success'), "historyID=".$historyID."");
		}
		
		//---------------------------post process------------------------>
		$siteID = DB::getField("?:history", "siteID", "historyID=".$historyID);
	
		$allParams = array('action' => 'getStats', 'args' => array('siteIDs' => array($siteID), 'extras' => array('sendAfterAllLoad' => false, 'doNotShowUser' => true)));
		
		panelRequestManager::handler($allParams);	
	}
}
manageClients::addClass('manageClientsBackup');

?>