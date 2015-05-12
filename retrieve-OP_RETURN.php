<?php

/*
 * retrieve-OP_RETURN.php
 * 
 * CLI wrapper for OP_RETURN.php to retrieve data from OP_RETURNs
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
php retrieve-OP_RETURN.php <ref> <testnet (optional)>

HEREDOC;
		exit;
	}
	
	@list($dummy, $ref, $testnet)=$argv;
	
	require 'OP_RETURN.php';
	
	$results=OP_RETURN_retrieve($ref, 1, $testnet);
	
	if (isset($results['error']))
		echo 'Error: '.$results['error']."\n";
		
	elseif (count($results))
		foreach ($results as $result) {
			echo "Hex: (".strlen($result['data'])." bytes)\n".bin2hex($result['data'])."\n\n";
			echo "ASCII:\n".preg_replace('/[^\x20-\x7E]/', '?', $result['data'])."\n\n";
			echo "TxIDs: (count ".count($result['txids']).")\n".implode("\n", $result['txids'])."\n\n";
			echo "Blocks:".str_replace("\n0\n", "\n[mempool]\n", "\n".implode("\n", $result['heights'])."\n")."\n";

			if (isset($result['ref']))
				echo "Best ref:\n".$result['ref']."\n\n";

			if (isset($result['error']))
				echo "Error:\n".$result['error']."\n\n";
		}
	
	else
		echo "No matching data was found\n";
