<?php
/************************************************************
 * InfiniteWP Admin panel									*
 * Copyright (c) 2012 Revmakx								*
 * www.revmakx.com											*
 *															*
 ************************************************************/

class panelRequestManager{
	
	private static $addonFunctions = array();
	
	function handler($requestData){
		//$GLOBALS['printAll'] = true;
		$requestStartTime = microtime(true);
		
		$clearPrint = empty($GLOBALS['printAll']) ? true :  false;
		
		if($clearPrint){
			ob_start();
		}
		
		$actionResult = $data = array();
		
		$action 	= $requestData['action'];
		$args		= $requestData['args'];
		$siteIDs 	= $requestData['args']['siteIDs'];
		$params 	= $requestData['args']['params'];
		$extras 	= $requestData['args']['extras'];
		$requiredData = $requestData['requiredData'];		
		$actionID = uniqid('', true);
		Reg::set('currentRequest.actionID', $actionID);
		
		if(manageClients::methodExists($action)){
			//self::userAccess($siteIDs);
			manageClients::execute($action, array('siteIDs' => $siteIDs, 'params' => $params, 'extras' => $extras));			
			
			if(Reg::get('currentRequest.exitOnComplete') === true){
				if(Reg::get('settings.executeUsingBrowser') != 1){//to fix update notification going "everything up to date" for fsock users
					executeJobs();
				}
				$exitOnCompleteT = microtime(true);
				exitOnComplete();
				$exitOnCompleteTT = microtime(true) - $exitOnCompleteT;
			}
			if(Reg::get('currentRequest.sendAfterAllLoad') === true){
				sendAfterAllLoad($requiredData);	
			}
			
			$actionResult = self::getActionStatus($actionID, $action);
		}
		if(Reg::get('settings.executeUsingBrowser') != 1){
			executeJobs();
		}
		
		$data = self::requiredData($requiredData);
		$finalResponse = array();
		$finalResponse = array('actionResult' => $actionResult, 'data' => $data);		
		
		if(empty($requestData['noGeneralCheck'])){
			self::generalCheck($finalResponse);
		}
		
		if($clearPrint){
			$printedText = ob_get_clean();
		}		
		
		$finalResponse['debug'] = array('exitOnCompleteTimeTaken' => $exitOnCompleteTT, 
										'currentRequest.exitOnComplete' => var_export(Reg::get('currentRequest.exitOnComplete'), true),
										'totalRequestTimeTaken' => (microtime(true) - $requestStartTime),
										/*'printedText' => $printedText,*/
										);
		
		
	    return json_encode($finalResponse);
	}
	
	public static function userAccess($siteIDs){
		$count = count($siteIDs);
		$accessSitesCount = DB::getfield("?:user_access", "count(siteID)", "userID = '".$_SESSION["userID"]."' AND siteID IN (". implode(', ', $siteIDs).")" );
		if($accessSitesCount == $count && $count > 0){
			return true;
		}		
		return false; 
	}
	
	public static function requiredData($requiredData){
		$data = array();
		if(empty($requiredData)){
			 return $data;
		}
		Reg::tplSet('sitesData', self::getSites());
		foreach($requiredData as $action => $args){
			if(method_exists('panelRequestManager', $action)){
				$data[ $action ] = self::$action($args);
			}
			elseif(in_array($action, self::$addonFunctions) && function_exists($action)){
				$data[ $action ] = call_user_func($action, $args);
			}
		}
		return $data;
	}
	
	public static function addFunctions(){
		$args = func_get_args();
		self::$addonFunctions = array_merge(self::$addonFunctions, $args);
	}	

	
	public static function getBackups($siteID, $refresh=false){//viewBackups
		
		if($refresh){
			manageClients::getStatsProcessor(array($siteID));
		}
		$sitesStatRaw = DB::getRow("?:site_stats", "*", "stats IS NOT NULL AND siteID = ".$siteID);	
		$backups = unserialize(base64_decode($sitesStat['stats']['iwp_backups'])); 
		
		return $backups;
	}
	
	
	public static function addSiteSetGroups($siteID, $groupsPlainText, $groupIDs){
		
		if(empty($siteID)) return false;	
		
		if(empty($groupIDs)){ $groupIDs = array(); }
		
		DB::delete("?:groups_sites", "siteID='".$siteID."'");//for updating
		
		$groupNames = explode(',', $groupsPlainText);
		array_walk($groupNames, 'trimValue');
		$groupNames = array_filter($groupNames);
		if(!empty($groupNames)){
			$existingGroups = DB::getArray("?:groups", "*", "name IN ('". implode("', '", $groupNames) ."')", "name");
			foreach($groupNames as $groupName){
				if(isset($existingGroups[$groupName])){
					array_push($groupIDs, $existingGroups[$groupName]['groupID']);					
				}
				else{
					$newGroupID = self::addGroup($groupName);
					array_push($groupIDs, $newGroupID);	
				}
			}			
		}
		$groupIDs = array_filter(array_unique($groupIDs));
		
		if(!empty($groupIDs)){
			foreach($groupIDs as $groupID){
				DB::replace("?:groups_sites", array('groupID' => $groupID, 'siteID' => $siteID));
			}
		}		
	}
	
	public static function manageGroups($groupsData){
		
		$newGroups = $groupsData['new'];//array('new-0' => 'name', 'new-1' => 'groupname2');
		if(!empty($newGroups)){
			$newGroups = array_filter($newGroups);
		}
		$deleteGroups = $groupsData['delete'];//array(1, 2);//groupIDS
		$updateGroupsSites = (!empty($groupsData['updateSites'])) ? $groupsData['updateSites'] : array();//array(5 => array(1,2), 'new-1' => array(2,4));//'new-1' => its new group this key will be replaced by it id, before processing this array
		$updateGroupsNames  = $groupsData['updateNames'];//array(101 => 'newname', 102 => 'newname2');
		
		if(!empty($newGroups)){
			foreach($newGroups as $newGroupKey => $newGroupName){
				$newGroupID = self::addGroup($newGroupName);
				if($newGroupID){
					$updateGroupsSites[$newGroupID] = $updateGroupsSites[$newGroupKey];//here new-0 will be replaced by groupID
					unset($updateGroupsSites[$newGroupKey]);
				}
			}
		}
		
		if(!empty($updateGroupsSites)){
			$tempUpdateGroupsSites = $updateGroupsSites;
			foreach($tempUpdateGroupsSites as $groupID => $temp){
				if(!is_numeric($groupID)){ unset($updateGroupsSites[$groupID]); }
			}
			self::updateGroupsSites($updateGroupsSites);
		}
		
		if(!empty($updateGroupsNames)){
			foreach($updateGroupsNames as $groupID => $groupName){
				self::updateGroup($groupID, $groupName);
			}
		}
		
		if(!empty($deleteGroups)){
			foreach($deleteGroups as $groupID){
				self::deleteGroup($groupID);
			}
		}
		return true;		
	}
	
	private static function updateGroupsSites($params){
		
		if(empty($params)){ return false; }
		foreach($params as $groupID => $siteIDs){
			if(empty($siteIDs)){ continue; }
			DB::delete("?:groups_sites", "groupID = ".$groupID);
			foreach($siteIDs as $siteID){
				if(is_numeric($siteID)){
					DB::replace("?:groups_sites", array('groupID' => $groupID, 'siteID' => $siteID));
				}
			}
		}
		return true;		
	}
	
	public static function getGroupsSites(){
		$groupsSites = DB::getArray("?:groups_sites GS, ?:groups G", "GS.siteID, GS.groupID", "GS.groupID =  G.groupID");
		$groups = DB::getArray("?:groups", "groupID, name", "1 ORDER BY groupID", "groupID");

		foreach($groupsSites as $groupSites){
			$groups[ $groupSites['groupID'] ]['siteIDs'][] = $groupSites['siteID'];
		}
		return $groups;
	}
	
	private static function addGroup($name){
		return DB::insert("?:groups", array('name' => $name));
	}
	
	private static function updateGroup($groupID, $name){
		return DB::update("?:groups", array('name' => $name), "groupID=".$groupID);
	}
	
	private static function deleteGroup($groupID){
		$done = DB::delete("?:groups", "groupID=".$groupID);
		if($done){
			$done = DB::delete("?:groups_sites", "groupID=".$groupID);
		}
		return $done;
	}
	
	public static function getRawSitesStats($siteIDs=array()){
		$where = "";
		if(!empty($siteIDs)){
			$where = " AND SS.siteID IN (". implode(',', $siteIDs) .")";
		}
		$sitesStats = DB::getArray("?:site_stats SS, ?:sites S", "SS.*", "S.siteID = SS.siteID AND SS.stats IS NOT NULL ".$where, "siteID");
		if(empty($sitesStats)){ return array(); }
		foreach($sitesStats as $siteID => $sitesStat){
			$sitesStats[$siteID]['stats'] = unserialize(base64_decode($sitesStat['stats']));
		}
		return $sitesStats;
	}
	
	public static function getSitesBackups($siteIDs=array()){
		$sitesStats = self::getRawSitesStats($siteIDs);	
		
		$sitesBackups = array();
		
		foreach($sitesStats as $siteID => $siteStats){
			
			$backupKeys = array_keys($siteStats['stats']['iwp_backups']);
				
			foreach($backupKeys as $key => $backupKey){
				
				$backupTaskType = 'backupNow';
				if($backupKey != 'Backup Now'){
					$siteExist = DB::getExists("?:backup_schedules_link BSL, ?:backup_schedules BS", "siteID", "BS.scheduleKey = '".$backupKey."' AND BSL.siteID='".$siteID."' AND BS.scheduleID = BSL.scheduleID");	
					
					if($siteExist){ continue;  }					
					$backupTaskType = 'otherBackup';
				}
				
				if(empty($siteStats['stats']['iwp_backups'][$backupKey])){
					continue;
				}
				
				$siteBackupsTemp = $siteStats['stats']['iwp_backups'][$backupKey];
				$siteBackups[$siteID] = array();
				krsort( $siteBackupsTemp );
				
				foreach($siteBackupsTemp as $referenceKey => $siteBackupTemp){
					if(!empty($siteBackupTemp['error'])){ continue; }
					
					$otherParts = '';
													
					if(empty($siteBackupTemp['server']['file_url']) && !empty($siteBackupTemp['ftp'])){
						$otherParts = $siteBackupTemp['ftp'];
					}
					if(empty($siteBackupTemp['server']['file_url']) && !empty($siteBackupTemp['amazons3'])){
						$otherParts = $siteBackupTemp['amazons3'];
					}
					if(empty($siteBackupTemp['server']['file_url']) && !empty($siteBackupTemp['dropbox'])){
						$otherParts = $siteBackupTemp['dropbox'];
					}
					$fileURLParts = explode('/', $siteBackupTemp['server']['file_url'] ? $siteBackupTemp['server']['file_url'] : $otherParts);
					$fileName = array_pop($fileURLParts);
					$fileNameParts = explode('_', $fileName);
					$what = $fileNameParts[2];
					
					$repo = '';
					//only showing files which are available
					if(array_key_exists('server', $siteBackupTemp))
					{
						$repo = "Server";
					}
					if(array_key_exists('ftp', $siteBackupTemp))
					{
						$repo = "FTP";
					}
					if(array_key_exists('amazons3', $siteBackupTemp))
					{
						$repo = "Amazon S3";
					}
					if(array_key_exists('dropbox', $siteBackupTemp))
					{
						$repo = "Dropbox";
					}
										
					$sitesBackups[$siteID][$backupTaskType][] = array('time' => $siteBackupTemp['time'],
																  'type' => 'backupNow',
																  'downloadURL' => $siteBackupTemp['server']['file_url'],
																  'size' => $siteBackupTemp['size'],
																  'what' => $what,
																  'referenceKey' => $referenceKey,
																  'backupName' => $siteBackupTemp['backup_name'],
																  'siteID' => $siteID,
																  'repository' => $repo,
																  'backupTaskType' => $backupTaskType,
																  'data' => array('scheduleKey' => $backupKey));
				}
			}			
		}
		
		return $sitesBackups;
	}
	
	public static function getSitesBackupsHTML(){
		$sitesBackups = self::getSitesBackups();
		$HTML = TPL::get('/templates/backup/view.tpl.php', array('sitesBackups' => $sitesBackups));
		return $HTML;
	}
	
	public static function getSiteBackupsHTML($siteID){
		$sitesBackups = self::getSitesBackups(array($siteID));
		$HTML = TPL::get('/templates/backup/sitePopup.tpl.php', array('siteBackups' => $sitesBackups, 'siteID' => $siteID));
		return $HTML;
	}
	
	public static function siteIsWritable(){
		
		$sitesStats = self::getRawSitesStats();
		foreach($sitesStats as $siteID){
			$siteIsWritable[$siteID['siteID']] = $siteID['stats']['writable'];			
		}
	    return $siteIsWritable;
	}
	
	public static function getSitesUpdates(){
		
		$siteView = $pluginView = $themeView = $coreView = array();
		$sitesStats = self::getRawSitesStats();

		foreach($sitesStats as $siteID){
			
			$siteID['stats']['premium_updates'] = (array)$siteID['stats']['premium_updates'];
			foreach($siteID['stats']['premium_updates'] as $item){			
				$isHiddenItem = false;
				$ignoredUpdates = DB::getField("?:hide_list", "URL", "URL = '".$item['slug']."' AND siteID = '".$siteID['siteID']."'"); 
				
				$pluginView['plugins'][$item['slug']][$siteID['siteID']] = $siteView[$siteID['siteID']]['plugins'][$item['slug']] = array_change_key_case($item, CASE_LOWER);				
				
				if($ignoredUpdates){ 
					$pluginView['plugins'][$item['slug']][$siteID['siteID']]['hiddenItem'] = $siteView[$siteID['siteID']]['plugins'][$item['slug']]['hiddenItem'] = true;
				} 
			}
			
			$siteID['stats']['upgradable_plugins'] = (array)$siteID['stats']['upgradable_plugins'];
			foreach($siteID['stats']['upgradable_plugins'] as $item){			
				$temp = objectToArray($item);
				if(!is_array($temp))
				$temp=array();
				
				$isHiddenItem = false;
				$ignoredUpdates = DB::getField("?:hide_list", "URL", "URL = '".$item->file."' AND siteID = '".$siteID['siteID']."'"); 
				if($ignoredUpdates){ 
					$isHiddenItem = true;
				} 
				$temp['hiddenItem'] = $isHiddenItem;
				
				$pluginView['plugins'][$item->file][$siteID['siteID']] = $siteView[$siteID['siteID']]['plugins'][$item->file] = $temp;
			}
			
			$siteID['stats']['upgradable_themes'] = (array)$siteID['stats']['upgradable_themes'];
			foreach($siteID['stats']['upgradable_themes'] as $item){
				
				$isHiddenItem = false;
				$ignoredUpdates = DB::getField("?:hide_list", "URL", "URL = '".$item['theme_tmp']."' AND siteID = '".$siteID['siteID']."'"); 
				if($ignoredUpdates){ 
					$isHiddenItem = true;
				} 
				$item['hiddenItem'] = $isHiddenItem;
				
				$themeView['themes'][$item['theme_tmp']][$siteID['siteID']] = $siteView[$siteID['siteID']]['themes'][$item['theme_tmp']] = $item;
							
			}
			
			if(!empty($siteID['stats']['core_updates'])){
				
				$item = $siteID['stats']['core_updates'];
				$temp = objectToArray($item);
				if(!is_array($temp))
				$temp=array();
				$isHiddenItem = false;
				$ignoredUpdates = DB::getField("?:hide_list", "URL", "URL = '".$item->current."' AND siteID = '".$siteID['siteID']."'"); 
				if($ignoredUpdates){ 
					$isHiddenItem = true;
				}
				$temp['hiddenItem'] = $isHiddenItem;
				
				$coreView['core'][$item->current][$siteID['siteID']] = $siteView[$siteID['siteID']]['core'][$item->current] = $temp;
			}	
		}

		ksortTree($siteView, 3);
		ksortTree($pluginView, 2);
		ksortTree($themeView, 2);
		ksortTree($coreView, 2);
		
		/*if(!empty($siteView)){//sort by site name
			$sitesData = self::getSites();
			$_siteView = $tempOrder = array();
			foreach($siteView as $siteID => $siteUpdates){
				$_siteView[$sitesData[$siteID]['name']] = $siteUpdates;
				$tempOrder[$sitesData[$siteID]['name']] = $siteID;
			}
			ksort($_siteView);
			$siteView = array();
			foreach($_siteView as $siteName => $siteUpdates){
				$siteView[$tempOrder[$siteName]] = $siteUpdates;
			}
			unset($_siteView, $tempOrder);
			
		}*/
		
		$siteViewCount = array();//count of plugins, themes, core by site view
		$totalUpdateCount = $allUpdatesCount = 0;
		foreach($siteView as $siteID => $siteValues){
			$siteViewCount[$siteID]['core'] = $siteViewCount[$siteID]['themes'] = $siteViewCount[$siteID]['plugins'] = 0;
			foreach($siteValues as $type => $items){
				foreach($items as $item){
					if(empty($item['hiddenItem'])){						
						$siteViewCount[$siteID][$type]++;
						$totalUpdateCount++;
					}
				}
			}
			
		}
		
		$lastReloadTime = DB::getField("?:site_stats", "lastUpdatedTime", "1 ORDER BY lastUpdatedTime LIMIT 1");
		$lastReloadTime = ($lastReloadTime > 0) ? @date('M d @ h:ia', $lastReloadTime) : '';
		
    	return array('siteView' => $siteView, 'pluginsView' => $pluginView, 'themesView' => $themeView, 'coreView' => $coreView, 'siteViewCount' => $siteViewCount, 'totalUpdateCount' => $totalUpdateCount, 'lastReloadTime' => $lastReloadTime);
	}
	
	public static function getSites(){
		$sitesData = DB::getArray("?:sites", "siteID, URL, adminURL, name, IP, adminUsername, isOpenSSLActive, network, parent, httpAuth, callOpt, connectURL", "1 ORDER BY name", "siteID");
		$groupsSites = DB::getArray("?:groups_sites", "*", "1");
		if(!empty($groupsSites)){
			foreach($groupsSites as $groupSite){
				if(!empty($sitesData[$groupSite['siteID']])){
					$sitesData[$groupSite['siteID']]['groupIDs'][] = $groupSite['groupID'];
				}
			}
		}
		if(!empty($sitesData)){
			foreach($sitesData as $siteID => $siteData){
				if(!empty($siteData['httpAuth'])){
					$sitesData[$siteID]['httpAuth'] = @unserialize($siteData['httpAuth']);
				}
				if(!empty($siteData['callOpt'])){
					$sitesData[$siteID]['callOpt'] = @unserialize($siteData['callOpt']);
				}
			}
		}
		return $sitesData;
	}
	
	public static function getSitesList(){
		$d = DB::getArray("?:sites", "siteID, URL, adminURL, name, IP, adminUsername, isOpenSSLActive, network, parent", "1 ORDER BY name", "siteID");
		foreach($d as $k => $v){
			$d['s'.$k] = $v;
			unset($d[$k]);
		}
		return $d;
	}
	
	public static function getSearchedPluginsThemes(){
		
		$actionID = Reg::get('currentRequest.actionID');
		
		$datas = DB::getFields("?:temp_storage", "data", "type = 'getPluginsThemes' AND paramID = '".$actionID."'");
		
		DB::delete("?:temp_storage", "type = 'getPluginsThemes' AND paramID = '".$actionID."'");
		
		if(empty($datas)){
			return array();
		}
		$finalData = array();
		foreach($datas as $data){
			$finalData = array_merge_recursive($finalData, (array)unserialize($data));	
		}
	
		arrayMergeRecursiveNumericKeyHackFix($finalData);		
		ksortTree($finalData);	
		
		//finding not installed for site view only	
		$typeItems = array_keys($finalData['typeView']);		
		foreach($typeItems as $item){		
			foreach($finalData['siteView'] as $siteID => $value){
				if(empty($value['active'][$item]) && empty($value['inactive'][$item])){
					$finalData['siteView'][$siteID]['notInstalled'][$item] = reset(reset($finalData['typeView'][$item]));
				}
			}		
		}
		
		return $finalData;
	}
	
	public static function updateSettings($settings){
		
		
		DB::delete("?:allowed_login_ips", "1");
		if(!empty($settings['allowedLoginIPs'])){
			foreach($settings['allowedLoginIPs'] as $IP){
				DB::insert("?:allowed_login_ips", array('IP' => $IP));
			}
		}
		
		$updateSettings = array();
		
		if(!empty($settings['general'])){			
			if($settings['general']['autoSelectConnectionMethod'] == 1){
				$currentGeneralSettings = Reg::get('settings');
				$settings['general']['executeUsingBrowser'] = $currentGeneralSettings['executeUsingBrowser'];
			}
			$updateSettings['general'] = serialize($settings['general']);
		}
		if(!empty($settings['notifications'])){
			$updateSettings['notifications'] = serialize($settings['notifications']);
		}
		
		if(!empty($updateSettings)){
			$updateSettings['timeUpdated'] = time();
			return DB::update("?:settings", $updateSettings, "1");
		}
	}
	
	public static function getSettings(){
		$settings =  array();
		$settings['allowedLoginIPs'] = DB::getFields("?:allowed_login_ips", "IP", "1", "IP");
		
		$settingsRow = DB::getRow("?:settings", "*", "1");
		$settings['general'] = @unserialize($settingsRow['general']);
		$settings['notifications'] = @unserialize($settingsRow['notifications']);
		
		return $settings;
	}
	
	public static function getSettingsAll(){
		
		return array('settings' => self::getSettings(),
					 'accountSettings' => self::getAccountSettings($_SESSION['userID']));
	}
	
	public static function getRecentHistory(){
		$limit = 10;
		$actionIDs = DB::getFields("?:history", "actionID", "showUser='Y' GROUP BY actionID ORDER BY historyID DESC LIMIT ".$limit);	
		if(empty($actionIDs)){ return array(); }
		$actionHistory = array();
		foreach($actionIDs as $actionID){
			$actionHistory[ $actionID ] = self::getActionStatus();
		}
		return $actionHistory;
	}
	
	public static function getActionStatus($actionID, $action=''){
		

		$historyDatas = DB::getArray("?:history", "historyID, siteID, type, action, status, error, microtimeAdded", "actionID = '".$actionID."' ORDER BY historyID ASC", "historyID");		

		if(empty($historyDatas)){ return false;	}
		
		$totalRequest = count($historyDatas);
		$totalNonSuccessRequest = DB::getField("?:history", "count(status)", "actionID = '".$actionID."' AND status != 'completed'");
		$totalPendingRequest = DB::getField("?:history", "count(status)", "actionID = '".$actionID."' AND status IN ('pending', 'running', 'initiated')");
		$totalSuccessRequest = DB::getField("?:history", "count(status)", "actionID = '".$actionID."' AND status = 'completed'");

		
		if($totalPendingRequest > 0){ $status = 'pending';  }
		elseif($totalNonSuccessRequest == 0){ $status = 'success'; }
		elseif($totalNonSuccessRequest < $totalRequest){ $status = 'partial'; }
		elseif($totalNonSuccessRequest == $totalRequest){ $status = 'error'; }	
		
		$historyStatusSummary = array('total' => $totalRequest,
									  'pending' => $totalPendingRequest,
									  'nonSuccess' => $totalNonSuccessRequest,
									  'success' => $totalSuccessRequest);
	
		$historyData = reset($historyDatas);
		$type = $historyData['type'];//getting type from first history only, assuming type is common for one actionID
		$action = $historyData['action'];
		$time = $historyData['microtimeAdded'];//getting time from first history ordered by historyID ASC
		$actionSitesCount = count($actionHistory);
		
		$historyIDs = array_keys($historyDatas);
		

		$historyAdditionalDatas = DB::getArray("?:history_additional_data HAD, ?:history H", "HAD.*, H.siteID, H.URL", "H.actionID = '".$actionID."' AND HAD.historyID = H.historyID");		
		if(empty($historyAdditionalDatas)){ return false; }
		

		$historyAdditionalDatasStatusArray = DB::getFields("?:history_additional_data HAD, ?:history H", "count(HAD.historyID), HAD.status", "H.actionID = '".$actionID."' AND HAD.historyID = H.historyID GROUP BY status", "status");		
		if(empty($historyAdditionalDatasStatusArray)){
			$historyAdditionalDatasStatusArray = array();
		}
		$historyAdditionalDatasStatusArray['total'] = count($historyAdditionalDatas);
		
		$detailedActions = DB::getArray("?:history_additional_data HAD, ?:history H", "count(DISTINCT HAD.historyID) as sitesCount, count(DISTINCT HAD.uniqueName) as detailedActionCount,  HAD.detailedAction, HAD.uniqueName", "H.actionID = '".$actionID."' AND HAD.historyID = H.historyID GROUP BY detailedAction ","detailedAction");
		
		
		if($status == 'success'){//up to this line status only check connection is done successfully after this we will check task completed or not
			if(empty($historyAdditionalDatasStatusArray['success'])){
				$status = 'error';
			}
			elseif($historyAdditionalDatasStatusArray['total'] > $historyAdditionalDatasStatusArray['success']){
				$status = 'partial';
			}
		}
			
		$actionResult = array(
						'status' => $status,
						'statusMsg' => $status,
						'actionID' => $actionID,
						'historyID' => $historyID,
						'statusSummary' => $historyAdditionalDatasStatusArray,
						'historyStatusSummary' => $historyStatusSummary,
						'detailedStatus' => $historyAdditionalDatas,
						'detailedActions' => $detailedActions, 
						'type' => $type,
						'action' => $action,
						'time' => (int)$time,
						'actionSitesCount' => $actionSitesCount,
						'errors' => $errors,
						);
		
		return $actionResult;
	}
	
	public static function getWaitData($params=array()){
		if(!empty($params)){
			foreach($params as $actionID => $value){
				if($value == 'sendData'){
					$_SESSION['waitActions'][$actionID]['sendData'] = true;
				}
			}
			return true;
		}
		
			
		if(empty($_SESSION['waitActions'])) return false;
		$result = array();
		
		foreach($_SESSION['waitActions'] as $actionID => $waitAction){
			$sendData = false;
			$result[$actionID] = array();
			
			if( !empty($waitAction['sendData']) || ($waitAction['timeInitiated'] > 0 && $waitAction['timeInitiated'] < (time() - (5 *60))) ){
				$sendData = true;
			}
			
			$totalRequest = DB::getField("?:history", "count(status)", "actionID = '".$actionID."'");
			$totalPendingRequest = DB::getField("?:history", "count(status)", "actionID = '".$actionID."' AND status IN ('pending', 'running', 'initiated', 'processingResponse')");
			$totalSuccessRequest = DB::getField("?:history", "count(status)", "actionID = '".$actionID."' AND status = 'completed'");
			
			$result[$actionID]['total'] = $totalRequest;
			$result[$actionID]['loaded'] = $totalSuccessRequest;
			
			
			if($totalPendingRequest == 0) $sendData = true;
			
			if($sendData){
				$currentActionID = Reg::get('currentRequest.actionID');
				Reg::set('currentRequest.actionID', $actionID);
				$result[$actionID]['requiredData'] = $_SESSION['waitActions'][$actionID]['requiredData'];			
				$result[$actionID]['data'] = self::requiredData($result[$actionID]['requiredData']);
				$result[$actionID]['actionResult'] = self::getActionStatus($actionID);
				Reg::set('currentRequest.actionID', $currentActionID);
			}
			
			if($sendData || $waitAction['timeExpiresFromSession'] < time()){
				unset($_SESSION['waitActions'][$actionID]);
			}			
		}
		
		return $result;
		
	}	
	
	public static function getHistoryPageHTML($args){
		$itemsPerPage = 20;		
		$page = (!$args['page']) ? 1 : $args['page'];
		$where = "showUser='Y'";
		if(!empty($args['dates'])){
			$dates 		= explode('-', $args['dates']);
			$fromDate 	= strtotime(trim($dates[0]));
			$toDate		= strtotime(trim($dates[1]));
			if(!empty($fromDate) && !empty($toDate) && $fromDate != -1 && $toDate != -1){
				$toDate += 86399;
				$where .= " AND microtimeAdded >= ".$fromDate." AND  microtimeAdded <= ".$toDate." ";
			}
		}
				
		$total = DB::getField("?:history", "SQL_CALC_FOUND_ROWS actionID", $where. " GROUP BY actionID");
		$total = DB::getField("SELECT FOUND_ROWS()");

		$limitSQL = paginate($page, $total, $itemsPerPage);
		
		$actionIDs = DB::getFields("?:history", "actionID", $where. " GROUP BY actionID ORDER BY historyID DESC ".$limitSQL);	
		
		if(!empty($actionIDs)){ 
			$actionsHistoryData = array();
			foreach($actionIDs as $actionID){
				$actionsHistoryData[ $actionID ] = self::getActionStatus($actionID);
			}
		}
		
		$HTML = TPL::get('/templates/history/view.tpl.php', array('actionsHistoryData' => $actionsHistoryData));
		
		return $HTML;
	}
	
	public static function getHistoryPanelHTML(){
		$itemsPerPage = 10;		
		$actionIDs = DB::getFields("?:history", "actionID", "showUser='Y' GROUP BY actionID ORDER BY historyID DESC LIMIT ".$itemsPerPage);	
		if(empty($actionIDs)){ $actionIDs = array(); }
		$actionsHistoryData = array();
		$showInProgress = false;
		foreach($actionIDs as $actionID){
			$actionsHistoryData[ $actionID ] = self::getActionStatus($actionID);
			if($actionsHistoryData[ $actionID ]['status'] == 'pending'){ $showInProgress = true; }
		}		
		$HTML = TPL::get('/templates/history/processQueue.tpl.php', array('actionsHistoryData' => $actionsHistoryData, 'showInProgress' => $showInProgress));
		
		return $HTML;
	}
	
	public static function addHide($params){
		
		if(empty($params)){
			 return false; 
		}
		foreach($params as $siteID => $value){			
			DB::insert("?:hide_list", array('type' => $value['type'], 'siteID' => $siteID, 'name' => $value['name'], 'URL' => $value['path']));			
		}
	}
	
	public static function getHide(){
	
		$getHide = DB::getArray("?:hide_list", "*", "1");
		$hide = array();
		foreach($getHide as $v){
			$hide[$v["siteID"]][] = array('type' => $v["type"], 'name' => $v["name"], 'URL' => $v["URL"]);	
		}
		return $hide;
	}
	
	public static function removeHide($params){
		
		if(empty($params)){
			 return false; 
		}		
		foreach($params as $siteID => $value){
			$isDone = DB::delete("?:hide_list","type = '".$value['type']."' AND siteID = '".$siteID."' AND URL  = '".$value['path']."' ");
		}
		return $isDone;
	}
	
	public static function addFavourites($params){
		if(empty($params)){
			 return false; 
		}

		return DB::insert("?:favourites", array('type' => $params['type'], 'name' => $params['name'], 'URL' => $params['URL']));
	}
	
	public static function getFavourites(){
		
		$getFavourites = DB::getArray("?:favourites", "*", 1);
		$favourites = array();
		foreach($getFavourites as $v){
			$favourites[$v["type"]][] = array('name' => $v["name"], 'URL' => $v["URL"]);			
		}
		return $favourites;
	}
	
	public static function removeFavourites($params){
		return DB::delete("?:favourites","type = '".$params['type']."' AND URL  = '".$params['URL']."' ");
	}
	
	public static function updateAccountSettings($params){
		$userData = array();
		$userID = $_SESSION['userID'];
		$where = "userID = ".$userID;
		
		if( !empty($params['currentPassword']) && !empty($params['newPassword']) ){
			
			$where .= " && password='".sha1($params['currentPassword'])."'";
			$isPasswordCorrect = DB::getExists("?:users", "userID", $where);
			if(!$isPasswordCorrect){
				return array('status' => 'error', 'error' => 'invalid_password', 'errorArray' => array('currentPassword' => 'invalid'));
			}
			
			$userData['password'] = sha1($params['newPassword']);
		}
		if( !empty($params['email']) ){
			$userData['email'] = $params['email'];
		}
		if(empty($userData)){
			return array('status' => 'error', 'error' => 'empty', 'errorArray' => array('currentPassword' => 'invalid', 'email' => 'invalid'));
		}
		
		$isUpdated = DB::update("?:users", $userData, $where);
		if($isUpdated){
			return array('status' => 'success', 'error' => '');	
		}
		return array('status' => 'error', 'error' => 'db_error');
	}
	
	public static function getAccountSettings($userID){
		return DB::getRow("?:users", "email", "userID=".$userID);
	}
	
	public static function getWPRepositoryHTML($params){
		
		$searchVar = $params['searchVar'];
		$searchItem = $params['searchItem'];
		$type = $params['type'];
		if($type =='plugins')
		{
			$action='query_plugins';
			$URL= 'http://api.wordpress.org/plugins/info/1.0/';
		}
		if($type=='themes')
		{
			$action='query_themes';
			$URL= 'http://api.wordpress.org/themes/info/1.0/';
		}
		$args = (object)$args;
		//$args->search= 'WP ecommerce';
		if($searchVar==1)
		$args->search=$searchItem;
		else
		$args->browse=$searchItem;
		$args->per_page=30;
		$args->page=1;
		$Array['action']=$action;
		$Array['request']=serialize($args);
		
	
		$return = unserialize(repoDoCall($URL,$Array));
		$return=$return->$params['type'];
		foreach($return as $item)
		{
	
			//Limit description to 400char, and remove any HTML.
			$description = strip_tags( $item->description);
			if ( strlen( $description ) > 400 )
				$description = mb_substr( $description, 0, 400 ) . '&#8230;';
			//remove any trailing entities
			$description = preg_replace( '/&[^;\s]{0,6}$/', '', $description );
			//strip leading/trailing & multiple consecutive lines
			$description = trim( $description );
			$description = preg_replace( "|(\r?\n)+|", "\n", $description );
			//\n => <br>
			$description = nl2br( $description );	
			$existFav = DB::getField("?:favourites", "count(ID)", "type = '".$type."' AND name = '".$item->name."'");
						
			if($type=='plugins')
			{
				$content = $content.'<div class="tr"><div class="name">'.$item->name.'<div class="wp_repository_search_results_actions"><a class="installItem multiple" dlink="http://downloads.wordpress.org/plugin/'.$item->slug.'.zip">Install</a>';
				$content = $content.'<a href="http://wordpress.org/extend/plugins/'.$item->slug.'/" target="_blank">Details</a>';
				if($existFav == 1){
				$content = $content.'<a class="addToFavorites disabled" >Favourite</a>'; 
				}
				else 
				$content = $content.'<a class="addToFavorites" utype="'.$type.'" iname="'.$item->name.'" dlink="http://downloads.wordpress.org/plugin/'.$item->slug.'.zip" >Add to Favorites</a>';  
				$content = $content.'</div></div> <div class="version">'.$item->version.'</div> <a class="rating" title="(based on '.$item->num_ratings.' ratings)"><div class="rating_fill" style="width:'.$item->rating.'%;"></div><div class="stars"></div></a>   <div class="descr">'.$description.'</div>
                  <div class="clear-both"></div>
                </div>';
			}
			else
			{
				$content=$content.'<div class="theme_column"> <div class="thumb" preview="'.$item->preview_url.'"><div class="icon_preview rep_sprite_backup">Preview</div><div class="btn_preview"></div><img src="'.$item->screenshot_url.'"  /></div><div class="theme_name droid700">'.$item->name.'</div>
                <div class="wp_repository_search_results_actions"><a class="installItem multiple" dlink="http://wordpress.org/extend/themes/download/'.$item->slug.'.'.$item->version.'.zip">Install</a>';
				
			$content=$content.'<a href="http://wordpress.org/extend/themes/'.$item->slug.'/" target="_blank">Details</a>';
			  if($existFav == 1){
				$content = $content.'<a class="addToFavorites disabled" >Favourite</a>'; 
				}
	 			else
			$content=$content.'	<a class="addToFavorites" utype="'.$type.'" iname="'.$item->name.'" dlink="http://wordpress.org/extend/themes/download/'.$item->slug.'.'.$item->version.'.zip" >Add to Favorites</a>';
			$content = $content.'</div>
                <div class="clear-both"></div>
                <div class="theme_descr">'.$description.'</div>
              </div>';
			
			}
		}
		return utf8_encode($content);
	}
	
	public static function getUserHelp(){
		$help = DB::getField("?:users", "help", "userID=".$_SESSION['userID']);
		if(empty($help)){
			return array();	
		}
		return (array)unserialize($help);
	}
	
	public static function updateUserHelp($params){
		$oldHelp = self::getUserHelp();
		$params = array_merge($oldHelp, (array)$params);
		$help = DB::update("?:users", array('help' => serialize($params)), "userID=".$_SESSION['userID']);
		return $help;
	}
	
	public static function getReportIssueData($actionID){
		$issue = getReportIssueData($actionID);
		$issue['report'] = serialize($issue['report']);
		return $issue;
	}
	
	public static function updatesNotificationMailTest(){
		return updatesNotificationMailSend(true);	
	}
	
	public static function getClientUpdateAvailableSiteIDs(){
		
		$rawSiteStats = self::getRawSitesStats();
		
		unset($_SESSION['clientUpdates']);
		
		foreach($rawSiteStats as $siteID => $statsArray){
			
			$stats = $statsArray['stats'];

			//check iwp-client plugin have any updates
			if( !empty($stats['client_new_version']) || version_compare($stats['client_version'], '0.1.4') != 1 ){
				if(!isset($_SESSION['clientUpdates'])){
					$_SESSION['clientUpdates'] = array();
				}				
				
				if( !empty($stats['client_new_version']) && version_compare($stats['client_version'], $stats['client_new_version']) == -1 ){//fixed repeated Client update popup
					//$_SESSION['clientUpdates']['sitesUpdate'][$siteID] = $stats['client_new_package'];
					if(!isset($_SESSION['clientUpdates']['clientUpdateVersion']) || version_compare($_SESSION['clientUpdates']['clientUpdateVersion'], $stats['client_new_version']) == -1){
						$_SESSION['clientUpdates']['clientUpdateVersion'] = $stats['client_new_version'];
						$_SESSION['clientUpdates']['clientUpdatePackage'] = $stats['client_new_package'];
					}
				}
				elseif( version_compare($stats['client_version'], '0.1.4') != 1 ){
					//$_SESSION['clientUpdates']['sitesUpdate'][$siteID] = 'http://downloads.wordpress.org/plugin/iwp-client.zip';
					$_SESSION['clientUpdates']['clientUpdateVersion'] = '1.0.0';
					$_SESSION['clientUpdates']['clientUpdatePackage'] = 'http://downloads.wordpress.org/plugin/iwp-client.zip';
				}
				
			}
		}
		
		if(empty($_SESSION['clientUpdates']['clientUpdateVersion'])){
			return false;
		}
		
		$_SESSION['clientUpdates']['siteIDs'] = array();
		foreach($rawSiteStats as $siteID => $statsArray){			
			$stats = $statsArray['stats'];
			//check iwp-client plugin have any updates
			if(version_compare($stats['client_version'], $_SESSION['clientUpdates']['clientUpdateVersion']) == -1  && DB::getExists("?:sites", "siteID", "siteID = '".$siteID."' AND (network = 0 OR (network = 1 AND parent = 1)) ")){
				$_SESSION['clientUpdates']['siteIDs'][] = $siteID;
			}
		}
		
		return $_SESSION['clientUpdates']['siteIDs'];
		
	}
	
	public static function generalCheck(&$finalResponse){
		
		if($updateAvailable = checkUpdate()){
			if( getOption('updateHideNotify') != $updateAvailable['newVersion'] && getOption('updateNotifySentToJS') != $updateAvailable['newVersion'] ){
				$finalResponse['updateAvailable'] = $updateAvailable;
				updateOption('updateNotifySentToJS', $updateAvailable['newVersion']);
			}
		}

		$notifications = getNotifications(true);
		if(!empty($notifications)){
			$finalResponse['notifications'] = $notifications;
		}
		
		$waitData = self::getWaitData();
		if(!empty($waitData)){
			$finalResponse['data']['getWaitData'] = $waitData;
		}
		
	}
	
	public static function updateHideNotify($version){//IWP update
		return updateOption('updateHideNotify', $version);
	}
	
	public static function isUpdateHideNotify(){
		$updateAvailable = checkUpdate(false, false);
		if(!empty($updateAvailable)){
			if($updateAvailable['newVersion'] == getOption('updateHideNotify')){
				return true;	
			}
		}
		return false;
	}
	
	public static function forceCheckUpdate(){
		return checkUpdate(true);
	}
	
	public static function sendReportIssue($params){
		return sendReportIssue($params);
	}
	
	public static function getResponseMoreInfo($historyID){
		return getResponseMoreInfo($historyID);
	}
	
	public static function updateSite($params){
		
		if(empty($params['siteID'])){ return false; }
		
		$siteData = array( "adminURL" 		=> $params['adminURL'],
						   "adminUsername"	=> $params['adminUsername'],
						   "URL"			=> $params['URL'],
						   "connectURL"		=> $params['connectURL'],
						  ); // save data
						  
		if(!empty($params['httpAuth']['username'])){
			  $siteData['httpAuth'] = serialize($params['httpAuth']);
		}
		else{
			$siteData['httpAuth'] = '';
		}
		
		if(!empty($params['callOpt'])){
			$siteData['callOpt'] = serialize($params['callOpt']);
		}
		else{
			$siteData['callOpt'] = '';
		}
	  
		$isDone = DB::update('?:sites', $siteData, "siteID = ".$params['siteID']); 
		//DB::replace("?:user_access", array('userID' => $_SESSION['userID'], 'siteID' => $siteID));			  
		
		if($isDone){
			panelRequestManager::addSiteSetGroups($params['siteID'], $params['groupsPlainText'], $params['groupIDs']);	
		}
		return $isDone;
	}	
	public static function repositoryTestConnection($params){
		return repositoryTestConnection($params);
	}
	public static function getAddonsPageHTML(){
		
		$data = array();
		$data['installedAddons'] = getInstalledAddons();
		$data['newAddons'] = getNewAddonsAvailable();
		$data['promoAddons'] = getPromoAddons();
		$data['promos'] = getOption('promos');
		$data['isAppRegistered'] = isAppRegistered();
		

		$HTML = TPL::get('/templates/addons/view.tpl.php', $data);
		return $HTML;
	}
	
	public static function activateAddons($params){
		return activateAddons($params['addons']);
	}
	
	public static function deactivateAddons($params){
		return deactivateAddons($params['addons']);
	}
	
	public static function IWPAuthUser($params){
		
		$serviceURL = getOption('serviceURL');
		$registerURL = str_replace(array('service.', 'dev_service/', 'http://'), array('', '', 'https://'), $serviceURL);// to bring http://infinitewp.com/
		$registerURL .= 'app-login/';
		
		$data = array('appInstallHash' => APP_INSTALL_HASH,
					  'installedHash' => getInstalledHash());

		$params['appDetails'] = base64_encode(serialize($data));
		
		list($rawResponseData, , , $curlInfo)  = doCall($registerURL, $params, $timeout=60, array('normalPost' => 1));
		if($curlInfo['info']['http_code'] != 200 || !empty($curlInfo['errorNo'])){
			
		}
		return json_decode($rawResponseData);
	}
	
	public static function runOffBrowserLoad($params){
		
		if(Reg::get('settings.executeUsingBrowser') != 1){//using fsock
			callURLAsync(APP_URL.EXECUTE_FILE, array('runOffBrowserLoad' => 'true'));	
		}
		elseif(Reg::get('settings.executeUsingBrowser') == 1){
			Reg::set('currentRequest.runOffBrowserLoad', 'true');
		}
	}
	
	//public static function getLoadSiteURL(){
//		return Reg::get('currentRequest.loadSiteURL');
//	}
}

?>