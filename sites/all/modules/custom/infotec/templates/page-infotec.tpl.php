<?php
/**
 * @package       infotec Module
 * @subpackage    course page template
 * @version       1.5
 * @author        gbellucci
 * @copyright (c) 2015 ECPI University
 *
 * @file
 * Display for Infotec course information.
 * This template only requires the content of the page.
 *
 * template variables:
 *    $page['category'] = the course category
 *    $page['code'] = the course code
 *    $page['sections'] = array containing the page sections
 *    $page['related'] = list of related courses (unnumbered list)
 *    $page['formats'] = course in other formats (unnumbered list)
 *    $page['method'] = the delivery method
 *    $page['length'] = the length of the course
 *    $page['group'] = the group within the category
 *    $page['vendor'] = the course vendor
 *
 * NOTE:
 *    1. all variables must be tested using the 'isset' function before displaying them.
 *    2. not all fields are available for each course
 */
?>
	<div class="course-page">
		<?php doSections($page);?>
	</div><!-- /course-page -->

<?php

/**
 * prints all of the available sections
 * @param $page
 */
function doSections($page) {
	$sections = $page['sections']; ?>

	<div class="row">
		<!-- left side -->
		<div class="eight columns">
			<h2 class="course-title"><?php print render($page['title']); ?></h2>

			<?php foreach ($sections as $section) { ?>
			<div class="<?php echo $section['class']; ?>">
				<h3 class="sub-heading"><?php echo $section['title']; ?></h3>
				<?php print render($section['content']); ?>
			</div>
			<?php } ?>
		</div>
		<!-- right side -->
		<div class="eight columns">
			<?php rightSideBar($page); ?>
		</div>
	</div>
	<?php
}

/*
 * Displays the course meta list
 * @param $page - render array
 */
function courseMeta($page) { ?>
	<div class="info-box">
		<ul class="unstyled-list">
			<?php if (isset($page['category'])) : ?>
				<li><span class="fieldname">Category: </span><?php print render($page['category']); ?></li>
			<?php endif; ?>
			<?php if (isset($page['group'])) : ?>
				<li><span class="fieldname">Course Group: </span><?php print render($page['group']); ?></li>
			<?php endif; ?>
			<?php if (isset($page['code'])) : ?>
				<li><span class="fieldname">Course Code: </span><?php print render($page['code']); ?></li>
			<?php endif; ?>
			<?php if (isset($page['length'])) : ?>
				<li><span class="fieldname">Course Length: </span><?php print render($page['length']); ?></li>
			<?php endif; ?>
			<?php if (isset($page['vendor'])) : ?>
				<li><span class="fieldname">Course Vendor: </span><?php print render($page['vendor']); ?></li>
			<?php endif; ?>
			<?php if (isset($page['method'])) : ?>
				<li><span class="fieldname">Delivery Method: </span><?php print render($page['method']); ?></li>
			<?php endif; ?>
		</ul>
	</div>
<?php
}

/**
 * Adds the right sidebar
 *
 * @param $page
 */
function rightSideBar($page) {

	if(isset($page['method']) && !stristr($page['method'], 'not-found')) {
		// NOTE: contact block has a specific id number
		// The block contains classes that interfere with this template
		// so they are removed.
		//
		$block = module_invoke('block', 'block_view', '16');
		$content = str_replace(array('columns', 'row'), '', $block['content']);
		print render($content);

		courseMeta($page);
	}

	// related courses
	if (isset($page['related'])) {
		relatedCourses($page);
	}

	// other delivery method formats
	if (isset($page['formats'])) {
		otherFormats($page);
	}
}

/**
 * Add related courses
 *
 * @param $page
 */
function relatedCourses($page) { ?>
	<div class="info-box">
		<h3 class="sub-heading">Related Courses</h3>
		<p>You may also be interested in these related courses:</p>
		<?php print render($page['related']); ?>
	</div>
<?php
}

/**
 * Add course in other formats
 *
 * @param $page
 */
function otherFormats($page) { ?>
	<div class="info-box">
		<h3 class="sub-heading">Other Formats</h3>
		<p>This course is also available in these formats:</p>
		<?php print render($page['formats']); ?>
	</div>
<?php
}
