<div id="store">

<div id="smenu">

        <ul>
            <li <?php if ($section == '') echo 'class="active"'?>><a href="<?=store_cp_url()?>"><i class="fa fa-home"></i> <?=lang('store.dashboard')?></a></li>
            <li <?php if ($section == 'reports') echo 'class="active"'?> ><a href="<?=store_cp_url('reports')?>"><i class="fa fa-bar-chart-o"></i> <?=lang('store.reports')?></a></li>
        </ul>

        <h4>Store</h4>
        <ul>
            <li <?php if ($section == 'orders') echo 'class="active"'?> ><a href="<?=store_cp_url('orders')?>"><i class="fa fa-list-ul"></i> <?=lang('store.orders')?></a></li>
            <li <?php if ($section == 'customers') echo 'class="active"'?> ><a href="<?=store_cp_url('customers')?>"><i class="fa fa-user"></i> <?=lang('store.customers')?></a></li>
            <li <?php if ($section == 'inventory') echo 'class="active"'?> ><a href="<?=store_cp_url('inventory')?>"><i class="fa fa-cubes"></i> <?=lang('store.inventory')?></a></li>
        </ul>

        <h4>Promotions</h4>
        <ul>
            <li <?php if ($section == 'sales') echo 'class="active"'?> ><a href="<?=store_cp_url('sales')?>"><i class="fa fa-tags"></i> <?=lang('store.sales')?></a></li>
            <li <?php if ($section == 'discounts') echo 'class="active"'?> ><a href="<?=store_cp_url('discounts')?>"><i class="fa fa-ticket"></i> <?=lang('store.discounts')?></a></li>
        </ul>

        <ul>
            <li <?php if ($section == 'settings') echo 'class="active"'?> ><a href="<?=store_cp_url('settings')?>"><i class="fa fa-cog"></i> <?=lang('store.settings')?></a></li>
        </ul>
</div>


<div id="spage">
    <?=$content?>
</div>

</div>