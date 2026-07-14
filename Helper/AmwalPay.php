<?php

namespace Amwal\Pay\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\RequestInterface;

class AmwalPay extends AbstractHelper
{
    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * Constructor
     *
     * @param Context $context
     * @param RequestInterface $request
     */
    public function __construct(
        Context $context,
        RequestInterface $request
    ) {
        parent::__construct($context);
        $this->request = $request;
    }
    public function HttpRequest($apiPath, $data = array())
    {
        try {
            $payload = json_encode($data);
            $ch = curl_init($apiPath);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'User-Agent: ' . $this->sanitizeVar('HTTP_USER_AGENT', 'SERVER')
            ));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                throw new \Exception('cURL error: ' . curl_error($ch));
            }
            curl_close($ch);
            return json_decode($response, false);
        } catch (\Exception $e) {
            error_log('HttpRequest Error: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Returns the appropriate SmartBox and Webhook URL based on the environment.
     */
    public static function getWebhookUrl($env)
    {
        if ($env == "prod") {
            return 'https://webhook.amwalpg.com/';
        } else if ($env == "uat") {
            return 'https://test.amwalpg.com:14443/';
        } else if ($env == "sit") {
            return 'https://test.amwalpg.com:24443/';
        }
    }
    /**
     * Generates a secure hash for transaction validation.
     */
    public static function generateString(
        $amount,
        $currencyId,
        $merchantId,
        $merchantReference,
        $terminalId,
        $hmacKey,
        $trxDateTime,
        $sessionToken
    ) {

        $string = "Amount={$amount}&CurrencyId={$currencyId}&MerchantId={$merchantId}&MerchantReference={$merchantReference}&RequestDateTime={$trxDateTime}&SessionToken={$sessionToken}&TerminalId={$terminalId}";

        $sign = self::encryptWithSHA256($string, $hmacKey);
        return strtoupper($sign);
    }
    /**
     * Encrypts a given string using SHA-256 HMAC.
     */
    public static function encryptWithSHA256($input, $hexKey)
    {
        // Convert the hex key to binary
        $binaryKey = hex2bin($hexKey);
        // Calculate the SHA-256 hash using hash_hmac
        $hash = hash_hmac('sha256', $input, $binaryKey);
        return $hash;
    }
    /**
     * Generates a signed string for filtering data.
     *
     */
    public static function generateStringForFilter(
        $data,
        $hmacKey

    ) {
        // Convert data array to string key value with and sign
        $string = '';
        foreach ($data as $key => $value) {
            $string .= $key . '=' . ($value === "null" || $value === "undefined" ? '' : $value) . '&';
        }
        $string = rtrim($string, '&');
        // Generate SIGN
        $sign = self::encryptWithSHA256($string, $hmacKey);
        return strtoupper($sign);
    }
    public function sanitizeVar($name, $global = 'GET')
    {
        switch (strtoupper($global)) {
            case 'POST':
                $value = $this->request->getPostValue($name);
                break;

            case 'GET':
            default:
                $value = $this->request->getParam($name);
                break;
        }

        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    /**
     * Adds logs to a specified file.
     *
     */
    public static function addLogs($debug, $file, $note, $data = false)
    {
        if (is_bool($data)) {
            ('1' === $debug) ? error_log(PHP_EOL . gmdate('d.m.Y h:i:s') . ' - ' . $note, 3, $file) : false;
        } else {
            ('1' === $debug) ? error_log(PHP_EOL . gmdate('d.m.Y h:i:s') . ' - ' . $note . ' -- ' . json_encode($data), 3, $file) : false;
        }
    }
}