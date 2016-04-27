<?php
	require_once('Classes/SandR.php');

	if( php_sapi_name() != "cli" ) {
		die("<h1>This script can only be executed via command line</h1>");
	}

	$base_path       = "/tmp";
	$file_extension = 'sandr';

	$sandr = new SandR();

	foreach($argv as $index => $arg) {
		if($index == 0) {
			continue;
		}

		$arg_array = explode("=", $arg);

		// @todo - fix arguments to better handle regular expressions when passed via command line
		switch($arg_array[0]) {
			case "base_path":
				$base_path = $arg_array[1];
				if(!is_dir($base_path)) {
					die("ERROR: Please pass a valid directory\n\n");
				}

				break;

			case "file_extension":
				$file_extension = $arg_array[1];
				break;

			default:
				$arg = ucwords(str_replace("_", " ", $arg_array[0]));
				$method = "set".str_replace(" ", "", $arg);

				if(method_exists($sandr, $method)) {
					$sandr->$method($arg_array[1]);
				}

				break;
		}
	}

	$sandr->clean($path, $file_extension);
