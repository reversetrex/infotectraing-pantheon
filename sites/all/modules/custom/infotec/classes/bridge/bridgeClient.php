<?php
/**
 * BridgeClient Class
 */
namespace infotec {
	use CCheckMail;

	/**
	 * @package        infotec module
	 * @subpackage     bridgeClient
	 * @version        1.0
	 * @author         gbellucci (ECPI University)
	 * @copyright  (c) 2015 ECPI University
	 * @license        Proprietary
	 * @abstract
	 *
	 * The following illustrates the processing flow of the bridgeClient.
	 *
	 *                               infotectraining.com -------/ /---------> bridge.infotectraining.com --/ /-------> infotec database server
	 *                                                                        10.142.2.185/199.36.14.206               10.142.201.199
	 *  +--module------------+        +--------------------+                  +-----------------------+             +---------------------+
	 *  | hook_menu function +------->+  bridgeClient      |   data request   | bridgeServer          |  TDS PDO    | database server     |
	 *  +--------------------+        |                    +----------------->+                       +------------>+                     |
	 *                                |                    |   data response  |                       |             |                     |
	 *  +--form--------------+  ajax  |                    +<-----------------+                       +<------------+                     |
	 *  | request function   +------->|                    |                  |                       |             |                     |
	 *  +--------------------+        +---------+----------+                  +-----------------------+             +---------------------+
	 *                                +---------+----------+
	 *                                |                    |
	 *                                |       dman         |
	 *                                |                    |
	 *                                +--------------------+
	 *
	 * The bridgeClient class defined in this file provides functions for obtaining data and records from the bridgeServer located on a server inside
	 * a private network. The bridgeServer acts as a database webservice by communicating data requests to a MSSQL database server. The data returned by
	 * the webservice is formatted by a data manager class (dman). The output format depends on the purpose and type of data. The webservice is written on
	 * top of the PHP micro framework known as Slim (http://www.slimframework.com)
	 */
	class BridgeClient {

		/**
		 * @var object - configuration object
		 */
		public $cfg;

		/**
		 * @var object - sql string storage
		 */
		private $sqlStore;

		/**
		 * @var object dataManager class
		 */
		private $dataMan;

		/**
		 * @var mixed - response from a BridgeServer request for sql data records
		 */
		private $results;

		/**
		 * @var string - the last BridgeServer request
		 */
		private $lastRequest;

		/**
		 * @var string - agent name
		 */
		private $agent = "BridgeServiceClient/1.0 (http://infotectraining.com)";

		/**
		 * @var string - bridge server user id
		 */
		private $user;

		/**
		 * @var string - bridge server password
		 */
		private $pass;

		/**
		 * @var string - bridge server url
		 */
		private $serverUrl;

		/**
		 * @var object - debug logger
		 */
		private $dbg;

		/**
		 * @var object - cache
		 */
		private $cache;

		/**
		 * @var bool - true = use cache, false otherwise
		 */
		private $useCache;

		/**
		 * @var int - the session id
		 */
		private $sessionId;

		/**
		 * @var int - a form token
		 */
		private $token;

		/**
		 * @var boolean
		 *  1 = test mode
		 *  0 = activated
		 */
		private $testMode;

		/**
		 * Constructor function requires a config.ini file (full path)
		 *
		 * @param $configFile
		 */
		function __construct($configFile) {
			try {
				if (empty($configFile) || !file_exists($configFile)) {
					throw new \Exception($this->exString(__FUNCTION__, __LINE__, 'Config file name is empty or file does not exist.'));
				}

				// Initialize
				$this->cfg = new \ecpi\cfgObj($configFile);
				$this->dbg = new \ecpi\dbc('bridge.client');
				$this->dbg->set_debug((intval($this->cfg->get('client.debug'), 10)));
				$this->testMode = intval($this->cfg->get('module.mode'), 10);

				// setup data manager and sql storage
				$this->dataMan  = new \infotec\dman($configFile);
				$this->sqlStore = new \infotec\sqlStore();

				// setup cache usage
				$this->useCache = intval($this->cfg->get('cache.use'), 10);
				if ($this->useCache) {
					$this->cache = new \infotec\Cache(
						'bridge.cache',
						$this->cfg->get('cache.dir'),
						intval($this->cfg->get('cache.expires'), 10),
						intval($this->cfg->get('cache.debug'), 10)
					);
				}

				// get bridge server user name/password
				$this->user      = $this->cfg->get('client.user');
				$this->pass      = $this->cfg->get('client.pass');
				$this->serverUrl = $this->cfg->get('server.url');

				// Get the session id
				$this->sessionId = session_id();
				$this->dbg->log_entry('::', sprintf('BridgeClient running (%s).', $this->sessionId));
			}
			catch (\Exception $ex) {
				$this->dbg->log_entry('::', 'BridgeClient terminated.');
				exit(0);
			}
		}

		/**
		 * Runs one or more sql queries by sending them to the bridge server.
		 * The results for multiple queries is combined into a single response
		 *
		 * @param $qList - list of queries (separated by ';' character)
		 * @param $args  - stdClass Object (if any)
		 *
		 * @return array|null $data
		 */
		function runQuery($qList, $args = null) {
			$data     = array();
			$_qList   = null;
			$reqCount = 0;

			try {
				$_arglist = array();
				$_qList   = (stristr($qList, ';') ? explode(';', $qList) : array($qList));

				// for each request...
				foreach ($_qList as $qName) {
					$this->lastRequest = $qName;
					$reqCount++;

					$query = $this->sqlStore->get($qName);
					if (empty($query)) {
						throw new \Exception($this->exString(__FUNCTION__, __LINE__, sprintf("Query: '%s' is not defined.", $qName)));
					}

					// Clean up the string
					$query = str_replace(array("\r", "\n", "\t"), ' ', $query);
					$query = preg_replace('/\s\s+/', ' ', $query);

					// check for substitution variables in the query string
					// the query format may be  "select * from [view] where [view.[field] = '@value'" - the @ character identifies the variable name
					// to be used from the $args array. For example: $args['value'] => 'abc'. Any number of unique substitution variables can be used
					// in the query string. Variable names must begin with @ and must be alpha characters (a-z or A-Z) and may contain underscores
					if (stristr($query, '@') && !empty($args)) {
						preg_match_all('/\s@[a-z_0-9]+/i', $query, $result, PREG_PATTERN_ORDER);
						for ($i = 0; $i < count($result[0]); $i++) {
							$var = trim(str_replace('@', '', $result[0][$i]));
							$val = isset($args->$var) ? $args->$var : "_empty_";
							if (!stristr($val, "_empty_")) {
								$_arglist[] = $val;
							}
							else {
								// substitution variable is not defined in the array.
								throw new \Exception(
									$this->exString(
										__FUNCTION__, __LINE__,
										sprintf("args member: '%s' is not defined.", $var)
									)
								);
							}
						}
						// if $_arglist contains values...
						$_query = ((count($_arglist) > 0)
							? str_replace('@', '', vsprintf($query, $_arglist))
							: $query);
					}
					else {
						$_query = $query;
					}

					// are we using the cache?
					if ($this->useCache) {
						$rsp = $this->cache->read($_query);
					}

					// send the request if we didn't find it in the cache
					if (empty($rsp)) {
						$rsp = $this->sendQuery($this->serverUrl, $this->user, $this->pass, $_query);
						if (is_object($rsp)) {
							if ($this->useCache) {
								// save this to the cache
								$this->cache->write($_query, $rsp);
							}
						}
					}

					$this->dbg->log_entry($_query, $rsp);

					/**
					 * Check the response
					 *   s = the status, 0=problems, 1=ok
					 *   i = information, m=error message, a~xxx = array::record count, o~xxx = object::record count
					 *   d = the data, can be null
					 */
					if ($rsp->s != 0) {
						$data[$qName] = $rsp->d;
					}
					else {
						// message returned?
						if (stristr($rsp->i, 'm~')) {
							$data[$qName] = array();
							list($sym, $msg) = explode('~', $rsp->i);
							if (!empty($msg)) {
								// do not throw an exception here because it will stop
								// processing all requests after this one.
								$this->exString(__FUNCTION__, __LINE__, sprintf("request response to %s: %s", $qName, $msg));
							}
						}
					}

				}// end query loop
			}
			catch (\Exception $ex) {
				; // nothing happens
			}

			return ($data);
		}

		/**
		 * Returns information for a request
		 * This is a direct function call
		 *
		 * @param $args
		 *      sql => (string) the name of one or more semi-colon separated sql requests
		 *      dman => (string) the name of the data manager class function that will format the data records
		 *      <database field> => the data base field(s) needed for sql requests (if any)
		 *
		 * @return array
		 */
		function request($args) {
			$dManRsp = null;
			$_sql    = array();

			try {
				if (!is_array($args)) {
					throw new \Exception(
						$this->exString(
							__FUNCTION__, __LINE__,
							"invalid call argument."
						)
					);
				}

				$args = $this->convertToObject($args);
				$dman = isset($args->dman) ? $args->dman : null;
				$sql  = isset($args->sql) ? $args->sql : null;

				if (empty($sql) || empty($dman)) {
					throw new \Exception(
						$this->exString(
							__FUNCTION__, __LINE__,
							"dman method or sql request string is missing"
						)
					);
				}
				elseif (!method_exists($this->dataMan, $dman)) {
					throw new \Exception(
						$this->exString(
							__FUNCTION__, __LINE__,
							sprintf("unknown data manager function '%s'", $dman)
						)
					);
				}

				$_sql = (stristr($sql, ';') ? explode(';', $sql) : array($sql));
				foreach ($_sql as $sqlReq) {
					// validate each sql request before we run them
					if ($this->sqlStore->get($sqlReq) == null) {
						throw new \Exception(
							$this->exString(
								__FUNCTION__, __LINE__,
								sprintf("unknown sql request '%s'", $sqlReq)
							)
						);
					}
				}

				// run the queries
				if (($sqlResults = $this->runQuery($sql, $args)) != null) {
					if (($dManRsp = $this->dataMan->$dman($sqlResults)) != null) {
						$this->dbg->log_entry(__FUNCTION__, $dManRsp);
					}
				}
			}
			catch (\Exception $ex) {
				; // nothing to do - check log entries
			}

			return ($dManRsp);
		}

		/**
		 * Handle an ajax request from a form
		 *
		 *   Ajax requests come from web site forms requesting information from the data bridge.
		 *   This function basically serves as the interface for two areas:
		 *      1. The data bridge server.
		 *      2. The data manager.
		 *
		 *   The form's request must contain three items when requesting services.
		 *      1. The token given to the form when the handshake took place,
		 *      2. The name of a database request (known to the sqlStore class),
		 *      3. The name of the data manager class function that will format the data records for the form's needs.
		 *
		 *   Basically, the javascript code connected with the form directs the nature of the processing by asking
		 *   for a known sql request (by name) and then asking that the data be processed by a named data manager
		 *   method. The ajaxRequest function validates the request but doesn't actually process any of the data
		 *   itself.
		 *
		 * @param array $req - request array
		 *
		 * @return array
		 */
		function ajaxRequest($req) {
			$rsp         = $this->mkResponse(403, array('rc' => 0, 'data' => 0));
			$_req        = $req;
			$req         = $this->convertToObject($req);
			$this->token = $this->sessionId . $this->cfg->get('client.seed');
			$this->dbg->log_entry(__FUNCTION__, $req);

			try {
				if (!isset($req->bridge)) {
					throw new \Exception($this->exString(__FUNCTION__, __LINE__, "expecting 'bridge' member in request message."));
				}
				else {
					$bridgeRequest = trim($req->bridge);
				}

				$args = isset($req->q) ? $this->convertToObject($req->q) : null;

				// handshake?
				if (stristr($bridgeRequest, 'hello')) {
					if (isset($args->fid) && ($args->fid == 'sform' || $args->fid == 'cform')) {
						$rsp = $this->mkResponse(200, array('rc' => 0, 'token' => $this->token));
					}
				}
				// bridgeSql Request?
				elseif (stristr($bridgeRequest, 'bridgesql')) {
					if (!empty($args)) {
						$dman = isset($args->dman) ? $args->dman : null;
						$sql  = isset($args->sql) ? $args->sql : null;
						if (empty($dman) || empty($sql)) {
							throw new \Exception(
								$this->exString(
									__FUNCTION__, __LINE__,
									"dman method or sql request string is missing", $bridgeRequest
								)
							);
						}
						elseif (!method_exists($this->dataMan, $dman)) {
							throw new \Exception(
								$this->exString(
									__FUNCTION__, __LINE__,
									sprintf("unknown data manager function '%s'", $dman)
								)
							);
						}

						$_sql = (stristr($sql, ';') ? explode(';', $sql) : array($sql));
						foreach ($_sql as $sqlReq) {
							// validate each sql request before we run them
							if ($this->sqlStore->get($sqlReq) == null) {
								throw new \Exception(
									$this->exString(
										__FUNCTION__, __LINE__,
										sprintf("unknown sql request '%s'", $sqlReq)
									)
								);
							}
						}

						// run the queries
						if (($sqlResults = $this->runQuery($sql, $args)) != null) {
							if (($dManRsp = $this->dataMan->$dman($sqlResults)) != null) {
								$rsp = $this->mkResponse(200, array('rc' => 0, 'list' => $dManRsp));
								$this->dbg->log_entry(__FUNCTION__, $dManRsp);
							}
						}
					}
				}
				// contact form submission
				elseif (stristr($bridgeRequest, 'export')) {
					$rsp = $this->exportForm($_req);
				}

				else {
					// another type of request?
				}
			}
			catch (\Exception $ex) {
				; // nothing to do - check log entries
			}

			$this->dbg->log_entry(__FUNCTION__, $rsp);

			return ($rsp);
		}

		/**
		 * Returns an ajax response
		 *
		 * @param $status
		 * @param $data
		 *
		 * @return array
		 */
		private function mkResponse($status, $data) {
			$json = new \Services_JSON();
			try {
				if (empty($status) || empty($data) || !is_array($data)) {
					throw new \Exception($this->exString(__FUNCTION__, __LINE__, "status or data variables are null or invalid"));
				}
				$rsp = array('status' => $status, 'ajaxRsp' => $json->encode($data));
			}
			catch (\Exception $ex) {
				$rsp = array('status' => 500, 'ajaxRsp' => $json->encode(array('rc' => 500)));
			}

			return ($rsp);
		}

		/**
		 * Sends user string to the restful interface
		 *
		 * @param string $bridgeUrl - bridge url
		 * @param string $authUser  - basic auth userid
		 * @param string $authPass  - basic auth password
		 * @param string $query     - database query string
		 *
		 * @return mixed|null
		 * @throws \Exception
		 */
		private function sendQuery($bridgeUrl, $authUser, $authPass, $query) {
			$ar    = null;
			$query = str_replace(array("\n", "\r"), ' ', trim($query));
			$url   = $bridgeUrl . '/api/sql/query/' . urlencode($query);
			$ch    = curl_init();

			$this->dbg->log_entry(__FUNCTION__, sprintf("bridge url: %s, query: %s", $bridgeUrl, $query));
			$this->dbg->log_entry(__FUNCTION__, sprintf("complete url: %s", $url));

			try {
				if (empty ($ch)) {
					throw new \Exception($this->exString(__FUNCTION__, __LINE__, 'cURL init failed'));
				}

				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
				curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
				curl_setopt($ch, CURLOPT_USERPWD, "{$authUser}:{$authPass}");
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
				curl_setopt($ch, CURLOPT_TIMEOUT, 30);

				$result = curl_exec($ch);

				if (curl_errno($ch)) {
					throw new \Exception (
						$this->exString(
							__FUNCTION__, __LINE__,
							sprintf("cURL Error: %s, errno: (%s)", curl_error($ch), curl_errno($ch))
						)
					);
				}

				if (empty($result)) {
					$ar = $this->convertToObject(array("s" => 0, "i" => "m~'no results", "d" => null));
				}

				// get the response code - expecting 200
				$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				if ($httpCode != 200) {
					$ar = $this->convertToObject(array("s" => 0, "i" => "m~http code:" . $httpCode, "d" => null));
				}
				else {
					$json = new \Services_JSON();
					$ar   = $json->decode($result);
				}
			}
			catch (\Exception $ex) {
				$ar = $this->convertToObject(array("s" => 0, "i" => "m~" . $ex->getMessage(), "d" => null));
			}

			if ($ch) {
				curl_close($ch);
			}

			return ($ar);
		}

		/**
		 * Contact (inquiry) form handling
		 * This function currently emails the form information
		 *
		 * @param $args
		 *
		 * @return array
		 * @throws \Exception
		 */
		private function exportForm($args) {
			date_default_timezone_set('America/New_York');
			$name = $email = $fname = $lname = $organization = $course = $preference = $interest = '';
			$arr  = $rows = array();

			$savedState = $this->dbg->get_state();
			$this->dbg->set_debug(intval($this->cfg->get('contactForm.debug'), 10));

			$this->dbg->log_entry(__FUNCTION__ . '/form export:', $args);
			$rsp  = $this->mkResponse(200, array('rc' => 0, 'url' => $this->cfg->get('contactForm.returnUrl')));
			$data = $args['q'];

			try {
				// sort by field order
				uasort($data, array($this, 'cmp'));

				// rebuild the array - use the field name as the key
				foreach ($data as $idx => $f) {
					$key       = $f['name'];
					$arr[$key] = array(
						'value' => (!is_array($f['value']) ? $this->sanitize(trim($f['value'])) : implode(', ', $f['value'])),
						'label' => $f['label'],
						'type'  => $f['type'],
						'req'   => $f['req']
					);
				}

				$this->dbg->log_entry(__FUNCTION__ . '/rebuilt: ', $arr);

				// validate the fields again - this is crudely done in the javascript
				// so we do it again here.
				$errors = array();
				if ($errs = $this->validate($arr, $errors)) {
					$rsp = $this->mkResponse(200, array('rc' => 0, 'err' => $errors));
					throw new \Exception($this->exString(__FUNCTION__, __LINE__, sprintf("data validation errors (%s)", $errors)));
				}

				// create the body of the email
				$fmt    = "\t<tr class=\"%s\"><th>%s</th><td>%s</td></tr>\n";
				$isSpam = 0;
				$row    = 0;
				foreach ($arr as $field => $item) {
					if(empty($item['value'])) {
						continue;
					}
					if ($item['type'] == 'hp') { // honey pot field
						$isSpam = !empty($item['value']) ? 1 : 0;
						continue;
					}
					if (!stristr($item['label'], 'null')) {
						$rows[] = sprintf($fmt, ($row % 2) ? "e" : "o", $item['label'], $item['value']);
						$row++;
					}
					if ('cf-email' == $field) {
						$email = $item['value'];
					}
					elseif ('cf-first' == $field) {
						$fname = $item['value'];
					}
					elseif ('cf-last' == $field) {
						$lname = $item['value'];
					}
					elseif ('cf-preferred-training' == $field) {
						$preference = $item['value'];
					}
					elseif ('cf-interest' == $field) {
						$interest = $item['value'];
					}
					elseif ('cf-organization' == $field) {
						$organization = $item['value'];
					}
				}

				// spam?
				if ($isSpam) {
					$rsp = $this->mkResponse(200, array('rc' => 0, 'url' => $this->cfg->get('contactForm.spamUrl')));
					throw new \Exception($this->exString(__FUNCTION__, __LINE__, sprintf("Spam Detected: Ip: ", $_SERVER["REMOTE_ADDR"])));
				}

				$course = (stristr($interest, 'courses') ? $arr['cf-course']['value'] : '');
				$name   = "{$fname} {$lname}";

				// substitutions for the subject line
				$subs = array(
					'%name'       => $name,
					'%email'      => $email,
					'%company'    => $organization,
					'%course'     => $course,
					'%preference' => $preference,
					'%interest'   => $interest
				);

				$logo    = $this->cfg->get('contactForm.logo');
				$logoTag = (!empty($logo) ? sprintf('<img src="%s" />', $logo) : '');

				ob_start(); ?>
				<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
				<html xmlns="http://www.w3.org/1999/xhtml">
				<head>
					<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
					<title></title>
					<style>
						tr.o {
							background-color: #f7f7f7;
						}

						th {
							text-align: right;
							color: #828282;
						}

						th, td {
							padding: 3px;
						}

						table {
							font-family: sans-serif;
						}
					</style>
				</head>
				<body>
				<table border="0" cellpadding="0" cellspacing="0" height="100%" width="100%" id="bodyTable">
					<tr>
						<td align="center" valign="top">
							<p><?php echo $logoTag; ?></p>

							<p><?php echo date("F j, Y, g:i a"); ?></p>

							<p>You have received the following inquiry.</p>
							<table border="0" cellpadding="20" cellspacing="0" width="800" id="emailContainer">
								<?php echo implode("\n", $rows); ?>
								<tr>
									<td colspan="2" style="color:#828282;font-size:.85em;">
										<hr/>
										This communication is to be treated as confidential and the information in it may not be used or disclosed
										except for the purpose for which it has been sent. If you have reason to believe that you are not the intended
										recipient of this communication, please contact the <a href="mailto:webmaster@infotecpro.com">Webmaster</a> immediately.
									</td>
								</tr>
							</table>
						</td>
					</tr>
				</table>
				</body>
				</html>
				<?php

				$body    = ob_get_clean();
				$subject = str_replace(array_keys($subs), array_values($subs), $this->cfg->get('contactForm.subjectline'));

				// create a mail object
				$mail = new \PHPMailer();
				if (empty($mail)) {
					throw new \Exception($this->exString(__FUNCTION__, __LINE__, "could not create PHPMailer"));
				}

				// setup the mailer
				$mail->isSMTP();
				$mail->Host     = $this->cfg->get('smtp.ip');
				$mail->Port     = $this->cfg->get('smtp.port');
				$mail->SMTPAuth = false;
				$mail->SetFrom($this->cfg->get('contactForm.sender'));

				if (!$this->testMode) {
					// not in test mode - send to email address in the configuration file
					$email = $this->cfg->get('contactForm.recipient');
					$emailAddr = (stristr($email, ';') ? explode(';', $email): array($email));

					if(count($emailAddr) > 1) {
						for($i = 0; $i < count($emailAddr); $i++) {
							if($i > 0) {
								// this is a carbon
								$mail->AddCC($emailAddr[$i++]);
							}
							else {
								// recipient is the first name in the list
								$mail->AddAddress($emailAddr[$i++]);
							}
						}
					}
					else {
						// single address
						$mail->AddAddress($emailAddr[0]);
					}
				}
				else {
					// test mode sends the email to the address in the form
					$mail->AddAddress($email);
				}

				$mail->Subject = $subject;
				$mail->MsgHtml($body);

				if (!$mail->Send()) {
					throw new \Exception($this->exString(__FUNCTION__, __LINE__, "could not send mail (%s)", $mail->ErrorInfo));
				}
			}
			catch (\Exception $ex) {
				// nothing to do here
			}

			// restore the saved debug state
			$this->dbg->set_debug($savedState);
			return ($rsp);
		}

		/**
		 * Form field validation
		 *
		 * @param $fields
		 * @param $errors
		 *
		 * @return int
		 */
		private function validate(&$fields, &$errors) {
			$this->dbg->log_entry(__FUNCTION__, 'validating form fields');
			foreach ($fields as $field => $item) {
				if ($item['req'] == true) {
					// validate the email address
					if ($item['type'] == 'email') {
						if (!$this->checkEmailAddress($item['value'])) {
							$errors[] = "Please check the email address you entered.";
						}
					}
					// validate a name field
					elseif ($item['type'] == 'name') {
						if(empty($item['value'])) {
							$errors[] = sprintf("%s field is empty", $item['label']);
						}
						elseif(!preg_match('/^[a-zA-Z-]+$/', $item['value'])) {
							$errors[] = sprintf("%s field contains invalid characters.", $item['label']);
						}
					}
				}
				else {
					// field is not required
					// but the string is filtered
					$fields[$field]['value'] = filter_var(trim($fields[$field]['value']), FILTER_SANITIZE_STRING);
				}
			}

			return (count($errors));
		}

		/**
		 * Checks an email address by connecting with the domain's mail
		 * server and verifying the email address.
		 *
		 * @param $email
		 *
		 * @return int - returns true if the email address is real,
		 *             zero otherwise.
		 */
		private function checkEmailAddress($email) {
			$this->dbg->log_entry(__FUNCTION__, sprintf("checking email address: %s"));
			$checkmail = new CCheckMail (intval($this->cfg->get('smtp.debug'), 10));
			return ($checkmail->execute($email));
		}

		/**
		 * Sanitize user input information
		 *
		 * @param $value
		 *
		 * @return mixed $value
		 */
		private function sanitize($value) {
			$value = filter_var($value, FILTER_SANITIZE_STRING);

			return ($value);
		}

		/**
		 * Compares the field order
		 *
		 * @param array $a
		 * @param array $b
		 *
		 * @return int
		 */
		public function cmp($a, $b) {
			$_a = intval($a['order'], 10);
			$_b = intval($b['order'], 10);
			$r  = 0;

			if ($_a < $_b) {
				$r = -1;
			}
			elseif ($_a > $_b) {
				$r = 1;
			}

			return ($r);
		}

		/**
		 * Convert an array to an object
		 *
		 * @param $array
		 *
		 * @return \stdClass
		 */
		private function convertToObject($array) {
			$object = new \stdClass();
			foreach ($array as $key => $value) {
				if (is_array($value)) {
					$value = $this->convertToObject($value);
				}
				$object->$key = $value;
			}

			return $object;
		}

		/**
		 * Creates an exception message string
		 *
		 * @param $func
		 * @param $line
		 * @param $exception
		 *
		 * @return string
		 */
		function exString($func, $line, $exception) {
			$err = new \ecpi\dbc("bridge.client.exceptions");
			$msg = sprintf("%s->%s() (line: %s) - %s", __CLASS__, $func, $line, $exception);
			$err->log_entry("exception", $msg);

			return ($msg);
		}
	}
}