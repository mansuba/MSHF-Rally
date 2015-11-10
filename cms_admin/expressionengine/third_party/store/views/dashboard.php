<div class="container-fluid">


<div class="row pagehead">
    <div class="col-xs-6">
        <!--<h2><?=lang('s:dashboard')?></h2>-->
    </div>
    <div class="col-xs-6">
        <div class="dateranger pull-right">
            <i class="fa fa-calendar"></i>
            <span class="datestr"><?=date('F j, Y', $start_date)?> - <?=date('F j, Y', $end_date)?></span>
            <strong class="caret"></strong>
        </div>
    </div>
</div>


<div class="row ">
    <div class="col-xs-12 metric-boxes">
        <?php foreach ($metrics as $name => $metric):?>
        <div class="metric" data-metric="<?=$name?>" data-date_start="<?=date('Y-m-d', $start_date)?>" data-date_end="<?=date('Y-m-d', $end_date)?>">
            <div class="loading-chart"></div>
            <a>
                <!-- <div class="more">
                    <span>View Details</span>
                </div> -->
                <div class="content" style="visibility: visible; display: block;">
                    <div class="primary"></div>
                    <div class="trend">
                        <p class="percentage"></p>
                        <strong class="prev_days"></strong>
                    </div>
                    <div class="chart" id="chart_<?=$name?>" style="padding: 0px; position: relative;"></div>
                </div>
                <div class="bottom">
                    <h6><?=$metric['label']?></h6>
                    <?php if (isset($metric['desc'])):?><small><?=$metric['desc']?></small><?php endif;?>
                </div>
            </a>
        </div>
        <?php endforeach;?>
    </div>
</div>

<!--
<br><br><br>

<h3><?= lang('store.dashboard.title') ?></h3>
<div id="store_dashboard_graph"></div>

<div class="store_dashboard_stats">
    <div class="cell">
        <div class="title"><?= lang('store.revenue') ?></div>
        <div class="value"><?= store_currency($stats['revenue']) ?></div>
        <div class="change"><?= store_format_indicator($stats['revenue'], $stats['prev_revenue']) ?></div>
    </div>
    <div class="cell">
        <div class="title"><?= lang('store.orders') ?></div>
        <div class="value"><?= $stats['orders'] ?></div>
        <div class="change"><?= store_format_indicator($stats['orders'], $stats['prev_orders']) ?></div>
    </div>
    <div class="cell">
        <div class="title"><?= lang('store.dashboard.products_sold') ?></div>
        <div class="value"><?= $stats['items'] ?></div>
        <div class="change"><?= store_format_indicator($stats['items'], $stats['prev_items']) ?></div>
    </div>
    <div class="cell">
        <div class="title"><?= lang('store.dashboard.average_order') ?></div>
        <div class="value"><?= store_currency($stats['average_order']) ?></div>
        <div class="change"><?= store_format_indicator($stats['average_order'], $stats['prev_average_order']) ?></div>
    </div>
</div>
-->

</div>