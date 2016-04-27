<?php
	require_once('../SandR.php');

	/**
	 * SandR (Search and Replace) extension for replacing ereg* functions with preg* functions
	 *
	 * replace_function - string representation of the function to use in the replacement
	 * insensitive - case insensitive search
	 *
	 * example command: php ereg_sandr.php search=ereg_replace replace=preg_replace
	 *
	 * dry runs will not replace anything, it will simply find the matches and log them.
	 * pathnames are now logged in windows format (using \ instead of /), this is to make it easier
	 * to open the file (copy/paste) in notepad++ and any other editor that requires the backslash in windows pathnames.
	 */
	class SandR_Ereg extends SandR {
		private $replace_function = null;
		private $insensitive      = '';

		public function __construct() {
			parent::__construct();
		}

		/**
		 * searches for matches in the file or line
		 *
		 * @param type $contents
		 * @param type $search_type
		 * @todo break this up into multiple methods
		 * @return string
		 */
		protected function matchFound($contents, $search_type) {
			$result  = false;
			$matches = array();

			/* file searches are the only searches that can use the php_function_regex
			 * this helps to narrow down the possiblities on difficult to match searches.
			 */
			if($search_type == "file") {
				$search = $this->search;
				if(strpos($search, "/") !== 0) {
					$search = "/".$search;
				}
				if(strrpos($search, "/") !== (strlen($search) -1) ) {
					$search = $search."/";
				}

				if( (isset($this->php_function_regex) && preg_match($this->php_function_regex, $contents, $matches))
				|| (!isset($this->php_function_regex) && preg_match($search, $contents, $matches))) {
					$result['match'] = $matches[0];
					$result = implode(", ", $matches)." --> ".trim($contents);
				}
			}
			else {
				//make sure our match is not on the file match
				if( empty($this->php_function_regex) || preg_match($this->php_function_regex, $contents)) {
					//when passing a regex expect delimeters to be included on $this->search
					if($this->method == "preg_replace") {
						if(preg_match($this->search, $contents, $matches)) {
							$result['match']   = $matches[0];
							$this->replace     = $this->replace_function . "(" . $matches[2] . "~" . $matches[3] . "~g" . $this->insensitive . $matches[4] . ", " . $matches[5] . ")";
							$result['message'] = implode(", ", $matches)." --> ".trim($contents);
						} else {
							$this->log("Match found but not exact, please manually fix this file");
						}
					}
					else {
						$offset = strpos($contents, $this->search);
						if($offset !== false) {
							$length            = strlen($this->search);
							$result['match']   = substr($contents, $offset, $length);
							$result['message'] = $result['match']." --> ".trim($contents);
						}
					}
				}
			}

			return $result;
		}

		public function setReplaceFunction($replace) {
			$this->replace = $this->replace_function = $replace;
			$this->log("replace_function is set to '".$this->replace_function."'");
		}

		public function setInsensitive($insensitive) {
			if($this->getBoolean($insensitive) == true) {
				$this->insensitive = 'i';
			}
		}

	}
