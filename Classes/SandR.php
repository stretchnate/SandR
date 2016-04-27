<?php

	/**
	 * SandR (Search and Replace)
	 * Change log
	 * this script now accepts arguments
	 * search                  - required - string/regex - this will be the function name you are searching for;
	 * replace                 - required - string - this is the replacement function name
	 * dry_run                 - optional - boolean - must be passed as true if you want to replace anything, otherwise it's a dry run.
	 * method                  - optional - string - accepts "string" or "regex" to determin what type of php function to use for replacement;
	 * php_function_regex      - optional - regex - probably not useful most of the time, but came in handy for split() when I had to search by one regex and replace by another;
	 * central_backup_location - optional - boolean - defaults to false forcing backups to be created in the directory of the main file, backups like this will have the extensions .sandr
	 * hps_path                - optional - string - will be appended to C:\Source\WebApps1\web\hps\
	 * split                   - optional - Mixed - should only be used with dry_run or hps_path or nothing. this will force the script to use the split parameters.
	 * trim                    - optional - Mixed - should only be used with hps_path or nothing. this will force the script to use the trim parameters.
	 *
	 * example command: php sandr.php search=ereg_replace replace=preg_replace
	 *
	 * dry runs will not replace anything, it will simply find the matches and log them.
	 * pathnames are now logged in windows format (using \ instead of /), this is to make it easier
	 * to open the file (copy/paste) in notepad++ and any other editor that requires the backslash in windows pathnames.
	 */

	class SandR {

		const BACKUP_DIRECTORY                = "C:/logs/query_class_sandr_backup/";
		const LOG_FILE                        = "C:/logs/sandr_log.txt";

		protected static $extensions_to_use     = array("php", "class", "inc");
		protected static $directories_to_ignore = array(".", "..");

		protected $php_function_regex           = null; //"/(\s|\n|\r|=|\?|:|\r\n|\n\r)split[\s]*\(/";
		protected $search                       = null; //"/split[\s]*\(/";
		protected $replace                      = null; //"explode(";
		protected $format_replacement           = false;
		protected $dry_run                      = true;
		protected $central_backup_location      = false;
		protected $method                       = "str_replace";
		protected $strpos_type                  = "strpos";

		protected $undo                         = false;

		protected $file_counter                 = 0;
		protected $directory_counter            = 0;
		protected $matches                      = 0;
		protected $files_with_matches           = 0;
		protected $files_unable_to_overwrite    = 0;
		protected $time_to_complete;

		public function __construct() {}

		/**
		 * main method for executing the iteration
		 *
		 * @param string $directory_path - required
		 *
		 * return void
		 */
		public function run($directory_path) {
			if(!isset($this->search)) {
				$this->log("\nERROR: Please provide search values, aborting");
				die();
			}

			$this->log("\nSearching from $directory_path\n");

			if($this->undo === true) {
				self::$extensions_to_use = array("sandr");
			}

			$start_time = new DateTime();

			$this->iterate($directory_path);

			$end_time = new DateTime();

			$date_interval          = $start_time->diff($end_time);
			$this->time_to_complete = $date_interval->format('%H:%i:%s');

			$this->finalReport($directory_path);
		}

		/**
		 * iterates through the directory
		 *
		 * @param string $directory_path - required
		 *
		 * return void
		 */
		protected function iterate($directory_path) {
			$directory = new DirectoryIterator($directory_path);

			while($directory->valid()) {
				if($directory->isDir()) {
					$this->drillDown($directory);
				} else if($directory->isFile()) {
					$this->searchFile($directory);
				}

				$directory->next();
			}
		}

		/**
		 * searches a file for matches
		 *
		 * @param string $directory
		 * @return void
		 */
		protected function searchFile(&$directory) {
			if(in_array(strtolower($this->getFileExtension($directory)), self::$extensions_to_use)) {
				if($directory->isReadable()) {
					$this->file_counter++;
					//do we have a match?
					if($this->search($directory->getPathname()) !== false) {
						$this->files_with_matches++;

						//if directory is writeable make the changes.
						if($directory->isWritable()) {
							if($this->undo !== true) {
								$this->replace($directory->getPathname());
							}
							else {
								$this->undoChanges($directory->getPathname());
							}
						}
						else {
							$this->files_unable_to_overwrite++;
							$this->log("matches found for {$directory->getFilename()} but is not writeable, fix it.");
						}
					}
				} else {
					$this->log( $directory->getPathname() . " is not readable, moving on" );
				}
			}
		}

		/**
		 * drills down through directories.
		 *
		 * @param type $directory
		 */
		protected function drillDown(&$directory) {
			if(!in_array($directory->getFilename(), self::$directories_to_ignore)) {
				if($directory->isReadable()) {
					$this->directory_counter++;
					$this->iterate($directory->getPathname());
				} else {
					$this->log( $directory->getPathname() . " is not readable, moving on" );
				}
			}
		}

		/**
		 * undoes previous changes using the .sandr backup files
		 *
		 * @param type $file_path
		 */
		protected function undoChanges($file_path) {
			$this->log("\tUndoing ".$file_path);
			$current_file = substr($file_path, 0, -5);

			if(file_exists($current_file)) {
				unlink($current_file);
			}

			rename($file_path, $current_file);
		}

		/**
		 * searches for the "search" parameter in each file
		 *
		 * @param string $file_path - required
		 *
		 * return bool
		 */
		protected function search($file_path) {
			$contents = file_get_contents($file_path);
			$result   = $this->matchFound($contents, "file");

			if($result !== false ) {
				//if we managed to navigate that condition, log a match
				$file_path = str_replace("/", "\\", $file_path);
				$this->log($this->getMatchMessage($file_path));
			}

			return $result;
		}

		/**
		 * searches for a match in a file or line
		 *
		 * @param string $contents
		 * @param string $search_type
		 * @return mixed
		 */
		protected function matchFound($contents, $search_type) {
			/* file searches are the only searches that can use the php_function_regex
			 * this helps to narrow down the possiblities on difficult to match searches.
			 */
			if($search_type == "file") {
				$result = $this->findMatchInFile($contents);
			}
			else {
				$result = $this->findMatchInLine($contents);
			}

			return $result;
		}

		/**
		 * finds a match in a line
		 *
		 * @param string $contents
		 * @return mixed
		 */
		protected function findMatchInLine(&$contents) {
			$result = false;
			$matches = array();

			//make sure our match is not on the file match
			if( empty($this->php_function_regex) || preg_match($this->php_function_regex, $contents)) {
				//when passing a regex expect delimeters to be included on $this->search
				if($this->method == "preg_replace") {
					if(preg_match($this->search, $contents, $matches)) {
						$result['match'] = $matches[0];
						$result['message'] = implode(", ", $matches)." --> ".trim($contents);
					}
				}
				else {
					$strpos = $this->strpos_type;
					$offset = $strpos($contents, $this->search);

					if($offset !== false) {
						$length            = strlen($this->search);
						$result['match']   = substr($contents, $offset, $length);
						$result['message'] = $result['match']." --> ".trim($contents);
					}
				}
			}

			return $result;
		}

		/**
		 * finds a match in a file
		 *
		 * @param string $contents
		 * @return mixed
		 */
		protected function findMatchInFile(&$contents) {
			$result = false;
			$matches = array();

			$search = $this->search;
			$strpos = $this->strpos_type;

			if($strpos($search, "/") !== 0) {
				$search = "/".$search;
			}
			if(strrpos($search, "/") !== (strlen($search) -1) ) {
				$search = $search."/";
			}

			if( (isset($this->php_function_regex) && preg_match($this->php_function_regex, $contents, $matches))
				|| (!isset($this->php_function_regex) && preg_match($search, $contents, $matches))) {
				// $result['match'] = $matches[0];
				// $result = implode(", ", $matches)." --> ".trim($contents);
				$result = " --> ".trim($contents);
			}

			return $result;
		}

		/**
		 * makes the change from search to replace if dry_run is false, otherwise we just create a log to show what
		 * would happen
		 *
		 * @todo - break this up into smaller methods
		 * @param string $file_path - required
		 * @return void
		 */
		protected function replace($file_path) {
			if($this->dry_run === false) {
				$this->backup($file_path);
			}

			$lines_array     = file($file_path);
			$new_lines_array = array();

			foreach($lines_array as $index => $line) {
				$match = $this->matchFound($line, "line");
				if( $match !== false ) {
					$this->matches++;
					if($this->dry_run === false && $this->replace) {
						$message = "\t".$this->getMatchMessage($match['message'], $index + 1);
						$this->log($message);

						// ok so replace really means replace, but it sounds better.
						$method = $this->method;
						if($this->format_replacement === true) {
							$replace = sprintf($this->replace, $match['match']);
							$line    = $method($this->search, $replace, $line);
						}
						else {
							$line = $method($this->search, $this->replace, $line);
						}

						$after_change = "\t\tnew line is --> ".$line;
						$this->log($after_change);
					}
					else {
						$message = "\t(dry run) ".$this->getMatchMessage($match['message'], $index + 1);
						$this->log($message);
					}

					if($this->format_replacement === true && !empty($match)) {
						//log an informational message showing what the replacement is.
						$replacement = "\t\treplacement = ";
						$replacement .= sprintf($this->replace, $match['match']);
						$this->log($replacement);
					}
				}
				$new_lines_array[] = $line;
			}

			if(count($lines_array) == count($new_lines_array)) {
				if($this->dry_run === false) {
					file_put_contents($file_path, $new_lines_array);
				}
			}
			else {
				$this->log("line count is off on new file, aborting.");
			}
		}

		/**
		 * get a message for the match
		 *
		 * @param string $match
		 * @param int $line
		 * @return string
		 */
		protected function getMatchMessage($match, $line = null) {
			if(!is_null($line)) {
				$message = "match found on line ".$line." -->".$match;
			}
			else {
				$message = "\npossible match(es) found in ".$match;
			}

			return $message;
		}

		/**
		 * create a backup of the file
		 *
		 * @param string $file_path - required
		 *
		 * return void
		 */
		// @todo - this method needs some help, prehaps another regex to take care of the two str_replace() instances
		protected function backup($file_path) {
			$this->log("backing up $file_path");

			if($this->central_backup_location === true) {
				$file_name = preg_replace("/C:[\\/]/i", "", $file_path);
				$file_name = str_replace("/", "_", $file_name);
				$file_name = str_replace("\\", "_", $file_name);

				copy($file_path, self::BACKUP_DIRECTORY . $file_name);
			}
			else {
				$file_name = $file_path . ".sandr";
				copy($file_path, $file_name);
			}
		}

		/**
		 * log message
		 *
		 * @param string $message - required
		 * @param bool   $echo    - optional (true)
		 * @param string $mode    - optional ('a') - mode for fopen
		 *
		 * return void
		 */
		protected function log($message, $echo = true, $mode = 'a') {
			$handle = fopen(self::LOG_FILE, $mode);
			fwrite($handle, $message . "\n");
			fclose($handle);

			if($echo === true) {
				echo $message . "\n";
			}
		}

		/**
		 * Generate the final report
		 *
		 * @param string $directory_path
		 *
		 * return void
		 */
		protected function finalReport($directory_path) {
			$this->log("\n******************************************************************************************************");
			$this->log("* SandR has finished attacking the directory \n* $directory_path");
			$this->log("* and all of it's children. Here are the results of what it found.");
			$this->log("*\n* Total Directories searched: {$this->directory_counter}");
			$this->log("* Total Files searched: {$this->file_counter}");
			$this->log("* Total Files with matches: {$this->files_with_matches}");
			$this->log("* Total matches that were found and replaced: {$this->matches}");
			$this->log("* Total Files unable to overwrite: {$this->files_unable_to_overwrite}");
			$this->log("*\n* Total time of completion: ".$this->getTimeToComplete());
			$this->log("*\n* Have a nice day :)");
			$this->log("******************************************************************************************************");
		}

		/**
		 * This method gets the file extension
		 *
		 * @param object $directory_iterator
		 *
		 * @since 01.30.2013
		 * @return String
		 */
		public function getFileExtension(DirectoryIterator $directory_iterator) {
			//DirectoryIterator::getExtension() is only available to 5.3 > 5.3.5 so this is for those who aren't quite up to 5.3.6 or later.
			if( !method_exists($directory_iterator, "getExtension") ) {
				$ext = pathinfo($directory_iterator->getFilename(), PATHINFO_EXTENSION);
			}
			else {
				$ext = $directory_iterator->getExtension();
			}

			return $ext;
		}

		/**
		 * calls the cleanSandRBox method and reports the findings
		 *
		 * @param string $path
		 * @param string $sandr_extension
		 * @return void
		 * @since 2013.06.04
		 */
		public function clean($path, $sandr_extension = 'sandr') {
			$total = $this->cleanSandRBox($path, $sandr_extension);

			$removed = "";
			if($this->dry_run === false) {
				$removed = "and removed ";
			}

			$this->log("\n*********************************************************************************************************************");
			$this->log("SandR found " . $removed . $total . " files with the ." . $sandr_extension . " file extension in " . $path);
			$this->log("*********************************************************************************************************************");
		}

		/**
		 * recursively removes all files with the .sandr extension from the $path
		 *
		 * @param $path string
		 * @return int
		 * @since 2013.06.04
		 */
		protected function cleanSandRBox($path, $sandr_extension) {
			$i        = 0;
			$iterator = new DirectoryIterator($path);
			$dry_run  = '';

			if($this->dry_run === true) {
				$dry_run = '(dry run) ';
			}

			while($iterator->valid()) {
				if($iterator->isFile() && strtolower($this->getFileExtension($iterator)) == $sandr_extension) {
					$i++;
					$this->log($dry_run . 'removing ' . $iterator->getPathname());

					if($this->dry_run === false) {
						unlink($iterator->getPathname());
					}
				} else if($iterator->isDir() && !in_array($iterator->getFilename(), self::$directories_to_ignore)) {
					$i += $this->cleanSandRBox($iterator->getPathname(), $sandr_extension);
				}

				$iterator->next();
			}

			return $i;
		}

		/**
		 * gets time to complete
		 * @return string
		 */
		protected function getTimeToComplete() {
			return $this->time_to_complete;
		}

		/**
		 * convert any value to boolean
		 * - string false will convert to boolean false
		 *
		 * @param mixed $var
		 * @return boolean
		 */
		public function getBoolean($var) {
			if(is_bool($var)) {
				return $var;
			}

			$return = false;

			if($var != 'false') {
				$return = (bool)$var;
			}

			return $return;
		}

		/**
		 * sets the php_function_regex property
		 *
		 * @param string $php_function_regex
		 * @return \SandR
		 */
		public function setPhpFunctionRegex($php_function_regex) {
			$this->php_function_regex = $php_function_regex;
			$this->log("php_function_regex is set to ".$this->php_function_regex);

			return $this;
		}

		/**
		 * sets the search string
		 *
		 * @param string $search
		 * @return \SandR
		 */
		public function setSearch($search) {
			$this->search = $search;
			$this->log("search is set to ".$this->search);

			return $this;
		}

		/**
		 * sets the replace value
		 *
		 * @param mixed $replace
		 * @return \SandR
		 */
		public function setReplace($replace) {
			$this->replace = $replace;
			$this->log("replace is set to '".$this->replace."'");

			return $this;
		}

		/**
		 * sets the dry run flag
		 *
		 * @param string $dry_run
		 * @return \SandR
		 */
		public function setDryRun($dry_run) {
			if($this->getBoolean($dry_run) === true) {
				$this->dry_run = true;
				$this->log("dry_run is set to (boolean) true");
			}
			else {
				$this->dry_run = false;
				$this->log("dry_run is set to (boolean) false");
			}

			return $this;
		}

		/**
		 * sets the replacement format
		 *
		 * @param string $format_replacement
		 * @return \SandR
		 */
		public function setFormatReplacement($format_replacement) {
			if($this->getBoolean($format_replacement) === true) {
				$this->format_replacement = true;
				$this->log("format_replacement is set to (boolean) true");
			}
			else {
				$this->format_replacement = false;
				$this->log("format_replacement is set to (boolean) false");
			}

			return $this;
		}

		/**
		 * sets the central backup location
		 *
		 * @param string $central_backup_location
		 * @return \SandR
		 */
		public function setCentralBackupLocation($central_backup_location) {
			if($this->getBoolean($central_backup_location) !== false) {
				$this->central_backup_location = true;
				$this->log("central_backup_location is set to (boolean) true");
			}
			else {
				$this->central_backup_location = false;
				$this->log("central_backup_location is set to (boolean) false");
			}

			return $this;
		}

		/**
		 * sets the search method (str_replace, preg_replace, str_ireplace)
		 *
		 * @param string $method
		 * @return \SandR
		 */
		public function setMethod($method) {
			switch($method) {
				case "string":
					$this->method      = "str_replace";
					$this->strpos_type = 'strpos';
					break;

				case "regex":
					$this->method = "preg_replace";
					break;

				case "stringi":
					$this->method      = "str_ireplace";
					$this->strpos_type = 'stripos';
					break;
			}

			if($this->method != 'preg_replace') {
				$this->log("Search using " . $this->strpos_type);
			}

			$this->log("Replace using " . $this->method);

			return $this;
		}

		/**
		 * sets the undo flag
		 *
		 * @param string $undo
		 * @return \SandR
		 */
		public function setUndo($undo) {
			if($this->getboolean($undo) !== false) {
				$this->undo = true;
				$this->log("undo is set to (boolean) true");
			}
			else {
				$this->undo = false;
				$this->log("undo is set to (boolean) false");
			}

			return $this;
		}
	}