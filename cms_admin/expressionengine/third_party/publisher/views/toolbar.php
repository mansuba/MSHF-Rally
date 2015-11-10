<div data-type="<?php echo $type ?>" data-type-id="<?php echo $type_id ?>" class="publisher-bar <?php echo $type ?> <?php echo $publisher_view_status ?> <?php if ($has_draft AND $publisher_view_status != PUBLISHER_STATUS_DRAFT): ?>has_draft<?php endif; ?>">
    <div class="publisher-bar-inner">

        <?php if ( !$disable_drafts && $has_draft && $publisher_view_status != PUBLISHER_STATUS_DRAFT): ?>
            <div class="publisher-indicator publisher-fallback-indicator">
                <div class="publisher-indicator-flag"><span></span></div>
                <div class="publisher-indicator-msg">A more recent draft is available.</div>
            </div>
        <?php endif; ?>

        <?php if ( $has_default && !$has_translation && $selected_language != $default_language): ?>
            <div class="publisher-indicator publisher-fallback-indicator">
                <div class="publisher-indicator-flag"><span></span></div>
                <div class="publisher-indicator-msg">No <?php echo $selected_language_name ?> translation found. Displaying <?php echo $default_language_name ?> content where available.</div>
            </div>
        <?php endif; ?>

        <?php if ($type == 'entry'): ?>
            <span class="publisher-submit-container"></span>
        <?php else: ?>
            <input type="submit" class="submit save" name="submit" id="submit_button" value="<?php echo lang('publisher_open') ?>" />
        <?php endif; ?>

        <?php if ($action_approve_and_publish): ?>
            <input type="submit" data-type="<?php echo $type ?>" data-type-id="<?php echo $type_id ?>" class="submit accept-approval <?php echo ($publisher_view_status == PUBLISHER_STATUS_OPEN ? 'publisher-hidden' : '') ?>" name="accept_draft" id="submit_button_accept_draft" value="<?php echo lang('publisher_approve_and_publish') ?>" />
        <?php endif; ?>

        <?php if ($action_deny_approval): ?>
            <input type="submit" data-type="<?php echo $type ?>" data-type-id="<?php echo $type_id ?>" class="submit deny-approval <?php echo ($publisher_view_status == PUBLISHER_STATUS_OPEN ? 'publisher-hidden' : '') ?>" name="reject_draft" id="submit_button_reject_draft" value="<?php echo lang('publisher_deny_approval') ?>" />
        <?php endif; ?>

        <?php if ($action_delete_draft): ?>
            <input type="submit" class="submit delete-draft" name="delete_draft" id="submit_button_delete_draft" value="<?php echo lang('publisher_delete_draft') ?>" />
        <?php endif; ?>

        <?php if ($action_delete_translation): ?>
            <input type="submit" class="submit delete-translation" name="delete_translation" id="submit_button_delete_translations" value="<?php echo lang('publisher_delete_translation') ?>" />
        <?php endif; ?>

        <?php if ($include_languages): ?>
            <div class="lang-selector">
                <h4>Language: <?php echo $this->publisher_helper->get_flag($lang_short_name); ?></h4> 
                <select name="site_language" class="publisher-view-language">
                    <option value="">-- Select --</option>
                    <?php foreach ($languages as $lang_id => $language): ?>
                        <option value="<?php echo $lang_id ?>" <?php echo ($lang_id == $selected_language ? 'selected="selected"' : '') ?>><?php echo $language['long_name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <?php if ($disable_drafts): ?>
            <input type="hidden" name="publisher_save_status" value="<?php echo PUBLISHER_STATUS_OPEN ?>" class="publisher-save-status" />
        <?php else: ?>
            <div class="status-selector">
                <h4>View:</h4>
                <select name="publisher_view_status" class="publisher-view-status">
                    <option value="">-- Select --</option>
                    <?php foreach ($view_options as $value => $label): ?>
                        <option value="<?php echo $value ?>" <?php echo ($publisher_view_status == $value ? 'selected="selected"' : '') ?>><?php echo $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="status-selector">
                <h4>Save As:</h4>
                <select name="publisher_save_status" class="publisher-save-status" <?php echo ( !$save_status_enabled ? 'disabled="disabled"' : '') ?>>
                    <option value="">-- Select --</option>
                    <?php foreach ($save_options as $value => $label): ?>
                        <option value="<?php echo $value ?>" <?php echo ($publisher_save_status == $value ? 'selected="selected"' : '') ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ( !$save_status_enabled): ?>
                    <input type="hidden" name="publisher_save_status" value="<?php echo PUBLISHER_STATUS_DRAFT ?>" />
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php echo $toolbar_extra; ?>

        <div style="clear: both; <?php if ($disable_drafts && $type != 'entry'): ?>padding-bottom: 8px<?php elseif($type != 'entry'): ?>padding-bottom: 3px<?php endif; ?>;"></div>
        
    </div>
</div>