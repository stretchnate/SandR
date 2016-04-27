<?php
	/**
	 * the main purpose of this script is to handle pass by reference scenarios
	 */
	require_once('Classes/SandR/Exclude.php');

	if( php_sapi_name() != "cli" ) {
		die("<h1>This script can only be executed via command line</h1>");
	}

	clearLog('/tmp/sandr/log.txt');

	$base_path = "/tmp";

	$sandr = new SandR_Exclude();

	foreach($argv as $index => $arg) {
		if($index == 0) {
			continue;
		}

		$arg_array = explode("=", $arg);

		// @todo - fix arguments to better handle regular expressions when passed via command line
		switch($arg_array[0]) {
			case "pass_by_reference":
				//command : php sandr\exclude_sandr.php pass_by_reference=true
				$sandr->setSearch('/(\->|::|\s)[\w]+[\s ]*\(.+[&]\$.+\)/');
				$sandr->setExclude('(array|function)[ \s]{1}');
				$sandr->setExcludePosition(SandR_Exclude::EXCLUDE_IMMEDIATLEY_BEFORE);
				$sandr->setDryRun(true); //never run pass_by_reference in anything but dry run mode.
				$sandr->setMethod('regex');
				break;

			case "base_path":
				$base_path = $arg_array[1];
				if(!is_dir($base_path)) {
					die("ERROR: Please pass a valid directory\n\n");
				}
			default:
				if($arg_array[0] == 'classname') {
					$classname = $arg_array[1];
				} else {
					$arg = ucwords(str_replace("_", " ", $arg_array[0]));
					$method = "set".str_replace(" ", "", $arg);

					if(method_exists($sandr, $method)) {
						$sandr->$method($arg_array[1]);
					}
				}

				break;
		}
	}

	$sandr->run($base_path);
