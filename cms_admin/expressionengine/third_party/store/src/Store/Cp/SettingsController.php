<?php

/*
 * Exp:resso Store module for ExpressionEngine
 * Copyright (c) 2010-2014 Exp:resso (support@exp-resso.com)
 */

namespace Store\Cp;

use Omnipay\Omnipay;
use Store\FormBuilder;
use Store\Model\Country;
use Store\Model\Email;
use Store\Model\Order;
use Store\Model\PaymentMethod;
use Store\Model\ShippingMethod;
use Store\Model\ShippingRule;
use Store\Model\State;
use Store\Model\Status;
use Store\Model\Tax;

class SettingsController extends AbstractController
{
    public $data = array();

    public function __construct($ee)
    {
        parent::__construct($ee);

        $this->requirePrivilege('can_access_settings');
        $this->addBreadcrumb(BASE.AMP.STORE_CP.'&amp;sc=settings', lang('nav_settings'));

        // some settings pages require countries/regions JSON data
        $this->ee->cp->add_js_script(array('ui' => 'sortable'));
        $this->ee->javascript->output('ExpressoStore.countries = '.$this->ee->store->shipping->get_countries_json().';');
    }

    protected function render($view, array $data, $selected_tab)
    {
        $content = $this->ee->load->view($view, $data, true);

        return $this->renderLayout($content, $selected_tab);
    }

    protected function renderLayout($content, $selected_tab)
    {
        $settings_url = BASE.AMP.STORE_CP.'&amp;sc=settings';

        $layout_data = array();
        $layout_data['content'] = $content;
        $layout_data['current_page'] = $selected_tab;
        $layout_data['pages'] = array(
            'general' => $settings_url,
            'reports' => $settings_url.AMP.'sm=reports',
            'email' => $settings_url.AMP.'sm=email',
            'order_fields' => $settings_url.AMP.'sm=order_fields',
            'status' => $settings_url.AMP.'sm=status',
            'payment' => $settings_url.AMP.'sm=payment',
            'shipping' => $settings_url.AMP.'sm=shipping',
            'country' => $settings_url.AMP.'sm=country',
            'tax' => $settings_url.AMP.'sm=tax',
            'conversions' => $settings_url.AMP.'sm=conversions',
            'security' => $settings_url.AMP.'sm=security'
        );

        // render layout
        return parent::render('settings/base', $layout_data);
    }

    public function index()
    {
        $this->setTitle(lang('store.settings.general'));

        $items = array(
            'store_currency_symbol', 'store_currency_suffix', 'store_currency_decimals',
            'store_currency_dec_point', 'store_currency_thousands_sep', 'store_currency_code',
            'store_weight_units', 'store_dimension_units', 'store_from_email', 'store_from_name',
            'store_default_order_address', 'store_cc_payment_method', 'store_force_member_login',
            'store_cart_expiry', 'store_secure_template_tags', 'store_order_invoice_url');

        return $this->renderSettings('general', $items);
    }

    protected function renderSettings($page, $items)
    {
        $data = array();
        $data['post_url'] = STORE_CP.'&amp;sc=settings&amp;sm='.$page;
        $data['settings'] = array();
        $data['setting_defaults'] = array();

        // check for submitted general form
        if (!empty($_POST)) {
            // load submitted settings
            $settings = $this->ee->input->post('settings');
            $this->ee->store->config->update($settings);

            $redirect_url = BASE.AMP.STORE_CP.'&amp;sc=settings';
            if ($page != 'general') {
                $redirect_url .= '&amp;sm='.$page;
            }

            $this->ee->session->set_flashdata('message_success', lang('store.settings.updated'));
            $this->ee->functions->redirect($redirect_url);
        }

        // generate setting inputs
        foreach ($items as $key) {
            $data['settings'][$key] = config_item($key);
            $data['setting_defaults'][$key] = $this->ee->store->config->settings[$key];
        }

        return $this->render('settings/general', $data, $page);
    }

    public function reports()
    {
        $this->setTitle(lang('store.settings.reports'));

        $items = array(
            'store_export_pdf_orientation','store_export_pdf_page_format',
            'store_order_details_header', 'store_order_details_header_right',
            'store_order_details_footer');

        return $this->renderSettings('reports', $items);
    }

    public function conversions()
    {
        $this->setTitle(lang('store.settings.conversions'));

        $items = array('store_google_analytics_ecommerce', 'store_conversion_tracking_extra');

        return $this->renderSettings('conversions', $items);
    }

    public function email()
    {
        $this->setTitle(lang('store.settings.email'));

        // handle form submit
        if (!empty($_POST)) {
            $selected = Email::where('site_id', config_item('site_id'))
                ->whereIn('id', (array) $this->ee->input->post('selected', true));

            switch ($this->ee->input->post('with_selected')) {
                case 'enable':
                    $selected->update(array('enabled' => 1));
                    break;
                case 'disable':
                    $selected->update(array('enabled' => 0));
                    break;
                case 'delete':
                    $selected->delete();
                    break;
            }

            $this->ee->session->set_flashdata('message_success', lang('store.settings.updated'));
            $this->ee->functions->redirect(BASE.AMP.STORE_CP.'&amp;sc=settings&amp;sm=email');
        }

        $data = array();
        $data['post_url'] = STORE_CP.'&amp;sc=settings&amp;sm=email';
        $data['edit_url'] = BASE.AMP.STORE_CP.'&amp;sc=settings&amp;sm=email_edit&amp;id=';
        $data['emails'] = Email::where('site_id', config_item('site_id'))->orderBy('name')->get();

        return $this->render('settings/email', $data, 'email');
    }

    public function email_edit()
    {
        $this->addBreadcrumb(BASE.AMP.STORE_CP.'&amp;sc=settings&amp;sm=email', lang('store.settings.email'));

        $this->ee->lang->loadfile('communicate');

        $id = $this->ee->input->get('id');
        if ('new' == $id) {
            $this->setTitle(lang('store.new_email_template'));

            $email = new Email;
            $email->site_id = config_item('site_id');
        } else {
            $this->setTitle(lang('store.settings.edit_email'));

            $email = Email::where('site_id', config_item('site_id'))->find($id);

            if (empty($email)) {
                return $this->show404();
            }
        }

        // handle form submit
        $this->ee->form_validation->set_rules('name', 'lang:name', 'required');
        $this->ee->form_validation->set_rules('subject', 'lang:subject', 'required');
        $this->ee->form_validation->set_rules('contents', 'lang:message', 'required');
        $this->ee->form_validation->set_rules('bcc', 'lang:bcc', 'valid_emails');

        if ($this->ee->form_validation->run()) {
            // DON'T run POST through XSS filter - it breaks inline CSS
            $email->fill($_POST);
            $email->save();

            // redirect
            $this->ee->session->set_flashdata('message_success', lang('store.settings.updated'));
            $this->ee->functions->redirect(BASE.AMP.STORE_CP.'&amp;sc=settings&amp;sm=email');
        }

        $data = array();
        $data['post_url'] = STORE_CP.'&amp;sc=settings&amp;sm=email_edit&amp;id='.$id;
        $data['email'] = $email;

        return $this->render('settings/email_edit', $data, 'email');
    }

    public function order_fields()
    {
        $this->setTitle(lang('store.settings.order_fields'));

        $data = array(
            'post_url' => STORE_CP.'&amp;sc=settings&amp;sm=order_fields',
            'order_fields' => $this->ee->store->config->order_fields(),
            'member_fields' => $this->ee->store->member->get_member_fields_select(),
        );

        // check for submitted form
        if ( ! empty($_POST)) {
            if ($this->ee->input->post('restore_defaults')) {
                $data['order_fields'] = $this->ee->store->config->order_field_defaults();
            } else {
                $post_order_fields = $this->ee->input->post('order_fields', true);
                foreach ($data['order_fields'] as $field_name => $field) {
                    if (isset($field['title'])) {
                        $data['order_fields'][$field_name]['title'] = isset($post_order_fields[$field_name]['title']) ? $post_order_fields[$field_name]['title'] : '';
                    }

                    $data['order_fields'][$field_name]['member_field'] = isset($post_order_fields[$field_name]['member_field']) ? $post_order_fields[$field_name]['member_field'] : '';
                }
            }

            // update database and redirect
            $this->ee->store->config->update(array('store_order_fields' => $data['order_fields']));

            $this->ee->session->set_flashdata('message_success', lang('store.settings.updated'));
            $this->ee->functions->redirect(BASE.AMP.$data['post_url']);
        }

        return $this->render('settings/order_fields', $data, 'order_fields');
    }

    public function status()
    {
        $this->setTitle(lang('store.settings.status'));

        // sortable ajax post
        if (!empty($_POST['sortable_ajax'])) {
            return $this->sortableAjax('\Store\Model\Status');
        }

        $data = array();
        $data['post_url'] = STORE_CP.'&amp;sc=settings&amp;sm=status';
        $data['edit_url'] = BASE.AMP.STORE_CP.'&amp;sc=settings&amp;sm=status_edit&amp;id=';
        $data['statuses'] = Status::where('site_id', config_item('site_id'))->orderBy('sort')->get();

        return $this->render('settings/status', $data, 'status');

        $this->ee->javascript->compile();
    }

    public function status_edit()
    {
        $this->addBreadcrumb(STORE_CP.'&amp;sc=settings&amp;sm=status', lang('store.settings.status'));

        $status_id = $this->ee->input->get('id');
        if ($status_id == 'new') {
            $status = new Status;
            $status->site_id = config_item('site_id');
            $status->sort = Status::max('sort') + 1;

            $this->setTitle(lang('store.status_add'));
        } else {
            $status = Status::where('site_id', config_item('site_id'))->find($status_id);

            if (empty($status)) {
                return $this->show404();
            }

            $this->setTitle(lang('store.status_edit'));
        }

        $locked = $status->exists &&
            Order::where('site_id', config_item('site_id'))
                ->where('order_status_name', $status->name)
                ->count() > 0;

        // handle form submit
        if (!empty($_POST['submit'])) {
            $this->ee->form_validation->set_rules('status[name]', "lang:name", 'unique_status_name['.$status->id.']');
            if ($locked) {
                unset($_POST['status']['name']);
            } else {
                $this->ee->form_validation->add_rules('status[name]', "lang:name", 'required');
            }
            $status->fill((array) $this->ee->input->post('status', true));

            if ($this->ee->form_validation->run()) {
                $status->save();

                // ensure there is only one default
                if ($status->is_default) {
                    Status::where('site_id', config_item('site_id'))
                        ->where('id', '!=', $status->id)
                        ->update(array('is_default' => 0));
                }

                $this->ee->session->set_flashdata('message_success', lang('store.settings.updated'));
                $this->ee->functions->redirect(BASE.AMP.STORE_CP.'&amp;sc=settings&amp;sm=status');
            }
        }

        // handle delete
        if (!$locked && !empty($_POST['delete'])) {
            $status->delete();

            $this->ee->session->set_flashdata('message_success', lang('store.settings.updated'));
            $this->ee->functions->redirect(BASE.AMP.STORE_CP.'&amp;sc=settings&amp;sm=status');
        }

        $data = array();
        $data['status'] = $status;
        $data['form'] = new FormBuilder($status);
        $data['locked'] = $locked;

        $data['emails'] = array('' => lang('store.none'));
        foreach (Email::where('site_id', config_item('site_id'))->get() as $email) {
            $data['emails'][$email->id] = store_email_template_name($email->name);
        }

        return $this->render('settings/status_edit', $data, 'status');
    }

    public function payment()
    {
        $this->setTitle(lang('store.settings.payment'));

        $data['gateways'] = array();
        foreach ($this->ee->store->payments->get_payment_gateways() as $name) {
            $gateway = Omnipay::create($name);
            $data['gateways'][$name] = array(
                'title' => $gateway->getName(),
                'class' => $name,
                'enabled' => false,
                'settings_url' => BASE.AMP.STORE_CP.'&amp;sc=settings&amp;sm=payment_edit&amp;class='.$name,
            );
        }

        $payment_methods = PaymentMethod::where('site_id', config_item('site_id'))->where('enabled', 1)->get();
        foreach ($payment_methods as $method) {
            if (isset($data['gateways'][$method->class])) {
                $data['gateways'][$method->class]['enabled'] = true;
            }
        }

        return $this->render('settings/payment', $data, 'payment');
    }

    public function payment_edit()
    {
        // allow extensions to load custom gateways
        $available_gateways = $this->ee->store->payments->get_payment_gateways();
        if (!in_array($this->ee->input->get('class'), $available_gateways)) {
            return $this->show404();
        }

        $gateway = Omnipay::create($this->ee->input->get('class'));

        $this->addBreadcrumb(BASE.AMP.STORE_CP.'&amp;sc=settings&amp;sm=payment', lang('store.settings.payment'));
        $this->setTitle($gateway->getName());

        $class = $gateway->getShortName();
        $method = PaymentMethod::where('site_id', config_item('site_id'))->where('class', $class)->first();
        if ($method) {
            $gateway->initialize($method->settings);
        }

        // check for submitted data
        if ( ! empty($_POST)) {
            $gateway->initialize((array) $this->ee->input->post('settings'));

            $method = $method ?: new PaymentMethod;
            $method->site_id = config_item('site_id');
            $method->class = $class;
            $method->title = $gateway->getName();
            $method->settings = $gateway->getParameters();
            $method->enabled = (int) $this->ee->input->post('enabled');
            $method->save();

            $this->ee->session->set_flashdata('message_success', lang('store.settings.updated'));
            $this->ee->functions->redirect(BASE.AMP.STORE_CP.'&amp;sc=settings&amp;sm=payment');
        }

        $data = array();
        $data['post_url'] = STORE_CP.'&amp;sc=settings&amp;sm=payment_edit&amp;class='.$class;
        $data['title'] = $gateway->getName();
        $data['short_name'] = $class;
        $data['default_settings'] = $gateway->getDefaultParameters();
        foreach ($data['default_settings'] as $key => $values) {
            if (is_array($values)) {
                $data['default_settings'][$key] = array('type' => 'select', 'options' => array());
                foreach ($values as $value) {
                    $data['default_settings'][$key]['options'][$value] = 'store.payment.'.snake_case($key).'.'.strtolower($value);
                }
            }
        }
        $data['settings'] = $gateway->getParameters();
        $data['enabled'] = $method && $method->enabled;

        return $this->render('settings/payment_edit', $data, 'payment');
    }

    public function shipping()
    {
        $this->setTitle(lang('store.settings.shipping'));

        // handle form submit
        if (!empty($_POST['submit'])) {
            $selected_ids = (array) $this->ee->input->post('selected', true);
            $selected = ShippingMethod::where('site_id', config_item('site_id'))->whereIn('id', $selected_ids);

            switch ($this->ee->input->post('with_selected')) {
                case 'enable':
                    $selected->update(array('enabled' => 1));
                    break;
                case 'disable':
                    $selected->update(array('enabled' => 0));
                    break;
                case 'delete':
                    // delete related shipping rules
                    ShippingRule::whereIn('shipping_method_id', $selected_ids)->delete();
                    $selected->delete();
            }

            $this->ee->session->set_flashdata('message_success', lang('store.settings.updated'));
            $this->ee->functions->redirect(BASE.AMP.STORE_CP.'&amp;sc=settings&amp;sm=shipping');
        }

        if (!empty($_POST['submit_default'])) {
            $settings = $this->ee->input->post('settings');
            $this->ee->store->config->update($settings);

            $this->ee->session->set_flashdata('message_success', lang('store.settings.updated'));
            $this->ee->functions->redirect(BASE.AMP.STORE_CP.'&amp;sc=settings&amp;sm=shipping');
        }

        // sortable ajax post
        if (!empty($_POST['sortable_ajax'])) {
            return $this->sortableAjax('\Store\Model\ShippingMethod');
        }

        $data = array();
        $data['shipping_methods'] = ShippingMethod::where('site_id', config_item('site_id'))->orderBy('sort')->get();
        $data['shipping_method_options'] = array('' => lang('store.none'));
        foreach ($data['shipping_methods'] as $method) {
            $data['shipping_method_options'][$method->id] = $method->name;
        }
        $data['default_shipping_method_id'] = config_item('store_default_shipping_method_id');

        $data['post_url'] = STORE_CP.'&amp;sc=settings&amp;sm=shipping';
        $data['edit_url'] = BASE.AMP.STORE_CP.'&amp;sc=settings&amp;sm=shipping_method&amp;id=';

        return $this->render('settings/shipping', $data, 'shipping');
    }

    public function shipping_method()
    {
        $this->addBreadcrumb(BASE.AMP.STORE_CP.'&amp;sc=settings&amp;sm=shipping', lang('store.settings.shipping'));

        $method_id = $this->ee->input->get('id');
        if ($method_id == 'new') {
            $method = new ShippingMethod;
            $method->site_id = config_item('site_id');
            $method->enabled = 1;
            $method->sort = ShippingMethod::max('sort') + 1;

            $this->setTitle(lang('store.shipping_method_add'));
        } else {
            $method = ShippingMethod::where('site_id', config_item('site_id'))
                ->find($this->ee->input->get('id'));

            if (!$method) {
                return $this->show404();
            }

            $this->setTitle(lang('store.shipping_method_edit'));
        }

        // handle form submit
        if (!empty($_POST['submit'])) {
            $method->fill($this->ee->input->post('method'));
            $this->ee->form_validation->set_rules('method[name]', 'lang:name', 'required');
            if ($this->ee->form_validation->run()) {
                $method->save();

                // redirect to current page
                $this->ee->session->set_flashdata('message_success', lang('store.settings.updated'));
                $this->ee->functions->redirect(BASE.AMP.STORE_CP.'&amp;sc=settings&amp;sm=shipping_method&amp;id='.$method->id);
            }
        }

        if (!empty($_POST['submit_selected'])) {
            $selected = ShippingRule::where('shipping_method_id', $method_id)
                ->whereIn('id', (array) $this->ee->input->post('selected', true));

            switch ($this->ee->input->post('with_selected')) {
                case 'enable':
                    $selected->update(array('enabled' => 1));
                    break;
                case 'disable':
                    $selected->update(array('enabled' => 0));
                    break;
                case 'delete':
                    $selected->delete();
                    break;
            }

            $this->ee->session->set_flashdata('message_success', lang('store.settings.updated'));
            $this->ee->functions->redirect(BASE.AMP.STORE_CP.'&amp;sc=settings&amp;sm=shipping_method&amp;id='.$method_id);
        }

        // sortable ajax post
        if (!empty($_POST['sortable_ajax'])) {
            return $this->sortableAjax('\Store\Model\ShippingRule', false);
        }

        $data = array();
        $data['method'] = $method;
        $data['rules'] = $method->rules()->orderBy('sort')->get();
        $data['post_url'] = STORE_CP.'&amp;sc=settings&amp;sm=shipping_method&amp;id='.$method_id;
        $data['edit_url'] = BASE.AMP.STORE_CP.'&amp;sc=settings&amp;sm=shipping_rule&amp;shipping_method_id='.$method_id.'&amp;id=';

        $this->ee->lang->language['store.shipping_rule_per_weight_unit'] = sprintf(
            lang('store.shipping_rule_per_weight_unit'), config_item('store_weight_units'));

        return $this->render('settings/shipping_method', $data, 'shipping');
    }

    public function shipping_rule()
    {
        $method = ShippingMethod::where('site_id', config_item('site_id'))
            ->find($this->ee->input->get('shipping_method_id'));
        if (!$method) {
            return $this->show404();
        }

        $this->addBreadcrumb(BASE.AMP.STORE_CP.'&amp;sc=settings&amp;sm=shipping', lang('store.settings.shipping'));
        $this->addBreadcrumb(BASE.AMP.STORE_CP.'&amp;sc=settings&amp;sm=shipping_method&amp;id='.$method->id, $method->name);

        $rule_id = $this->ee->input->get('id');
        if ($rule_id == 'new') {
            $rule = new ShippingRule;
            $rule->shipping_method_id = $method->id;
            $rule->enabled = 1;
            $rule->sort = ShippingRule::max('sort') + 1;

            $this->setTitle(lang('store.shipping_rule_add'));
        } else {
            $rule = $method->rules()->find($this->ee->input->get('id'));

            if (!$rule) {
                return $this->show404();
            }

            $this->setTitle(lang('store.shipping_rule_edit'));
        }

        // handle form submit
        $rule->fill((array) $this->ee->input->post('shipping_rule', true));
        if (!empty($_POST)) {
            $rule->save();

            $this->ee->session->set_flashdata('message_success', lang('store.settings.updated'));
            $this->ee->functions->redirect(BASE.AMP.STORE_CP.'&amp;sc=settings&amp;sm=shipping_method&amp;id='.$method->id);
        }

        $data = array();
        $data['shipping_rule'] = $rule;
        $data['country_options'] = $this->ee->store->shipping->get_enabled_country_options($rule->country_code, lang('store.any'));
        $data['state_options'] = $this->ee->store->shipping->get_enabled_state_options($rule->country_code, $rule->state_code, lang('store.any'));

        $this->ee->lang->language['store.shipping_rule_per_weight_rate'] = sprintf(
            lang('store.shipping_rule_per_weight_rate'), config_item('store_weight_units'));

        return $this->render('settings/shipping_rule', $data, 'shipping');
    }

    public function country()
    {
        $this->setTitle(lang('store.settings.country'));

        // handle form submit
        if (!empty($_POST['submit'])) {
            $selected = Country::where('site_id', config_item('site_id'))
                ->whereIn('id', (array) $this->ee->input->post('selected', true));

            switch ($this->ee->input->post('with_selected')) {
                case 'enable':
                    $selected->update(array('enabled' => 1));
                    break;
                case 'disable':
                    $selected->update(array('enabled' => 0));
                    break;
            }

            $this->ee->session->set_flashdata('message_success', lang('store.settings.updated'));
            $this->ee->functions->redirect(BASE.AMP.STORE_CP.'&amp;sc=settings&amp;sm=country');
        }

        if (!empty($_POST['submit_default'])) {
            $defaults = $this->ee->input->post('default', true);
            $this->ee->store->config->update(array(
                'store_default_country' => isset($defaults['country_code']) ? $defaults['country_code'] : '',
                'store_default_state' => isset($defaults['state_code']) ? $defaults['state_code'] : '',
            ));

            $this->ee->session->set_flashdata('message_success', lang('store.settings.updated'));
            $this->ee->functions->redirect(BASE.AMP.STORE_CP.'&amp;sc=settings&amp;sm=country');
        }

        $data = array();
        $data['countries'] = Country::where('site_id', config_item('site_id'))->orderBy('name')->get();
        $data['post_url'] = STORE_CP.'&amp;sc=settings&amp;sm=country';
        $data['edit_url'] = BASE.AMP.STORE_CP.'&amp;sc=settings&amp;sm=country_edit&amp;id=';

        $data['country_options'] = $this->ee->store->shipping->get_enabled_country_options(
            config_item('store_default_country'),
            lang('store.none')
        );
        $data['state_options'] = $this->ee->store->shipping->get_enabled_state_options(
            config_item('store_default_country'),
            config_item('store_default_state'),
            lang('store.none')
        );

        return $this->render('settings/country', $data, 'country');
    }

    public function country_edit()
    {
        $country = Country::where('site_id', config_item('site_id'))
            ->find($this->ee->input->get('id'));

        if (empty($country)) {
            return $this->show404();
        }

        // handle form submit
        if (!empty($_POST)) {
            $states = array();
            $state_codes = array();
            foreach ((array) $this->ee->input->post('states') as $key => $row) {
                // find or initialize state
                $state_id = isset($row['id']) ? $row['id'] : null;
                $state = State::where('country_id', $country->id)->find($state_id) ?: new State;
                $state->site_id = $country->site_id;
                $state->country_id = $country->id;
                $state->fill($row);
                $states[$key] = $state;

                // don't validate rows scheduled for deletion
                if ($state->delete) {
                    continue;
                }

                // add required fields
                $this->ee->form_validation->set_rules("states[$key][name]", 'lang:name', 'required');
                $this->ee->form_validation->set_rules("states[$key][code]", 'lang:store.code', 'required|max_length[5]');

                // check for duplicate codes
                if (!empty($row['code'])) {
                    $code = strtoupper($row['code']);
                    if (isset($state_codes[$code])) {
                        $duplicate_key = $state_codes[$code];
                        $this->ee->form_validation->add_error("states[$duplicate_key][code]", lang('store.duplicate_code'));
                        $this->ee->form_validation->add_error("states[$key][code]", lang('store.duplicate_code'));
                    } else {
                        $state_codes[$code] = $key;
                    }
                }
            }

            // must have at least one validation rule for run() to return true
            $this->ee->form_validation->set_rules('submit', 'lang:submit', 'required');
            if ($this->ee->form_validation->run()) {
                foreach ($states as $key => $state) {
                    if ($state->delete) {
                        $state->exists && $state->delete();
                    } else {
                        $state->save();
                    }
                }

                $this->ee->session->set_flashdata('message_success', lang('store.settings.updated'));
                $this->ee->functions->redirect(BASE.AMP.STORE_CP.'&amp;sc=settings&amp;sm=country');
            }
        } else {
            $states = $country->states()->orderBy('name')->get();
        }

        $this->addBreadcrumb(BASE.AMP.STORE_CP.'&amp;sc=settings&amp;sm=country', lang('store.settings.country'));
        $this->setTitle($country->name);

        $data = array();
        $data['country'] = $country;
        $data['states'] = $states;
        $data['post_url'] = STORE_CP.'&amp;sc=settings&amp;sm=country_edit&amp;id='.$country->id;

        return $this->render('settings/country_edit', $data, 'country');
    }

    public function tax()
    {
        $this->setTitle(lang('store.settings.tax'));

        // handle form submit
        if (!empty($_POST['submit'])) {
            $selected = Tax::where('site_id', config_item('site_id'))
                ->whereIn('id', (array) $this->ee->input->post('selected', true));

            switch ($this->ee->input->post('with_selected')) {
                case 'enable':
                    $selected->update(array('enabled' => 1));
                    break;
                case 'disable':
                    $selected->update(array('enabled' => 0));
                    break;
                case 'delete':
                    $selected->delete();
                    break;
            }

            $this->ee->session->set_flashdata('message_success', lang('store.settings.updated'));
            $this->ee->functions->redirect(BASE.AMP.STORE_CP.'&amp;sc=settings&amp;sm=tax');
        }

        // sortable ajax post
        if (!empty($_POST['sortable_ajax'])) {
            return $this->sortableAjax('\Store\Model\Tax');
        }

        $data = array();
        $data['post_url'] = STORE_CP.'&amp;sc=settings&amp;sm=tax';
        $data['tax_rates'] = Tax::where('site_id', config_item('site_id'))->orderBy('sort')->get();
        $data['edit_link'] = BASE.AMP.STORE_CP.'&amp;sc=settings&amp;sm=tax_edit&amp;id=';

        return $this->render('settings/tax', $data, 'tax');
    }

    public function tax_edit()
    {
        $tax_id = $this->ee->input->get('id');
        if ($tax_id == 'new') {
            $tax = new Tax;
            $tax->site_id = config_item('site_id');
            $tax->enabled = 1;
            $tax->sort = Tax::max('sort') + 1;
        } else {
            $tax = Tax::where('site_id', config_item('site_id'))->find($tax_id);

            if (empty($tax)) {
                return $this->show404();
            }
        }

        // handle form submit
        $tax->fill((array) $this->ee->input->post('tax', true));
        $this->ee->form_validation->set_rules('tax[name]', "lang:store.tax_name", 'required');
        if ($this->ee->form_validation->run()) {
            $tax->save();
            $tax->categories()->sync(array_filter($_POST['tax']['category_ids']));

            $this->ee->session->set_flashdata('message_success', lang('store.settings.updated'));
            $this->ee->functions->redirect(BASE.AMP.STORE_CP.'&amp;sc=settings&amp;sm=tax');
        }

        $data = array();
        $data['tax'] = $tax;
        $data['country_options'] = $this->ee->store->shipping->get_enabled_country_options($tax->country_code, lang('store.any'));
        $data['state_options'] = $this->ee->store->shipping->get_enabled_state_options($tax->country_code, $tax->state_code, lang('store.any'));
        $data['category_options'] = $this->ee->store->products->get_categories();

        return $this->render('settings/tax_edit', $data, 'tax');
    }

    public function security()
    {
        $this->setTitle(lang('store.settings.security'));
        $data = array(
            'post_url' => STORE_CP.'&amp;sc=settings&amp;sm=security',
            'security' => $this->ee->store->config->security(),
            'member_groups' => $this->ee->member_model->get_member_groups(array(),array('can_access_cp' => 'y'))->result_array(),
        );

        if ( ! empty($_POST)) {
            $security_settings = $this->ee->input->post('security', true);
            $this->ee->store->config->update(array('store_security' => $security_settings));

            $this->ee->session->set_flashdata('message_success', lang('store.settings.updated'));
            $this->ee->functions->redirect(BASE.AMP.$data['post_url']);
        }

        return $this->render('settings/security', $data, 'security');
    }
}
