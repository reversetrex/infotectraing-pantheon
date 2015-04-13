<?php
/**
 * @file
 * Display for Infotec course information.
 * This template only requires the content of the page.
 */
?>

	<?php if(!isset($page['overview'])) : ?>
		<?php print render($page['overview']); ?>
	<?php endif; ?>

	<?php if(!isset($page['description'])) : ?>
		<?php print render($page['description']); ?>
	<?php endif; ?>