<?php
/**
 * @package bridge
 * @subpackage cache
 * @author gbellucci
 * @version 1.0
 * @abstract
 *    A disk caching class for caching database records.
 */
namespace infotec {
	/**
	 * cache
	 *
	 * @author gbellucci
	 */
	class Cache {

		/**
		 * @var null cache directory path
		 */
		protected $cache_dir;     // cache directory name

		/**
		 * @var int expireTime time for cached item
		 */
		private $expireTime;      // cache expire (in minutes)

		/**
		 * @var object - logging object
		 */
		private $dbg;

		/**
		 * constructor - create the cache directory name and create it
		 * if it doesn't exist. Set directory permissions
		 *
		 * @param string $moduleName - logging file name
		 * @param null   $cache_dir - path to the cache directory
		 * @param int    $lifetime  - the number of minutes the cached object stays alive
		 * @param int    $debug 1=active, 0=inactive
		 */
		function __construct($moduleName = 'cache', $cache_dir = null, $lifetime = 30, $debug=0) {
			$this->dbg = new \ecpi\dbc($moduleName);
			$this->dbg->set_debug($debug);
			$this->expireTime = (60 * intval($lifetime, 10));
			$this->cache_dir = $_SERVER['DOCUMENT_ROOT'] . $cache_dir;

			if(!empty($cache_dir)) {
				if (!@file_exists($this->cache_dir)) {
					$this->dbg->log_entry(__FUNCTION__ . '/creating: ', $this->cache_dir);
					@mkdir($this->cache_dir);
					@chmod($this->cache_dir, 0775);
				}
				else {
					if (!is_writable($this->cache_dir)) {
						@chmod($this->cache_dir, 0775);
					}
				}
			}
			// check all files for expiration
			$this->checkExpiredAll();
			$this->dbg->log_entry('cache', sprintf('dir: %s, expires: %s', $this->cache_dir, $this->expireTime) );
		}

		/**
		 * Function read retrieves value from cache
		 * Cached files contain a header that consists of a tim stamp and the data that is to be saved.
		 * Timestamps are used inside the file because creation time and modified (file) timestamps
		 * are unreliable for stat function calls. The read function will return only the data if the
		 * header flag is 0, otherwise the header containing the timestamp and data is returned if
		 * the header value is 1.
		 *
		 * @param $key - name of the cache file
		 * @param int $header
		 *
		 * @return mixed $value
		 */
		function read($key, $header = 0) {
			$value = $data = null;
			if($this->checkDir() && !empty($key)) {
				$key   = $this->makeKeyName($key);

				if (file_exists($key)) {
					if (!$this->checkExpired($key)) {
						if( $handle = fopen($key, 'rb') ) {
							flock($handle, LOCK_SH);
							$data = fread($handle, filesize($key));
							$data = (!$header ? $data['value'] : $data);
							flock($handle, LOCK_UN);
							fclose($handle);
						}
						$value = $this->unpack($data);
						$this->dbg->log_entry('cache', sprintf('read: %d bytes from cache', filesize($key)));
					}
				}
			}
			return($value);
		}

		/**
		 * Function for writing key,  value to cache
		 * @param $key - name of the cache file
		 * @param mixed $value - value
		 */
		function write($key, $value) {
			if ( $this->checkDir() && !empty($key) ) {
				$key = $this->makeKeyName($key);
				if (!file_exists($key)) {
					if ($handle = fopen($key, 'w')) {
						if (flock($handle, LOCK_EX)) {
							$data = array('time' => (time() + $this->expireTime), 'value' => $value);
							$bytes = fwrite($handle, $this->pack($data));
							$this->dbg->log_entry('cache', sprintf('write: %d bytes to cache', $bytes));
							flock($handle, LOCK_UN);
						}
						fclose($handle);
					}
				}
			}
		}

		/**
		 * Function for deleting cache file
		 * @param $key - name of the cache file (key)
		 */
		function delete($key) {
			if( $this->checkDir() && !empty($key)) {
				if (@file_exists($key)) {
					@unlink($key);
					$filename = pathinfo ( $key, PATHINFO_FILENAME);
					$this->dbg->log_entry('cache', sprintf('deleted: %s', $filename));
				}
			}
		}

		/**
		 * Checks the directory
		 * @return bool - 1=valid, 0=not valid
		 */
		function checkDir() {
			clearstatcache();

			if(empty($this->cache_dir)) {
				$this->dbg->log_entry(__FUNCTION__, 'cache path is null.');
			}
			elseif(!is_dir($this->cache_dir)) {
				$this->dbg->log_entry(__FUNCTION__, 'cache path: ' . $this->cache_dir . ' is not a directory.');
			}
			elseif(!is_writeable($this->cache_dir)) {
				$this->dbg->log_entry(__FUNCTION__, 'cache path: ' . $this->cache_dir . ' is not writeable.');
			}
			return(!empty($this->cache_dir) && is_dir($this->cache_dir) && is_writable($this->cache_dir));
		}

		/**
		 * Unpacks the data in the file
		 * This routine expects all data to be stored as a JSON string
		 * @param $data
		 * @return mixed $data
		 */
		function unpack($data) {
			return(json_decode($data));
		}

		/**
		 * Packs the data for storage
		 * This routine saves all data as a JSON string
		 * @param $data
		 * @return mixed $data
		 */
		function pack($data) {
			return(json_encode($data));
		}

		/**
		 * Checks for cache file that have expired
		 * @param $key
		 * @return bool - 1=expired, 0=not expired
		 */
		function checkExpired($key) {
			$rc = 0;
			$this->dbg->log_entry(__FUNCTION__, sprintf('checking time for %s', $key));
			if($this->checkDir() && !empty($key)) {
				if(file_exists($key)) {
					$data = $this->read($key, true);
					$header = $this->unpack($data);
					if($header['time'] < time()) {
						$filename = pathinfo($key, PATHINFO_FILENAME);
						$this->dbg->log_entry('cache', sprintf('expired: %s - %s', $filename, date ("H:i:s", $header['time'])));
						$this->delete($key);
						$rc = 1;
					}
				}
				else {
					$this->dbg->log_entry(__FUNCTION__, sprintf('file not found %s', $key));
				}
			}
			return($rc);
		}

		/**
		 * Checks for expired files
		 */
		function checkExpiredAll() {
			$files = @scandir ( $this->cache_dir );
			if(!empty($files)) {
				foreach ($files as $file) {
					if ($file != '.' && $file != '..') {
						$this->checkExpired($this->cache_dir . '/' . $file);
					}
				}
			}
		}

		/**
		 * Creates a complete key name
		 * @param $key
		 *
		 * @return string
		 */
		function makeKeyName($key) {
			return($this->cache_dir . '/cache_' . sha1($key));
		}
	}
}