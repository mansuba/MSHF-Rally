<?php

if ($install_complete)
{
    echo '<div class="ootb-message-go">'. $install_complete .'</div>';
}
else if( !$is_publisher_lite)
{
    echo '<div class="ootb-message">'. lang('publisher_language_reminder') .'</div>';
}

$this->table->set_heading(
    array('data' => 'ID', 'style' => 'width: 3%'),
    array('data' => 'Language', 'style' => 'width: 37%'),
    array('data' => 'Language Code', 'style' => 'width: 20%'),
    array('data' => 'Default', 'style' => 'width: 10%'),
    array('data' => 'Enabled', 'style' => 'width: 10%'),
    array('data' => 'Actions', 'style' => 'width: 20%')
);

foreach($languages as $language)
{
    $action_links = '<a href="'. $language_manage_url .AMP.'language_id='. $language->id .'">Edit</a> |
                     <a href="'. $language_delete_url .AMP.'language_id='. $language->id .'">Delete</a>';

    $this->table->add_row(
        $language->id,
        '<a href="'. $language_manage_url .AMP.'language_id='. $language->id .'">'. $language->long_name .'</a>',
        $language->short_name,
        ($language->is_default == 'y' ? 'Yes' : 'No'),
        ($language->is_enabled == 'y' ? 'Yes' : 'No'),
        $action_links
    );
}

echo $this->table->generate();
$this->table->clear();

echo '<a class="button submit" href="'. $language_manage_url .'">'. lang('publisher_new_language') .'</a>';