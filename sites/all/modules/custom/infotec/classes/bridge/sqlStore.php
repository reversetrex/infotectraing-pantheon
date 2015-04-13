<?php
/**
 * @package bridge
 * @subpackage sql string storage
 * @author gbellucci
 * @version 1.0
 * @abstract
 *   A class for storing and retrieving sql statements by name
 */

namespace infotec {

	/**
	 * Class sqlStore
	 * @package infotec
	 */
	Class sqlStore {
		/**
		 * Selecting course details for a specified course number
		 * Returns the majority of items required for a course page
		 * @var string
		 */
		private $courseDetails = <<< MSSQL
			SELECT
				CategoryName,
				CourseCode,
				CourseWebName,
				DeliveryMethodName,
				LengthInDays,
				LengthDecription,
				GroupName,
				OVERVIEW as Overview,
				OBJECTIVES as Objectives,
				COURSECONTENT as CourseContent,
				VendorName
			FROM
				[InfotecWeb].[dbo].[SelectCompleteCourseDetailsView]
			WHERE
				@CourseNumber = '%s'
MSSQL;
		/**
		 * Used for creating a list of courses for all delivery methods
		 * ordered by method, group name and course name.
		 * @var string
		 */
		private $courseList = <<< MSSQL
			SELECT
				GroupName,
				CategoryName,
				CourseNumber,
				CourseWebName,
				DeliveryMethodName
			FROM
				[InfotecWeb].[dbo].[SelectCompleteCourseDetailsView]
			WHERE
				DeliveryMethodID IN (1,2,3)
            ORDER BY
                DeliveryMethodName,
                GroupName,
                CourseWebName
MSSQL;
		/**
		 * Returns a class schedule for a specific course number
		 * @var string
		 */
		/**
		private $classSchedByCourse = <<< MSSQL
			SELECT
				COURSE_NO as CourseNumber,
                STARTDATE as StartDate,
                ENDDATE as EndDate,
                STARTTIME as StartTime,
                LOCATION as Location
			FROM
				[GeoTalent].[dbo].[dbViewClassSchedule]
			WHERE
				@COURSE_NO = '%s'
			ORDER BY
				STARTDATE ASC
MSSQL;
		**/
		private $classSchedByCourse = <<< MSSQL
		SELECT
                COURSE_NO as CourseNumber,
                STARTDATE as StartDate,
                ENDDATE as EndDate,
                STARTTIME as StartTime,
                LOCATION as Location,
                BlurbText as defaultText
		FROM
                [GeoTalent].[dbo].[dbViewClassSchedule2]
		WHERE
			@COURSE_NO = '%s'
		UNION
		SELECT
			NULL,NULL,NULL,NULL,NULL,BlurbText as defaultText
		FROM
                [GeoTalent].[dbo].[dbViewClassSchedule2]
		WHERE
			COURSE_NO IS NULL
		ORDER BY
			STARTDATE ASC
MSSQL;

		/**
		 * Returns courses delivered in different formats as related
		 * to a specific course number
		 * @var string
		 */
		private $courseFormats =  <<< MSSQL
			SELECT
				CourseNumber_2 as CourseNumber,
				CourseName_2 as CourseName,
				DeliveryMethodName_2 as MethodName
			FROM
				[InfotecWeb].[dbo].[CoursesInMultipleFormatsView]
			WHERE
				@CourseNumber_1 = '%s'
			ORDER BY
				CourseName_2 ASC
MSSQL;
		/**
		 * Returns courses related to a specific course.
		 * @var string
		 */
		private $relatedCourses = <<< MSSQL
			SELECT
				CourseName_2 as CourseName,
				CourseNumber_2 as CourseNumber,
				DeliveryMethodName as MethodName
			FROM
				[InfotecWeb].[dbo].[CoursesOfInterestView]
			WHERE
				@CourseNumber_1 = '%s'
			ORDER BY
				CourseName_2 ASC
MSSQL;
		/**
		 * @var array
		 */
		protected $query;

		/**
		 * Constructor - initializes the internal query table
		 */
		function __construct() {

			// defines a table of database queries by name
			$this->query = array(
				// name          => string
			    'courseDetails'  => $this->courseDetails,
				'courseList'     => $this->courseList,
			    'courseSched'    => $this->classSchedByCourse,
			    'relatedCourses' => $this->relatedCourses,
			    'courseFormats'  => $this->courseFormats
			);
		}

		/**
		 * Returns a string by $id
		 * @param $id
		 *
		 * @return string | null
		 */
		function get($id) {
			return (isset($this->query[$id]) ? $this->query[$id] : null);
		}

	}//end class
}// end namespace