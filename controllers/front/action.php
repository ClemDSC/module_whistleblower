<?php

class Kl_whistleblowerActionModuleFrontController extends ModuleFrontController
{
    public function init()
    {
        parent::init();

        switch (Tools::getValue('action')) {
            case 'alertJsCarrier':
                $this->module->mailAlertCarrierUnavailable();
                die();
                break;
        }
    }
}