<?php
	require_once('../SandR.php');

	/**
	 * SandR (Search and Replace) extension for replacing pass by reference instances
	 *
	 * dry runs will not replace anything, it will simply find the matches and log them.
	 * pathnames are now logged in windows format (using \ instead of /), this is to make it easier
	 * to open the file (copy/paste) in notepad++ and any other editor that requires the backslash in windows pathnames.
	 */
	class SandR_Exclude extends SandR {

		const EXCLUDE_BEFORE             = 'before';
		const EXCLUDE_AFTER              = 'after';
		const EXCLUDE_IMMEDIATLEY_BEFORE = 'ibefore';//ignores whitespace
		const EXCLUDE_IMMEDIATLEY_AFTER  = 'iafter';//ignores whitespace

		private $exclude          = null;
		private $exclude_position = null;

		public function __construct() {
			parent::__construct();
		}

		/**
		 * executes the search
		 *
		 * @param type $directory_path
		 */
		public function run($directory_path) {
			$this->buildExcludeExpression();
			parent::run($directory_path);
		}

		/**
		 * finds a match in a line/string
		 *
		 * @param string $contents
		 * @return string
		 */
		protected function findMatchInLine(&$contents) {
			$result  = false;
			$matches = array();

			//make sure our match is not on the file match
			if( empty($this->php_function_regex) || preg_match($this->php_function_regex, $contents)) {
				//when passing a regex expect delimeters to be included on $this->search
				if($this->method == "preg_replace") {
					if(preg_match($this->search, $contents, $matches)) {
						if( !preg_match($this->exclude, $contents) ) {
							$result['match']   = $matches[0];
							$result['message'] = implode(", ", $matches)." --> ".trim($contents);
						}
					}
				}
			}

			return $result;
		}

		/**
		 * builds a regular expression to use for exclusion
		 *
		 * @access protected
		 */
		protected function buildExcludeExpression() {
			//remove delimiters
			$delimiter = substr($this->search, 0, 1);
			$search    = trim($this->search, $delimiter);

			switch($this->exclude_position) {
				case self::EXCLUDE_BEFORE:
					$this->exclude = $delimiter . $this->exclude . '.*' . $search . $delimiter;
					break;

				case self::EXCLUDE_AFTER:
					$this->exclude = $delimiter . $search . '.*' . $this->exclude . $delimiter;
					break;

				case self::EXCLUDE_IMMEDIATLEY_BEFORE:
					$this->exclude = $delimiter . $this->exclude . '[ \s]*' . $search . $delimiter;
					break;

				case self::EXCLUDE_IMMEDIATLEY_AFTER:
					$this->exclude = $delimiter . $search . '[ \s]*' . $this->exclude . $delimiter;
					break;

				default:
			}

			$this->log("exclude Expression = " . $this->exclude);
		}

		/**
		 * set the exclude property
		 *
		 * @param string $exclude
		 * @return \SandR_Exclude
		 */
		public function setExclude($exclude) {
			$this->exclude = $exclude;
			$this->log("exclude is set to '".$this->exclude."'");

			return $this;
		}

		/**
		 * sets the exclude position property
		 *
		 * @param string $exclude_position
		 * @return \SandR_Exclude
		 */
		public function setExcludePosition($exclude_position) {
			$this->exclude_position = $exclude_position;

			return $this;
		}
	}
