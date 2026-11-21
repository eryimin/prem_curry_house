<?php
// Shared admin sidebar, loaded by files inside admin/.
$currentPage=basename($_SERVER['SCRIPT_NAME']??'dashboard.php');
if (!function_exists('adminNavLink')) {
    function adminNavLink(string $file,string $icon,string $label,string $currentPage):void {
        $active=$file===$currentPage;
        echo '<a class="sidebar-link'.($active?' selected':'').'" href="'.htmlspecialchars($file,ENT_QUOTES,'UTF-8').'"'.($active?' aria-current="page"':'').'><i class="bi bi-'.htmlspecialchars($icon,ENT_QUOTES,'UTF-8').'"></i>'.htmlspecialchars($label,ENT_QUOTES,'UTF-8').'</a>';
    }
}
$isFoodPage=in_array($currentPage,['food_analytics.php','menu_analytics.php'],true);
$isOrdersPage=in_array($currentPage,['food_orders.php','tiffin_orders.php'],true);
?>
<aside class="dashboard-sidebar" aria-label="Admin navigation">
 <div class="sidebar-heading">Overview</div>
 <?php adminNavLink('dashboard.php','speedometer2','Dashboard',$currentPage); ?>
 <div class="sidebar-heading mt-4">Analytics</div>
 <div class="pch-food-nav">
  <button type="button" class="sidebar-link pch-food-toggle<?= $isFoodPage?' selected':'' ?>" aria-expanded="<?= $isFoodPage?'true':'false' ?>" aria-controls="pch-food-submenu" id="pch-food-toggle"><i class="bi bi-pie-chart"></i><span class="pch-food-label">Food Analytics</span><i class="bi bi-chevron-down pch-food-arrow"></i></button>
  <div id="pch-food-submenu" class="pch-food-submenu" <?= $isFoodPage?'':'hidden' ?>>
   <?php adminNavLink('food_analytics.php','speedometer2','Overview',$currentPage); ?>
   <?php adminNavLink('menu_analytics.php','bar-chart-line','Menu Analytics',$currentPage); ?>
  </div>
 </div>
 <?php adminNavLink('tiffin_analytics.php','graph-up-arrow','Tiffin Analytics',$currentPage); ?>
 <div class="sidebar-heading mt-4">Management</div>
 <?php adminNavLink('add_item.php','plus-circle','Add Menu Item',$currentPage); ?>
 <?php adminNavLink('menu_items.php','list-ul','Manage Menu',$currentPage); ?>
 <div class="pch-orders-nav">
  <button type="button" class="sidebar-link pch-orders-toggle<?= $isOrdersPage?' selected':'' ?>" aria-expanded="<?= $isOrdersPage?'true':'false' ?>" aria-controls="pch-orders-submenu" id="pch-orders-toggle"><i class="bi bi-bag-check"></i><span class="pch-orders-label">Manage Orders</span><i class="bi bi-chevron-down pch-orders-arrow"></i></button>
  <div id="pch-orders-submenu" class="pch-orders-submenu" <?= $isOrdersPage?'':'hidden' ?>>
   <?php adminNavLink('food_orders.php','egg-fried','Food Orders',$currentPage); ?>
   <?php adminNavLink('tiffin_orders.php','calendar-check','Tiffin Orders',$currentPage); ?>
  </div>
 </div>
 <?php adminNavLink('tiffin_enquiries.php','chat-dots','Manage Enquiries',$currentPage); ?>
 <div class="sidebar-heading mt-4">Customer website</div>
 <a class="sidebar-link" href="../menu.php"><i class="bi bi-eye"></i>Customer Menu</a>
 <a class="sidebar-link" href="../tiffin.php"><i class="bi bi-cup-hot"></i>Tiffin Plans</a>
</aside>
<style>
.pch-food-toggle,.pch-orders-toggle{display:flex;align-items:center;gap:9px;width:100%;text-align:left;white-space:nowrap;cursor:pointer;font-family:inherit;border:0}
.pch-food-label,.pch-orders-label{flex:1;min-width:0;white-space:nowrap}
.pch-food-arrow,.pch-orders-arrow{flex-shrink:0;margin-left:auto;font-size:11px;transition:transform .2s}
.pch-food-toggle[aria-expanded="true"] .pch-food-arrow,.pch-orders-toggle[aria-expanded="true"] .pch-orders-arrow{transform:rotate(180deg)}
.pch-food-submenu,.pch-orders-submenu{margin:3px 0 7px 18px;padding-left:10px;border-left:2px solid #dce8d5}
.pch-food-submenu[hidden],.pch-orders-submenu[hidden]{display:none!important}
.pch-food-submenu .sidebar-link,.pch-orders-submenu .sidebar-link{font-size:12px;padding:9px 8px!important;white-space:nowrap}
.pch-orders-toggle,.pch-food-toggle{padding-top:9px!important;padding-bottom:9px!important;font-size:12px!important}
</style>
<script>
(function(){
  function init(toggleId,submenuId){
    const toggle=document.getElementById(toggleId),submenu=document.getElementById(submenuId);
    if(!toggle||!submenu)return;
    toggle.addEventListener('click',()=>{
      submenu.hidden=!submenu.hidden;
      toggle.setAttribute('aria-expanded',String(!submenu.hidden));
    });
  }
  init('pch-food-toggle','pch-food-submenu');
  init('pch-orders-toggle','pch-orders-submenu');
})();
</script>
