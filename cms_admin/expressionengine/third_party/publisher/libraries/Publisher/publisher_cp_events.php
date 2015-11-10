<?php if ( !defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine Publisher Model Class
 *
 * @package     ExpressionEngine
 * @subpackage  Models
 * @category    Publisher
 * @author      Brian Litzinger
 * @copyright   Copyright (c) 2012, 2013 - Brian Litzinger
 * @link        http://boldminded.com/add-ons/publisher
 * @license
 *
 * Copyright (c) 2012, 2013. BoldMinded, LLC
 * All rights reserved.
 *
 * This source is commercial software. Use of this software requires a
 * site license for each domain it is used on. Use of this software or any
 * of its source code without express written permission in the form of
 * a purchased commercial or other license is prohibited.
 *
 * THIS CODE AND INFORMATION ARE PROVIDED "AS IS" WITHOUT WARRANTY OF ANY
 * KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND/OR FITNESS FOR A
 * PARTICULAR PURPOSE.
 *
 * As part of the license agreement for this software, all modifications
 * to this source must be submitted to the original author for review and
 * possible inclusion in future releases. No compensation will be provided
 * for patches, although where possible we will attribute each contribution
 * in file revision notes. Submitting such modifications constitutes
 * assignment of copyright to the original author (Brian Litzinger and
 * BoldMinded, LLC) for such modifications. If you do not wish to assign
 * copyright to the original author, your license to  use and modify this
 * source is null and void. Use of this software constitutes your agreement
 * to this clause.
 */

require PATH_THIRD.'publisher/config.php';

/**
 * A generic class to "listen" to events happening in the CP.
 * Uses POST and SESSION data to determine if/when an event
 * should be triggered. This isn't really a model, I know.
 */

class Publisher_cp_events
{
    public function handler($session)
    {
        // Gets set in the upd.publisher.php file during the install process.
        if (ee()->publisher_lib->is_installing())
        {
            // Let any 3rd party fieldtypes run their install process.
            ee()->publisher_lib->call('install');

            // Move all core/default data to our custom tables.
            ee()->publisher_entry->migrate_data();
            ee()->publisher_category->migrate_data();
            ee()->publisher_relationships->migrate_data();

            // Redirect to the languages page so the user can add more.
            $_SESSION['installing_publisher'] = FALSE;
            $_SESSION['install_complete'] = TRUE;

            $redirect_to = PUBLISHER_LITE ? 'settings' : 'languages';

            ee()->publisher_helper_url->redirect(ee()->publisher_helper_cp->mod_link($redirect_to));
        }
        else
        {
            // Upon field creation/deletion, make sure our custom tables are in sync.
            ee()->publisher_entry->sync_columns('entry', $session);
            ee()->publisher_category->sync_columns('category', $session);
        }

        // Is Matrix or Playa being installed AFTER Publisher?
        if (ee()->input->get_post('install_fieldtype') && in_array(ee()->input->get_post('package'), array('matrix', 'playa')))
        {
            $package = ee()->input->get_post('package');
            $class_name = 'Publisher_'. $package;

            require_once PATH_THIRD .'publisher/libraries/Publisher/Publisher_fieldtype.php';
            require_once PATH_THIRD .'publisher/libraries/Publisher/fieldtypes/'. $class_name .'.php';
            ee()->$class_name = new $class_name();
            ee()->$class_name->install();
        }

        // If changing languages, clear the cookies so we don't get an error when changing languages.
        if (ee()->publisher_router->class_is('sites') && ee()->input->get('site_id') !== ee()->config->item('site_id'))
        {
            ee()->publisher_session->set(ee()->publisher_session->cookie_name, '');
            ee()->publisher_session->set(ee()->publisher_session->cookie_name.'_cp', '');
        }

        // Deleting a category? Make sure approvals are removed too.
        if (ee()->publisher_router->method_is('category_delete') && ee()->input->get_post('cat_id'))
        {
            ee()->publisher_approval->delete(ee()->input->post('cat_id'), 'category');
        }

        // Is a template or group being deleted?
        // Make sure to remove our custom translation too, can screw up requests if its not.
        if (ee()->publisher_router->method_is('template_delete') && ee()->input->get_post('template_id'))
        {
            ee()->publisher_template->delete_translation(ee()->input->post('template_id'), 'template');
        }

        if (ee()->publisher_router->method_is('template_group_delete') && ee()->input->get_post('group_id'))
        {
            ee()->publisher_template->delete_translation(ee()->input->post('group_id'), 'group');
        }

        // Delete pages from site_pages array if checkbox is checked in the module page.
        if (ee()->input->get_post('module') == 'pages' && ee()->input->get_post('method') == 'delete')
        {
            $entries = ee()->input->post('delete');

            foreach ($entries as $entry_id)
            {
                ee()->publisher_site_pages->delete($entry_id);
            }
        }

        // Saving a custom field
        if(ee()->input->post('field_edit_submit'))
        {
            // Make sure our Grid columns exist
            if (version_compare(APP_VER, '2.7', '>='))
            {
                ee()->load->library('Publisher/Publisher_fieldtype');
                ee()->load->library('Publisher/fieldtypes/Publisher_grid');
                ee()->publisher_grid->install();
            }

            // Updating existing field
            if (ee()->input->get_post('field_id'))
            {
                ee()->load->library('Publisher/Publisher_diff');
                ee()->publisher_diff->save_field_setting();
            }
        }

        // acc.publisher.php contains the code to add the diff field options
        // to the settings page. Ideally that code would be  here,
        // but for some reason only works whe in the acc file.

        if (REQ == 'CP' && ee()->publisher_router->method_is('multi_entry_category_update'))
        {
            ee()->publisher_category->multi_entry_category_update();
        }

        // Clear our custom cache along with EE's native cache.
        if (ee()->publisher_router->method_is('clear_caching') && ee()->input->get_post('type') == 'all')
        {
            ee()->publisher_cache->driver->delete();
        }
    }
}