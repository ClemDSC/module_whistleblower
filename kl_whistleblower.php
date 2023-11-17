<?php
/**
 * 2007-2023 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2023 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

use PrestaShop\PrestaShop\Core\MailTemplate\Layout\Layout;
use PrestaShop\PrestaShop\Core\MailTemplate\ThemeCatalogInterface;
use PrestaShop\PrestaShop\Core\MailTemplate\ThemeCollectionInterface;
use PrestaShop\PrestaShop\Core\MailTemplate\ThemeInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Kl_whistleblower extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'kl_whistleblower';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Klorel';
        $this->need_instance = 0;

        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Email Whistleblower');
        $this->description = $this->l('The \"Email Whistleblower\" module is a powerful tool designed for PrestaShop stores to streamline the verification of critical functionalities, such as payment and carrier availability in the checkout process. It allows you to maintain complete control over these essential aspects of your online store.');

        $this->ps_versions_compliancy = ['min' => '1.6', 'max' => _PS_VERSION_];
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        Configuration::updateValue('KL_WHISTLEBLOWER_LIVE_MODE', false);

        if (version_compare(_PS_VERSION_, '1.7.7.0', '>=')) {
            return parent::install()
                && $this->registerHook('displayBackOfficeHeader')
                && $this->registerHook('header')
                && $this->registerHook('displayPaymentTop')
                && $this->registerHook('displayBeforeCarrier')
                && $this->registerHook(ThemeCatalogInterface::LIST_MAIL_THEMES_HOOK);
        } else {
            return parent::install()
                && $this->registerHook('displayBackOfficeHeader')
                && $this->registerHook('header')
                && $this->registerHook('displayPaymentTop')
                && $this->registerHook('displayBeforeCarrier');
        }
    }

    public function uninstall()
    {
        Configuration::deleteByName('KL_WHISTLEBLOWER_LIVE_MODE');

        if (version_compare(_PS_VERSION_, '1.7.7.0', '>=')) {
            return parent::uninstall()
                && $this->unregisterHook('displayBackOfficeHeader')
                && $this->unregisterHook('header')
                && $this->unregisterHook('displayPaymentTop')
                && $this->unregisterHook('displayBeforeCarrier')
                && $this->unregisterHook(ThemeCatalogInterface::LIST_MAIL_THEMES_HOOK);
        } else {
            return parent::uninstall()
                && $this->unregisterHook('displayBackOfficeHeader')
                && $this->unregisterHook('header')
                && $this->unregisterHook('displayPaymentTop')
                && $this->unregisterHook('displayBeforeCarrier');
        }
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        if (((bool) Tools::isSubmit('submitKl_whistleblowerModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');

        return $this->renderForm() . $output;
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitKl_whistleblowerModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFormValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$this->getConfigForm()]);
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        $paymentModules = Module::getPaymentModules();
        $paymentOptions = [];

        foreach ($paymentModules as $module) {
            $paymentOptions[] = [
                'type' => 'switch',
                'tab' => 'payment',
                'label' => $this->l($module['name']),
                'name' => 'KL_' . Tools::strtoupper($module['name']) . '_UNAVAILABLE',
                'is_bool' => true,
                'values' => [
                    [
                        'id' => 'active_on',
                        'value' => true,
                        'label' => $this->l('Enabled'),
                    ],
                    [
                        'id' => 'active_off',
                        'value' => false,
                        'label' => $this->l('Disabled'),
                    ],
                ],
            ];
        }

        $form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Alerts settings'),
                    'icon' => 'icon-cogs',
                ],
                'tabs' => [
                    'config' => $this->l('Settings'),
                    'payment' => $this->l('Payment'),
                    'carrier' => $this->l('Carrier'),
                ],
                'input' => [
                    [
                        'col' => 3,
                        'type' => 'text',
                        'tab' => 'config',
                        'label' => $this->l('E-mail'),
                        'name' => 'KL_EMAILS_FIELD',
                        'desc' => $this->l('Enter the email address to receive alerts.'),
                    ],
                    [
                        'type' => 'html',
                        'label' => '',
                        'name' => 'HTML',
                        'tab' => 'payment',
                        'html_content' => '<hr/><h3>' . $this->l('Watch all payment methods') . '</h3>',
                    ],
                    [
                        'type' => 'switch',
                        'tab' => 'payment',
                        'label' => $this->l('No payment method available'),
                        'name' => 'KL_PAYMENT_UNAVAILABLE',
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'html',
                        'label' => '',
                        'name' => 'HTML',
                        'tab' => 'payment',
                        'html_content' => '<hr/><h3>' . $this->l('Select the payment methods to be watched') . '</h3>',
                    ],
                    [
                        'type' => 'html',
                        'label' => '',
                        'name' => 'HTML',
                        'tab' => 'carrier',
                        'html_content' => '<hr/><h3>' . $this->l('Watch all carrier') . '</h3>',
                    ],
                    [
                        'type' => 'switch',
                        'tab' => 'carrier',
                        'label' => $this->l('No carrier available'),
                        'name' => 'KL_CARRIERS_UNAVAILABLE',
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];

        foreach ($paymentOptions as $paymentOption) {
            $form['form']['input'][] = $paymentOption;
        }

        return $form;
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        $fields = [
            'KL_PAYMENT_UNAVAILABLE',
            'KL_CARRIERS_UNAVAILABLE',
            'KL_EMAILS_FIELD',
        ];

        $values = [];

        foreach ($fields as $key) {
            $values[$key] = Configuration::get($key, true);
        }

        $paymentModules = Module::getPaymentModules();
        foreach ($paymentModules as $module) {
            $moduleKey = 'KL_' . Tools::strtoupper($module['name']) . '_UNAVAILABLE';
            $values[$moduleKey] = Configuration::get($moduleKey, true);
        }

        $emailsField = Configuration::get('KL_EMAILS_FIELD', true);
        if (empty($emailsField)) {
            $values['KL_EMAILS_FIELD'] = Configuration::get('PS_SHOP_EMAIL');
        }

        return $values;
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    public function hookDisplayBackOfficeHeader()
    {
        if (Tools::getValue('configure') == $this->name) {
            $this->context->controller->addCSS($this->_path . 'views/css/back.css');
        }
    }

    public function hookHeader()
    {
        $isCarrierAvailable = Configuration::get('KL_CARRIERS_UNAVAILABLE');

        if ($isCarrierAvailable && Tools::getValue("controller") == "order") {
            $this->context->controller->addJS($this->_path . '/views/js/whistleblower.js');
        }
    }

    /**
     * If Prestashop version >= 1.7.7.0, add custom mails layouts
     */
    public function hookActionListMailThemes(array $hookParams)
    {
        if (!isset($hookParams['mailThemes'])) {
            return;
        }

        /** @var ThemeCollectionInterface $themes */
        $themes = $hookParams['mailThemes'];

        /** @var ThemeInterface $theme */
        foreach ($themes as $theme) {
            if (!in_array($theme->getName(), ['classic', 'modern'])) {
                continue;
            }

            $pathPayment = '@Modules/' . $this->name . '/mails/layouts/kl_' . $theme->getName() . '_payment_unavailable.html.twig';
            $pathCarrier = '@Modules/' . $this->name . '/mails/layouts/kl_' . $theme->getName() . '_carrier_unavailable.html.twig';

            $theme->getLayouts()->add(new Layout(
                'kl_payment_unavailable',
                $pathPayment,
                '',
                $this->name
            ));

            $theme->getLayouts()->add(new Layout(
                'kl_carrier_unavailable',
                $pathCarrier,
                '',
                $this->name
            ));
        }
    }

    public function hookDisplayPaymentTop()
    {
        $paymentModules = Module::getPaymentModules();

        $configKeys = [];

        $configKeys['KL_PAYMENT_UNAVAILABLE'] = '';

        foreach ($paymentModules as $module) {
            $configKeys['KL_' . Tools::strtoupper($module['name']) . '_UNAVAILABLE'] = $module['name'];
        }

        foreach ($configKeys as $configKey => $moduleName) {
            $isPaymentUnavailable = Configuration::get($configKey);
            if ($isPaymentUnavailable) {
                $this->verifyPaymentsUnavailable($moduleName);
            }
        }
    }

    /**
     * Are payment methods available to customers?
     *
     * @param string $module
     */
    public function verifyPaymentsUnavailable($module)
    {
        $payment = new PaymentOptionsFinder();
        $paymentOptions = $payment->find();

        if (empty($paymentOptions)) {
            PrestaShopLogger::addLog(
                'No payment method available',
                3,
                null,
                null,
                null
            );
            $this->mailAlertPaymentUnavailable($module);
        }

        $moduleId = Module::getModuleIdByName($module);

        if ($moduleId && Module::isEnabled($module)) {
            if (!array_key_exists($module, $paymentOptions)) {
                PrestaShopLogger::addLog(
                    'Payment module ' . $module . ' unavailable',
                    3,
                    null,
                    null,
                    null
                );
                $this->mailAlertPaymentUnavailable($module);
            }
        }
    }

    /**
     * Send an e-mail if no payment/ method payment to check is proposed
     *
     * @param string $module
     */
    public function mailAlertPaymentUnavailable($module)
    {
        $email1 = Configuration::get('KL_EMAILS_FIELD');
        $email2 = Configuration::get('PS_SHOP_EMAIL');
        $recipients = [$email2, $email1];

        $template = 'kl_payment_unavailable';
        $templatePath = _PS_MODULE_DIR_ . 'kl_whistleblower'
            . DIRECTORY_SEPARATOR . 'mails';

        $subject = $this->l('Alert - Payment module unavailable');

        if ($module === '') {
            $message = $this->l('No payment method available.');
        } else {
            $message = $this->l('Payment module *' . $module . ' * is enabled but not proposed to the customer.');
        }
        $cartId = $this->context->cart->id;
        $customerId = $this->context->customer->id;
        $currencyId = $this->context->currency->id;
        $data = [
            '{message}' => $message,
            '{cart_id}' => $cartId,
            '{customer_id}' => $customerId,
            '{currency_id}' => $currencyId,
        ];

        try {
            foreach ($recipients as $recipient) {
                Mail::send(
                    (int) $this->context->language->id,
                    $template,
                    $subject,
                    $data,
                    $recipient,
                    null,
                    $email1,
                    null,
                    null,
                    null,
                    $templatePath,
                    true
                );
            }
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'Error sending alert email : ' . $e->getMessage(),
                3,
                0,
                null,
                null
            );

            return false;
        }

        return true;
    }

    public function hookDisplayBeforeCarrier()
    {
        $isCarrierAvailable = Configuration::get('KL_CARRIERS_UNAVAILABLE');

        if ($isCarrierAvailable) {
            $this->verifyCarrierUnavailable();
        }
    }

    /**
     * Are carriers available to customers?
     */
    public function verifyCarrierUnavailable()
    {
        $deliveryOption = Context::getContext()->cart->delivery_option;

        if (empty($deliveryOption)) {
            PrestaShopLogger::addLog(
                'No carrier method available',
                3,
                null,
                null,
                null
            );
            $this->mailAlertCarrierUnavailable();
        }
    }

    /**
     * Send an e-mail if no carrier is proposed
     */
    public function mailAlertCarrierUnavailable()
    {
        $email1 = Configuration::get('KL_EMAILS_FIELD');
        $email2 = Configuration::get('PS_SHOP_EMAIL');
        $recipients = [$email2, $email1];

        $template = 'kl_carrier_unavailable';
        $templatePath = _PS_MODULE_DIR_ . 'kl_whistleblower'
            . DIRECTORY_SEPARATOR . 'mails';

        $subject = $this->l('Alert - No carrier method available');
        $message = $this->l('No carrier method available');
        $cartId = $this->context->cart->id;
        $customerId = $this->context->customer->id;
        $deliveryAddressId = $this->context->cart->id_address_delivery;
        $invoiceAddressId = $this->context->cart->id_address_invoice;
        $currencyId = $this->context->currency->id;
        $data = [
            '{message}' => $message,
            '{cart_id}' => $cartId,
            '{customer_id}' => $customerId,
            '{delivery_address_id}' => $deliveryAddressId,
            '{invoice_address_id}' => $invoiceAddressId,
            '{currency_id}' => $currencyId,
        ];

        try {
            foreach ($recipients as $recipient) {
                Mail::send(
                    (int) $this->context->language->id,
                    $template,
                    $subject,
                    $data,
                    $recipient,
                    null,
                    $email1,
                    null,
                    null,
                    null,
                    $templatePath,
                    true
                );
            }
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'Error sending alert email : ' . $e->getMessage(),
                3,
                0,
                null,
                null
            );

            return false;
        }

        return true;
    }
}
