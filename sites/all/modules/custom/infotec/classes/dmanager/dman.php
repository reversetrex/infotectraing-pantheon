<?php
namespace infotec {
	use ecpi\dbc;

	/**
	 * @package    infotec module
	 * @subpackage data manager class
	 * @version    1.0
	 * @author     gbellucci (ECPI University)
	 * @copyright  (c) 2015 ECPI University
	 * @license    Proprietary
	 * @abstract
	 *
	 * This class is responsible for converting sql data objects and data arrays received from sql requests into html tags or
	 * text used by the web site. This is a collection of formatting routines. Each formatting function handles a specific
	 * requirement.
	 */
	class dman {

		/**
		 * @var \ecpi\dbc - logging class
		 */
		private $dbg;

		/**
		 * @var object - configuration object
		 */
		private $cfg;

		// constructor
		function __construct($configFile) {
			$this->cfg = new \ecpi\cfgObj($configFile);
			$this->dbg = new \ecpi\dbc('dataManager');
			$this->dbg->set_debug((intval($this->cfg->get('dman.debug'), 10)));
		}

		/**
		 * Shortform options formatter. This routine expects an array of data records. Each record contains:
		 *
		 * [<index>] => stdClass Object
		 * (
		 *      [GroupName] => A (sub) group name
		 *      [CategoryName] => A category name
		 *      [CourseNumber] => A course identifier
		 *      [CourseWebName] => Name of the course
		 *      [DeliveryMethodName] => The Group this sub group belongs to
		 * )
		 * This function produces options for a select tag. Each option belongs to a delivery method group and sub group.
		 * Classes are applied to each option allowing the short-form javascript to control which option groups are displayed
		 * in the select tag. Selection visibility is determined by the user's group and sub group selections in the form.
		 *
		 * The value for each option is the URL for the web site's course page. The URL is constructed as:
		 *
		 *    //courses/<DeliveryMethodName>/<CourseNumber/<CourseWebName>/
		 *
		 * @param $data - array of data records from sql query
		 * @param $type - string
		 *
		 * @return string - html tags
		 */
		public function sfOptionsFormat($data, $type='url') {

			$dbg = new dbc('sort');
			$courseList = array();
			extract($data);

			// sprintf formats
			$optFormat = '<option class="%s" value="%s">%s</option>';
			$grpFormat = '<option value="%s">%s</option>';

			$methodList = $categoryList = $courses = $methodFilter = $categoryFilter = array();
			$methodName = $categoryName = '';
			$tmp1 = $tmp2 = array();

			// begin by sorting into delivery methods
			for($idx = 0; $idx < count($courseList); $idx++) {
				$r = $courseList[$idx];
				$tmp1[$r->DeliveryMethodName][] = array(
					'category' => $r->CategoryName,
					'name'     => $r->CourseWebName,
					'id'       => $r->CourseNumber
				);
			}

			// sort delivery methods into categories
			foreach($tmp1 as $deliveryMethod => $group) {
				for($idx = 0; $idx < count($group); $idx++) {
					$rec = $group[$idx];
					$tmp2[$deliveryMethod][$rec['category']][] = array(
						'name'     => $rec['name'],
						'id'       => $rec['id'],
						'class'    => $this->sanitize_title_with_dashes("{$deliveryMethod}-{$rec['category']}")
					);
				}
			}

			//$dbg->log_entry('method/category', $tmp2);

			// for each delivery method
			foreach($tmp2 as $deliveryMethod => $category) {

				$methodName = $this->sanitize_title_with_dashes($deliveryMethod);
				if(!in_array($deliveryMethod, $methodFilter)) {
					$methodFilter[] = $deliveryMethod;
					//'<option value="%s">%s</option>';
					$methodList[] = sprintf($grpFormat, $methodName, $deliveryMethod);
				}

				// reset the category filter - do this because other delivery methods have
				// the same categories.
				$categoryFilter = array();

				// for each category assigned to this delivery method....
				foreach($category as $catName => $items) {
					$categoryName = $this->sanitize_title_with_dashes($catName);

					if(!in_array( $catName, $categoryFilter)) {
						$categoryFilter[] = $catName;
						// '<option class="%s" value="%s">%s</option>';
						$categoryList[] = sprintf($optFormat, "{$methodName} opt", "{$methodName}-{$categoryName}", $catName);
					}

					// for each item in this category...
					foreach($items as $item) {
						$url = $this->makeCourseUrl($deliveryMethod, $item['id'], $item['name']);

						// '<option class="%s" value="%s">%s</option>';
						$courses[] = sprintf($optFormat, "{$item['class']} opt", ($type == 'url' ? $url : $item['name']), $item['name']);
					}
				}
			}

			// sort the category and course lists
			sort($categoryList);
			sort($courses);

			// push these onto the front of the array
			array_unshift($methodList, sprintf($optFormat, 'static', '', 'Choose Training Method'));
			array_unshift($categoryList, sprintf($optFormat, 'static', '', 'Choose Type'));
			array_unshift($courses, sprintf($optFormat, 'static', '', 'Choose Course'));

			//$dbg->log_entry('methods:', $_methodList);
			//$dbg->log_entry('categories:', $_categoryList);
			//$dbg->log_entry('courses:', $_courses);

			// returns an array containing the groups, sub groups and courses
			return(
				array('groups'      => join(' ', $methodList),     // category
			          'subgroups'   => join(' ', $categoryList),  // group
			          'courses'     => join(' ', $courses)      // courses
					)
				);
		}

		/**
		 * Creates a url from course components
		 *
		 * @param $method - delivery method name
		 * @param $number - course number
		 * @param $title   - course title
		 *
		 * @return string
		 */
		public function makeCourseUrl($method, $number, $title) {

			// the course url must follow the format below
			$urlFormat = '/course/%s/%s/%s/';

			return(
				sprintf($urlFormat,
			        $this->sanitize_title_with_dashes($method),
			        $number,
			        $this->sanitize_title_with_dashes($title)
				)
			);
		}

		/**
		 * Contact Form Options formatter
		 * @param $data
		 *
		 * @return string
		 */
		public function cfOptionsFormat($data) {
			return($this->sfOptionsFormat($data, 'names'));
		}

		/**
		 * Page format function
		 * Creates a render array for a drupal page template
		 *
		 * @param $data - data record from sql query
		 *
		 * @return mixed
		 */
		function pageFormat($data) {

			$this->dbg->log_entry(__FUNCTION__, $data);
			$templateData = $page = array();
			$courseDetails = $courseSched = $courseFormats = $relatedCourses = array();
			$sections = array();

			try {

				extract($data);

				if(empty($courseDetails)) {
					throw new \Exception($this->exString(__FUNCTION__, __LINE__, 'Course Details were not found'));
				}

				// setup the course record
				$r = $courseDetails[0];

				// load the table - empty values are ignored
				$table = array(
					'code'     => $r->CourseCode,
					'category' => $r->CategoryName,
					'title'    => $r->CourseWebName,
					'length'   => $r->LengthDecription,
					'group'    => $r->GroupName,
					'method'   => $r->DeliveryMethodName,
					'vendor'   => $r->VendorName
				);

				// these are added to the template
				foreach ($table as $key => $value) {
					if (!empty($value)) {
						$page[$key] = $value;
					}
				}

				/**
				 * Related courses
				 * convert this array into an un-numbered list
				 * each course is wrapped in an anchor containing the url
				 */
				if (count($relatedCourses) > 0) {
					$tags   = array();
					$tags[] = '<ul class="list">';
					foreach ($relatedCourses as $related) {
						$url    = $this->makeCourseUrl($related->MethodName, $related->CourseNumber, $related->CourseName);
						$tags[] = sprintf(
							'<li><a title="%s" href="%s" class="courseUrl">%s</a></li>',
							$related->CourseName, $url, $related->CourseName
						);
					}
					$tags[]          = "</ul>";
					$page['related'] = implode("\n", $tags);
				}

				/**
				 * This course in other formats
				 * Converts the returned array into one or more un-numbered lists
				 * grouped by Delivery Method
				 */
				if (count($courseFormats) > 0) {
					$lists = $tags = array();
					$fmt   = '<li><a title="%s" href="%s" class="courseUrl">%s</a></li>';
					foreach ($courseFormats as $f) {
						$url                     = $this->makeCourseUrl($f->MethodName, $f->CourseNumber, $f->CourseName);
						$lists[$f->MethodName][] = sprintf($fmt, $f->CourseName, $url, $f->CourseName);
					}
					foreach ($lists as $listName => $list) {
						$tags[] = sprintf('<h6 class="format-header">%s</h6>', $listName);
						$tags[] = '<ul class="list">';
						foreach ($list as $item) {
							$tags[] = $item;
						}
						$tags[] = '</ul>';
					}
					$page['formats'] = implode("\n", $tags);
				}

				// -------------------------------------------
				// Sections are processed in a specific order
				// Each text file is assigned to a page section.

				// build the schedule table (if any)
				if (count($courseSched) > 0 && stristr($r->DeliveryMethodName, 'instruct')) {
					$leadIn = '';

					// only a single entry?
					if(count($courseSched) == 1) {
						// the first one is always the empty record.
						if(empty($courseSched[0]->StartDate) && empty($courseSched[0]->EndDate) && !empty($courseSched[0]->defaultText)) {
							$table = '<p class="warning">' . trim($courseSched[0]->defaultText) . '</p>';
						}
					}
					else {
						$table  = $this->makeSchedTable($courseSched);
						$leadIn = sprintf("<p>%s is scheduled for the following dates and times:</p>", $r->CourseWebName);
					}
					$sections[] = array(
						"title" => "Course Schedule:",
					    "content" => $leadIn . $table,
						"class" => "course-schedule schedule-div"
					);
				}


				// get the overview text file (if any)
				if (!empty($r->Overview)) {
					$text = $this->get_file_contents(
						$_SERVER['DOCUMENT_ROOT'] . $this->cfg->get('dman.overviewDir'),
						$r->Overview,
						'overview'
					);
					$sections[] = array(
						"title" => "Overview",
						"content" => $text,
						"class" => "overview-div"
					);
				}

				// get the objectives text file (if any)
				if (!empty($r->Objectives)) {
					$text = $this->get_file_contents(
						$_SERVER['DOCUMENT_ROOT'] . $this->cfg->get('dman.objectivesDir'),
						$r->Objectives,
						'objectives'
					);
					$sections[] = array(
						"title" => "Objectives",
						"content" => $text,
						"class" => "objectives-div"
					);
				}

				// get the course content text file (if any)
				if (!empty($r->CourseContent)) {
					$text = $this->get_file_contents(
						$_SERVER['DOCUMENT_ROOT'] . $this->cfg->get('dman.contentDir'),
						$r->CourseContent,
						'course-content',
						true
					);
					$sections[] = array(
						"title" => "Course Content",
						"content" => $text,
						"class" => "course-content-div"
					);
				}

				// add the page sections
				$page['sections'] = $sections;
				$this->dbg->log_entry(__FUNCTION__, $page);
			}
			catch(\Exception $e) {
				$page['sections'][] = array(
					"title" => "Not Found",
					"content" => "<p>Course information is not available</p>",
					"class" => "course-content-div"
				);
			}

			// return the template data
			$templateData = array('page' => $page);
			return($templateData);
		}

		/**
		 * Returns a course schedule table
		 *
		 * @param $schedule array
		 *
		 * @return null|string
		 */
		function makeSchedTable($schedule) {
			$markup = $thead = $tbody = null;
			if (is_array($schedule)) {
				$fmtRow = "<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>";
				$fmtTable = '<table class="schedule-table"><thead>%s</thead><tbody>%s</tbody></table>';
				$thead = '<th>Start Date</th><th>End Date</th><th>Start Time</th><th>Location</th>';

				foreach ($schedule as $entry) {
					// ignore the empty record
					if(empty($entry->StartDate) && empty($entry->EndDate)) {
						continue;
					}
					else {
						$tbody .= sprintf(
							$fmtRow,
							str_replace('12:00:00:000AM', '', $entry->StartDate), // remove weird time format added to the date
							str_replace('12:00:00:000AM', '', $entry->EndDate), // remove weird time format added to the date
							$entry->StartTime,
							$entry->Location
						);
					}
					$markup = sprintf($fmtTable, $thead, $tbody);
				}
			}
			return($markup);
		}

		/**
		 * Returns the contents of a text file
		 *
		 * @param        $dir
		 * @param        $filename
		 * @param string $class
		 * @param int    $addMore - true = add read more button
		 *
		 * @return null|string
		 * @internal param string $prefix
		 */
		function get_file_contents($dir, $filename, $class = '', $addMore = 0) {
			$this->dbg->log_entry(__FUNCTION__, compact('dir', 'filename', 'class'));
			ini_set('mbstring.substitute_character', " ");

			$_addMore = $addMore;
			$fmt   = "%s<d" . "iv class=\"%s read-more\" %s>%s</div>%s";
			$style = $more = '';
			$text  = $ret = null;
			$file  = $this->remove_trailing_slash($dir) . '/' . $filename;
			$err = 0;

			if(empty($filename)) {
				$ret = null;
			}
			else {
				if (file_exists($file)) {
					$text = $this->remove_utf8_bom(file_get_contents($file));
					$text = mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');

					// $text = utf8_encode($text);

					// format adjustments (for really bad html)
					$search = $replace = array();
					$rsTable = array(
						// search item  => replacement item
						'<ul>'          => '<ul class="list">',
						'<blockquote>'  => '<p>',
						'</blockquote>' => '</p>',
						'<h4>'          => '<h6>',
						'</h4>'         => '</h6>',
						'<h3>'          => '<h5>',
						'</h3>'         => '</h5>',
						'<h2>'          => '<h4>',
						'</h2>'         => '</h4>',
					    '<li><em>'      => '<li class="italic">',
						'</em>'         => '',
					    '?'             => ' '
						);

					// replace: <p><strong>something that should be a header</strong></p>
					// with a header tag


					$text = str_replace(array_keys($rsTable), array_values($rsTable), $text);
				}
				else {
					$this->dbg->log_entry(__FUNCTION__, sprintf('file: %s was not found', $file));
					$class .= " error";
					$style = 'style="color:red;"';
					$text  = $filename . ' (not found)';
					$err = true;
				}

				if(!$err) {
					if( !empty($text) ) {
						if (strlen($text) > 1000 && $addMore) {
							$more     = sprintf('<a id="open-read-more" href="%s" class="button default small">read more</a>', '#more');
							$style    = 'style="display:none;"';
							$_addMore = true;
						}
						else {
							$_addMore = false;
						}

						// format the text - add a more button if necessary;
						$ret = sprintf(
							$fmt,
							$more,
							$class,
							$style,
							$text,
							(($_addMore) ? $this->add_more_script() : '')
						);
					}
					else {
						$ret = null;
					}
				}
			}

			return($ret);
		}

		/**
		 * Removes a BOM (Byte-Order-Mark) from a file
		 * @param $text
		 * @return mixed
		 */
		function remove_utf8_bom($text)	{
			$bom = pack('H*','EFBBBF');
			$text = preg_replace("/^$bom/", '', $text);
			return $text;
		}

		/**
		 * Append a button script to the text and return it
		 *
		 * @param $text
		 *
		 * @return string
		 */
		function add_more_script($text='') {
			ob_start(); ?>

			<script type="application/javascript">
				(function($) {
					$(document).ready(function ($) {
						$('body').on('click', '#open-read-more', function() {
							$('.read-more').fadeIn(1200);
							$(this).hide();
						});
					});
				})(jQuery);
			</script>

			<?php
			$script = ob_get_clean();
			return($text . $script);
		}

		/**
		 * Removes a trailing slash from a directory pathname
		 * @param $path
		 *
		 * @return string
		 */
		function remove_trailing_slash($path) {
			return(rtrim($path, '/'));
		}

		/**
		 * Sanitizes a title, replacing whitespace and a few other characters with dashes.
		 *
		 * Limits the output to alphanumeric characters, underscore (_) and dash (-).
		 * Whitespace becomes a dash.
		 *
		 * @param string $title     The title to be sanitized.
		 * @param string $raw_title Optional. Not used.
		 * @param string $context   Optional. The operation for which the string is sanitized.
		 *
		 * @since 1.0
		 *
		 * @return string The sanitized title.
		 */
		function sanitize_title_with_dashes($title, $raw_title = '', $context = 'display') {
			$title = strip_tags($title);
			// Preserve escaped octets.
			$title = preg_replace('|%([a-fA-F0-9][a-fA-F0-9])|', '---$1---', $title);
			// Remove percent signs that are not part of an octet.
			$title = str_replace('%', '', $title);
			// Restore octets.
			$title = preg_replace('|---([a-fA-F0-9][a-fA-F0-9])---|', '%$1', $title);

			if ($this->seems_utf8($title)) {
				if (function_exists('mb_strtolower')) {
					$title = mb_strtolower($title, 'UTF-8');
				}
				$title = $this->utf8_uri_encode($title, 200);
			}

			$title = strtolower($title);
			$title = preg_replace('/&.+?;/', '', $title); // kill entities
			$title = str_replace('.', '-', $title);

			if ('save' == $context) {
				// Convert nbsp, ndash and mdash to hyphens
				$title = str_replace(array('%c2%a0', '%e2%80%93', '%e2%80%94'), '-', $title);

				// Strip these characters entirely
				$title = str_replace(
					array(
						// iexcl and iquest
						'%c2%a1', '%c2%bf',
						// angle quotes
						'%c2%ab', '%c2%bb', '%e2%80%b9', '%e2%80%ba',
						// curly quotes
						'%e2%80%98', '%e2%80%99', '%e2%80%9c', '%e2%80%9d',
						'%e2%80%9a', '%e2%80%9b', '%e2%80%9e', '%e2%80%9f',
						// copy, reg, deg, hellip and trade
						'%c2%a9', '%c2%ae', '%c2%b0', '%e2%80%a6', '%e2%84%a2',
						// acute accents
						'%c2%b4', '%cb%8a', '%cc%81', '%cd%81',
						// grave accent, macron, caron
						'%cc%80', '%cc%84', '%cc%8c',
					), '', $title
				);

				// Convert times to x
				$title = str_replace('%c3%97', 'x', $title);
			}

			$title = preg_replace('/[^%a-z0-9 _-]/', '', $title);
			$title = preg_replace('/\s+/', '-', $title);
			$title = preg_replace('|-+|', '-', $title);
			$title = trim($title, '-');

			return $title;
		}

		/**
		 * Encode the Unicode values to be used in the URI.
		 *
		 * @since 1.0
		 *
		 * @param string $utf8_string
		 * @param int    $length Max length of the string
		 *
		 * @return string String with Unicode encoded for URI.
		 */
		private function utf8_uri_encode($utf8_string, $length = 0) {
			$unicode        = '';
			$values         = array();
			$num_octets     = 1;
			$unicode_length = 0;

			$this->mbstring_binary_safe_encoding();
			$string_length = strlen($utf8_string);
			$this->reset_mbstring_encoding();

			for ($i = 0; $i < $string_length; $i++) {

				$value = ord($utf8_string[$i]);

				if ($value < 128) {
					if ($length && ($unicode_length >= $length)) {
						break;
					}
					$unicode .= chr($value);
					$unicode_length++;
				}
				else {
					if (count($values) == 0) {
						$num_octets = ($value < 224) ? 2 : 3;
					}

					$values[] = $value;

					if ($length && ($unicode_length + ($num_octets * 3)) > $length) {
						break;
					}
					if (count($values) == $num_octets) {
						if ($num_octets == 3) {
							$unicode .= '%' . dechex($values[0]) . '%' . dechex($values[1]) . '%' . dechex($values[2]);
							$unicode_length += 9;
						}
						else {
							$unicode .= '%' . dechex($values[0]) . '%' . dechex($values[1]);
							$unicode_length += 6;
						}

						$values     = array();
						$num_octets = 1;
					}
				}
			}

			return $unicode;
		}

		/**
		 * Set the mbstring internal encoding to a binary safe encoding when func_overload
		 * is enabled.
		 *
		 * When mbstring.func_overload is in use for multi-byte encodings, the results from
		 * strlen() and similar functions respect the utf8 characters, causing binary data
		 * to return incorrect lengths.
		 *
		 * This function overrides the mbstring encoding to a binary-safe encoding, and
		 * resets it to the users expected encoding afterwards through the
		 * `reset_mbstring_encoding` function.
		 *
		 * It is safe to recursively call this function, however each
		 * `mbstring_binary_safe_encoding()` call must be followed up with an equal number
		 * of `reset_mbstring_encoding()` calls.
		 *
		 * @since 1.0
		 *
		 * @see   reset_mbstring_encoding()
		 *
		 * @param bool $reset Optional. Whether to reset the encoding back to a previously-set encoding.
		 *                    Default false.
		 */
		private function mbstring_binary_safe_encoding($reset = false) {
			static $encodings = array();
			static $overloaded = null;

			if (is_null($overloaded)) {
				$overloaded = function_exists('mb_internal_encoding') && (ini_get('mbstring.func_overload') & 2);
			}

			if (false === $overloaded) {
				return;
			}

			if (!$reset) {
				$encoding = mb_internal_encoding();
				array_push($encodings, $encoding);
				mb_internal_encoding('ISO-8859-1');
			}

			if ($reset && $encodings) {
				$encoding = array_pop($encodings);
				mb_internal_encoding($encoding);
			}
		}

		/**
		 * Reset the mbstring internal encoding to a users previously set encoding.
		 *
		 * @see   mbstring_binary_safe_encoding()
		 *
		 * @since 1.0
		 */
		private function reset_mbstring_encoding() {
			$this->mbstring_binary_safe_encoding(true);
		}

		/**
		 * Checks to see if a string is utf8 encoded.
		 *
		 * NOTE: This function checks for 5-Byte sequences, UTF8
		 *       has Bytes Sequences with a maximum length of 4.
		 *
		 * @param string $str The string to be checked
		 *
		 * @return bool True if $str fits a UTF-8 model, false otherwise.
		 */
		private function seems_utf8($str) {
			$this->mbstring_binary_safe_encoding();
			$length = strlen($str);
			$this->reset_mbstring_encoding();
			for ($i = 0; $i < $length; $i++) {
				$c = ord($str[$i]);
				if ($c < 0x80) {
					$n = 0;
				} # 0bbbbbbb
				elseif (($c & 0xE0) == 0xC0) {
					$n = 1;
				} # 110bbbbb
				elseif (($c & 0xF0) == 0xE0) {
					$n = 2;
				} # 1110bbbb
				elseif (($c & 0xF8) == 0xF0) {
					$n = 3;
				} # 11110bbb
				elseif (($c & 0xFC) == 0xF8) {
					$n = 4;
				} # 111110bb
				elseif (($c & 0xFE) == 0xFC) {
					$n = 5;
				} # 1111110b
				else {
					return false;
				} # Does not match any model
				for ($j = 0; $j < $n; $j++) { # n bytes matching 10bbbbbb follow ?
					if ((++$i == $length) || ((ord($str[$i]) & 0xC0) != 0x80)) {
						return false;
					}
				}
			}

			return true;
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
			$err = new \ecpi\dbc("dataManager.exceptions");
			$msg = sprintf("%s->%s() (line: %s) - %s", __CLASS__, $func, $line, $exception);
			$err->log_entry("exception", $msg);

			return ($msg);
		}
	}
}