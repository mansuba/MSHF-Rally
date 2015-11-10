<div id="publisher-dialog-has-approval">
<?php if ($role == ROLE_PUBLISHER): ?>
	<?php echo sprintf(lang('publisher_has_approval_msg_publisher'), $type, lang('publisher_open')); ?>
<?php else: ?>
	<?php echo sprintf(lang('publisher_has_approval_msg'), $type); ?>
<?php endif; ?>
</div>