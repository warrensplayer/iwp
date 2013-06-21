<?php 
/************************************************************
 * InfiniteWP Admin panel									*
 * Copyright (c) 2012 Revmakx								*
 * www.revmakx.com											*
 *															*
 ************************************************************/

?>
<?php 
TPL::captureStart('newAddons'); ?>
<div class="result_block shadow_stroke_box purchased_addons">
  <div class="th rep_sprite">
    <div class="title"><span class="droid700">YOUR PURCHASED ADDONS</span></div>
    <div class="btn_reload rep_sprite" style=" width: 103px; float: left; margin: 7px;"><a class="rep_sprite_backup" <?php if(!$d['isAppRegistered']){ ?>register="no" actionvar="register"<?php } ?>  id="checkNowAddons"  style="width:62px;">Check Now</a></div>
    <div class="btn_action float-right <?php if(empty($d['newAddons'])){ ?> disabled<?php } ?>"><a class="rep_sprite" id="installIWPAddons"  actionvar="installAddons">Install Addons</a></div>
    
  </div>
  <div class="rows_cont" style="margin-bottom:-1px;">
  <?php if(!empty($d['newAddons'])){
	  foreach($d['newAddons'] as  $addon){ ?>
	  	<div class="addons_cont"><?php echo $addon['addon']; ?></div>
<?php  }
	 } else{ ?>
	  <div class="addons_empty_cont">You have installed all purchased addons / You have not purchased any new addons. You can purchase addons from the <a href="http://infinitewp.com/addons/" target="_blank">InfiniteWP addon store</a>.</div>
  <?php } ?>
    <div class="clear-both"></div>
  </div>
</div>
<?php TPL::captureStop('newAddons');

 TPL::captureStart('installedAddons'); ?>
<div class="result_block shadow_stroke_box addons">
  <div class="th rep_sprite">
    <div class="title"><span class="droid700">INSTALLED ADDONS</span></div>
    <?php
	if(!empty($d['installedAddons'])){
		$updateBulkAddons = array();
		foreach($d['installedAddons'] as  $addon){
			if(!empty($addon['updateAvailable']) && !$addon['isValidityExpired']){
				$updateBulkAddons[] = $addon['slug'];
			}
		}
	}
	if(!empty($updateBulkAddons)){	
	$updateBulkAddonsString = implode('__IWP__', $updateBulkAddons);
	?>
    <div class="btn_action float-right"><a class="rep_sprite updateIWPAddons" authlink="updateAddons&addons=<?php echo $updateBulkAddonsString ?>">Update All Addons</a></div>
    <?php } ?>
  </div>
  <div class="rows_cont">
  <?php
   reset($d['installedAddons']);
   if(!empty($d['installedAddons'])){

	  foreach($d['installedAddons'] as  $addon){ ?>
      
        <div class="ind_row_cont">
          <div class="row_no_summary">
            <div class="row_checkbox on_off">
              <div class="cc_mask cc_addon_mask" addonSlug="<?php echo $addon['slug']; ?>"><div class="cc_img cc_addon_img <?php echo $addon['status'] == 'active' ? 'on' : 'off'; ?>"></div></div>
            </div>
            <div class="row_name"><?php echo $addon['addon']; ?> <?php echo 'v'.$addon['version']; ?></div>
            <?php if(!empty($addon['updateAvailable'])){ ?>
            <div class="row_action float-right"><a href="<?php echo $addon['updateAvailable']['changeLogLink']; ?>" target="_blank"><?php echo $addon['updateAvailable']['version']; ?></a></div>
            <span style="float: right; padding-top: 10px;"> to </span>
            <div class="row_action float-right"><a authlink="updateAddons&addon=<?php echo $addon['slug']; ?>" addonslug="<?php echo $addon['slug']; ?>" class="updateIWPAddons <?php if($addon['isValidityExpired']){ ?> disabled<?php }?>">Update</a></div>
            <?php } ?>
            <div class="clear-both"></div>
          </div>
        </div>
 <?php }
 } else{ ?>
	   <div class="addons_empty_cont">You have not installed any addons yet.</div>
  <?php } ?>
  </div>
</div>
<?php TPL::captureStop('installedAddons'); 

if(!empty($d['promoAddons'])){
 TPL::captureStart('promoAddons');?>
 
<div class="result_block shadow_stroke_box addons">
  <div class="th rep_sprite">
    <div class="title"><span class="droid700">OTHER USEFUL ADDONS</span></div>
  </div>
  <div class="rows_cont" style="margin-bottom:-1px">
  <?php
    foreach($d['promoAddons'] as  $addon){ ?>
    <div class="buy_addons_cont">
      <div class="addon_name"><?php echo $addon['addon']; ?></div>
      <div class="addon_descr"><?php echo $addon['descr']; ?></div>
      <div class="th_sub rep_sprite">
        <div class="price_strike"><?php $addon['listPrice'] = (float)$addon['listPrice']; echo (!empty($addon['listPrice'])) ? '$ '.$addon['listPrice'] : ''; ?></div>
        <div class="price">$<?php echo $addon['price']; ?></div>
        <div class="full_details"><a href="<?php echo $addon['URL']; ?>" target="_blank">Full Details</a></div>
      </div>
    </div>    
	<?php } ?>
   <div class="clear-both"></div>
  </div>
</div>
<?php TPL::captureStop('promoAddons'); } 

//===================================================================================================================> 

if(!empty($d['promos']['addon_page_top'])){ echo '<div id="addon_page_top">'.$d['promos']['addon_page_top'].'</div>'; }
echo TPL::captureGet('newAddons');
echo TPL::captureGet('installedAddons');
echo TPL::captureGet('promoAddons');
if(!empty($d['promos']['addon_page_bottom'])){ echo '<div id="addon_page_top">'.$d['promos']['addon_page_bottom'].'</div>'; }

?>