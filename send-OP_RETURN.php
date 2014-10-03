<?php

/*
 * send-OP_RETURN v1.0
 * 
 * CLI wrapper for php-OP_RETURN.php which generates OP_RETURN bitcoin transactions
 *
 * Copyright (c) 2014 Coin Sciences Ltd - http://coinspark.org/
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */


	if ($argc<4) {
		echo <<<HEREDOC
Usage:
php send-OP_RETURN.php <send-address> <send-amount> <metadata> <testnet (optional)>'

Examples:
php send-OP_RETURN.php 149wHUMa41Xm2jnZtqgRx94uGbZD9kPXnS 0.001 'Hello, blockchain!'
php send-OP_RETURN.php 149wHUMa41Xm2jnZtqgRx94uGbZD9kPXnS 0.001 48656c6c6f2c20626c6f636b636861696e21
php send-OP_RETURN.php mzEJxCrdva57shpv62udriBBgMECmaPce4 0.001 'Hello, testnet blockchain!' 1

HEREDOC;
		exit;
	}
	
	@list($dummy, $send_address, $send_amount, $metadata, $testnet)=$argv;
	
	require 'php-OP_RETURN.php';

	if (preg_match('/^([0-9A-Fa-f]{2})*$/', $metadata))
		$metadata=pack('H*', $metadata);
	
	$result=coinspark_OP_RETURN_send($send_address, $send_amount, $metadata, $testnet);
	
	if (isset($result['error']))
		echo 'Error: '.$result['error']."\n";
	else
		echo 'TxID: '.$result['txid']."\nWait a few seconds then check on: http://".
			($testnet ? 'testnet.' : '')."coinsecrets.org/\n";