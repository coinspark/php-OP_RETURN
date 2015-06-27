php-OP_RETURN v2
================
Simple PHP commands and libraries for using OP_RETURNs in bitcoin transactions.

Copyright (c) Coin Sciences Ltd - http://coinsecrets.org/

MIT License (see headers in files)


REQUIREMENTS
------------
* PHP 5.x or later
* Bitcoin Core 0.9 or later


BEFORE YOU START
----------------
Check the constant settings at the top of OP_RETURN.php.
If you just installed Bitcoin Core, wait for it to download and verify old blocks.
If using as a library, include/require 'OP_RETURN.php' in your PHP script file.


TO SEND A BITCOIN TRANSACTION WITH SOME OP_RETURN METADATA
----------------------------------------------------------

On the command line:

* php send-OP_RETURN.php <send-address> <send-amount> <metadata> <testnet (optional)>

  <send-address> is the bitcoin address of the recipient
  <send-amount> is the amount to send (in units of BTC)
  <metadata> is a hex string or raw string containing the OP_RETURN metadata
             (auto-detection: treated as a hex string if it is a valid one)
  <testnet> should be 1 to use the bitcoin testnet, otherwise it can be omitted

* Outputs an error if one occurred or the txid if sending was successful

* Wait a few seconds then check http://coinsecrets.org/ for your OP_RETURN transaction.

* Examples:

  php send-OP_RETURN.php 149wHUMa41Xm2jnZtqgRx94uGbZD9kPXnS 0.001 'Hello, blockchain!'
  php send-OP_RETURN.php 149wHUMa41Xm2jnZtqgRx94uGbZD9kPXnS 0.001 48656c6c6f2c20626c6f636b636861696e21
  php send-OP_RETURN.php mzEJxCrdva57shpv62udriBBgMECmaPce4 0.001 'Hello, testnet!' 1


As a library:

* OP_RETURN_send($send_address, $send_amount, $metadata, $testnet=false)

  $send_address is the bitcoin address of the recipient
  $send_amount is the amount to send (in units of BTC)
  $metadata is a string of raw bytes containing the OP_RETURN metadata
  $testnet is whether to use the bitcoin testnet network (false if omitted)

* Returns: array('error' => '[some error string]')
       or: array('txid' => '[sent txid]')

* Examples

  OP_RETURN_send('149wHUMa41Xm2jnZtqgRx94uGbZD9kPXnS', 0.001, 'Hello, blockchain!')
  OP_RETURN_send('149wHUMa41Xm2jnZtqgRx94uGbZD9kPXnS', 0.001, 'Hello, testnet!', true)



TO STORE SOME DATA IN THE BLOCKCHAIN USING OP_RETURNs
-----------------------------------------------------

On the command line:

* php store-OP_RETURN.php <data> <testnet (optional)>

  <data> is a hex string or raw string containing the data to be stored
         (auto-detection: treated as a hex string if it is a valid one)
  <testnet> should be 1 to use the bitcoin testnet, otherwise it can be omitted

* Outputs an error if one occurred or if successful, the txids that were used to store
  the data and a short reference that can be used to retrieve it using this library.

* Wait a few seconds then check http://coinsecrets.org/ for your OP_RETURN transactions.

* Examples:

  php store-OP_RETURN.php 'This example stores 47 bytes in the blockchain.'
  php store-OP_RETURN.php 'This example stores 44 bytes in the testnet.' 1
  
  
As a library:

* OP_RETURN_store($data, $testnet=false)

  $data is the string of raw bytes to be stored
  $testnet is whether to use the bitcoin testnet network (false if omitted)
  
* Returns: array('error' => '[some error string]')
       or: array('txids' => array('[1st txid]', '[2nd txid]', ...),
                 'ref' => '[ref for retrieving data]')
           
* Examples:

  OP_RETURN_store('This example stores 47 bytes in the blockchain.')
  OP_RETURN_store('This example stores 44 bytes in the testnet.', true)



TO RETRIEVE SOME DATA FROM OP_RETURNs IN THE BLOCKCHAIN
-------------------------------------------------------

On the command line:

* php retrieve-OP_RETURN.php <ref> <testnet (optional)>

  <ref> is the reference that was returned by a previous storage operation
  <testnet> should be 1 to use the bitcoin testnet, otherwise it can be omitted
  
* Outputs an error if one occurred or if successful, the retrieved data in hexadecimal
  and ASCII format, a list of the txids used to store the data, a list of the blocks in
  which the data is stored, and (if available) the best ref for retrieving the data
  quickly in future. This may or may not be different from the ref you provided.
  
* Examples:

  php retrieve-OP_RETURN.php 356115-052075
  php retrieve-OP_RETURN.php 396381-059737 1
  
  
As a library:

* OP_RETURN_retrieve($ref, $max_results=1, $testnet=false)

  $ref is the reference that was returned by a previous storage operation
  $max_results is the maximum number of results to retrieve (in general, omit for 1)
  $testnet is whether to use the bitcoin testnet network (false if omitted)

* Returns: array('error' => '[some error string]')
       or: array('data' => '[raw binary data]',
                 'txids' => array('[1st txid]', '[2nd txid]', ...),
                 'heights' => array([block 1 used], [block 2 used], ...),
                 'ref' => '[best ref for retrieving data]',
                 'error' => '[error if data only partially retrieved]')
           
           A value of 0 in the 'heights' array means some data is still in the mempool.      
           The 'ref' and 'error' elements are only present if appropriate.
                 
* Examples:

  OP_RETURN_retrieve('356115-052075')
  OP_RETURN_retrieve('396381-059737', 1, true)
  
  

VERSION HISTORY
---------------
v2.0.2 - 27 June 2015
* Use Bitcoin Core getblock API to get raw block content instead of wire protocol

v2.0.1 - 14 May 2015
* More efficient checking of mempool for the first transaction in the chain

v2.0 - 12 May 2015
* Added functions for general storage and retrieval of data in the blockchain
* Now uses Bitcoin Core JSON-RPC API (bitcoin-cli still an option), so supports Windows

v1.0.2 - 9 December 2014
* Now really fixed that issue (fixed typo in the fix!)

v1.0.1 - 3 December 2014
* Fixed issue when running under 32-bit PHP

v1.0 - 3 October 2014
* First release
