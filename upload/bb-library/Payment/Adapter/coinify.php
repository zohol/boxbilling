<?php
/**
 * coinify
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

require('CoinifyAPI.php');
require('CoinifyCallback.php');
const coinify_plugin_name = "BoxBilling";
const coinify_plugin_version = "1.2.1";
const COINIFY_SIGNATURE_HEADER_NAME = 'HTTP_X_COINIFY_CALLBACK_SIGNATURE';

class Payment_Adapter_coinify
{

    private $config = [];

    public function __construct($config)
    {
        $this->config = $config;

        if ( ! function_exists('curl_exec')) {
            throw new Exception('PHP Curl extension must be enabled in order to use Coinify gateway');
        }

        if ( ! $this->config['coinify_api_key']) {
            throw new Exception('Payment gateway "Coinify" is not configured properly. Please update configuration parameter "Coinify Invoice API Key" at "Configuration -> Payments".');
        }

        if ( ! $this->config['coinify_api_secret']) {
            throw new Exception('Payment gateway "Coinify" is not configured properly. Please update configuration parameter "Coinify Invoice API Secret" at "Configuration -> Payments".');
        }

        if ( ! $this->config['coinify_secret']) {
            throw new Exception('Payment gateway "Coinify" is not configured properly. Please update configuration parameter "Coinify Secret" at "Configuration -> Payments".');
        }
    }

    public static function getConfig()
    {
        return [
            'supports_one_time_payments' => true,
            'supports_subscriptions'     => false,
            'description'                => 'Enter your Coinify invoice API Key to start accepting payments by Bitcoin.',
            'form'                       => [
                'coinify_api_key'    => [
                    'password',
                    [
                        'label' => 'Coinify invoice API Key for Invoice'
                    ]
                ],
                'coinify_api_secret' => [
                    'password',
                    [
                        'label' => 'Coinify invoice API Secret for Invoice'
                    ]
                ],
                'coinify_secret'     => [
                    'password',
                    [
                        'label' => 'Coinify Secret for callback'
                    ]
                ]
            ]
        ];
    }

    public function getHtml($api_admin, $invoice_id, $subscription)
    {
        $invoice = $api_admin->invoice_get(['id' => $invoice_id]);

        $p = [
            ':id'    => sprintf('%05s', $invoice['nr']),
            ':serie' => $invoice['serie'],
            ':title' => $invoice['lines'][0]['title']
        ];
        $title = __('Payment for invoice :serie:id [:title]', $p);
        $number = $invoice['nr'];
        $description = $title . ' - ' . $number;

        $form = '';

        if ( ! isset($_GET['status'])) {
            $amount = $this->moneyFormat($invoice['total']);
            $currency = $invoice['currency'];
            $plugin_name = coinify_plugin_name;
            $plugin_version = coinify_plugin_version;
            $description = $description;
            $custom = ['invoice_id' => $invoice_id];
            $return_url = $this->config['return_url'];
            $cancel_url = $this->config['cancel_url'];
            $api = new CoinifyAPI($this->config['coinify_api_key'], $this->config['coinify_api_secret']);
            $result = $api->invoiceCreate($amount, $currency, $plugin_name, $plugin_version, $description, $custom,
                null, null, $return_url, $cancel_url);
            $payment_url = $result['data']['payment_url'];
            if (strlen($payment_url) == 0) {
                return 'error';
            }

            $form = '';
            $form .= '<form name="payment_form" action="' . $payment_url . '" method="POST">' . PHP_EOL;
            $form .= '<input class="bb-button bb-button-submit" type="submit" value="Pay with Coinify" id="payment_button"/>' . PHP_EOL;
            $form .= '</form>' . PHP_EOL . PHP_EOL;

            if (isset($this->config['auto_redirect']) && $this->config['auto_redirect']) {
                $form .= sprintf('<h2>%s</h2>', __('Redirecting to coinify'));
                $form .= "<script type='text/javascript'>$(document).ready(function(){ document.getElementById('payment_button').style.display = 'none'; document.forms['payment_form'].submit();});</script>";
            }
        }

        return $form;
    }

    public function processTransaction($api_admin, $id, $data, $gateway_id)
    {
        header('HTTP/1.1 200 OK');

        $body = file_get_contents('php://input');
        $json_body = json_decode($body, true);

        if ($json_body === null) {
            return;
        }

        $resp = $json_body['data'];
        if ( ! array_key_exists(COINIFY_SIGNATURE_HEADER_NAME, $_SERVER)) {
            return;
        }
        $signature = $_SERVER[COINIFY_SIGNATURE_HEADER_NAME];
        $callback = new CoinifyCallback($this->config['coinify_secret']);

        if ( ! $callback->validateCallback($body, $signature) || $resp['state'] !== 'complete') {
            return;
        }
        $invoice_id = intval($resp["custom"]["invoice_id"]);

        $tx = $api_admin->invoice_transaction_get(['id' => $id]);

        if ( ! $tx['invoice_id']) {
            $api_admin->invoice_transaction_update(['id' => $id, 'invoice_id' => $invoice_id]);
        }

        if ( ! $tx['amount']) {
            $api_admin->invoice_transaction_update(['id' => $id, 'amount' => $resp['native']["amount"]]);
        }

        $invoice = $api_admin->invoice_get(['id' => $invoice_id]);
        $client_id = $invoice['client']['id'];

        $transaction_hash = $resp["payments"][0]['txid'];
        $bd = [
            'id'          => $client_id,
            'amount'      => $resp["native"]["amount"],
            'description' => 'coinify transaction ' . $transaction_hash,
            'type'        => 'coinify',
            'rel_id'      => $transaction_hash
        ];
        $api_admin->client_balance_add_funds($bd);
        $api_admin->invoice_batch_pay_with_credits(['client_id' => $client_id]);

        $d = [
            'id'         => $id,
            'error'      => '',
            'error_code' => '',
            'status'     => 'processed',
            'updated_at' => date('c')
        ];
        $api_admin->invoice_transaction_update($d);
    }

    private function moneyFormat($amount, $currency)
    {
        //HUF currency do not accept decimal values
        if ($currency == 'HUF') {
            return number_format($amount, 0);
        }

        return number_format($amount, 2, '.', '');
    }
}
