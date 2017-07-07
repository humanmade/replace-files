<?php

namespace HM\Replace_Files;

use WP_Error;
use WP_Post;

/**
 * Bootstrap.
 */
function bootstrap() {
	Admin\bootstrap();

	add_action( 'sc_updated_post_with_clone', __NAMESPACE__ . '\\replace_image_with_new', 10, 2 );
	add_filter( 'sc_clone_post_excluded_meta_keys', __NAMESPACE__ . '\\exclude_attached_file_from_clone' );
	add_action( 'sc_workflow_reject_post', __NAMESPACE__ . '\\on_reject_post', 10, 2 );
}

/**
 * Get keys to exclude from cloning.
 *
 * By default, excludes only the _wp_attached_file key. We handle this
 * ourselves in `replace_image_with_new()` instead.
 *
 * @return string[] Meta keys to exclude from copying.
 */
function get_excluded_meta_keys() {
	$meta_keys = [
		'_wp_attached_file',
	];

	/**
	 * Filter which keys are excluded when cloning.
	 *
	 * @param string[] $meta_keys List of meta keys to exclude from clone.
	 */
	return apply_filters( 'replace_files.get_excluded_meta_keys', $meta_keys );
}

/**
 * Clone post meta from one attachment to another.
 *
 * @param WP_Post $from Post to clone meta from.
 * @param WP_Post $to Post to clone meta to.
 * @return boolean|WP_Error True on success, error otherwise.
 */
function clone_post_meta( $from, $to, $is_update = false ) {
	$from = get_post( $from );
	$to = get_post( $to );
	if ( empty( $from ) || empty( $to ) ) {
		$ids = func_get_args();
		return new WP_Error(
			'replace_files.clone_post_meta.invalid_post',
			__( 'Post is invalid', 'replace_files' ),
			compact( 'from', 'to', 'ids' )
		);
	}

	$from_meta = get_post_meta( $from );
	$to_meta = get_post_meta( $to );
	foreach ( get_excluded_meta_keys() as $key ) {
		unset( $from_meta[ $key ] );
		unset( $to_meta[ $key ] );
	}

	foreach ( $from_meta as $key => $values ) {
		if ( $is_update && isset( $to_meta[ $key ] ) ) {
			delete_post_meta( $to->ID, wp_slash( $key ) );
		}

		foreach ( $values as $value ) {
			$result = add_post_meta( $to->ID, wp_slash( $key ), wp_slash( maybe_unserialize( $value ) ) );
			if ( ! $result ) {
				return new WP_Error(
					'replace_files.clone_post_meta.could_not_save',
					__( 'Unable to add post meta.', 'replace_files' ),
					compact( 'key', 'value', 'from', 'to' )
				);
			}
		}
	}

	return true;
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
		trigger_error( sprintf( 'Unable to replace %s with %s (#%d)', $old_file, $new_file, $original->ID ), E_USER_WARNING ); // @codingStandardsIgnoreLine (WordPress.PHP.DevelopmentFunctions.error_log_trigger_error)
	}
}

/**
 * Delete attachments on rejection.
 *
 * @param mixed $result
 * @param WP_Post $post Post being rejected.
 * @return mixed
 */
function on_reject_post( $result, WP_Post $post ) {
	if ( is_wp_error( $result ) || $post->post_type !== 'attachment' ) {
		return $result;
	}

	// Remove the attachment.
	wp_delete_attachment( $post->ID );
	return $result;
}
