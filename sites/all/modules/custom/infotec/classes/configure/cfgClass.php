<?php
/**
 * @package ecpi utilities
 * @subpackage configuration settings class
 * @version 1.1
 * @author gbellucci
 * @abstract
 *
 * Reads/parses a config.ini file and returns individual section values using a get request function.
 * Name items are requested by using "section-name.parameter" - the get function returns the parameter
 * value or false if not found. An entire section of the config.ini can be requested by asking for
 * "section-name"
 */

namespace ecpi {

	class cfgObj {

		private $properties = array();

		function __construct($ini_file) {
			try {
				if(empty($ini_file) || !file_exists($ini_file)) {
					throw new \Exception(sprintf('missing ini file: %s::%s()', __CLASS__, __FUNCTION__), __LINE__);
				}
				$this->properties = parse_ini_file( $ini_file, true );
				if(empty($this->properties) || !is_array($this->properties)) {
					throw new \Exception(sprintf('invalid ini file - check file contents %s::%s()', __CLASS__, __FUNCTION__),  __LINE__);
				}
			}
			catch(\Exception $e) {
				printf("Error: %s (line: %s)", $e->getMessage(), $e->getCode());
			}
		}

		/**
		 * return parameter
		 *   name = section.name | section
		 *
		 * @param $name
		 * @return bool
		 */
		function get($name) {
			$ret = false;
			if(!empty($name)) {
				if ( stristr( $name, "." ) ) {
					list( $section_name, $property ) = explode( ".", $name );
					if ( isset( $this->properties[ $section_name ] ) && isset( $this->properties[ $section_name ][ $property ] ) ) {
						$ret = $this->doSubs($this->properties[ $section_name ][ $property ]);
					}
				} else {
					if ( isset( $this->properties[ $name ] ) ) {
						$ret = $this->doSubs($this->properties[ $name ]);
					}
				}
			}
			return($ret);
		}

		/**
		 * Performs substitutions for symbols in the value
		 *      %root = the root directory name
		 *      %path = the host path
		 *      %host = the name of the host
		 *
		 * @param $item
		 *
		 * @return mixed
		 */
		function doSubs($item) {
			if(is_array($item)) {
				foreach($item as $keyword => $value) {
					$item[$keyword] = $this->_sub($value);
				}
			}
			$value = $this->_sub($item);
			return($item);
		}

		/**
		 * Return substituted value or just the value
		 * @param $value
		 *
		 * @return mixed
		 */
		function _sub($value) {
			return(trim($value));
		}
	}
}