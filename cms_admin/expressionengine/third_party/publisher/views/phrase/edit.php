<script type="text/javascript">
$(function(){
	$('.phrase-save-<?php echo $phrase_id ?>').publisherToolbar();
});
</script>

<?php echo (isset($approval) ? $this->load->view('dialog/deny_approval') : ''); ?>

<?php echo form_open($save_url, array('class' => 'ajax-form phrase-save-'. $phrase_id)); ?>
	<?php echo $this->load->view('toolbar'); ?>
	<?php if (isset($data[1]->phrase_desc) AND $data[1]->phrase_desc != '') : ?>
		<div class="publisher-phrase-description">
			<?php echo $data[1]->phrase_desc; ?>
		</div>
	<?php endif; ?>
	<div class="ajax-form-wrapper">
		<?php echo $this->load->view('phrase/edit_form'); ?>
	</div>
</form>

