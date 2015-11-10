<script type="text/javascript">
$(function(){
	$('.category-save-<?php echo $cat_id ?>').publisherToolbar();
});
</script>

<?php echo (isset($approval) ? $this->load->view('dialog/deny_approval') : ''); ?>

<?php echo form_open($save_url, array('class' => 'ajax-form category-save-'. $cat_id)); ?>
	<?php echo $this->load->view('toolbar'); ?>
	<div class="ajax-form-wrapper">
		<?php echo $this->load->view('category/edit_form'); ?>
	</div>
</form>
