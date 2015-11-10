<h5><?php echo $type ?></h5>
<?php foreach ($rows as $row): ?>
	<a href="<?php echo $row->link ?>">
	<div class="publisher-approval-item">
		<b><?php echo $row->data->title ?> (<?php echo $row->lang_code; ?>)</b>
        <?php if ($row->data->member_data): ?>
            <p>
                Edited by <span class="publisher-screenname"><?php echo $row->data->member_data->screen_name ?></span>
                <span class="publisher-date">on <?php echo $row->date ?></span>
            </p>
        <?php endif; ?>
	</div>
	</a>
<?php endforeach; ?>

