<?= form_open($post_url); ?>
<div id="communicate_info">
    <div id="email_help_text">
        <p><?= lang('store.email_templates_can_contain') ?></p>
        <p><?= lang('store.email_templates_to_display_order_details') ?></p>
        <ul>
            <li>{order_id}</li>
            <li>{order_total}</li>
            <li>{shipping_name}</li>
            <li>{shipping_address1}</li>
            <li>{order_status_message}</li>
        </ul>
        <br />
        <p><?= lang('store.email_templates_to_display_items') ?></p>
        <ul>
            <li>{items}</li>
            <li>
                <ul>
                    <li>{title}</li>
                    <li>{item_total}</li>
                    <li>{description}</li>
                    <li>{sku}</li>
                </ul>
            </li>
            <li>{/items}</li>
        </ul>
        <br />
        <p><?= lang('store.email_templates_member_details') ?></p>
        <ul>
            <li>{screen_name}</li>
            <li>{ip_address}</li>
            <li>{total_comments}</li>
            <li>{timezone}</li>
        </ul>
    </div>
</div>
<div id="communicate_compose">
    <p></p>
        <strong class="notice">*</strong> <?= lang('store.email_name', 'name') ?>
        <?= form_input('name', set_value('name', $email->name)) ?>
        <?= form_error('name') ?>
    <p></p>
        <strong class="notice">*</strong> <?=lang('subject', 'subject') ?>
        <?= form_input(array('id'=>'subject','name'=>'subject','class'=>'fullfield','value'=>set_value('subject', $email->subject))) ?>
        <?= form_error('subject') ?>
    <p></p>
        <?= lang('store.email_to', 'to') ?>
        <?= form_input('to', set_value('to', $email->to)) ?>
        <?= form_error('to') ?>
    <p></p>
        <?= lang('bcc', 'bcc') ?>
        <?= form_input('bcc', set_value('bcc', $email->bcc)) ?>
        <?= form_error('bcc') ?>
    <p style="margin-bottom:15px"></p>
        <strong class="notice">*</strong> <?= lang('message', 'message') ?><br />
        <?= form_error('contents') ?>
        <?= form_textarea(array('id'=>'message','name'=>'contents','rows'=>20,'cols'=>85,'class'=>'fullfield','value'=>set_value('contents', $email->contents))) ?>
    <?php
        $this->table->set_template($store_table_template);

        $this->table->add_row(
            array(
                array('data' => lang('mail_format', 'mail_format'), 'style' => 'width:30%;'),
                form_dropdown('mail_format', array('text' => lang('plain_text'), 'html' => lang('html')), $email->mail_format)
            )
        );

        $this->table->add_row(
            array(
                lang('wordwrap', 'wordwrap'),
                form_dropdown('word_wrap', array('1' => lang('on'), '0' => lang('off')), $email->word_wrap)
            )
        );

        $this->table->add_row(
            array(
                '<strong>'.lang('store.enabled', 'enabled').'</strong>',
                form_hidden('enabled', '0').
                form_checkbox('enabled', '1', $email->enabled)
            )
        );

        echo $this->table->generate();
    ?>
    <p></p><strong class="notice">*</strong> <?= lang('required_fields') ?>
    <div style="clear: left; text-align: right;">
        <?= form_submit(array('name' => 'submit', 'value' => lang('store.submit'), 'class' => 'submit')); ?>
    </div>
</div>
<div style="clear:both">
</div>
<?= form_close(); ?>
