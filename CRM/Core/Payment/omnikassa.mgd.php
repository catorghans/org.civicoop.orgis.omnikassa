<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return array (
  0 =>
  array (
    'name' => 'omnikassa',
    'entity' => 'PaymentProcessorType',
    'params' =>
    array (
      'version' => 3,
      'name' => 'omnikassa',
      'title' => 'Rabobank Omnikassa',
      'description' => 'Rabobank Omnikassa Payment Processor',
      'user_name_label' => 'Merchant ID',
      'password_label' => 'Secret Key',
      'signature_label' => 'Key Version',
      'subject_label' => '',
      'class_name' => 'Payment_Omnikassa',
      'billing_mode' => 4,
      'url_site_default' => 'https://payment-webinit.omnikassa.rabobank.nl/paymentServlet',
      'url_site_test_default' => 'https://payment-webinit.simu.omnikassa.rabobank.nl/paymentServlet',
    ),
  ),
);
