<?php

/*
 * php-OP_RETURN v1.0
 * 
 * A simple PHP script to generate OP_RETURN bitcoin transactions
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


	define('CONST_BITCOIN_CMD', '/usr/bin/bitcoin-cli'); // path to bitcoin executable on this server
	define('CONST_BITCOIN_FEE', 0.00010000); // transaction fee to pay
	

//	Main function in library

	function coinspark_OP_RETURN_send($send_address, $send_amount, $metadata, $testnet=false)
	{
	
	//	Validate some parameters
		
		if (!file_exists(CONST_BITCOIN_CMD))
			return array('error' => 'Please check CONST_BITCOIN_CMD is set correctly');
	
		$result=coinspark_bitcoin_cli('validateaddress', $testnet, $send_address);
		if (!$result['isvalid'])
			return array('error' => 'Send address could not be validated: '.$send_address);
			
		if (strlen($metadata)>75)
			return array('error' => 'Metadata limit is 75 bytes, and you should probably stick to 40.');

		
	//	List and sort unspent inputs by priority
	
		$unspent_inputs=coinspark_bitcoin_cli('listunspent', $testnet, 0);		
		if (!is_array($unspent_inputs))
			return array('error' => 'Could not retrieve list of unspent inputs');
		
		foreach ($unspent_inputs as $index => $unspent_input)
			$unspent_inputs[$index]['priority']=$unspent_input['amount']*$unspent_input['confirmations']; // see: https://en.bitcoin.it/wiki/Transaction_fees

		coinspark_sort_by($unspent_inputs, 'priority');
		$unspent_inputs=array_reverse($unspent_inputs); // now in descending order of priority

	
	//	Identify which inputs should be spent
	
		$inputs_spend=array();
		$input_amount=0;
		$output_amount=$send_amount+CONST_BITCOIN_FEE;		
		
		foreach ($unspent_inputs as $unspent_input) {
			$inputs_spend[]=$unspent_input;

			$input_amount+=$unspent_input['amount'];
			if ($input_amount>=$output_amount)
				break; // stop when we have enough
		}
		
		if ($input_amount<$output_amount)
			return array('error' => 'Not enough funds are available to cover the amount and fee');
	
	
	//	Build the initial raw transaction
			
		$change_amount=$input_amount-$output_amount;		
		$change_address=coinspark_bitcoin_cli('getrawchangeaddress', $testnet);
		
		$raw_txn=coinspark_bitcoin_cli('createrawtransaction', $testnet, $inputs_spend, array(
			$send_address => (float)$send_amount,
			$change_address => $change_amount,
		));

	
	//	Unpack the raw transaction, add the OP_RETURN, and re-pack it
		
		$txn_unpacked=coinspark_unpack_raw_txn($raw_txn);
	
		$txn_unpacked['vout'][]=array(
			'value' => 0,
			'scriptPubKey' => '6a'.reset(unpack('H*', chr(strlen($metadata)).$metadata)), // here's the OP_RETURN
		);
			
		$raw_txn=coinspark_pack_raw_txn($txn_unpacked);

		
	//	Sign and send the transaction

		$signed_txn=coinspark_bitcoin_cli('signrawtransaction', $testnet, $raw_txn);
		if (!$signed_txn['complete'])
			return array('error' => 'Could not sign the transaction');
			
		$send_txid=coinspark_bitcoin_cli('sendrawtransaction', $testnet, $signed_txn['hex']);
		if (strlen($send_txid)!=64)
			return array('error' => 'Could not send the transaction');
	
	
	//	Return the result if successful
			
		return array('txid' => $send_txid);
	}
	

//	Talking to bitcoin-cli

	function coinspark_bitcoin_cli($command, $testnet) // more params are read from here
	{
		$command=CONST_BITCOIN_CMD.' '.($testnet ? '-testnet ' : '').escapeshellarg($command);
		
		$args=func_get_args();
		array_shift($args);
		array_shift($args);
		
		foreach ($args as $arg)
			$command.=' '.escapeshellarg(is_array($arg) ? json_encode($arg) : $arg);
		
		$raw_result=rtrim(shell_exec($command), "\n");
		
		$result=json_decode($raw_result, true);
		
		return isset($result) ? $result : $raw_result;
	}
	

//	Unpacking and packing bitcoin transactions	
	
	function coinspark_unpack_raw_txn($raw_txn_hex)
	{
		// see: https://en.bitcoin.it/wiki/Transactions
		
		$binary=pack('H*', $raw_txn_hex);
		
		$txn=array();
		
		$txn['version']=coinspark_string_shift_unpack($binary, 4, 'V'); // small-endian 32-bits

		for ($inputs=coinspark_string_shift_unpack_varint($binary); $inputs>0; $inputs--) {
			$input=array();
			
			$input['txid']=coinspark_string_shift_unpack($binary, 32, 'H*', true);
			$input['vout']=coinspark_string_shift_unpack($binary, 4, 'V');
			$length=coinspark_string_shift_unpack_varint($binary);
			$input['scriptSig']=coinspark_string_shift_unpack($binary, $length, 'H*');
			$input['sequence']=coinspark_string_shift_unpack($binary, 4, 'V');
			
			$txn['vin'][]=$input;
		}
		
		for ($outputs=coinspark_string_shift_unpack_varint($binary); $outputs>0; $outputs--) {
			$output=array();
			
			$output['value']=coinspark_string_shift_unpack_uint64($binary)/100000000;
			$length=coinspark_string_shift_unpack_varint($binary);
			$output['scriptPubKey']=coinspark_string_shift_unpack($binary, $length, 'H*');
			
			$txn['vout'][]=$output;
		}
		
		$txn['locktime']=coinspark_string_shift_unpack($binary, 4, 'V');
		
		if (strlen($binary))
			die('More data in transaction than expected');
		
		return $txn;
	}
	
	function coinspark_pack_raw_txn($txn)
	{
		$binary='';
		
		$binary.=pack('V', $txn['version']);
		
		$binary.=coinspark_pack_varint(count($txn['vin']));
		
		foreach ($txn['vin'] as $input) {
			$binary.=strrev(pack('H*', $input['txid']));
			$binary.=pack('V', $input['vout']);
			$binary.=coinspark_pack_varint(strlen($input['scriptSig'])/2); // divide by 2 because it is currently in hex
			$binary.=pack('H*', $input['scriptSig']);
			$binary.=pack('V', $input['sequence']);
		}
		
		$binary.=coinspark_pack_varint(count($txn['vout']));
		
		foreach ($txn['vout'] as $output) {
			$binary.=coinspark_pack_uint64(round($output['value']*100000000));
			$binary.=coinspark_pack_varint(strlen($output['scriptPubKey'])/2); // divide by 2 because it is currently in hex
			$binary.=pack('H*', $output['scriptPubKey']);
		}
		
		$binary.=pack('V', $txn['locktime']);
		
		return reset(unpack('H*', $binary));
	}
	
	function coinspark_string_shift(&$string, $chars)
	{
		$prefix=substr($string, 0, $chars);
		$string=substr($string, $chars);
		return $prefix;
	}
	
	function coinspark_string_shift_unpack(&$string, $chars, $format, $reverse=false)
	{
		$data=coinspark_string_shift($string, $chars);
		if ($reverse)
			$data=strrev($data);
		$unpack=unpack($format, $data);
		return reset($unpack);
	}
	
	function coinspark_string_shift_unpack_varint(&$string)
	{
		$value=coinspark_string_shift_unpack($string, 1, 'C');
		
		if ($value==0xFF)
			$value=coinspark_string_shift_unpack_uint64($string);
		elseif ($value==0xFE)
			$value=coinspark_string_shift_unpack($string, 4, 'V');
		elseif ($value==0xFD)
			$value=coinspark_string_shift_unpack($string, 2, 'v');
			
		return $value;
	}
	
	function coinspark_string_shift_unpack_uint64(&$string)
	{
		return coinspark_string_shift_unpack($string, 4, 'V')+(coinspark_string_shift_unpack($string, 4, 'V')*4294967296);
	}
	
	function coinspark_pack_varint($integer)
	{
		if ($integer>0xFFFFFFFF)
			$packed="\xFF".coinspark_pack_uint64($integer);
		elseif ($integer>0xFFFF)
			$packed="\xFE".pack('V', $integer);
		elseif ($integer>0xFC)
			$packed="\xFD".pack('v', $integer);
		else
			$packed=pack('C', $integer);
		
		return $packed;
	}
	
	function coinspark_pack_uint64($integer)
	{
		$upper=floor($integer/4294967296);
		$lower=$integer-$upper*4294967296;
		
		return pack('V', $integer%4294967296).pack('V', $upper);
	}
	

//	Sort-by utility functions
	
	function coinspark_sort_by(&$array, $by1, $by2=null)
	{
		global $sort_by_1, $sort_by_2;
		
		$sort_by_1=$by1;
		$sort_by_2=$by2;
		
		uasort($array, 'coinspark_sort_by_fn');
	}

	function coinspark_sort_by_fn($a, $b)
	{
		global $sort_by_1, $sort_by_2;
		
		$compare=coinspark_sort_cmp($a[$sort_by_1], $b[$sort_by_1]);

		if (($compare==0) && $sort_by_2)
			$compare=coinspark_sort_cmp($a[$sort_by_2], $b[$sort_by_2]);

		return $compare;
	}

	function coinspark_sort_cmp($a, $b)
	{
		if (is_numeric($a) && is_numeric($b)) // straight subtraction won't work for floating bits
			return ($a==$b) ? 0 : (($a<$b) ? -1 : 1);
		else
			return strcasecmp($a, $b); // doesn't do UTF-8 right but it will do for now
	}


