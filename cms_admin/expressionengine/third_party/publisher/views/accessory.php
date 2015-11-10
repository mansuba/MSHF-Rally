<?php if ( !$this->publisher_setting->enabled()) : ?>

	<p>It looks like Publisher is disabled. Visit the <a href="<?php echo $this->publisher_helper_cp->mod_link('settings'); ?>">settings page</a> to enable Publisher.</p>

<?php else: ?>

	<?php if (isset($heading)): ?>
		<h1><?php echo $heading ?></h1>
	<?php endif; ?>

	<?php if ($total_approvals == 0): ?>
		<div class="publisher-acc-section">
			<p>There are no pending approvals. You are all set!</p>
		</div>
	<?php else: ?>
		<?php foreach ($sections as $section):  ?>
			<div class="publisher-acc-section">
				<?php echo $section ?>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>

	<?php if ($debug): ?>
		<div class="publisher-debug">
			<?php echo $debug; ?>
		</div>
	<?php endif; ?>

<?php endif; ?>