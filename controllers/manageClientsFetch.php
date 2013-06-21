<?php
/************************************************************
 * InfiniteWP Admin panel									*
 * Copyright (c) 2012 Revmakx								*
 * www.revmakx.com											*
 *															*
 ************************************************************/

class manageClientsFetch {
	
	public static function getStatsProcessor($siteIDs, $params, $extras) // Get the complete update Data
	{
		$type = 'stats';
		$action = 'getStats';
		$requestAction = 'get_stats';
		$requestParams =  array(
						   'refresh' => 'transient',
						   'force_refresh' => ($params['forceRefresh'] == 1) ? '1' : '0',
						   'item_filter' => array
							   (
								   'get_stats' => array
									   (
										   '0' => array
											   (
												   '0' => 'updates',
												   '1' => array
													   (
														   'plugins' => '1',
														   'themes' => '1',
														   'premium' => '1'
													   )
											   ),
										   '1' => array
											   (
												   '0' => 'core_update',
												   '1' => array
													   (
														   'core' => '1'
													   )
											   ),
										   '2' => array
											   (
												   '0' => 'backups'
											   ),
										   '3' => array
											   (
												   '0' => 'errors',
												   '1' => array
													   (
														   'days' => '1',
														   'get' =>''
													   )
											   ),
											   											
											//'4' => array
//										   (
//											   '0' => 'comments',
//											   '1' => array
//												   (
//													   'numberposts' => ''
//												   )
//										   )
									   )
							   ),
					   );
		
		setHook('getStatsRequestParams', $requestParams);
				   
		if(!empty($siteIDs)){
			$sites = getSitesData($siteIDs);
		}
		else{
			$sites = getSitesData();
		}
	
		$historyAdditionalData[] = array('uniqueName' => 'getStats', 'detailedAction' => 'get');
		
		
		$sendAfterAllLoad = isset($extras['sendAfterAllLoad']) ? $extras['sendAfterAllLoad'] : true;
		$exitOnComplete = isset($extras['exitOnComplete']) ? $extras['exitOnComplete'] : false;
		$doNotShowUser = isset($extras['doNotShowUser']) ? $extras['doNotShowUser'] : false;
		$directExecute = isset($extras['directExecute']) ? $extras['directExecute'] : false;
		
		$events = 1;
		if(empty($sites)) return;
		DB::delete("?:site_stats","siteID IN (".implode(',', array_keys($sites)).")");//clearing lastUpdatedTime, stats
		foreach($sites as $siteID => $siteData){
			
			$PRP = array();
			$PRP['requestAction'] 	= $requestAction;
			$PRP['requestParams'] 	= $requestParams;
			$PRP['siteData'] 		= $siteData;
			$PRP['type'] 			= $type;
			$PRP['action'] 			= $action;
			$PRP['events'] 			= $events;
			$PRP['historyAdditionalData'] 	= $historyAdditionalData;
			$PRP['doNotExecute'] 			= false;
			$PRP['directExecute'] 		= $directExecute;
			$PRP['sendAfterAllLoad'] 	= $sendAfterAllLoad;
			$PRP['exitOnComplete'] 		= $exitOnComplete;
			$PRP['doNotShowUser'] 		= $doNotShowUser;
			
			
			prepareRequestAndAddHistory($PRP);
		}	
	}
	
	public static function getStatsResponseProcessor($historyID, $responseData){
		
		responseDirectErrorHandler($historyID, $responseData);
		if(empty($responseData['success'])){
			return false;
		}
		
		$siteID = DB::getField("?:history", "siteID", "historyID=".$historyID);
		
		if(empty($siteID)){
			return false;	
		}
		
		DB::update("?:history_additional_data", array('status' => 'success'), "historyID=".$historyID." AND uniqueName = 'getStats'");
		
		$siteStatsData = array();
		$siteStatsData['siteID'] = $siteID;
		$siteStatsData['stats'] = base64_encode(serialize($responseData['success']));
		$siteStatsData['lastUpdatedTime'] = time();
		DB::replace("?:site_stats", $siteStatsData);
		
		$callOpt = false;
		if($responseData['success']['use_cookie'] == 1){
			$callOpt = DB::getField("?:sites", "callOpt", "siteID = ".$siteID);
			if(!empty($callOpt)){
				$callOpt = unserialize($callOpt);
			}
			else{
				$callOpt = array();
			}			
			$callOpt['useCookie'] = 1;
			$callOpt = serialize($callOpt);
		}
		if( !empty($responseData['success']['wordpress_version']) && !empty($responseData['success']['client_version']) ){
			$updateSiteData = array("WPVersion" => $responseData['success']['wordpress_version'], "pluginVersion" => $responseData['success']['client_version']);
			if(!empty($callOpt)){
				$updateSiteData['callOpt'] = $callOpt;
			}
			DB::update("?:sites", $updateSiteData, "siteID = ".$siteID);
		}	
		
	}

	public static function getPluginsProcessor($siteIDs){
		$type ="plugins";
		return self::getPluginsThemesProcessor($siteIDs,$type);
	}
	public static function getThemesProcessor($siteIDs,$params){
		$type = "themes";
		return self::getPluginsThemesProcessor($siteIDs,$type);
	}
	public static function getPluginsThemesProcessor($siteIDs,$type){
			
		$requestParams = array("items" => array($type),"type" =>'',"search" => '');
		foreach($siteIDs as $siteID){
			self::getPluginsThemesSite($siteID, $requestParams, $type);	
		} 
	}
	public static function getPluginsThemesSite($siteID, $requestParams, $type){
		$action = "get";
		$siteData = getSiteData($siteID);
		$requestAction = "get_plugins_themes";
		
		$historyAdditionalData = array();
		$historyAdditionalData[] = array('detailedAction' => 'get', 'uniqueName' => 'getStats');
		
			$events=1;
			$PRP = array();
			$PRP['requestAction'] 	= $requestAction;
			$PRP['requestParams'] 	= $requestParams;
			$PRP['siteData'] 		= $siteData;
			$PRP['type'] 			= $type;
			$PRP['action'] 			= $action;
			$PRP['events'] 			= $events;
			$PRP['historyAdditionalData'] 	= $historyAdditionalData;
			$PRP['doNotExecute'] 			= false;
			$PRP['sendAfterAllLoad'] = true;
						
		return prepareRequestAndAddHistory($PRP);
		
	}
	
	public static function getPluginsThemesResponseProcessor($historyID, $responseData){
		
		responseDirectErrorHandler($historyID, $responseData);
		if(empty($responseData['success'])){
			return false;
		}
		
		$historyData = DB::getRow("?:history", "type, actionID, siteID", "historyID=".$historyID);
		$type = $historyData['type'];
		$actionID = $historyData['actionID'];
		$siteID = $historyData['siteID'];
		
		$data = array();
		
		if(!empty($responseData['success'][$type])){
			$items = $responseData['success'][$type];
			
			$siteView = array();
			$typeView = array();

			foreach($items as $status => $pluginsThemes){
				foreach($pluginsThemes as $pluginTheme){
					
					$pluginTheme['slug'] = reset(explode('/', $pluginTheme['path']));
					$siteView[$status][ $pluginTheme['path'] ] = $pluginTheme;
					$typeView[ $pluginTheme['path'] ][$status]['_'.$siteID] = $pluginTheme;
				}
			}
			
			$data['siteView']['_'.$siteID] = $siteView;
			$data['typeView'] = $typeView;
			DB::insert("?:temp_storage", array('type' => 'getPluginsThemes', 'paramID' => $actionID, 'time' => time(), 'data' =>  serialize($data)));
			DB::update("?:history_additional_data", array('status' => 'success'), "historyID=".$historyID." AND uniqueName = 'getStats'");
			return;
		}
		DB::update("?:history_additional_data", array('status' => 'error'), "historyID=".$historyID." AND uniqueName = 'getStats'");
	}

}

manageClients::addClass('manageClientsFetch');

?>
