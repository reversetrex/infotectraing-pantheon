<?php

/**
 * @package debug/logging classes
 * @author gbellucci
 * @version $Id: log.class.php 18 2014-07-27 16:18:23Z gbellucci $
 */
namespace ecpi {

	if(defined ( 'NO_LOGGING' )) {
		// class stub
		class dbc {
			public function log_entry() {}
			public function set_debug() {}
			public function empty_log() {}
		}
	}
	else {

		/**
		 * Debug/logging class
		 * for development use
		 * @author gbellucci
		 */
		class dbc {

			private $debug = true;
			private $module_name;
			private $fn;

			/**
			 * constructor
			 * @param $module_name - name of the module
			 * @param $path - path name to the directory location
			 */
			function __construct($module_name = false, $path=false) {
				$this->module_name = (empty($module_name) ? 'debugger' : $module_name);
				$this->module_name = str_replace(' ', '-', $this->module_name);
				// $dir = (empty($path) ? dirname(__FILE__) : trim($path, '/'));
				$dir = (empty($path) ? './../../../../default/files/' : trim($path, '/'));
				$dir .= '/infotechlogs';

				// the directory is always the 'logs' subdirectory
				if(!file_exists($dir)) {
					// create the log directory if needed
					if( mkdir($dir, 0774, true) === false) {
						$dir = ./../../../../default/files . '/infotechlogs';
//						$dir = dirname(__FILE__) . '/infotechlogs';
					}
				}
				$this->fn = $dir . '/' . $this->module_name . '.log';
				$this->set_timezone();
			}

			/**
			 * Sets the timezone
			 * @param $tz
			 */
			public function set_timezone($tz=false) {
				$tz = (empty($tz) ? "America/New_York" : $tz);
				date_default_timezone_set($tz);
			}

			/**
			 * make a log entry in the debug file
			 * @param $label - the location where the log entry was made (i.e. __FUNCTION__)
			 * @param $data - the log entry (can be an array, object, variable or string)
			 */
			public function log_entry($label, $data) {
				if($this->debug) {
					$this->write ( $label, $data );
				}
			}

			/**
			 * To change the state of the debug
			 * true = on, false = off
			 * @param bool $state
			 */
			public function set_debug($state) {
				$this->debug = $state;
			}

			/**
			 * Returns the state of the debug switch
			 * @return bool
			 */
			public function get_state() {
				return($this->debug);
			}

			/**
			 * Empties a file
			 */
			public function empty_log() {
				file_put_contents($this->fn, '--', LOCK_EX);
			}

			/**
			 * Log an entry
			 *
			 * @param $label - string (usually class/function) creating the log entry
			 * @param $var - mixed var (array|object|string)
			 */
			private function write($label = 'no-label', $var = 'no-var') {
				if(!empty($label)) {
					$curtime = date ( 'M j,Y g:i a' );
					$user_ip = isset ( $_SERVER ["REMOTE_ADDR"] ) ? $_SERVER ["REMOTE_ADDR"] : "localhost";
					$contents  = "\n{$curtime}/{$this->module_name} [{$user_ip}]: [{$label}]:";
					$contents .= sprintf ( " %s", print_r ( $var, 1 ) );
					file_put_contents($this->fn, $contents, FILE_APPEND);
					if(!(fileperms($this->fn) & 0774)) {
						@chmod($this->fn, 0774);
					}
				}
			}
		} // end class

	}// end else

	/**
	 * Class logException
	 * @author: gbellucci
	 * An exception with logging capability
	 * with or without backtrace
	 */
	class logException extends \Exception {

		/**
		 * @param object $module - 'dbc' object
		 * @param int $backtrace - 1 = output a stack trace, otherwise 0
		 * @param string $message - exception message
		 * @param int $code - exception code
		 * @param \Exception $previous
		 */
		public function __construct($module = null, $backtrace = 0, $message = null, $code = 0, \Exception $previous = null) {
			parent::__construct($message, $code, $previous);

			if(!empty($module) && get_class($module) == 'dbc') {
				$m = (!empty($message) ? '"' . $message . '"': $this->getFile());
				$c = (!empty($code) ? '(' . $code . ')' : sprintf("(line#: %s)", $this->getLine()));
				$trace = ($backtrace ? "\n\tStack Trace:\n" . $this->getExceptionTraceAsString(): '');
				$module->log_entry('>>> exception', sprintf("msg: %s code: %s", $m, $c) . $trace);
			}
		}

		/**
		 * Creates strings containing the backtrace. Strings are not truncated.
		 * @return string
		 */
		private function getExceptionTraceAsString() {
			$rtn = "";
			$count = 0;
			foreach ($this->getTrace() as $frame) {
				$args = "";
				if (isset($frame['args'])) {
					$args = array();
					foreach ($frame['args'] as $arg) {
						if (is_string($arg)) {
							$args[] = "'" . $arg . "'";
						} elseif (is_array($arg)) {
							$args[] = "Array";
						} elseif (is_null($arg)) {
							$args[] = 'NULL';
						} elseif (is_bool($arg)) {
							$args[] = ($arg) ? "true" : "false";
						} elseif (is_object($arg)) {
							$args[] = get_class($arg);
						} elseif (is_resource($arg)) {
							$args[] = get_resource_type($arg);
						} else {
							$args[] = $arg;
						}
					}
					$args = join(", ", $args);
				}
				$rtn .= sprintf( "\t\t#%s %s(%s): %s(%s)\n",
				                 $count,
				                 isset($frame['file'])   ? $frame['file'] : 'unk file',
				                 isset($frame['line'])   ? $frame['line'] : 'unk line',
				                 (isset($frame['class'])) ? $frame['class'] . $frame['type'] . $frame['function'] : $frame['function'],
				                 $args );
				$count++;
			}
			return $rtn;
		}
	}
}// end exception class
