<?php
	/**
	 * this is a SandR extension built to handle replacing the ereg* functions with preg_* functions
	 */
	require_once('Classes/SandR/Ereg.php');

	if( php_sapi_name() != "cli" ) {
		die("<h1>This script can only be executed via command line</h1>");
	}

	$base_path = "/tmp";

	$sandr = new SandR_Ereg();

	foreach($argv as $index => $arg) {
		if($index == 0) {
			continue;
		}

		$arg_array = explode("=", $arg);

		// @todo - fix arguments to better handle regular expressions when passed via command line
		switch($arg_array[0]) {
			case "ereg":
				//command = php ereg_sandr.php ereg=true dry_run=false
				$sandr->setPhpFunctionRegex('/ereg[ \s]*\(/');
				$sandr->setSearch('/(ereg)[ \s]*\((\'|")(.+)(\'|"),[ \s]*(.+)\)/');
				$sandr->setReplaceFunction('preg_match');
				$sandr->setMethod('regex');
				break;

			case "eregi":
				//command = php ereg_sandr.php ereg=true dry_run=false
				$sandr->setPhpFunctionRegex('/eregi[ \s]*\(/');
				$sandr->setSearch('/(eregi)[ \s]*\((\'|")(.+)(\'|"),[ \s]*(.+)\)/');
				$sandr->setReplaceFunction('preg_match');
				$sandr->setInsensitive('i');
				$sandr->setMethod('regex');
				break;

			case "ereg_replace":
				//command = php ereg_sandr.php ereg_replace=true dry_run=false
				$sandr->setPhpFunctionRegex('/ereg_replace[ \s]*\(/');
				$sandr->setSearch('/(ereg_replace)[ \s]*\([ \s]*(\'|")(.+)(\'|")[ \s]*,[ \s]*(.+,.+)\)/');
				$sandr->setReplaceFunction('preg_replace');
				$sandr->setMethod('regex');
				break;

			case "base_path":
				$base_path = $arg_array[1];
				if(!is_dir($base_path)) {
					die("ERROR: Please pass a valid directory\n\n");
				}

				break;

			default:
				$arg    = ucwords(str_replace("_", " ", $arg_array[0]));
				$method = "set".str_replace(" ", "", $arg);

				if(method_exists($sandr, $method)) {
					$sandr->$method($arg_array[1]);
				}
				break;
		}
	}

	$sandr->run($base_path);