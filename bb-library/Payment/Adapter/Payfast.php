<?php

/**
 * BoxBilling.
 *
 * @version   $Id$
 */
require_once __DIR__ . '/vendor/autoload.php';

use Payfast\PayfastCommon\PayfastCommon;

class Payment_Adapter_Payfast
{
    private $config = [];

    public function __construct($config)
    {
        $this->config = $config;

        if (!function_exists('curl_exec')) {
            throw new InvalidArgumentException('PHP Curl extension must be enabled in order to use Payfast gateway');
        }

        if (!$this->config['merchantId'] || !$this->config['merchantKey']) {
            throw new InvalidArgumentException(
                'Payment gateway "Payfast" is not configured properly.'
                . ' Please update configuration parameter "Payfast Merchant ID and Key" at "Configuration -> Payments".'
            );
        }
    }

    public static function getConfig()
    {
        return [
            'supports_one_time_payments' => true,
            'supports_subscriptions'     => false,
            'description'                => 'Enter your Payfast merchant id and key to start accepting payments by Payfast.',
            'form'                       => [
                'merchantId'  => [
                    'text',
                    [
                        'label' => 'Payfast merchant id for payments',
                    ],
                ],
                'merchantKey' => [
                    'text',
                    [
                        'label' => 'Payfast merchant key for payments',
                    ],
                ],
                'passphrase'  => [
                    'text',
                    [
                        'label'    => 'Payfast Passphrase: only enter a passphrase if it is set on your Payfast account.',
                        'required' => false,
                    ],
                ],
                'debug'       => [
                    'select',
                    [
                        'multiOptions' => [
                            '0' => 'Off',
                            '1' => 'On',
                        ],
                        'label'        => 'Debug Mode',
                    ],
                ],
            ],
        ];
    }

    public function getHtml($api_admin, $invoice_id)
    {
        $invoice = $api_admin->invoice_get(['id' => $invoice_id]);

        $p      = [
            ':id'    => sprintf('%05s', $invoice['nr']),
            ':serie' => $invoice['serie'],
            ':title' => $invoice['lines'][0]['title'],
        ];
        $title  = __('Payment for invoice :serie:id [:title]', $p);
        $number = $invoice['nr'];

        $data                 = [];
        $data['merchant_id']  = trim($this->config['merchantId']);
        $data['merchant_key'] = trim($this->config['merchantKey']);

        if ($this->config['test_mode']) {
            $url = 'https://sandbox.payfast.co.za/eng/process';
        } else {
            $url = 'https://www.payfast.co.za/eng/process';
        }

        $data['return_url']   = $this->config['return_url'];
        $data['cancel_url']   = $this->config['cancel_url'];
        $data['notify_url']   = $this->config['notify_url'];
        $data['m_payment_id'] = $number;
        $data['amount']       = $this->moneyFormat($invoice['total'], $invoice['currency']);
        $data['item_name']    = $title;
        $data['custom_str1']  = 'PayFast_BoxBilling' . '_' . '4.22' . '_' . '1.2.0';

        $pfOutput = '';
        // Create output string
        foreach ($data as $key => $val) {
            $pfOutput .= $key . '=' . urlencode(trim($val)) . '&';
        }

        $passPhrase = trim($this->config['passphrase']);
        if (empty($passPhrase)) {
            $pfOutput = substr($pfOutput, 0, -1);
        } else {
            $pfOutput = $pfOutput . 'passphrase=' . urlencode($passPhrase);
        }

        $data['signature'] = md5($pfOutput);

        $form = '<form name="payment_form" action="' . $url . '" method="post">' . PHP_EOL;
        foreach ($data as $key => $value) {
            $form .= sprintf('<input type="hidden" name="%s" value="%s" />', $key, $value) . PHP_EOL;
        }
        $form .= '<input class="bb-button bb-button-submit" type="submit" value="Pay with Payfast" id="payment_button"/>' . PHP_EOL;
        $form .= '</form>' . PHP_EOL . PHP_EOL;

        if (isset($this->config['auto_redirect']) && $this->config['auto_redirect']) {
            $form .= sprintf('<h2>%s</h2>', __('Redirecting to Payfast'));
            $form .= "<script type='text/javascript'>$(document).ready(function(){    document.getElementById('payment_button').style.display = 'none';    document.forms['payment_form'].submit();});</script>";
        }

        return $form;
    }

    public function processTransaction($api_admin, $id, $data)
    {
        // Variable Initialization
        $pfData        = $data['post'];
        $pfError       = false;
        $pfErrMsg      = '';
        $pfDone        = false;
        $pfParamString = '';

        // Notify Payfast that information has been received
        if (!$pfError && !$pfDone) {
            header('HTTP/1.0 200 OK');
            flush();
        }

        $tx = $api_admin->invoice_transaction_get(['id' => $id]);

        if (!$tx['invoice_id']) {
            $api_admin->invoice_transaction_update(['id' => $id, 'invoice_id' => $data['get']['bb_invoice_id']]);
        }

        if (!$tx['type']) {
            $api_admin->invoice_transaction_update(['id' => $id, 'type' => 'payfast_payment']);
        }

        if (!$tx['txn_id']) {
            $api_admin->invoice_transaction_update(['id' => $id, 'txn_id' => $pfData['pf_payment_id']]);
        }

        if (!$tx['txn_status']) {
            $api_admin->invoice_transaction_update(['id' => $id, 'txn_status' => $pfData['payment_status']]);
        }

        if (!$tx['amount']) {
            $api_admin->invoice_transaction_update(['id' => $id, 'amount' => $pfData['amount_gross']]);
        }

        if (!$tx['currency']) {
            $api_admin->invoice_transaction_update(['id' => $id, 'currency' => 'ZAR']);
        }

        $invoice = $api_admin->invoice_get(['id' => $data['get']['bb_invoice_id']]);

        $client_id = $invoice['client']['id'];

        $pfHost = ($this->config['test_mode'] ? 'sandbox' : 'www') . '.payfast.co.za';

        $payfastCommon = new PayfastCommon($this->isDebugMode());

        $payfastCommon->pflog('Payfast ITN call received');

        $this->processTransactionStepB(
            $pfError,
            $pfErrMsg,
            $pfHost,
            $pfDone,
            $pfParamString,
            $invoice,
            $pfData
        );
        $this->processTransactionStepC(
            $pfError,
            $pfDone,
            $pfData,
            $client_id,
            $api_admin,
            $pfErrMsg,
            $id
        );
    }

    public function processTransactionStepB(
        $pfError,
        $pfErrMsg,
        $pfHost,
        $pfDone,
        $pfParamString,
        $invoice,
        $pfData
    ): void {
        $payfastCommon = new PayfastCommon($this->isDebugMode());
        // Verify security signature
        if (!$pfError) {
            $payfastCommon->pflog('Verify security signature');

            $passPhrase   = trim($this->config['passphrase']);
            $pfPassPhrase = empty($passPhrase) ? null : $passPhrase;

            $payfastCommon->pflog('$pfPassPhrase ' . $pfPassPhrase);

            // If signature different, log for debugging
            if (!$payfastCommon->pfValidSignature($pfData, $pfParamString, $pfPassPhrase)) {
                $pfError  = true;
                $pfErrMsg = $payfastCommon::PF_ERR_INVALID_SIGNATURE;
            }
        }

        // Verify data received
        if (!$pfError) {
            $payfastCommon->pflog('Verify data received');

            $moduleInfo = [
                "pfSoftwareName"       => 'BoxBilling',
                "pfSoftwareVer"        => '4.22',
                "pfSoftwareModuleName" => 'PayFast_BoxBilling',
                "pfModuleVer"          => '1.2.0',
            ];

            $pfValid = $payfastCommon->pfValidData($moduleInfo, $pfHost, $pfParamString);

            if (!$pfValid) {
                $pfError  = true;
                $pfErrMsg = $payfastCommon::PF_ERR_BAD_ACCESS;
            }
        }

        // Check data against internal order
        if (!$pfError && !$pfDone) {
            $payfastCommon->pflog('Check data against internal order');

            // Check order amount
            if (!$payfastCommon->pfAmountsEqual($pfData['amount_gross'], number_format($invoice['total'], 2))) {
                $pfError  = true;
                $pfErrMsg = $payfastCommon::PF_ERR_AMOUNT_MISMATCH;
            }
        }
    }

    public function processTransactionStepC(
        $pfError,
        $pfDone,
        $pfData,
        $client_id,
        $api_admin,
        $pfErrMsg,
        $id
    ): void {
        $payfastCommon = new PayfastCommon($this->isDebugMode());
        // Check status and update order
        if (!$pfError && !$pfDone) {
            $payfastCommon->pflog('Check status and update order');

            switch ($pfData['payment_status']) {
                case 'COMPLETE':
                    $payfastCommon->pflog('- Complete');

                    $bd = [
                        'id'          => $client_id,
                        'amount'      => $pfData['amount_gross'],
                        'description' => 'Payfast transaction ' . $pfData['pf_payment_id'],
                        'type'        => 'Payfast',
                        'rel_id'      => $pfData['pf_payment_id'],
                    ];
                    $api_admin->client_balance_add_funds($bd);
                    $api_admin->invoice_batch_pay_with_credits(['client_id' => $client_id]);

                    break;

                case 'FAILED':
                    $payfastCommon->pflog('- Failed');

                    break;

                case 'PENDING':
                    $payfastCommon->pflog('- Pending');

                    // Need to wait for "Completed" before processing
                    break;

                default:
                    // If unknown status, do nothing (safest course of action)
                    break;
            }
        }

        // If an error occurred
        if ($pfError) {
            $payfastCommon->pflog('Error occurred: ' . $pfErrMsg);
            throw new InvalidArgumentException('ITN is not valid: ' . $pfErrMsg);
        }

        $d = [
            'id'         => $id,
            //    'error'     => '',
            'error_code' => '',
            'status'     => 'processed',
            'updated_at' => date('c'),
        ];
        $api_admin->invoice_transaction_update($d);
    }

    /**
     * @return bool
     */
    public function isDebugMode(): bool
    {
        return trim($this->config['debug']) === '1';
    }

    private function moneyFormat($amount, $currency)
    {
        // HUF currency do not accept decimal values
        if ('HUF' == $currency) {
            return number_format($amount, 0);
        }

        return number_format($amount, 2, '.', '');
    }
}
