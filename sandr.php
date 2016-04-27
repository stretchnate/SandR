<?php
	require_once('Classes/SandR.php');

	/**
	 * SandR (Search and Replace)
	 * Change log
	 * this script now accepts arguments
	 * search                  - required - string/regex - this will be the function name you are searching for;
	 * replace                 - required - string - this is the replacement function name
	 * dry_run                 - optional - boolean - must be passed as false if you want to replace anything, otherwise no files will be changed.
	 * method                  - optional - string - accepts "string" or "regex" to determin what type of php function to use for replacement;
	 * php_function_regex      - optional - regex - probably not useful most of the time, but came in handy for split() when I had to search by one regex and replace by another;
	 * central_backup_location - optional - boolean - defaults to false forcing backups to be created in the directory of the main file, backups like this will have the extensions .sandr
	 * hps_path                - optional - string - will be appended to C:\Source\WebApps1\web\hps\
	 * split                   - optional - Mixed - this will force the script to use the split parameters.
	 * trim                    - optional - Mixed - should only be used with base_path or nothing. this will force the script to use the trim parameters.
	 *
	 * example command: php sandr.php search=ereg_replace replace=preg_replace
	 *
	 * dry runs will not replace anything, it will simply find the matches and log them.
	 * pathnames are now logged in windows format (using \ instead of /), this is to make it easier
	 * to open the file (copy/paste) in notepad++ and any other editor that requires the backslash in windows pathnames.
	 */
	if( php_sapi_name() != "cli" ) {
		die("<h1>This script can only be executed via command line</h1>");
	}

	$base_path = "/tmp";

	$sandr = new SandR();

	foreach($argv as $index => $arg) {
		if($index == 0) {
			continue;
		}

		$arg_array = explode("=", $arg);

		// @todo - fix arguments to better handle regular expressions when passed via command line
		switch($arg_array[0]) {
			case "split":
				//command : php.exe C:\Source\WebApps1\web\W1\sandr.php split=true
				$sandr->setPhpFunctionRegex('/(\s|\n|\r|=|\?|:|\r\n|\n\r)split[\s]*\(/');
				$sandr->setSearch('/split[\s]*\(/');
				$sandr->setReplace("explode(");
				$sandr->setMethod('regex');
				break;
			case "trim":
				//command : php.exe C:\Source\WebApps1\web\W1\sandr.php trim=true
				$sandr->setPhpFunctionRegex('/(\s|\n|\r|=|\?|:|\r\n|\n\r)trim[\s]*\(/');
				$sandr->setSearch('/trim[\s]*\(/');
				$sandr->setReplace("trim(");
				$sandr->setMethod('regex');
				$sandr->setDryRun("true");
				break;
			case "return_new_by_reference":
				//command : php.exe C:\Source\WebApps1\web\W1\sandr.php return_new_by_reference=true
				$sandr->setSearch("/=[ \s]*&[ \s]+new[\s]+/");
				$sandr->setReplace("= new ");
				$sandr->setMethod('regex');
				break;
			case "get_class":
				//command : php.exe C:\Source\WebApps1\web\W1\sandr.php get_class=true
				$sandr->setPhpFunctionRegex('/[^strtolower\(]get_class[\s]*\([\$a-zA-Z_0-9\[\]\-\>\.]+\)/');
				$sandr->setSearch("/get_class[\s]*\([\$a-zA-Z_0-9\[\]\-\>\.]+\)/");
				$sandr->setFormatReplacement('true');
				$sandr->setReplace("strtolower(%s)");
				$sandr->setMethod('regex');
				break;
			case "data_start_row":
				//command : php.exe C:\Source\WebApps1\web\W1\sandr.php data_start_row=true
				// $sandr->setPhpFunctionRegex('/[^strtolower\(]get_class[\s]*\([\$a-zA-Z_0-9\[\]\-\>\.]+\)/');
				$sandr->setSearch("<data_start_row>1");
				// $sandr->setFormatReplacement('true');
				$sandr->setReplace("<data_start_row>2");
				// $sandr->setMethod('regex');
				break;
			case "class_instantiated":
				//command : php sandr.php classname=[classname] class_instantiated=true
				static $classname_set = false;

				if(!isset($classname)) {
					if($classname_set === false) {
						//if we don't have a classname yet move class_instantiated to the end of the array.
						if( in_array($arg_array[0].'='.$arg_array[1], $argv) ) {
							$argv[] = $arg_array[0].'='.$arg_array[1];
							$classname_set = true;
						} else {
							die("\nno classname provided\n");
						}
					}
				} else {
					$search = sprintf('(new|extends)[ \s]+%s[ \s]*\(', $classname);
					$sandr->setSearch($search);
					$sandr->setMethod('regex');
				}

				break;

			case "hps_path":
				if(strpos(strtoupper($arg_array[1]), "C:") !== false) {
					die("ERROR: Argument 'hps_path' must be a subdirectory of C:\\Source\\WebApps1\\web\\hps\\. Aborting.\n\n");
				}
				else {
					$base_path .= str_replace("\\", "/", $arg_array[1]);
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
