<?php

/*
 * OP_RETURN.php
 *
 * PHP script to generate and retrieve OP_RETURN bitcoin transactions
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

	define('OP_RETURN_BITCOIN_IP', '127.0.0.1'); // IP address of your bitcoin node
	define('OP_RETURN_BITCOIN_USE_CMD', false); // use command-line instead of JSON-RPC?
	
	if (OP_RETURN_BITCOIN_USE_CMD) {
		define('OP_RETURN_BITCOIN_PATH', '/usr/bin/bitcoin-cli'); // path to bitcoin-cli executable on this server

	} else {
		define('OP_RETURN_BITCOIN_PORT', ''); // leave empty to use default port for mainnet/testnet
		define('OP_RETURN_BITCOIN_USER', ''); // leave empty to read from ~/.bitcoin/bitcoin.conf (Unix only)
		define('OP_RETURN_BITCOIN_PASSWORD', ''); // leave empty to read from ~/.bitcoin/bitcoin.conf (Unix only)
	}
	
	define('OP_RETURN_BTC_FEE', 0.0001); // BTC fee to pay per transaction
	define('OP_RETURN_BTC_DUST', 0.00001); // omit BTC outputs smaller than this

	define('OP_RETURN_MAX_BYTES', 40); // maximum bytes in an OP_RETURN (40 as of Bitcoin 0.10)
	define('OP_RETURN_MAX_BLOCKS', 10); // maximum number of blocks to try when retrieving data

	define('OP_RETURN_NET_TIMEOUT_CONNECT', 5); // how long to time out when connecting to bitcoin node
	define('OP_RETURN_NET_TIMEOUT_RECEIVE', 10); // how long to time out retrieving data from bitcoin node
	

//	User-facing functions

	function OP_RETURN_send($send_address, $send_amount, $metadata, $testnet=false)
	{
		
	//	Validate some parameters
		
		if (!OP_RETURN_bitcoin_check($testnet))
			return array('error' => 'Please check Bitcoin Core is running and OP_RETURN_BITCOIN_* constants are set correctly');

		$result=OP_RETURN_bitcoin_cmd('validateaddress', $testnet, $send_address);
		if (!$result['isvalid'])
			return array('error' => 'Send address could not be validated: '.$send_address);
			
		$metadata_len=strlen($metadata);
			
		if ($metadata_len>65536)
			return array('error' => 'This library only supports metadata up to 65536 bytes in size');
			
		if ($metadata_len>OP_RETURN_MAX_BYTES)
			return array('error' => 'Metadata has '.$metadata_len.' bytes but is limited to '.OP_RETURN_MAX_BYTES.' (see OP_RETURN_MAX_BYTES)');

		
	//	Calculate amounts and choose inputs
	
		$output_amount=$send_amount+OP_RETURN_BTC_FEE;		

		$inputs_spend=OP_RETURN_select_inputs($output_amount, $testnet);
		
		if (isset($inputs_spend['error']))
			return $inputs_spend;
		
		$change_amount=$inputs_spend['total']-$output_amount;		


	//	Build the raw transaction
			
		$change_address=OP_RETURN_bitcoin_cmd('getrawchangeaddress', $testnet);
		
		$outputs=array($send_address => (float)$send_amount);
		
		if ($change_amount>=OP_RETURN_BTC_DUST)
			$outputs[$change_address]=$change_amount;

		$raw_txn=OP_RETURN_create_txn($inputs_spend['inputs'], $outputs, $metadata, count($outputs), $testnet);

		
	//	Sign and send the transaction, return result

		return OP_RETURN_sign_send_txn($raw_txn, $testnet);
	}
	
	
	function OP_RETURN_store($data, $testnet=false)
	{
	/*
		Data is stored in OP_RETURNs within a series of chained transactions.
		The data is referred to by the txid of the first transaction containing an OP_RETURN.
		If the OP_RETURN is followed by another output, the data continues in the transaction spending that output.
		When the OP_RETURN is the last output, this also signifies the end of the data.
	*/
	
	//	Validate parameters and get change address
	
		if (!OP_RETURN_bitcoin_check($testnet))
			return array('error' => 'Please check Bitcoin Core is running and OP_RETURN_BITCOIN_* constants are set correctly');
			
		$data_len=strlen($data);
		if ($data_len==0)
			return array('error' => 'Some data is required to be stored');

		$change_address=OP_RETURN_bitcoin_cmd('getrawchangeaddress', $testnet);
			
	
	//	Calculate amounts and choose first inputs to use
	
		$output_amount=OP_RETURN_BTC_FEE*ceil($data_len/OP_RETURN_MAX_BYTES); // number of transactions required
		
		$inputs_spend=OP_RETURN_select_inputs($output_amount, $testnet);
		if (isset($inputs_spend['error']))
			return $inputs_spend;
			
		$inputs=$inputs_spend['inputs'];
		$input_amount=$inputs_spend['total'];

	
	//	Find the current blockchain height and mempool txids
	
		$height=OP_RETURN_bitcoin_cmd('getblockcount', $testnet);
		$avoid_txids=OP_RETURN_bitcoin_cmd('getrawmempool', $testnet);

	
	//	Loop to build and send transactions
	
		$result['txids']=array();
	
		for ($data_ptr=0; $data_ptr<$data_len; $data_ptr+=OP_RETURN_MAX_BYTES) {
		
		//	Some preparation for this iteration
		
			$last_txn=(($data_ptr+OP_RETURN_MAX_BYTES)>=$data_len); // is this the last tx in the chain?
			$change_amount=$input_amount-OP_RETURN_BTC_FEE;
			$metadata=substr($data, $data_ptr, OP_RETURN_MAX_BYTES);
				
		//	Build and send this transaction
		
			$outputs=array();
			if ($change_amount>=OP_RETURN_BTC_DUST) // might be skipped for last transaction
				$outputs[$change_address]=$change_amount;
				
			$raw_txn=OP_RETURN_create_txn($inputs, $outputs, $metadata, $last_txn ? count($outputs) : 0, $testnet);
			
			$send_result=OP_RETURN_sign_send_txn($raw_txn, $testnet);
		
		//	Check for errors and collect the txid
		
			if (isset($send_result['error'])) {
				$result['error']=$send_result['error'];
				break;
			}
			
			$result['txids'][]=$send_result['txid'];
			
			if ($data_ptr==0)
				$result['ref']=OP_RETURN_calc_ref($height, $send_result['txid'], $avoid_txids);
			
		//	Prepare inputs for next iteration

			$inputs=array(array(
				'txid' => $send_result['txid'],
				'vout' => 1,
			));

			$input_amount=$change_amount;
		}
		
		
	//	Return the final result
	
		return $result;
	}
	
	
	function OP_RETURN_retrieve($ref, $max_results=1, $testnet=false)
	{
	
	//	Validate parameters and get status of Bitcoin Core
	
		if (!OP_RETURN_bitcoin_check($testnet))
			return array('error' => 'Please check Bitcoin Core is running and OP_RETURN_BITCOIN_* constants are set correctly');
			
		$max_height=OP_RETURN_bitcoin_cmd('getblockcount', $testnet);
		$heights=OP_RETURN_get_ref_heights($ref, $max_height);
		
		if (!is_array($heights))
			return array('error' => 'Ref is not valid');
			

	//	Collect and return the results
		
		$results=array();
		
		foreach ($heights as $height) {
			if ($height==0) {
				$txids=OP_RETURN_list_mempool_txns($testnet); // if mempool, only get list for now (to save RPC calls)
				$txns=null;
			} else {
				$txns=OP_RETURN_get_block_txns($height, $testnet); // if block, get all fully unpacked
				$txids=array_keys($txns);
			}
			
			foreach ($txids as $txid)
				if (OP_RETURN_match_ref_txid($ref, $txid)) {
					if ($height==0)
						$txn_unpacked=OP_RETURN_get_mempool_txn($txid, $testnet);
					else
						$txn_unpacked=$txns[$txid];
						
					$found=OP_RETURN_find_txn_data($txn_unpacked);
					
					if (is_array($found)) {
					
					//	Collect data from txid which matches $ref and contains an OP_RETURN
					
						$result=array(
							'txids' => array($txid),
							'data' => $found['op_return'],
						);
						
					//	Work out which other block heights / mempool we should try
						
						$key_heights=array($height => true);
						
						if ($height==0)
							$try_heights=array(); // nowhere else to look if first still in mempool
						else {
							$result['ref']=OP_RETURN_calc_ref($height, $txid, array_keys($txns));
							$try_heights=OP_RETURN_get_try_heights($height+1, $max_height, false);
						}
						
					//	Collect the rest of the data, if appropriate
						
						if ($height==0)
							$this_txns=OP_RETURN_get_mempool_txns($testnet); // now retrieve all to follow chain
						else
							$this_txns=$txns;
						
						$last_txid=$txid;
						$this_height=$height;
						
						while ($found['index'] < (count($txn_unpacked['vout'])-1)) { // this means more data to come
							$next_txid=OP_RETURN_find_spent_txid($this_txns, $last_txid, $found['index']+1);
							
						//	If we found the next txid in the data chain
						
							if (isset($next_txid)) {
								$result['txids'][]=$next_txid;
								
								$txn_unpacked=$this_txns[$next_txid];
								$found=OP_RETURN_find_txn_data($txn_unpacked);

								if (is_array($found)) {
									$result['data'].=$found['op_return'];
									$key_heights[$this_height]=true;
								} else {
									$result['error']='Data incomplete - missing OP_RETURN';
									break;
								}
								
								$last_txid=$next_txid;
								
						//	Otherwise move on to the next height to keep looking
								
							} else {
								if (count($try_heights)) {
									$this_height=array_shift($try_heights);

									if ($this_height==0)
										$this_txns=OP_RETURN_get_mempool_txns($testnet);
									else
										$this_txns=OP_RETURN_get_block_txns($this_height, $testnet);	

								} else {
									$result['error']='Data incomplete - could not find next transaction';
									break;
								}
							}
						}
						
					//	Finish up the information about this result 
						
						$result['heights']=array_keys($key_heights);
						
						$results[]=$result;
					}
				}
						
			if (count($results)>=$max_results)
				break; // stop if we have collected enough
		}
		
		return $results;
	}
	

//	Utility functions

	function OP_RETURN_select_inputs($total_amount, $testnet)
	{
	
	//	List and sort unspent inputs by priority
	
		$unspent_inputs=OP_RETURN_bitcoin_cmd('listunspent', $testnet, 0);		
		if (!is_array($unspent_inputs))
			return array('error' => 'Could not retrieve list of unspent inputs');
		
		foreach ($unspent_inputs as $index => $unspent_input)
			$unspent_inputs[$index]['priority']=$unspent_input['amount']*$unspent_input['confirmations'];
				// see: https://en.bitcoin.it/wiki/Transaction_fees

		OP_RETURN_sort_by($unspent_inputs, 'priority');
		$unspent_inputs=array_reverse($unspent_inputs); // now in descending order of priority
		
	//	Identify which inputs should be spent
	
		$inputs_spend=array();
		$input_amount=0;
		
		foreach ($unspent_inputs as $unspent_input) {
			$inputs_spend[]=$unspent_input;

			$input_amount+=$unspent_input['amount'];
			if ($input_amount>=$total_amount)
				break; // stop when we have enough
		}
		
		if ($input_amount<$total_amount)
			return array('error' => 'Not enough funds are available to cover the amount and fee');
			
	//	Return the successful result
	
		return array(
			'inputs' => $inputs_spend,
			'total' => $input_amount,
		);
	}
	
	function OP_RETURN_create_txn($inputs, $outputs, $metadata, $metadata_pos, $testnet)
	{
		$raw_txn=OP_RETURN_bitcoin_cmd('createrawtransaction', $testnet, $inputs, $outputs);

		$txn_unpacked=OP_RETURN_unpack_txn(pack('H*', $raw_txn));
		
		$metadata_len=strlen($metadata);
		
		if ($metadata_len<=75)
			$payload=chr($metadata_len).$metadata; // length byte + data (https://en.bitcoin.it/wiki/Script)
		elseif ($metadata_len<=256)
			$payload="\x4c".chr($metadata_len).$metadata; // OP_PUSHDATA1 format
		else
			$payload="\x4d".chr($metadata_len%256).chr(floor($metadata_len/256)).$metadata; // OP_PUSHDATA2 format
		
		$metadata_pos=min(max(0, $metadata_pos), count($txn_unpacked['vout'])); // constrain to valid values
	
		array_splice($txn_unpacked['vout'], $metadata_pos, 0, array(array(
			'value' => 0,
			'scriptPubKey' => '6a'.reset(unpack('H*', $payload)), // here's the OP_RETURN
		)));
			
		return reset(unpack('H*', OP_RETURN_pack_txn($txn_unpacked)));
	}
	
	function OP_RETURN_sign_send_txn($raw_txn, $testnet)
	{
		$signed_txn=OP_RETURN_bitcoin_cmd('signrawtransaction', $testnet, $raw_txn);
		if (!$signed_txn['complete'])
			return array('error' => 'Could not sign the transaction');
			
		$send_txid=OP_RETURN_bitcoin_cmd('sendrawtransaction', $testnet, $signed_txn['hex']);
		if (strlen($send_txid)!=64)
			return array('error' => 'Could not send the transaction');
		
		return array('txid' => $send_txid);
	}
	
	function OP_RETURN_get_height_txns($height, $testnet)
	{
		if ($height==0)
			return OP_RETURN_get_mempool_txns($testnet);
		else
			return OP_RETURN_get_block_txns($height, $testnet);	
	}
	
	function OP_RETURN_list_mempool_txns($testnet)
	{
		return OP_RETURN_bitcoin_cmd('getrawmempool', $testnet);
	}
	
	function OP_RETURN_get_mempool_txn($txid, $testnet)
	{
		$raw_txn=OP_RETURN_bitcoin_cmd('getrawtransaction', $testnet, $txid);
		return OP_RETURN_unpack_txn(pack('H*', $raw_txn));
	}
	
	function OP_RETURN_get_mempool_txns($testnet)
	{
		$txids=OP_RETURN_list_mempool_txns($testnet);

		$txns=array();
		foreach ($txids as $txid)
			$txns[$txid]=OP_RETURN_get_mempool_txn($txid, $testnet);
		
		return $txns;
	}

	function OP_RETURN_get_raw_block($height, $testnet)
	{
		$block_hash=OP_RETURN_bitcoin_cmd('getblockhash', $testnet, $height);
		if (strlen($block_hash)!=64)
			return array('error' => 'Block at height '.$height.' not found');
		
		return array(
			'block' => pack('H*', OP_RETURN_bitcoin_cmd('getblock', $testnet, $block_hash, false))
		);
	}
	
	function OP_RETURN_get_block_txns($height, $testnet)
	{
		$raw_block=OP_RETURN_get_raw_block($height, $testnet);
		if (isset($raw_block['error']))
			return array('error' => $raw_block['error']);
		
		$block=OP_RETURN_unpack_block($raw_block['block']);
		
		return $block['txs'];
	}
	
	
//	Talking to bitcoin-cli

	function OP_RETURN_bitcoin_check($testnet)
	{
		$info=OP_RETURN_bitcoin_cmd('getinfo', $testnet);
		
		return is_array($info);
	}
	
	function OP_RETURN_bitcoin_cmd($command, $testnet) // more params are read from here
	{
		$args=func_get_args();
		array_shift($args);
		array_shift($args);
	
		if (OP_RETURN_BITCOIN_USE_CMD) {
			$command=OP_RETURN_BITCOIN_PATH.' '.($testnet ? '-testnet ' : '').escapeshellarg($command);
		
			foreach ($args as $arg)
				$command.=' '.escapeshellarg(is_array($arg) ? json_encode($arg) : $arg);
		
			$raw_result=rtrim(shell_exec($command), "\n");

			$result=json_decode($raw_result, true); // decode JSON if possible
			if (!isset($result))
				$result=$raw_result;

		} else {
			$request=array(
				'id' => time().'-'.rand(100000,999999),
				'method' => $command,
				'params' => $args,
			);
			
			$port=OP_RETURN_BITCOIN_PORT;
			$user=OP_RETURN_BITCOIN_USER;
			$password=OP_RETURN_BITCOIN_PASSWORD;
			
			if (
				function_exists('posix_getpwuid') &&
				!(strlen($port) && strlen($user) && strlen($password))
			) {
				$posix_userinfo=posix_getpwuid(posix_getuid());
				$bitcoin_conf=file_get_contents($posix_userinfo['dir'].'/.bitcoin/bitcoin.conf');
				$conf_lines=preg_split('/[\n\r]/', $bitcoin_conf);

				foreach ($conf_lines as $conf_line) {
					$parts=explode('=', trim($conf_line), 2);
					
					if ( ($parts[0]=='rpcport') && !strlen($port) )
						$port=$parts[1];
					if ( ($parts[0]=='rpcuser') && !strlen($user) )
						$user=$parts[1];
					if ( ($parts[0]=='rpcpassword') && !strlen($password) )
						$password=$parts[1];
				}
			}
			
			if (!strlen($port))
				$port=$testnet ? 18332 : 8332;
				
			if (!strlen($user) && strlen($password))
				return null; // no point trying in this case
			
			$curl=curl_init('http://'.OP_RETURN_BITCOIN_IP.':'.$port.'/');
			curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
			curl_setopt($curl, CURLOPT_USERPWD, $user.':'.$password);
			curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, OP_RETURN_NET_TIMEOUT_CONNECT);
			curl_setopt($curl, CURLOPT_TIMEOUT, OP_RETURN_NET_TIMEOUT_RECEIVE);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);	
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($request));
			$raw_result=curl_exec($curl);
			curl_close($curl);
			
			$result_array=json_decode($raw_result, true);
			$result=@$result_array['result'];
		}

		return $result;
	}
	

//	Working with data references

	/*
		The format of a data reference is: [estimated block height]-[partial txid] - where:

		[estimated block height] is the block where the first transaction might appear and following
		which all subsequent transactions are expected to appear. In the event of a weird blockchain
		reorg, it is possible the first transaction might appear in a slightly earlier block. When
		embedding data, we set [estimated block height] to 1+(the current block height).

		[partial txid] contains 2 adjacent bytes from the txid, at a specific position in the txid:
		2*([partial txid] div 65536) gives the offset of the 2 adjacent bytes, between 0 and 28.
		([partial txid] mod 256) is the byte of the txid at that offset.
		(([partial txid] mod 65536) div 256) is the byte of the txid at that offset plus one.
		Note that the txid is ordered according to user presentation, not raw data in the block.
	*/		
	
	function OP_RETURN_calc_ref($next_height, $txid, $avoid_txids)
	{
		$txid_binary=pack('H*', $txid);
		
		for ($txid_offset=0; $txid_offset<=14; $txid_offset++) {
			$sub_txid=substr($txid_binary, 2*$txid_offset, 2);
			$clashed=false;
			
			foreach ($avoid_txids as $avoid_txid) {
				$avoid_txid_binary=pack('H*', $avoid_txid);
				
				if (
					(substr($avoid_txid_binary, 2*$txid_offset, 2)==$sub_txid) &&
					($txid_binary!=$avoid_txid_binary)
				) {
					$clashed=true;
					break;
				}
			}
				
			if (!$clashed)
				break;
		}
		
		if ($clashed) // could not find a good reference
			return null;
			
		$tx_ref=ord($txid_binary[2*$txid_offset])+256*ord($txid_binary[1+2*$txid_offset])+65536*$txid_offset;
		
		return sprintf('%06d-%06d', $next_height, $tx_ref);
	}
	
	function OP_RETURN_get_ref_parts($ref)
	{
		if (!preg_match('/^[0-9]+\-[0-9A-Fa-f]+$/', $ref)) // also support partial txid for second half
			return null;
		
		$parts=explode('-', $ref);
			
		if (preg_match('/[A-Fa-f]/', $parts[1])) {
			if (strlen($parts[1])>=4) {
				$txid_binary=hex2bin(substr($parts[1], 0, 4));
				$parts[1]=ord($txid_binary[0])+256*ord($txid_binary[1])+65536*0;
			} else
				return null;
		}
		
		if ($parts[1]>983039) // 14*65536+65535
			return null;
			
		return $parts;
	}
	
	function OP_RETURN_get_ref_heights($ref, $max_height)
	{
		$parts=OP_RETURN_get_ref_parts($ref);
		if (!is_array($parts))
			return null;
			
		return OP_RETURN_get_try_heights((int)$parts[0], $max_height, true);
	}
	
	function OP_RETURN_get_try_heights($est_height, $max_height, $also_back)
	{
		$forward_height=$est_height;
		$back_height=min($forward_height-1, $max_height);
		
		$heights=array();
		$mempool=false;
		
		for ($try=0; true; $try++) {
			if ($also_back && (($try%3)==2)) { // step back every 3 tries
				$heights[]=$back_height;
				$back_height--;

			} else {
				if ($forward_height>$max_height) {
					if (!$mempool) {
						$heights[]=0; // indicates to try mempool
						$mempool=true;
					
					} elseif (!$also_back)
						break; // nothing more to do here
				
				} else
					$heights[]=$forward_height;
				
				$forward_height++;
			}
		
			if (count($heights)>=OP_RETURN_MAX_BLOCKS)
				break;
		}
		
		return $heights;
	}
	
	function OP_RETURN_match_ref_txid($ref, $txid)
	{
		$parts=OP_RETURN_get_ref_parts($ref);
		if (!is_array($parts))
			return null;
	
		$txid_offset=floor($parts[1]/65536);
		$txid_binary=pack('H*', $txid);

		$txid_part=substr($txid_binary, 2*$txid_offset, 2);
		$txid_match=chr($parts[1]%256).chr(floor(($parts[1]%65536)/256));
		
		return $txid_part==$txid_match; // exact binary comparison
	}
	

//	Unpacking and packing bitcoin blocks and transactions	
	
	function OP_RETURN_unpack_block($binary)
	{
		$buffer=new OP_RETURN_buffer($binary);
		$block=array();
		
		$block['version']=$buffer->shift_unpack(4, 'V');
		$block['hashPrevBlock']=$buffer->shift_unpack(32, 'H*', true);
		$block['hashMerkleRoot']=$buffer->shift_unpack(32, 'H*', true);
		$block['time']=$buffer->shift_unpack(4, 'V');
		$block['bits']=$buffer->shift_unpack(4, 'V');
		$block['nonce']=$buffer->shift_unpack(4, 'V');
		$block['tx_count']=$buffer->shift_varint();
		
		$block['txs']=array();
		
		$old_ptr=$buffer->used();
		
		while ($buffer->remaining()) {
			$transaction=OP_RETURN_unpack_txn_buffer($buffer);
			$new_ptr=$buffer->used();
			$size=$new_ptr-$old_ptr;
			
			$raw_txn_binary=substr($binary, $old_ptr, $size);
			$txid=reset(unpack('H*', strrev(hash('sha256', hash('sha256', $raw_txn_binary, true), true))));
			$old_ptr=$new_ptr;
		
			$transaction['size']=$size;
			$block['txs'][$txid]=$transaction;
		}
		
		return $block;
	}
	
	function OP_RETURN_unpack_txn($binary)
	{
		return OP_RETURN_unpack_txn_buffer(new OP_RETURN_buffer($binary));
	}
	
	function OP_RETURN_unpack_txn_buffer($buffer)
	{
		// see: https://en.bitcoin.it/wiki/Transactions
		
		$txn=array();
		
		$txn['version']=$buffer->shift_unpack(4, 'V'); // small-endian 32-bits

		for ($inputs=$buffer->shift_varint(); $inputs>0; $inputs--) {
			$input=array();
			
			$input['txid']=$buffer->shift_unpack(32, 'H*', true);
			$input['vout']=$buffer->shift_unpack(4, 'V');
			$length=$buffer->shift_varint();
			$input['scriptSig']=$buffer->shift_unpack($length, 'H*');
			$input['sequence']=$buffer->shift_unpack(4, 'V');
			
			$txn['vin'][]=$input;
		}
		
		for ($outputs=$buffer->shift_varint(); $outputs>0; $outputs--) {
			$output=array();
			
			$output['value']=$buffer->shift_uint64()/100000000;
			$length=$buffer->shift_varint();
			$output['scriptPubKey']=$buffer->shift_unpack($length, 'H*');
			
			$txn['vout'][]=$output;
		}
		
		$txn['locktime']=$buffer->shift_unpack(4, 'V');
		
		return $txn;
	}
	
	function OP_RETURN_find_spent_txid($txns, $spent_txid, $spent_vout)
	{
		foreach ($txns as $txid => $txn_unpacked)
			foreach ($txn_unpacked['vin'] as $input)
				if ( ($input['txid']==$spent_txid) && ($input['vout']==$spent_vout) )
					return $txid;
					
		return null;
	}
	
	function OP_RETURN_find_txn_data($txn_unpacked)
	{
		foreach ($txn_unpacked['vout'] as $index => $output) {
			$op_return=OP_RETURN_get_script_data(pack('H*', $output['scriptPubKey']));
			
			if (isset($op_return))
				return array(
					'index' => $index,
					'op_return' => $op_return,
				);
		}
		
		return null;
	}
	
	function OP_RETURN_get_script_data($scriptPubKeyBinary)
	{
		$op_return=null;
		
		if ($scriptPubKeyBinary[0]=="\x6a") {
			$first_ord=ord($scriptPubKeyBinary[1]);
			
			if ($first_ord<=75)
				$op_return=substr($scriptPubKeyBinary, 2, $first_ord);
			elseif ($first_ord==0x4c)
				$op_return=substr($scriptPubKeyBinary, 3, ord($scriptPubKeyBinary[2]));
			elseif ($first_ord==0x4d)
				$op_return=substr($scriptPubKeyBinary, 4, ord($scriptPubKeyBinary[2])+256*ord($scriptPubKeyBinary[3]));
		}
		
		return $op_return;	
	}
	
	function OP_RETURN_pack_txn($txn)
	{
		$binary='';
		
		$binary.=pack('V', $txn['version']);
		
		$binary.=OP_RETURN_pack_varint(count($txn['vin']));
		
		foreach ($txn['vin'] as $input) {
			$binary.=strrev(pack('H*', $input['txid']));
			$binary.=pack('V', $input['vout']);
			$binary.=OP_RETURN_pack_varint(strlen($input['scriptSig'])/2); // divide by 2 because it is currently in hex
			$binary.=pack('H*', $input['scriptSig']);
			$binary.=pack('V', $input['sequence']);
		}
		
		$binary.=OP_RETURN_pack_varint(count($txn['vout']));
		
		foreach ($txn['vout'] as $output) {
			$binary.=OP_RETURN_pack_uint64(round($output['value']*100000000));
			$binary.=OP_RETURN_pack_varint(strlen($output['scriptPubKey'])/2); // divide by 2 because it is currently in hex
			$binary.=pack('H*', $output['scriptPubKey']);
		}
		
		$binary.=pack('V', $txn['locktime']);
		
		return $binary;
	}
	
	function OP_RETURN_pack_varint($integer)
	{
		if ($integer>0xFFFFFFFF)
			$packed="\xFF".OP_RETURN_pack_uint64($integer);
		elseif ($integer>0xFFFF)
			$packed="\xFE".pack('V', $integer);
		elseif ($integer>0xFC)
			$packed="\xFD".pack('v', $integer);
		else
			$packed=pack('C', $integer);
		
		return $packed;
	}
	
	function OP_RETURN_pack_uint64($integer)
	{
		$upper=floor($integer/4294967296);
		$lower=$integer-$upper*4294967296;
		
		return pack('V', $lower).pack('V', $upper);
	}
	

//	Helper class for unpacking bitcoin binary data

	class OP_RETURN_buffer
	{
		 var $data;
		 var $len;
		 var $ptr;
		 
		 function __construct($data, $ptr=0)
		 {
		 	$this->data=$data;
		 	$this->len=strlen($data);
		 	$this->ptr=$ptr;
		 }
		 
		 function shift($chars)
		 {
		 	$prefix=substr($this->data, $this->ptr, $chars);
		 	$this->ptr+=$chars;

		 	return $prefix;
		 }
		 
		 function shift_unpack($chars, $format, $reverse=false)
		 {
			$data=$this->shift($chars);
			if ($reverse)
				$data=strrev($data);

			$unpack=unpack($format, $data);

			return reset($unpack);
		}
	
		function shift_varint()
		{
			$value=$this->shift_unpack(1, 'C');
	
			if ($value==0xFF)
				$value=$this->shift_uint64();
			elseif ($value==0xFE)
				$value=$this->shift_unpack(4, 'V');
			elseif ($value==0xFD)
				$value=$this->shift_unpack(2, 'v');
		
			return $value;
		}
		
		function shift_uint64()
		{
			return $this->shift_unpack(4, 'V')+($this->shift_unpack(4, 'V')*4294967296);
		}
		
		function used()
		{
			return min($this->ptr, $this->len);
		}
		
		function remaining()
		{
			return max($this->len-$this->ptr, 0);
		}
	}
	

//	Sort-by utility functions
	
	function OP_RETURN_sort_by(&$array, $by1, $by2=null)
	{
		global $sort_by_1, $sort_by_2;
		
		$sort_by_1=$by1;
		$sort_by_2=$by2;
		
		uasort($array, 'OP_RETURN_sort_by_fn');
	}

	function OP_RETURN_sort_by_fn($a, $b)
	{
		global $sort_by_1, $sort_by_2;
		
		$compare=OP_RETURN_sort_cmp($a[$sort_by_1], $b[$sort_by_1]);

		if (($compare==0) && $sort_by_2)
			$compare=OP_RETURN_sort_cmp($a[$sort_by_2], $b[$sort_by_2]);

		return $compare;
	}

	function OP_RETURN_sort_cmp($a, $b)
	{
		if (is_numeric($a) && is_numeric($b)) // straight subtraction won't work for floating bits
			return ($a==$b) ? 0 : (($a<$b) ? -1 : 1);
		else
			return strcasecmp($a, $b); // doesn't do UTF-8 right but it will do for now
	}
