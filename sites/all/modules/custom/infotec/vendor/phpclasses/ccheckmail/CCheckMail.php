<?php
/*
*	This script was writed by Setec Astronomy - setec@freemail.it
*
*	This script is distributed  under the GPL License
*
*	This program is distributed in the hope that it will be useful,
*	but WITHOUT ANY WARRANTY; without even the implied warranty of
*	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* 	GNU General Public License for more details.
*
*	http://www.gnu.org/licenses/gpl.txt
*
*/

define ('DEBUG_OK', false);

class CCheckMail {
	var $dbg;
	var $timeout = 10;
	var $domain_rules
		= array("aol.com", "bigfoot.com", "brain.net.pk", "breathemail.net",
		        "compuserve.com", "dialnet.co.uk", "glocksoft.com", "home.com",
		        "msn.com", "rocketmail.com", "uu.net", "yahoo.com", "yahoo.de");

	function __construct($debug=0) {
		$this->dbg = new \ecpi\dbc('mail.check');
		$this->dbg->set_debug($debug);
	}

	function _is_valid_email($email = "") {
		return preg_match('/^[.\w-]+@([\w-]+\.)+[a-zA-Z]{2,6}$/', $email);
	}

	function _check_domain_rules($domain = "") {
		return in_array(strtolower($domain), $this->domain_rules);
	}

	/**
	 * @param string $email
	 *
	 * @return bool
	 */
	function execute($email = "") {
		$mxhosts = array();

		if (!$this->_is_valid_email($email)) {
			$this->dbg->log_entry(__FUNCTION__, 'invalid email address');

			return false;
		}

		$host = substr(strstr($email, '@'), 1);

		if ($this->_check_domain_rules($host)) {
			return false;
		}

		$host .= ".";

		if (getmxrr($host, $mxhosts[0], $mxhosts[1]) == true) {
			array_multisort($mxhosts[1], $mxhosts[0]);
		}
		else {
			$mxhosts[0] = $host;
			$mxhosts[1] = 10;
		}

		$this->dbg->log_entry(__FUNCTION__ . ' /mxhosts: ', $mxhosts);
		$port      = 25;
		$localhost = $_SERVER['HTTP_HOST'];
		$sender    = 'info@' . $localhost;

		$result = false;
		$id     = 0;

		while (!$result && $id < count($mxhosts[0])) {
			if (function_exists("fsockopen")) {
				$this->dbg->log_entry(__FUNCTION__, sprintf("mxhosts[0][%s] = %s", $id, $mxhosts[0][$id]));

				if ($connection = fsockopen($mxhosts[0][$id], $port, $errno, $error, $this->timeout)) {
					// read welcome msg first
					$data = fgets($connection, 1024);
					$this->dbg->log_entry(__FUNCTION__, sprintf("welcome msg: %s", $data));

					// say hello
					fputs($connection, "HELO $localhost\r\n"); // 250
					$rsp = fgets($connection, 1024);
					$ok = stristr($rsp, '250') ? 1: 0;
					$this->dbg->log_entry(__FUNCTION__, sprintf("HELO %s: %s", $localhost, $rsp));

					if ($ok) {
						fputs($connection, "MAIL FROM:<$sender>\r\n");
						$rsp = fgets($connection, 1024);
						$ok = stristr($rsp, '250') ? 1: 0;
						$this->dbg->log_entry(__FUNCTION__, sprintf("MAIL FROM: %s: %s", $sender, $rsp));

						if ($ok) {
							fputs($connection, "RCPT TO:<$email>\r\n");
							$rsp = fgets($connection, 1024);
							$ok = stristr($rsp, '250') ? 1: 0;
							$this->dbg->log_entry(__FUNCTION__, sprintf("RCPT TO: %s: %s", $email, $rsp));

							if ($ok) {
								fputs($connection, "DATA\r\n");
								$rsp = fgets($connection, 1024);
								$ok = stristr($rsp, '354') ? 1: 0;
								$this->dbg->log_entry(__FUNCTION__, sprintf("data: %s", $rsp));
								if ($ok) {
									$result = true;
								}
							}
						}
					}

					// Quit and close the connection
					fputs($connection, "QUIT\r\n");
					fclose($connection);

					if ($result) {
						return true;
					}
				}
			}
			else {
				break;
			}
			$id++;
		}

		return false;
	}
}

?>