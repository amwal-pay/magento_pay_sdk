<?php

namespace Amwal\Pay\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

class AmwalPay extends AbstractHelper
{
    
	public static function encryptWithSHA256($input, $hexKey)
	{
		// Convert the hex key to binary
		$binaryKey = hex2bin($hexKey);
		// Calculate the SHA-256 hash using hash_hmac
		$hash = hash_hmac('sha256', $input, $binaryKey);
		return $hash;
	}
    public function generateString(
        $amount,
        $currencyId,
        $merchantId,
        $merchantReference,
        $terminalId,
        $hmacKey,
        $trxDateTime
    ): string {

        $string = "Amount={$amount}&CurrencyId={$currencyId}&MerchantId={$merchantId}&MerchantReference={$merchantReference}&RequestDateTime={$trxDateTime}&SessionToken=&TerminalId={$terminalId}";

        //echo $string;
        // Generate SIGN
        $sign = self::encryptWithSHA256($string, $hmacKey);
        return strtoupper($sign);
    }
	public function generateStringForFilter(
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
	public static function addLogs( $debug, $file, $note, $data = false ) {
		if ( is_bool( $data ) ) {
			( '1' === $debug ) ? error_log( PHP_EOL . gmdate( 'd.m.Y h:i:s' ) . ' - ' . $note, 3, $file ) : false;
		} else {
			( '1' === $debug ) ? error_log( PHP_EOL . gmdate( 'd.m.Y h:i:s' ) . ' - ' . $note . ' -- ' . json_encode( $data ), 3, $file ) : false;
		}
	}
}
