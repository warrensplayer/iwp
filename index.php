<?php

/************************************************************
 * InfiniteWP Admin panel									*
 * Copyright (c) 2012 Revmakx								*
 * www.revmakx.com											*
 *															*
 ************************************************************/

include("includes/app.php");
onBrowserLoad();
initMenus();
?>
<?php
$mainJson = json_encode(panelRequestManager::getSitesUpdates());
$toolTipData = json_encode(panelRequestManager::getUserHelp());
$favourites =  json_encode(panelRequestManager::getFavourites());
$sitesData = json_encode(panelRequestManager::getSites());
$sitesListData = json_encode(panelRequestManager::getSitesList());
$groupData = json_encode(panelRequestManager::getGroupsSites());
$updateAvailable = json_encode(checkUpdate(false, false));
$updateAvailableNotify = json_encode(panelRequestManager::isUpdateHideNotify());
$totalSettings =  json_encode(array("data"=>panelRequestManager::requiredData(array("getSettingsAll"=>1))));
$fixedNotifications = json_encode(getNotifications(true));
$min = '.min';
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="robots" content="noindex">
<title>InfiniteWP</title>
<link href='https://fonts.googleapis.com/css?family=Droid+Sans:400,700' rel='stylesheet' type='text/css'>
<link rel="stylesheet" href="css/select2.css?<?php echo APP_VERSION; ?>" type="text/css" />
<link rel="stylesheet" href="css/core<?php echo $min; ?>.css?<?php echo APP_VERSION; ?>" type="text/css" />
<link rel="stylesheet" href="css/datepicker.css?<?php echo APP_VERSION; ?>" type="text/css" />
<link rel="stylesheet" href="css/nanoscroller.css?<?php echo APP_VERSION; ?>" type="text/css" />
<link rel="stylesheet" href="css/jPaginator.css?<?php echo APP_VERSION; ?>" type="text/css" media="screen"/>
<link rel="stylesheet" href="css/jquery-ui.min.css?<?php echo APP_VERSION; ?>" type="text/css" media="all" />
<link rel="stylesheet" href="css/jquery.qtip.css?<?php echo APP_VERSION; ?>" type="text/css" media="all" />
<link rel="stylesheet" href="css/custom_checkbox.css?<?php echo APP_VERSION; ?>" type="text/css" media="all" />
<link rel="shortcut icon" href="images/favicon.png" type="image/x-icon"/>
<!--[if lt IE 9]>
	<link rel="stylesheet" type="text/css" href="css/ie8nlr.css?<?php echo APP_VERSION; ?>" />
<![endif]-->
<script src="js/jquery.min.js?<?php echo APP_VERSION; ?>" type="text/javascript" charset="utf-8"></script>
<script src="js/jquery-ui.min.js?<?php echo APP_VERSION; ?>" type="text/javascript"></script>
<script src="js/select2.min.js?<?php echo APP_VERSION; ?>" type="text/javascript"></script>
<script src="js/fileuploader.js?<?php echo APP_VERSION; ?>" type="text/javascript"></script>
<script src="js/apps<?php echo $min; ?>.js?<?php echo APP_VERSION; ?>" type="text/javascript" charset="utf-8"></script>
<script src="js/load<?php echo $min; ?>.js?<?php echo APP_VERSION; ?>" type="text/javascript" charset="utf-8"></script>
<script src="js/jPaginator-min.js?<?php echo APP_VERSION; ?>" type="text/javascript"></script>
<script src="js/jquery.qtip.js?<?php echo APP_VERSION; ?>" type="text/javascript"></script>
<script src="js/jquery.mousewheel.js?<?php echo APP_VERSION; ?>" type="text/javascript"></script>
<script>
var systemURL = "<?php echo APP_URL;?>";
var serviceURL = "<?php echo getOption('serviceURL');?>";
var appVersion = "<?php echo APP_VERSION; ?>";
var appInstallHash = "<?php echo APP_INSTALL_HASH; ?>";
var mainJson = <?php echo $mainJson?>;
var sitesjson = mainJson.siteView;
var pluginsjson = mainJson.pluginsView.plugins;
var themesjson = mainJson.themesView.themes;
var wpjson = mainJson.coreView.core;
var toolTipData = <?php echo $toolTipData;?>;
var favourites = <?php echo $favourites; ?>;
var site = <?php echo  $sitesData;?>;
var sitesList = <?php echo  $sitesListData;?>;
var group = <?php echo  $groupData;?>;
var totalSites = getPropertyCount(site);
var totalGroups = getPropertyCount(group);
var totalUpdates =  getPropertyCount(mainJson);
var updateAvailable   = <?php echo $updateAvailable;?>;
var updateAvailableNotify=<?php echo $updateAvailableNotify;?>;
var fixedNotifications = <?php echo $fixedNotifications;?>;
var settingsData = <?php echo $totalSettings; ?>;
<?php echo getAddonHeadJS(); ?>
<?php if(!empty($_REQUEST['page'])) {?>
reloadStatsControl=0;
<?php } ?>

</script>
<script type="text/javascript" src="js/init<?php echo $min; ?>.js?<?php echo APP_VERSION; ?>" charset="utf-8"></script>
<script type="text/javascript" src="js/jquery.nanoscroller.min.js?<?php echo APP_VERSION; ?>"></script>
<script type="text/javascript" src="js/datepicker.js?<?php echo APP_VERSION; ?>"></script>
<script type="text/javascript" src="js/eye.js?<?php echo APP_VERSION; ?>"></script>
<script type="text/javascript" src="js/utils.js?<?php echo APP_VERSION; ?>"></script>
<script type="text/javascript" src="js/layout.js?<?php echo APP_VERSION; ?>"></script>
<!-- addon ext src starts here -->
<?php echo getAddonsHTMLHead(); ?>
<?php if(!empty($_REQUEST['page']))
{ ?>
<script>
$(function () { 
reloadStatsControl=0;
<?php if($_REQUEST['page']=="addons") ?>
$("#iwpAddonsBtn").click();
processPage("<?php echo $_REQUEST['page'];?>");

});
</script>
<?php } ?>
</head>
<body>
<div class="notification_cont"></div>
<div id="fb-root"></div>
<div id="updateOverLay" style='display:none;-ms-filter: "progid:DXImageTransform.Microsoft.Alpha(Opacity=70)"; background-color:#000; opacity:0.7;position:fixed;top: 0;left: 0;width: 100%;height: 100%; z-index:1020'></div>
<div id="loadingDiv" style="display:none">Loading...</div>
<div id="modalDiv"></div>

<!--<div class="overlay_test"></div>-->
<div id="dynamic_resize">
<div id="site_cont">
  <div id="header_bar"> <a href="<?php echo APP_URL; ?>" style="text-decoration:none;">
    <div id="logo"></div></a>
    <div id="admin_panel_label">Admin Panel v<?php echo APP_VERSION; ?></div>
    
    <a class="float-left fb_icon_hdr" href="https://www.facebook.com/infinitewp" target="_blank"></a><a class="float-left twitter_icon_hdr" href="https://twitter.com/infinitewp" target="_blank"></a>
    <ul id="header_nav">
      <!--<li><a href="">Suggest an Idea</a></li>-->
      <li><a class="updates_centre first-level" id="updateCentreBtn">IWP Update Centre<span id="updateCenterCount" style="display:none" class="count">1</span></a>
      	
        <div id="updates_centre_cont" style="display:none">
                   
          <div class="th rep_sprite" style="border-top: 1px solid #C1C4C6; height: 38px; border-bottom: 0;">
            <div class="btn_action float-right"><a class="rep_sprite updateActionBtn">Check Now</a></div>
            
          </div>
        </div>
      </li>
      <li><a class="first-level" id="iwpAddonsBtn">Addons<?php if(($addonUpdate = getAddonUpdateCount()) > 0){ ?><span class="count"><?php echo $addonUpdate; ?></span><?php } ?></a></li>
       <li><a class="first-level" href="http://www.google.com/moderator/#16/e=1f9ff1" target="_blank">Got an idea?</a></li>
      <li class="help"><a class="first-level">Help <span style="font-size:7px;">▼</span></a>
      	<ul class="sub_menu_ul">
        	<li><a href="http://infinitewp.com/forum/" target="_blank">Discussion Forum</a></li>
            <li><a href="javascript:loadReport('',1)">Report Issue</a></li>
            <li><a class="takeTour">Take the tour</a></li>
        </ul>
      </li>
      <li><a href="login.php?logout=now" class="logout">Logout</a></li>
      <li class="settings" id="mainSettings">
        <div id="settings_btn"></div>
        <div id="settings_cont" style="display:none">
          <div class="th rep_sprite">
            <ul class="btn_radio_slelect mainSettingsTab float-right">
            	
              <li><a class="active rep_sprite optionSelect settingsButtons" item='appSettingsTab'>App Settings</a></li>
              <li><a class="rep_sprite optionSelect settingsButtons"  item='settingsTab'>Account Settings</a></li>
            </ul>
          </div>
          <div class="form_cont settings settingsItem" id="settingsTab" style="border:0; display:none; padding:0;">
            <div style="padding:10px;">
              <div class="tr no_border">
                <div class="tl">EMAIL</div>
                <div class="td">
                  <div class="valid_cont">
                    <input name="" type="text" id="email"  class="hidedit rep_sprite_backup triggerSettingsButton emailEdit" value="samplemail@domain.com">
                    <div class="valid_error" style="top: 16px; height: 14px; right: 37px;">
                      <div class="padding"></div>
                    </div>
                  </div>
                  <div class="rep_sprite_backup edit editEmail"></div>
                </div>
                <div class="clear-both"></div>
              </div>
              <div class="tr no_border">
                <div class="tl"></div>
                <div class="td"> <a id="change_pass_btn">Change Password</a>
                  <div class="change_pass_cont" id="changePassContent" style="display:none">
                    <div class="clear-both"></div>
                    <div class="valid_cont">
                      <input name="" type="text" class="triggerSettingsButton passwords" id="currentPassword" value="Current Password" onfocus="if(this.value=='Current Password'){this.value=''; this.style.color='#676C70';}  else { this.style.color='#676C70'; this.select(); };" onblur="if(this.value==''){ this.value='Current Password'; this.style.color='#ccc'; }" style="color:#ccc;"  />
                      <div class="valid_error">
                        <div class="padding"></div>
                      </div>
                    </div>
                    <div class="valid_cont">
                      <input name="" type="text"  id="newPassword" class="triggerSettingsButton passwords" value="New Password" onfocus="if(this.value=='New Password'){this.value=''; this.style.color='#676C70';}  else { this.style.color='#676C70'; this.select(); };" onblur="if(this.value==''){ this.value='New Password'; this.style.color='#ccc'; }"  style="color:#ccc;"    />
                      <div class="valid_error">
                        <div class="padding"></div>
                      </div>
                    </div>
                    <div class="valid_cont">
                      <input name="" type="text"  id="newPasswordCheck"  class="triggerSettingsButton passwords" value="New Password Again" onfocus="if(this.value=='New Password Again'){this.value=''; this.style.color='#676C70';}  else { this.style.color='#676C70'; this.select(); };" onblur="if(this.value==''){ this.value='New Password Again'; this.style.color='#ccc'; }"  style="color:#ccc;"    />
                      <div class="valid_error">
                        <div class="padding"></div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="clear-both"></div>
              </div>
            </div>
            <div class="tr">
              <div class="padding">
                <div class="label" style="margin-bottom: 10px;">EMAIL NOTIFICATIONS<a class="test_mail rep_sprite_backup" id="sendTestEmail">Send test email</a></div>
                <div class="tl no_text_transform">Notify about <span>updates to</span></div>
                <div class="td">
                  <ul class="checkbox_cont">
                    <li><a class="checkbox generalSelect" id="notifyPlugins">Plugins</a></li>
                    <li><a class="checkbox generalSelect" id="notifyThemes">Themes</a></li>
                    <li><a class="checkbox generalSelect" id="notifyWordpress">WordPress</a></li>
                  </ul>
                </div>
                <div class="clear-both"></div>
                <div class="tl no_text_transform">Email me every</div>
                <div class="td">
                  <ul class="btn_radio_slelect float-left" style="margin-left:10px;">
                    <li><a class="rep_sprite optionSelect emailFrequency" id="emailDaily" def="daily">Day</a></li>
                    <li><a class="rep_sprite optionSelect emailFrequency" id="emailWeekly" def="weekly">Week</a></li>
                    <li><a class="rep_sprite  optionSelect emailFrequency" id="emailNever" def="never">Never</a></li>
                  </ul>
                </div>
                <div class="clear-both"></div>
                <div class="tl no_text_transform" style="width:475px;"><div class="rep_sprite_backup info_icon">You have to set a cron job for this to work. (suggested timing: every 20 min)</div><div class="clear-both"></div><div style="text-align:left; line-height: 20px;"><span class="droid700" style="white-space: pre; word-wrap: break-word; width: 480px; display: block;"><input type="text" class="selectOnText" style="width:466px;" readonly="true" value="<?php echo APP_PHP_CRON_CMD; ?><?php echo APP_ROOT; ?>/cron.php &gt;/dev/null 2&gt;&1" /></span></div></div>
                <div class="clear-both"></div>
              </div>
            </div>
          </div>
          <div class="app_settings settingsItem" id="appSettingsTab" >
            <div class="tr">
              <div class="padding ip">
                <div class="left" id="IPContent">
                  <div class="label">ALLOW ACCOUNT ACCESS FROM THESE IP<span>s</span> ONLY</div>
                </div>
                <div class="right"> Your current IP is <span class="droid700"><?php echo $_SERVER['REMOTE_ADDR']; ?></span>
                  <input name="" type="text" class="add_ip float-left" value="xxx.xxx.xxx.xxx"   id="tempIP" onfocus="if(this.value=='xxx.xxx.xxx.xxx'){this.value=''; this.style.color='#676C70';}  else { this.style.color='#676C70'; this.select(); };" onblur="if(this.value==''){ this.value='xxx.xxx.xxx.xxx'; this.style.color='#ccc'; }" style="color:#ccc;"  >
                  <div class="btn_add_ip rep_sprite float-left user_select_no" id="addIP">Add IP</div>
                </div>
                <div class="clear-both"></div>
              </div>
            </div>
            <div class="tr">
              <div class="padding">
                <div class="label">MAX SIMULTANEOUS READ / WRITE REQUESTS PER IP</div>
                <div class="slider_cont">
                  <input type="text" id="amount01" class="value_display" onfocus="this.blur();" />
                  <div class="slider_stroke">
                    <div id="slider-range01">
                      <div class="slider01_calib_cont">
                        <div class="calib">30</div>
                        <div class="calib">20</div>
                        <div class="calib" style="width: 140px;">10</div>
                        <div class="calib" style="width: 123px;">1</div>
                      </div>
                    </div>
                  </div>
                  <div class="clear-both"></div>
                </div>
              </div>
            </div>
            <div class="tr">
              <div class="padding">
                <div class="label">MAX SIMULTANEOUS REQUESTS FROM THIS SERVER <span>(IN WHICH IWP IS INSTALLED)</span></div>
                <div class="slider_cont">
                  <input type="text" id="amount02" class="value_display" onfocus="this.blur();" />
                  <div class="slider_stroke slider02">
                    <div id="slider-range02">
                      <div class="slider02_calib_cont">
                        <div class="calib">100</div>
                        <div class="calib" style="width: 193px; margin-left: 19px;">50</div>
                        <div class="calib" style="width: 175px;">1</div>
                      </div>
                    </div>
                  </div>
                  <div class="clear-both"></div>
                </div>
              </div>
            </div>
            <div class="tr">
              <div class="padding">
                <div class="label">TIME DELAY BETWEEN REQUESTS TO WEBSITES ON THE SAME IP <span>(milli-seconds)</span></div>
                <div class="slider_cont">
                  <input type="text" id="amount03" class="value_display" onfocus="this.blur();" />
                  <div class="slider_stroke slider02">
                    <div id="slider-range03">
                      <div class="slider03_calib_cont">
                        <div class="calib">1000</div>
                        <div class="calib">500</div>
                        <div class="calib" style="width: 196px;">0</div>
                      </div>
                    </div>
                  </div>
                  <div class="clear-both"></div>
                </div>
              </div>
            </div>
            <div class="tr">
              <div class="padding">
                <div class="label">HTTP AUTHENTICATION / FOLDER PROTECTION</div>
                <table border="0" class="http_auth">
  <tr>
    <td align="left"><input name="" id="authUsername" type="text" helptxt="username" class="txtHelp" value="username" /></td>
    <td align="right"><input name="" id="authPassword" type="password"  helptxt="password" class="txtHelp" value="password" /></td>
  </tr>
</table>

              </div>
            </div>
             <div class="tr">
              <div class="checkbox float-left active" id="autoSelectConnectionMethod">Automatically choose the best connection method</div><div class="checkbox float-right disabled" id="executeUsingBrowser" style="width: 134px; border-left: 1px solid #E0E0E0;">Do not use fsock</div><div class="clear-both"></div>
            </div>
             <div class="tr">
              <div class="checkbox" id="enableReloadDataPageLoad">Reload data on page load.</div>
            </div>
            <div class="tr">
              <div class="checkbox active" id="sendAnonymous">Send anonymous usage information to improve IWP.</div>
            </div>
              <div class="tr">
              <div class="checkbox active" id="ipRangeSame">Consider that the first 3 octets of IPs are from the same server (xxx.xxx.xxx.*)</div>
            </div>
          </div>
          
          <div class="th_sub rep_sprite" style="border-top:1px solid #c1c4c6;">
            <div class="success rep_sprite_backup float-left" id="saveSuccess" style="display:none">Saved successfully!</div>
            <div class="btn_action float-right"><a class="rep_sprite" id="saveSettingsBtn" page="appSettingsTab">Save Changes</a></div>
           
        </div>
        </div>
      </li>
    </ul>
    <div class="clear-both"></div>
  </div>
  <div id="main_cont">
    
    <ul class="site_nav">
    	<?php printMenus(); ?>

        <!--<li class="l1"><a>Manage</a>
        <ul class="l2">
            <li class="l2" page="updates"><a><span class="float-left">Updates</span><span class="update_count float-left droid700" id="totalUpdateCount">0</span></a></li>
            <li class="l2" page="items"><a>Plugins &amp; Themes</a></li>
            <li class="l2"><a>Comments</a></li>
            <li class="l2"><a>Users</a></li>
            <li class="l2"><a>Posts, Pages &amp; Links</a></li>
          </ul>
      </li>
        <li class="l1"><a>Protect</a>
        <ul class="l2">
            <li class="l2" page="backups"><a>Backup</a></li>
            <li class="l2"><a>Malware scanning</a></li>
          </ul>
      </li>
        <li class="l1"><a>Monitor</a>
        <ul class="l2">
            <li class="l2"><a>Uptime Monitor</a></li>
            <li class="l2"><a>Google Analytics</a></li>
          </ul>
      </li>
        <li class="l1"><a>Maintain</a>
        <ul class="l2">
            <li class="l2"><a>WP Maintenance</a></li>
          </ul>
      </li>
        <li class="l1"><a>Tools</a>
        <ul class="l2">
            <li class="l2"><a>Install / Clone WP</a></li>
            <li class="l2"><a>Code Snippets</a></li>
            <li class="l2"><a>Client Reporting</a></li>
          </ul>
      </li>-->
      </ul>
      
      
    
    <div class="btn_reload rep_sprite float-right"><a class="rep_sprite_backup user_select_no" id="reloadStats">Reload Data</a></div>
	<div class="checkbox user_select_no" style="float:right; width:70px; cursor:pointer;" id="clearPluginCache">Clear cache</div>
    <div id="lastReloadTime"></div>
    <ul class="site_nav single_nav float-left"><li class="l1 navLinks" page="history"><a>Activity Log</a></li></ul>
    <div class="clear-both"></div>
    <hr class="dotted" />
    <div id="pageContent">
      <div class="empty_data_set welcome">
        <div class="line1">Hey there. Welcome to InfiniteWP.</div>
        <div class="line2">Lets now manage WordPress, the IWP way!</div>
        <div class="line3">
          <div class="welcome_arrow"></div>
          Add a WordPress site to IWP.<br />
          <span style="font-size:12px">(Before adding the website please install and activate InfiniteWP Client Plugin in your WordPress site)</span> </div>
        <a href="http://www.youtube.com/watch?v=q94w5Vlpwog" target="_blank">See How</a>. </div>
    </div>
  </div>
</div>
</div>
<div id="bottom_toolbar" class="siteSearch">
  <div id="activityPopup"> </div>
</div>
<script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src="//platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","twitter-wjs");

  (function() {
    var po = document.createElement('script'); po.type = 'text/javascript'; po.async = true;
    po.src = 'https://apis.google.com/js/plusone.js';
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(po, s);
 
  })();

</script>
</body>
</html>