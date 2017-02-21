<?php

namespace SC\Replace_Files;

/**
 * Bootstrap.
 */
function bootstrap() {
	Admin\bootstrap();

	add_action( 'sc_updated_post_with_clone', __NAMESPACE__ . '\\replace_image_with_new', 10, 2 );
	add_filter( 'sc_clone_post_excluded_meta_keys', __NAMESPACE__ . '\\exclude_attached_file_from_clone' );
}

/**
 * Exclude the _wp_attached_file key from copying from clone.
 *
 * We handle this ourselves in `replace_image_with_new()` instead.
 *
 * @param array $meta_keys Meta keys to exclude from copying.
 * @return array
 */
function exclude_attached_file_from_clone( $meta_keys ) {
	$meta_keys[] = '_wp_attached_file';
	return $meta_keys;
}

/**
 * Replace the original image with the new one.
 *
 * @param int $original_id Original attachment ID.
 * @param int $new_id New attachment ID.
 */
function replace_image_with_new( $original_id, $new_id ) {
	$original = get_post( $original_id );
	$new      = get_post( $new_id );
	if ( $original->post_type !== 'attachment' || $new->post_type !== 'attachment' ) {
		return;
	}

	$old_file = get_attached_file( $original->ID );
	$new_file = get_attached_file( $new->ID );

	// Execute the replacement.
	$success = copy( $new_file, $old_file );
	if ( ! $success ) {
		trigger_error( sprintf( 'Unable to replace %s with %s (#%d)', $old_file, $new_file, $original->ID ), E_USER_WARNING );
	}
}