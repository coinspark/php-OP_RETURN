<?php

/*
 * store-OP_RETURN.php
 * 
 * CLI wrapper for OP_RETURN.php to store data using OP_RETURNs
 *
 * Copyright (c) Coin Sciences Ltd
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


	if ($argc<2) {
		echo <<<HEREDOC
Usage:
php store-OP_RETURN.php <data> <testnet (optional)>

HEREDOC;
		exit;
	}
	
	@list($dummy, $data, $testnet)=$argv;
	
	require 'OP_RETURN.php';

	if (preg_match('/^([0-9A-Fa-f]{2})*$/', $data))
		$data=pack('H*', $data); // convert from hex if it looks like hex
	
	$result=OP_RETURN_store($data, $testnet);
	
	if (isset($result['error']))
		echo 'Error: '.$result['error']."\n";
	else
		echo "TxIDs:\n".implode("\n", $result['txids'])."\n\nRef: ".$result['ref']."\n\nWait a few seconds then check on: http://".
			($testnet ? 'testnet.' : '')."coinsecrets.org/\n";