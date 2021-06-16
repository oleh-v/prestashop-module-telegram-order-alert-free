<?php

class TelegramOrderAlert extends Module
{

    /**
     * @var string
     */
    protected $html = '';

    /**
     * @var string
     */
    protected $confirm = '';

    /**
     * @var string
     */
    protected $inform = '';

    /**
     * @var string
     */
    protected $warn = '';

    /**
     * @var string
     */
    protected $error = '';

    /**
     * @var array
     */
    protected $fields = array(
        'id_order',
        'reference',
        'payment',
        'status',
        'total_paid',
        'firstname_lastname',
        'email',
        'phone',
        'carrier',
        'delivery_address',
        'products',
        'bot_token',
        'bot_chat_id',
        'cond_sum',
        'cond_status'
    );

    /**
     * TelegramOrderAlert constructor.
     */
	public function __construct()
	{
		$this->name = 'telegramorderalert';
		$this->tab = 'back_office_features';
		$this->version = '1.0';
		$this->author = 'Oleh Vasylyev';
        $this->need_instance = 0;
		$this->bootstrap = true;
        $this->displayName = $this->l('Telegram Order Alert FREE');
        $this->description = $this->l('With this module, you can setup telegram notification for new order and order status changes in Prestashop');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall this module?');
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
		parent::__construct();
	}

    /**
     * @return bool
     * @throws PrestaShopException
     */
	public function install()
	{
        $multistore = Shop::isFeatureActive();
        if ($multistore == true) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        foreach ($this->fields as $field) {
            if (!Configuration::updateValue($this->name.'_'.$field, NULL)
            ) {
                return false;
            }
        }
        if (!parent::install() ||
            !$this->registerHook('header') ||
            !$this->registerHook('backOfficeHeader') ||
            !$this->registerHook('displayOrderConfirmation')
        ) {
            return false;
        }

        return true;
	}

    /**
     * @return bool
     */
    public function uninstall()
    {
        foreach ($this->fields as $field) {
            if (!Configuration::deleteByName($this->name.'_'.$field)) {
                return false;
            }
        }
        if (!parent::uninstall()) {
            return false;
        }
        return true;
    }

    /**
     * @param $param
     * @return string
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function getOrderStateNameById($param)
    {
        $default_lang_id = (int)Configuration::get('PS_LANG_DEFAULT');
        $state = new OrderState($param, $default_lang_id);

        return $state->name;
    }

    /**
     * @param $params
     * @return string
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function getData($params)
    {
        $id_order = $params['id_order'];

        $data = "\n<b>".$this->l('Order notification')."</b> ";

        if (!empty(Configuration::get($this->name.'_id_order', true))) {
            $data .= " #<u>$id_order</u>";
        }

        if (!empty(Configuration::get($this->name.'_reference', true))) {
            $id_order_ref = $params['reference'];
            $data .= " (ref: <u>$id_order_ref</u>)";
        }

        if (!empty(Configuration::get($this->name.'_status', true))) {
            $status = $this->getOrderStateNameById($params['status']);
            $data .= "\n<b>".$this->l('Status').":</b> <u><i>$status</i></u>";
        }

        if (!empty(Configuration::get($this->name.'_total_paid', true))) {
            $data .= "\n<b>".$this->l('Payment').":</b> ";
            $order = new Order((int)$id_order);
            $currency = Currency::getCurrency((int)$order->id_currency);
            $total_paid = number_format($params['total_paid'], 2, '.', '') . " " . $currency['iso_code'];
            $data .= " <u>$total_paid</u>";
        }

        if (!empty(Configuration::get($this->name.'_firstname_lastname', true))) {
            $data .= "\n<b>".$this->l('Customer').":</b> ";
            $customer = new Customer($params['id_customer']);
                $firstname_lastname = $customer->firstname.' '.$customer->lastname;
                $data .= " $firstname_lastname";
        }

        return $data;
    }

    /**
     * @param $data
     */
    public function call($data)
    {
        $apiToken = Configuration::get($this->name.'_bot_token', true);
        $chat_id = Configuration::get($this->name.'_bot_chat_id', true);
        $data = [
            'chat_id' => "$chat_id",
            'parse_mode' => "html",
            'text' => "$data"
        ];

        @file_get_contents("https://api.telegram.org/bot$apiToken/sendMessage?" . http_build_query($data));
    }

    /**
     * @param null $params
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function hookDisplayOrderConfirmation($params = null)
    {
        $data['id_order'] = (int)$params['order']->id;
        $data['status'] = (int)$params['order']->current_state;
        $data['reference'] = $params['order']->reference;
        $data['total_paid'] = $params['order']->total_paid;
        $data['id_customer'] = (int)$params['order']->id_customer;

        return $this->call($this->getData($data));
    }

    /**
     * Set values for the inputs.
     * @return array
     */
    protected function getConfigFormValues()
    {
        $data = array();
        foreach ($this->fields as $field) {
            $data[$this->name.'_'.$field] = Configuration::get($this->name.'_'.$field, true);
        }

        return $data;
    }

    /**
     * Load the configuration form
     * @return string
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submit'.$this->name.'Settings')) == true) {
            $this->postProcess();
            $this->html .= $this->confirm;
            $this->html .= $this->inform;
            $this->html .= $this->warn;
            $this->html .= $this->error;
        }

        $this->context->smarty->assign('module_dir', $this->_path);
        $this->context->smarty->assign('widget', $this->widget('ps_telegram_free'));

        $this->html .= $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');
        $this->html .= $this->renderForm();
        $this->html .= $this->context->smarty->fetch($this->local_path.'views/templates/admin/widget.tpl');

        return $this->html;
    }

    /**
     * Save form data.
     * @return string
     */
    protected function postProcess()
    {
        $adminControllers = AdminController::$currentIndex;
        $token = '&token='.Tools::getAdminTokenLite('AdminModules');
        $configAndTask ='&configure='.$this->name;
        if (Tools::isSubmit('submit'.$this->name.'Settings')) {
            $form_values = $this->getConfigFormValues();
            foreach (array_keys($form_values) as $key) {
                Configuration::updateValue($key, Tools::getValue($key));
            }
            return $this->confirm = $this->displayConfirmation($this->l('The settings have been updated.'));
        }
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     * @return string
     * @throws PrestaShopException
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = true;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submit'.$this->name.'Settings';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * @return array
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function getOrderStates()
    {
        $states = new OrderState($this->context->language->id);
        $states2 = $states->getOrderStates($this->context->language->id);
        $arr[] = array('id_order_state' => '', 'name' => '');
        foreach ($states2 as $state) {
            $arr[] = array('id_order_state' => $state['id_order_state'], 'name' => $state['name']);
        }

        return $arr;
    }

    /**
     * @return array
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Telegram settings and what kind of information will be send to notification'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('Enter Telegram Bot token:'),
                        'name' => $this->name.'_bot_token',
                        'value' => '',
                        'desc' => $this->l(''),
                        'placeholder' => '1234567890:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                        'required' => true,
                   //     'class' => 'input col-width-lg',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Enter Telegram Chat ID of Group/Channel/User'),
                        'name' => $this->name.'_bot_chat_id',
                        'value' => '',
                        'desc' => $this->l(''),
                        'placeholder' => '123456789',
                        'required' => true,
                    //    'class' => 'input',
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Display order ID'),
                        'name' => $this->name.'_id_order',
                        'is_bool' => true,
                        'desc' => $this->l(''),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Display order reference'),
                        'name' => $this->name.'_reference',
                        'is_bool' => true,
                        'desc' => $this->l(''),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Display payment method'),
                        'hint' => 'This feature is available in the PRO Version',
                        'name' => $this->name.'_payment',
                        'disabled' => true,
                        'is_bool' => true,
                        'desc' => $this->l(''),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Display order status'),
                        'name' => $this->name.'_status',
                        'is_bool' => true,
                        'desc' => $this->l(''),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Display order total sum'),
                        'name' => $this->name.'_total_paid',
                        'is_bool' => true,
                        'desc' => $this->l(''),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Display customer first and last name'),
                        'name' => $this->name.'_firstname_lastname',
                        'is_bool' => true,
                        'desc' => $this->l(''),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Display customer email'),
                        'hint' => 'This feature is available in the PRO Version',
                        'name' => $this->name.'_email',
                        'is_bool' => true,
                        'disabled' => true,
                        'desc' => $this->l(''),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Display customer phone'),
                        'hint' => 'This feature is available in the PRO Version',
                        'name' => $this->name.'_phone',
                        'is_bool' => true,
                        'disabled' => true,
                        'desc' => $this->l(''),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Display delivery method'),
                        'hint' => 'This feature is available in the PRO Version',
                        'name' => $this->name.'_carrier',
                        'is_bool' => true,
                        'disabled' => true,
                        'desc' => $this->l(''),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Display delivery address'),
                        'hint' => 'This feature is available in the PRO Version',
                        'name' => $this->name.'_delivery_address',
                        'is_bool' => true,
                        'disabled' => true,
                        'desc' => $this->l(''),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Display ordered products'),
                        'hint' => 'This feature is available in the PRO Version',
                        'name' => $this->name.'_products',
                        'is_bool' => true,
                        'disabled' => true,
                        'desc' => $this->l(''),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Order amount for notification'),
                        'hint' => 'This feature is available in the PRO Version',
                        'name' => $this->name.'_cond_sum',
                        'value' => '200',
                        'desc' => $this->l('Condition: ONLY send notification when order amount greater than ...'),
                        'placeholder' => '',
                        'class' => 'input fixed-width-lg',
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Condition: ONLY send notification when order status is:'),
                        'hint' => 'This feature is available in the PRO Version',
                        'name' => $this->name.'_cond_status',
                        'desc' => $this->l('Notification will be send ONLY about orders with selected status'),
                        'placeholder' => '',
                        'class' => 'input fixed-width-lg',
                        'options' => array(
                            'query' => $this->getOrderStates(),
                            'id' => 'id_order_state',
                            'name' => 'name',
                        )
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Add the CSS & JavaScript files you want to be loaded in the BO.
     */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('configure') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }

    public function widget($param)
    {
        $send['widget'] = $param;
        $send['http_host'] = $_SERVER['HTTP_HOST'];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://tobiksoft.com/market/widget/api.php');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $send);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, 5);
        $output = curl_exec ($ch);
        curl_close ($ch);


        return $output;
    }

}
