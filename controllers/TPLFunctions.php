<?php
/************************************************************
 * InfiniteWP Admin panel									*
 * Copyright (c) 2012 Revmakx								*
 * www.revmakx.com											*
 *															*
 ************************************************************/

function TPLPrepareHistoryBriefTitle($actionHistory){
	$str = '';
	$count = 0;

	$itemSearchArray = array('<#detailedAction#>', '<#detailedActionCount#>', '<#type#>', '<#typePlural#>', '<#sitesCount#>', '<#sitesCountPlural#>', '<#action#>', '<#uniqueName#>');
	$template = array();
	$template[''][''][''] 		= "<#detailedAction#> <#detailedActionCount#> <#type#><#typePlural#> in <#sitesCount#> site<#sitesCountPlural#>";
	
	$template['PTC']['']['core'] = "<#action#> WordPress in <#sitesCount#> site<#sitesCountPlural#>";
    
	$template['PTC'][''][''] 	= "<#action#> <#detailedActionCount#> <#detailedAction#><#typePlural#> in <#sitesCount#> site<#sitesCountPlural#>";
	
	$template['site'][''][''] 	= "<#detailedAction#> <#detailedActionCount#> <#type#><#typePlural#>";
	$template['site']['load'][''] 	= "logged in as admin";
	
	$template['themes']['']['get'] = $template['plugins']['']['get'] = "load <#type#>s from <#sitesCount#> site<#sitesCountPlural#>";

	$template['stats']['']['get'] = "reload data from <#sitesCount#> site<#sitesCountPlural#>";
	
	$template['themes']['']['install'] = $template['plugins']['']['install'] = $template[''][''][''];
	
	$template['']['']['backup']	= "<#detailedAction#> <#sitesCount#> site<#sitesCountPlural#>";	
	
	$template['clientPlugin'][''][''] = "<#detailedAction#> IWP Client Plugin<#typePlural#> in <#sitesCount#> site<#sitesCountPlural#>";
	
	setHook('taskTitleTemplate', $template);
	
	$templateModifiedType = $actionHistory['type'];
	if(in_array($actionHistory['type'], array('plugins', 'themes', 'stats'))){
		$templateModifiedType = substr($actionHistory['type'], 0, -1);
	}
	
	foreach($actionHistory['detailedActions'] as $detailedAction => $detailedActionStat){		
		
		$itemReplaceArray = array($detailedAction, $detailedActionStat['detailedActionCount'], $templateModifiedType, ($detailedActionStat['detailedActionCount'] > 1 ? 's' : ''), $detailedActionStat['sitesCount'], ($detailedActionStat['sitesCount'] > 1 ? 's' : ''), $actionHistory['action'], $detailedActionStat['uniqueName']);
		
		if($count){ $str .= ', '; }
		
		for($i=7;$i>=0;$i--){
			
			$bin = decbin($i);
			$bin = str_pad ( $bin , 3,  '0', STR_PAD_LEFT);
			$templateType = !empty($bin{0}) ? $actionHistory['type'] : '';
			$templateAction = !empty($bin{1}) ? $actionHistory['action'] : '';
			$templateDetailedAction = !empty($bin{2}) ? $detailedAction : '';
			if(isset($template[$templateType][$templateAction][$templateDetailedAction])){
				$templateString = $template[$templateType][$templateAction][$templateDetailedAction];
				break;
			}
		}
		
		$str .= ucfirst(str_replace($itemSearchArray, $itemReplaceArray, $templateString));

		$count++;
	}
	return $str;
}

function TPLAddErrorHelp($actionData){
	
	//only error will come
	if(stripos($actionData['errorMsg'], 'please add FTP details') !== false){
		$actionData['errorMsg'] .= ' <a href="http://infinitewp.com/knowledge-base/adding-ftp-details-for-auto-update/?utm_source=application&utm_medium=userapp&utm_campaign=kb" target="_blank">How?</a>';
	}	
	elseif($actionData['error'] == 'fsock_error'){		
		$actionData['errorMsg'] .= ' Kindly contact your host.';
	}	
	elseif($actionData['error'] == 'timeoutClear'){
		if(Reg::get('settings.executeUsingBrowser') != 1){
			
			if(empty($_SESSION['fsockSameURLConnectCheckCache'])){
				$_SESSION['fsockSameURLConnectCheckCache'] = fsockSameURLConnectCheck(APP_URL.'execute.php');
			 }
			
			if(empty($_SESSION['fsockSameURLConnectCheckCache']['status'])){
				$actionData['errorMsg'] .= ' Fsock Error: '.$_SESSION['fsockSameURLConnectCheckCache']['error'];
				if($_SESSION['fsockSameURLConnectCheckCache']['errorNo'] != 'authentication_required'){
					$actionData['errorMsg'] .= ' Kindly contact your host.';
				}
			}
		}
	}
	elseif($actionData['error'] == 'unknown'){//for update
		
		if($actionData['detailedAction'] == 'plugin' || $actionData['detailedAction'] == 'theme'){ //for update
			$actionData['errorMsg'] .= ' Please <a onclick="$(\'#clearPluginCache\').addClass(\'active\');$(\'#reloadStats\').click();">Clear cache and Reload Data</a> and try again. <a href="http://infinitewp.com/knowledge-base/unknown-error-occurred-during-update-process?utm_source=application&utm_medium=userapp&utm_campaign=kb" target="_blank">See recommendations</a>.';
		}
	}
	elseif(($actionData['type'] == 'backup' && $actionData['action'] == 'now') || ($actionData['type'] == 'scheduleBackup' && $actionData['action'] == 'runTask')){
		$actionData['errorMsg'] = rtrim($actionData['errorMsg'], '. ').'.';//'. ' => this will remove "." and space " "
		$actionData['errorMsg'] .= ' Please try fail-safe options. <a href="http://infinitewp.com/knowledge-base/backup-fails-timesout/?utm_source=application&utm_medium=userapp&utm_campaign=kb" target="_blank">See how it works</a>.';
	}
	return $actionData['errorMsg'];
}

?>