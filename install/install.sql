-- /************************************************************
--  * InfiniteWP Admin panel									*
--  * Copyright (c) 2012 Revmakx								*
--  * www.revmakx.com											*
--  *															*
--  ************************************************************/

-- iwp_admin_panel Database SQL
-- DB Version 0.1.15

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;


--
-- Table structure for table `iwp_addons`
--

CREATE TABLE IF NOT EXISTS `iwp_addons` (
  `slug` varchar(64) NOT NULL,
  `status` enum('active','inactive') NOT NULL,
  `addon` varchar(255) DEFAULT NULL,
  `validityExpires` int(10) unsigned DEFAULT NULL,
  `initialVersion` varchar(20) DEFAULT NULL,
  UNIQUE KEY `slug` (`slug`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Table structure for table `iwp_allowed_login_ips`
--

CREATE TABLE IF NOT EXISTS `iwp_allowed_login_ips` (
  `IP` varchar(45) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `iwp_favourites`
--

CREATE TABLE IF NOT EXISTS `iwp_favourites` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('plugins','themes') NOT NULL,
  `name` varchar(250) NOT NULL,
  `URL` varchar(255) NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `iwp_groups`
--

CREATE TABLE IF NOT EXISTS `iwp_groups` (
  `groupID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`groupID`),
  UNIQUE KEY `name_UNIQUE` (`name`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `iwp_groups_sites`
--

CREATE TABLE IF NOT EXISTS `iwp_groups_sites` (
  `groupID` int(10) unsigned DEFAULT NULL,
  `siteID` int(10) unsigned DEFAULT NULL,
  UNIQUE KEY `index1` (`groupID`,`siteID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `iwp_hide_list`
--

CREATE TABLE IF NOT EXISTS `iwp_hide_list` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('plugins','themes','core') DEFAULT NULL,
  `siteID` int(10) unsigned DEFAULT NULL,
  `name` varchar(45) DEFAULT NULL,
  `URL` text,
  PRIMARY KEY (`ID`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `iwp_history`
--

CREATE TABLE IF NOT EXISTS `iwp_history` (
  `historyID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `siteID` int(10) unsigned NOT NULL,
  `actionID` varchar(45) NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `action` varchar(50) DEFAULT NULL,
  `status` enum('writingRequest','pending','initiated','running','completed','scheduled','netError','error','processingResponse') DEFAULT NULL,
  `error` varchar(256) DEFAULT NULL,
  `userID` int(10) unsigned NOT NULL,
  `URL` varchar(255) DEFAULT NULL,
  `timeout` int(10) unsigned NOT NULL,
  `microtimeAdded` double(14,4) NOT NULL,
  `microtimeInitiated` double(14,4) DEFAULT NULL,
  `microtimeStarted` double(14,4) DEFAULT NULL,
  `microtimeEnded` double(14,4) DEFAULT NULL,
  `timeScheduled` int(10) unsigned DEFAULT NULL,
  `events` smallint(5) unsigned NOT NULL DEFAULT '1',
  `param1` text,
  `param2` text,
  `param3` text,
  `showUser` enum('Y','N') DEFAULT 'Y',
  `retried` smallint(6) DEFAULT '0',
  `runCondition` text,
  `callOpt` text,
  `isPluginResponse` ENUM('1', '0') NOT NULL DEFAULT  '1',
  PRIMARY KEY (`historyID`),
  KEY `actionID` (`actionID`),
  KEY `microtimeInitiated` (`microtimeInitiated`),
  KEY `status` (`status`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `iwp_history_additional_data`
--

CREATE TABLE IF NOT EXISTS `iwp_history_additional_data` (
  `historyID` bigint(20) unsigned NOT NULL,
  `detailedAction` varchar(50) DEFAULT NULL,
  `uniqueName` varchar(255) NOT NULL,
  `resultID` int(10) unsigned DEFAULT NULL,
  `status` enum('pending','success','error','netError') NOT NULL DEFAULT 'pending',
  `error` varchar(255) DEFAULT NULL,
  `errorMsg` text,
  UNIQUE KEY `historyID_uniqueName` (`historyID`,`uniqueName`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `iwp_history_raw_details`
--

CREATE TABLE IF NOT EXISTS `iwp_history_raw_details` (
  `historyID` bigint(20) unsigned NOT NULL,
  `request` longtext,
  `response` longtext,
  `callInfo` longtext,
  `panelRequest` longtext,
  PRIMARY KEY (`historyID`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `iwp_options`
--

CREATE TABLE IF NOT EXISTS `iwp_options` (
  `optionID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `optionName` varchar(255) NOT NULL,
  `optionValue` longtext,
  PRIMARY KEY (`optionID`),
  UNIQUE KEY `optionName` (`optionName`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `iwp_settings`
--

CREATE TABLE IF NOT EXISTS `iwp_settings` (
  `notifications` text,
  `general` text,
  `timeUpdated` int(10) unsigned DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `iwp_sites`
--

CREATE TABLE IF NOT EXISTS `iwp_sites` (
  `siteID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `URL` varchar(250) DEFAULT NULL,
  `adminURL` varchar(250) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `IP` varchar(45) DEFAULT NULL,
  `WPVersion` varchar(10) NOT NULL,
  `pluginVersion` varchar(10) DEFAULT NULL,
  `adminUsername` varchar(45) DEFAULT NULL,
  `isOpenSSLActive` enum('1','0') NOT NULL DEFAULT '1',
  `randomSignature` varchar(40) DEFAULT NULL,
  `privateKey` text NOT NULL,
  `serverGroup` varchar(50) DEFAULT NULL,
  `network` tinyint(3) unsigned DEFAULT NULL,
  `multisiteID` smallint(6) DEFAULT NULL,
  `parent` tinyint(3) unsigned DEFAULT NULL,
  `httpAuth` varchar(255) DEFAULT NULL,
  `callOpt` text,
  `connectURL` enum('default','adminURL','siteURL') NOT NULL DEFAULT 'default',
  PRIMARY KEY (`siteID`),
  UNIQUE KEY `siteURL_UNIQUE` (`URL`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `iwp_site_stats`
--

CREATE TABLE IF NOT EXISTS `iwp_site_stats` (
  `siteID` int(10) unsigned NOT NULL,
  `lastUpdatedTime` int(10) unsigned DEFAULT NULL,
  `stats` longtext,
  PRIMARY KEY (`siteID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `iwp_temp_storage`
--

CREATE TABLE IF NOT EXISTS `iwp_temp_storage` (
  `ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(50) NOT NULL,
  `paramID` varchar(50) NOT NULL,
  `time` int(10) unsigned NOT NULL,
  `data` longtext NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `type` (`type`,`paramID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `iwp_users`
--

CREATE TABLE IF NOT EXISTS `iwp_users` (
  `userID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(45) DEFAULT NULL,
  `name` varchar(45) DEFAULT NULL,
  `password` varchar(40) DEFAULT NULL,
  `accessLevel` enum('admin','subUser') DEFAULT NULL,
  `help` text,
  PRIMARY KEY (`userID`),
  UNIQUE KEY `email_UNIQUE` (`email`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `iwp_user_access`
--

CREATE TABLE IF NOT EXISTS `iwp_user_access` (
  `userID` int(10) unsigned DEFAULT NULL,
  `siteID` int(10) unsigned DEFAULT NULL,
  UNIQUE KEY `index1` (`userID`,`siteID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Dumping data for table `iwp_settings`
--

INSERT INTO `iwp_settings` (`notifications`, `general`, `timeUpdated`) VALUES
('a:1:{s:23:"updatesNotificationMail";a:4:{s:9:"frequency";s:6:"weekly";s:11:"coreUpdates";s:1:"1";s:13:"pluginUpdates";s:1:"1";s:12:"themeUpdates";s:1:"1";}}', 'a:7:{s:31:"MAX_SIMULTANEOUS_REQUEST_PER_IP";s:1:"2";s:24:"MAX_SIMULTANEOUS_REQUEST";s:1:"3";s:33:"TIME_DELAY_BETWEEN_REQUEST_PER_IP";s:3:"200";s:13:"sendAnonymous";s:1:"1";s:24:"enableReloadDataPageLoad";s:1:"1";s:26:"autoSelectConnectionMethod";s:1:"1";s:32:"CONSIDER_3PART_IP_ON_SAME_SERVER";s:1:"1";}', 0);

-- --------------------------------------------------------

--
-- Dumping data for table `iwp_options`
--

INSERT INTO `iwp_options` (`optionID`, `optionName`, `optionValue`) VALUES
(1, 'installedTime', NULL),
(2, 'anonymousDataNextSchedule', NULL),
(3, 'serviceURL', 'http://service.infinitewp.com/'),
(4, 'anonymousDataLastSent', NULL),
(5, 'updateLastCheck', NULL),
(6, 'updateAvailable', NULL),
(7, 'updatesNotificationMailLastSent', NULL),
(8, 'cronLastRun', NULL),
(9, 'updateHideNotify', NULL),
(10, 'updateNotifySentToJS', NULL),
(11, 'updateNotificationDynamicContent', NULL),
(12, 'offlineNotifications', NULL),
(13, 'appRegisteredUser', NULL),
(14, 'updateAddonsAvailable', NULL),
(15, 'newAddonsAvailable', NULL),
(16, 'promoAddons', NULL);
