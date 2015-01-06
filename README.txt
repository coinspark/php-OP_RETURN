php-OP_RETURN v1.0.2

A simple PHP script to generate OP_RETURN bitcoin transactions.
Please use this responsibly and DO NOT bloat the blockchain!

Copyright (c) Coin Sciences Ltd - http://coinspark.org/

MIT License (see headers in files)


REQUIREMENTS:

* Unix-based operating system, e.g. Linux or Mac OS X
* PHP 5.x or later
* bitcoin-cli installed (does not use JSON-RPC)
* Must be run as a user who is permitted to run bitcoin-cli


USAGE ON THE COMMAND LINE:

* Ensure CONST_BITCOIN_CMD and CONST_BITCOIN_FEE in php-OP_RETURN.php are correct.

* php send-OP_RETURN.php <send-address> <send-amount> <metadata> <testnet (optional)>

- <send-address> is the bitcoin address of the recipient
- <send_amount> is the amount to send (in units of BTC)
- <metadata> is a hex string or raw string containing the OP_RETURN metadata
             (auto-detection: treated as a hex string if it is a valid one)
- <testnet> should be 1 to use the bitcoin testnet, otherwise it can be omitted

- Outputs an error if one occurred or the txid if sending was successful

* Examples:

php send-OP_RETURN.php 149wHUMa41Xm2jnZtqgRx94uGbZD9kPXnS 0.001 'Hello, blockchain!'
php send-OP_RETURN.php 149wHUMa41Xm2jnZtqgRx94uGbZD9kPXnS 0.001 48656c6c6f2c20626c6f636b636861696e21
php send-OP_RETURN.php mzEJxCrdva57shpv62udriBBgMECmaPce4 0.001 'Hello, testnet blockchain!' 1

* Wait a few seconds then check http://coinsecrets.org/ for your OP_RETURN transaction.


USAGE AS A LIBRARY:

* Ensure CONST_BITCOIN_CMD and CONST_BITCOIN_FEE in php-OP_RETURN.php are correct.

* Include/require 'php-OP_RETURN.php' in another script.

* coinspark_OP_RETURN_send($send_address, $send_amount, $metadata, $testnet=false)

- $send_address is the bitcoin address of the recipient
- $send_amount is the amount to send (in units of BTC)
- $metadata is a string of raw bytes containing the OP_RETURN metadata
- $testnet is whether to use the bitcoin testnet

- Returns: array('error' => '[some error string]') OR array('txid' => '[sent txid]')

* Wait a few seconds then check http://coinsecrets.org/ for your OP_RETURN transaction.


WHY NO WINDOWS SUPPORT?

There is an issue on Windows with the escapeshellarg() PHP function. A suitable replacement
is required which escapes shell argumentes safely and effectively. Alternatively the command
line execution of bitcoin-cli could be replaced by JSON-RPC calls to Bitcoin Core.


VERSION HISTORY

v1.0.2 - 9 December 2014
* Now really fixed that issue (fixed typo in the fix!)

v1.0.1 - 3 December 2014
* Fixed issue when running under 32-bit PHP

v1.0 - 3 October 2014
* First release
