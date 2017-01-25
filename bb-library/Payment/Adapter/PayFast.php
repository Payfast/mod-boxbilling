<?php
/**
 * BoxBilling
 *
 * LICENSE
 *
 * This source file is subject to the license that is bundled
 * with this package in the file LICENSE.txt
 * It is also available through the world-wide-web at this URL:
 * http://www.boxbilling.com/LICENSE.txt
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@boxbilling.com so we can send you a copy immediately.
 *
 * @copyright Copyright (c) 2010-2012 BoxBilling (http://www.boxbilling.com)
 * @license   http://www.boxbilling.com/LICENSE.txt
 * @version   $Id$
 */
class Payment_Adapter_PayFast
{
    private $config = array();

    const SANDBOX_MERCHANT_KEY = '46f0cd694581a';
    const SANDBOX_MERCHANT_ID = '10000100';

    public function __construct($config)
    {
        $this->config = $config;

        if(!function_exists('curl_exec')) {
            throw new Exception('PHP Curl extension must be enabled in order to use PayFast gateway');
        }

        if( !$this->config['merchantId'] || !$this->config['merchantKey']) {
            throw new Exception('Payment gateway "PayFast" is not configured properly. Please update configuration parameter "PayFast Merchant ID and Key" at "Configuration -> Payments".');
        }
    }

    public static function getConfig()
    {
        return array(
            'supports_one_time_payments'   =>  true,
            'supports_subscriptions'     =>  false,
            'description'     =>  'Enter your PayFast merchant id and key to start accepting payments by PayFast.',
            'form'  => array(
                'merchantId' => array('text', array(
                    'label' => 'PayFast merchant id for payments'
                )
                ),
                'merchantKey' => array('text', array(
                    'label' => 'PayFast merchant key for payments'
                ),
                ),
                'passphrase' => array('text', array(
                    'label' => 'PayFast Passphrase: only enter a passphrase if it is set on your PayFast account.',
                    'required' => false,
                ),
                ),
                'debug' => array('select', array(
                    'multiOptions' => array(
                        '0' => 'Off',
                        '1' => 'On'
                    ),
                    'label' => 'Debug Mode'
                ),
                ),
            ),
        );
    }

    public function getHtml($api_admin, $invoice_id, $subscription)
    {
        $invoice = $api_admin->invoice_get(array('id'=>$invoice_id));
        $buyer = $invoice['buyer'];

        $p = array(
            ':id'=>sprintf('%05s', $invoice['nr']),
            ':serie'=>$invoice['serie'],
            ':title'=>$invoice['lines'][0]['title']
        );
        $title = __('Payment for invoice :serie:id [:title]', $p);
        $number = $invoice['nr'];

        $data = array();

        if($this->config['test_mode'])
        {
            $url = 'https://sandbox.payfast.co.za/eng/process';
            $data['merchant_id'] = self::SANDBOX_MERCHANT_ID;
            $data['merchant_key'] = self::SANDBOX_MERCHANT_KEY;
        }
        else
        {
            $url = 'https://www.payfast.co.za/eng/process';

            $data['merchant_id'] = $this->config['merchantId'];
            $data['merchant_key'] = $this->config['merchantKey'];
        }

        $data['return_url']      = $this->config['return_url'];
        $data['cancel_url']      = $this->config['cancel_url'];
        $data['notify_url']      = $this->config['notify_url'];
        $data['m_payment_id']    = $number;
        $data['amount']          = $this->moneyFormat($invoice['total'], $invoice['currency']);
        $data['item_name']       = $title;


        $pfOutput = '';
        // Create output string
        foreach( $data as $key => $val )
            $pfOutput .= $key .'='. urlencode( trim( $val ) ) .'&';

        $passPhrase = $this->config['passphrase'];
        if( empty( $passPhrase ) || ( $this->config['test_mode'] ) )
        {
            $pfOutput = substr( $pfOutput, 0, -1 );
        }
        else
        {
            $pfOutput = $pfOutput."passphrase=".urlencode( $passPhrase );
        }

        $data['signature'] = md5( $pfOutput );
        $data['user_agent'] = 'BoxBilling 4.x';

        $form = '<form name="payment_form" action="'.$url.'" method="post">' . PHP_EOL;
        foreach($data as $key => $value)
        {
            $form .= sprintf('<input type="hidden" name="%s" value="%s" />', $key, $value) . PHP_EOL;
        }
        $form .=  '<input class="bb-button bb-button-submit" type="submit" value="Pay with PayFast" id="payment_button"/>'. PHP_EOL;
        $form .=  '</form>' . PHP_EOL . PHP_EOL;

        if(isset($this->config['auto_redirect']) && $this->config['auto_redirect'])
        {
            $form .= sprintf('<h2>%s</h2>', __('Redirecting to PayFast.com'));
            $form .= "<script type='text/javascript'>$(document).ready(function(){    document.getElementById('payment_button').style.display = 'none';    document.forms['payment_form'].submit();});</script>";
        }

        return $form;
    }

    public function processTransaction($api_admin, $id, $data, $gateway_id)
    {
        $result = $api_admin->system_env();

        define( 'PF_DEBUG', $this->config['debug'] );

        define( 'PF_SOFTWARE_NAME', 'BoxBilling' );
        define( 'PF_SOFTWARE_VER', $result['bb']['version'] );
        define( 'PF_MODULE_NAME', 'PayFast-BoxBilling' );
        define( 'PF_MODULE_VER', '1.1.2' );

        // Features
        // - PHP
        $pfFeatures = 'PHP '. phpversion() .';';

        // - cURL
        if( in_array( 'curl', get_loaded_extensions() ) )
        {
            define( 'PF_CURL', '' );
            $pfVersion = curl_version();
            $pfFeatures .= ' curl '. $pfVersion['version'] .';';
        }
        else
            $pfFeatures .= ' nocurl;';

        // Create user agrent
        define( 'PF_USER_AGENT', PF_SOFTWARE_NAME .'/'. PF_SOFTWARE_VER .' ('. trim( $pfFeatures ) .') '. PF_MODULE_NAME .'/'. PF_MODULE_VER );

        // General Defines
        define( 'PF_TIMEOUT', 15 );
        define( 'PF_EPSILON', 0.01 );

        // Messages
        // Error
        define( 'PF_ERR_AMOUNT_MISMATCH', 'Amount mismatch' );
        define( 'PF_ERR_BAD_ACCESS', 'Bad access of page' );
        define( 'PF_ERR_BAD_SOURCE_IP', 'Bad source IP address' );
        define( 'PF_ERR_CONNECT_FAILED', 'Failed to connect to PayFast' );
        define( 'PF_ERR_INVALID_SIGNATURE', 'Security signature mismatch' );
        define( 'PF_ERR_MERCHANT_ID_MISMATCH', 'Merchant ID mismatch' );
        define( 'PF_ERR_NO_SESSION', 'No saved session found for ITN transaction' );
        define( 'PF_ERR_ORDER_ID_MISSING_URL', 'Order ID not present in URL' );
        define( 'PF_ERR_ORDER_ID_MISMATCH', 'Order ID mismatch' );
        define( 'PF_ERR_ORDER_INVALID', 'This order ID is invalid' );
        define( 'PF_ERR_ORDER_PROCESSED', 'This order has already been processed' );
        define( 'PF_ERR_PDT_FAIL', 'PDT query failed' );
        define( 'PF_ERR_PDT_TOKEN_MISSING', 'PDT token not present in URL' );
        define( 'PF_ERR_SESSIONID_MISMATCH', 'Session ID mismatch' );
        define( 'PF_ERR_UNKNOWN', 'Unkown error occurred' );

        // General
        define( 'PF_MSG_OK', 'Payment was successful' );
        define( 'PF_MSG_FAILED', 'Payment has failed' );
        define( 'PF_MSG_PENDING',
            'The payment is pending. Please note, you will receive another Instant'.
            ' Transaction Notification when the payment status changes to'.
            ' "Completed", or "Failed"' );

        /**
         * pflog
         *
         * Log function for logging output.
         *
         * @author Jonathan Smit
         * @param $msg String Message to log
         * @param $close Boolean Whether to close the log file or not
         */
        function pflog( $msg = '', $close = false )
        {
            static $fh = 0;
            global $module;

            // Only log if debugging is enabled
            if( true )
            {
                if( $close )
                {
                    fclose( $fh );
                }
                else
                {
                    // If file doesn't exist, create it
                    if( !$fh )
                    {
                        $pathinfo = pathinfo( __FILE__ );
                        $fh = fopen( $pathinfo['dirname'] .'/payfast.log', 'a+' );
                    }

                    // If file was successfully created
                    if( $fh )
                    {
                        $line = date( 'Y-m-d H:i:s' ) .' : '. $msg ."\n";

                        fwrite( $fh, $line );
                    }
                }
            }
        }


        /**
         * pfValidSignature
         *
         * @author Jonathan Smit
         */
        function pfValidSignature( $pfData = null, &$pfParamString = null, $passPhrase = null )
        {
            // Dump the submitted variables and calculate security signature
            foreach( $pfData as $key => $val )
            {
                if( $key != 'signature' )
                {
                    $pfParamString .= $key .'='. urlencode( $val ) .'&';
                }
                else
                {
                    break;
                }
            }

            // Remove the last '&' from the parameter string
            $pfParamString = substr( $pfParamString, 0, -1 );
            if( is_null( $passPhrase ) || ( $this->config['test_mode'] ) )
            {
                $tempParamString = $pfParamString;
            }
            else
            {
                $tempParamString = $pfParamString."&passphrase=".urlencode( $passPhrase );
            }

            $signature = md5( $tempParamString );

            $result = ( $pfData['signature'] == $signature );

            pflog( 'Signature = '. ( $result ? 'valid' : 'invalid' ) );

            return( $result );
        }

        /**
         * pfValidData
         *
         * @author Jonathan Smit
         * @param $pfHost String Hostname to use
         * @param $pfParamString String
         */
        function pfValidData( $pfHost = 'www.payfast.local', $pfParamString = '' )
        {
            pflog( 'Host = '. $pfHost );
            pflog( 'Params = '. $pfParamString );

            // Use cURL (if available)
            if( defined( 'PF_CURL' ) )
            {
                // Variable initialization
                $url = 'https://'. $pfHost .'/eng/query/validate';

                // Create default cURL object
                $ch = curl_init();

                // Set cURL options - Use curl_setopt for freater PHP compatibility
                // Base settings
                curl_setopt( $ch, CURLOPT_USERAGENT, PF_USER_AGENT );  // Set user agent
                curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );      // Return output as string rather than outputting it
                curl_setopt( $ch, CURLOPT_HEADER, false );             // Don't include header in output
                curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 2 );
                curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );

                // Standard settings
                curl_setopt( $ch, CURLOPT_URL, $url );
                curl_setopt( $ch, CURLOPT_POST, true );
                curl_setopt( $ch, CURLOPT_POSTFIELDS, $pfParamString );
                curl_setopt( $ch, CURLOPT_TIMEOUT, PF_TIMEOUT );

                // Execute CURL
                $response = curl_exec( $ch );
                curl_close( $ch );
            }
            // Use fsockopen
            else
            {
                // Variable initialization
                $header = '';
                $res = '';
                $headerDone = false;

                // Construct Header
                $header = "POST /eng/query/validate HTTP/1.0\r\n";
                $header .= "Host: ". $pfHost ."\r\n";
                $header .= "User-Agent: ". PF_USER_AGENT ."\r\n";
                $header .= "Content-Type: application/x-www-form-urlencoded\r\n";
                $header .= "Content-Length: " . strlen( $pfParamString ) . "\r\n\r\n";

                // Connect to server
                $socket = fsockopen( 'ssl://'. $pfHost, 443, $errno, $errstr, PF_TIMEOUT );

                // Send command to server
                fputs( $socket, $header . $pfParamString );

                // Read the response from the server
                while( !feof( $socket ) )
                {
                    $line = fgets( $socket, 1024 );

                    // Check if we are finished reading the header yet
                    if( strcmp( $line, "\r\n" ) == 0 )
                    {
                        // read the header
                        $headerDone = true;
                    }
                    // If header has been processed
                    else if( $headerDone )
                    {
                        // Read the main response
                        $response .= $line;
                    }
                }

            }

            pflog( "Response:\n". print_r( $response, true ) );

            // Interpret Response
            $lines = explode( "\r\n", $response );
            $verifyResult = trim( $lines[0] );

            if( strcasecmp( $verifyResult, 'VALID' ) == 0 )
                return( true );
            else
                return( false );
        }

        /**
         * pfValidIP
         *
         * @author Jonathan Smit
         * @param $sourceIP String Source IP address
         */
        function pfValidIP( $sourceIP )
        {
            // Variable initialization
            $validHosts = array(
                'www.payfast.local',
                'sandbox.payfast.local',
                'w1w.payfast.co.za',
                'w2w.payfast.co.za',
            );

            $validIps = array();

            foreach( $validHosts as $pfHostname )
            {
                $ips = gethostbynamel( $pfHostname );

                if( $ips !== false )
                    $validIps = array_merge( $validIps, $ips );
            }

            // Remove duplicates
            $validIps = array_unique( $validIps );

            pflog( "Valid IPs:\n". print_r( $validIps, true ) );

            if( in_array( $sourceIP, $validIps ) )
                return( true );
            else
                return( false );
        }

        /**
         * pfAmountsEqual
         *
         * Checks to see whether the given amounts are equal using a proper floating
         * point comparison with an Epsilon which ensures that insignificant decimal
         * places are ignored in the comparison.
         *
         * eg. 100.00 is equal to 100.0001
         *
         * @author Jonathan Smit
         * @param $amount1 Float 1st amount for comparison
         * @param $amount2 Float 2nd amount for comparison
         */
        function pfAmountsEqual( $amount1, $amount2 )
        {
            if( abs( floatval( $amount1 ) - floatval( $amount2 ) ) > PF_EPSILON )
                return( false );
            else
                return( true );
        }

        // Variable Initialization
        $pfData = $data['post'];
        $pfError = false;
        $pfErrMsg = '';
        $pfDone = false;
        $pfOrderId = '';
        $pfParamString = '';

        //// Notify PayFast that information has been received
        if( !$pfError && !$pfDone )
        {
            header( 'HTTP/1.0 200 OK' );
            flush();
        }


        $tx = $api_admin->invoice_transaction_get(array('id'=>$id));

        if(!$tx['invoice_id']) {
            $api_admin->invoice_transaction_update(array('id'=>$id, 'invoice_id'=>$data['get']['bb_invoice_id']));
        }

        if(!$tx['type']) {
            $api_admin->invoice_transaction_update(array('id'=>$id, 'type'=>'payfast_payment'));
        }

        if(!$tx['txn_id']) {
            $api_admin->invoice_transaction_update(array('id'=>$id, 'txn_id'=>$pfData['pf_payment_id']));
        }

        if(!$tx['txn_status']) {
            $api_admin->invoice_transaction_update(array('id'=>$id, 'txn_status'=>$pfData['payment_status']));
        }

        if(!$tx['amount']) {
            $api_admin->invoice_transaction_update(array('id'=>$id, 'amount'=>$pfData['amount_gross']));
        }

        if(!$tx['currency']) {
            $api_admin->invoice_transaction_update(array('id'=>$id, 'currency'=>'ZAR'));
        }

        $invoice = $api_admin->invoice_get(array('id'=>$data['get']['bb_invoice_id']));

        $client_id = $invoice['client']['id'];


        $pfHost = ( $this->config['test_mode'] ? 'sandbox' : 'www' ) . '.payfast.local';

        pflog( 'PayFast ITN call received' );

        //// Verify security signature
        if( !$pfError && !$pfDone )
        {
            pflog( 'Verify security signature' );

            $passPhrase = $this->config['passphrase'];
            $pfPassPhrase = is_null( $passPhrase ) || $this->config['test_mode'] ? null : $passPhrase;

            // If signature different, log for debugging
            if( !pfValidSignature( $pfData, $pfParamString, $pfPassPhrase ) )
            {
                $pfError = true;
                $pfErrMsg = PF_ERR_INVALID_SIGNATURE;
            }
        }

        //// Verify source IP (If not in debug mode)
        if( !$pfError && !$pfDone )
        {
            pflog( 'Verify source IP' );

            if( !pfValidIP( $_SERVER['REMOTE_ADDR'] ) )
            {
                $pfError = true;
                $pfErrMsg = PF_ERR_BAD_SOURCE_IP;
            }
        }

        //// Verify data received
        if( !$pfError )
        {
            pflog( 'Verify data received' );

            $pfValid = pfValidData( $pfHost, $pfParamString );

            if( !$pfValid )
            {
                $pfError = true;
                $pfErrMsg = PF_ERR_BAD_ACCESS;
            }
        }

        //// Check data against internal order
        if( !$pfError && !$pfDone )
        {
            pflog( 'Check data against internal order' );

            // Check order amount
            if( !pfAmountsEqual( $pfData['amount_gross'], number_format($invoice['total'],2) ) )
            {
                $pfError = true;
                $pfErrMsg = PF_ERR_AMOUNT_MISMATCH;
            }
        }
        //// Check status and update order
        if( !$pfError && !$pfDone )
        {
            pflog( 'Check status and update order' );

            switch( $pfData['payment_status'] )
            {
                case 'COMPLETE':
                    pflog( '- Complete' );

                    $bd = array(
                        'id'            =>  $client_id,
                        'amount'        =>  $pfData['amount_gross'],
                        'description'   =>  'PayFast transaction '.$pfData['pf_payment_id'],
                        'type'          =>  'PayFast',
                        'rel_id'        =>  $pfData['pf_payment_id'],
                    );
                    $api_admin->client_balance_add_funds($bd);
                    $api_admin->invoice_batch_pay_with_credits(array('client_id'=>$client_id));


                    break;

                case 'FAILED':
                    pflog( '- Failed' );

                    break;

                case 'PENDING':
                    pflog( '- Pending' );

                    // Need to wait for "Completed" before processing
                    break;

                default:
                    // If unknown status, do nothing (safest course of action)
                    break;
            }
        }


        // If an error occurred
        if( $pfError )
        {
            pflog( 'Error occurred: '. $pfErrMsg );
            throw new Exception( 'ITN is not valid: '.$pfErrMsg );
        }

        $d = array(
            'id'        => $id,
            //    'error'     => '',
            'error_code'=> '',
            'status'    => 'processed',
            'updated_at'=> date('c'),
        );
        $api_admin->invoice_transaction_update($d);
    }

    private function moneyFormat($amount, $currency)
    {
        //HUF currency do not accept decimal values
        if($currency == 'HUF') {
            return number_format($amount, 0);
        }
        return number_format($amount, 2, '.', '');
    }


}
