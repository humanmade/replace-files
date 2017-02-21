<?php

namespace SC\Replace_Files;

use WP_Post;

const PAGE_SLUG = 'sc-replace-file';
const PENDING_STATUS = 'replace-pending';
const UPLOAD_CAP = 'upload_files';

add_action( 'admin_menu', __NAMESPACE__ . '\\register_page' );
add_filter( 'attachment_fields_to_edit', __NAMESPACE__ . '\\add_replace_button_to_fields', 10, 2 );
add_filter( 'wp_insert_attachment_data', __NAMESPACE__ . '\\override_attachment_status' );
add_action( 'sc_updated_post_with_clone', __NAMESPACE__ . '\\replace_image_with_new', 10, 2 );
add_filter( 'sc_clone_post_excluded_meta_keys', __NAMESPACE__ . '\\exclude_attached_file_from_clone' );

function register_page() {
	// Register the page, but don't add to the menu.
	$parent = 'upload.php';
	$hookname = get_plugin_page_hookname( PAGE_SLUG, $parent );
	$GLOBALS['_registered_pages'][ $hookname ] = true;
	if ( ! current_user_can( UPLOAD_CAP ) ) {
		$GLOBALS['_wp_submenu_nopriv'][ $parent ][ PAGE_SLUG ] = true;
	}

	add_action( 'load-' . $hookname, __NAMESPACE__ . '\\prepare_page' );
	add_action( $hookname, __NAMESPACE__ . '\\render_page' );
}

/**
 * Add "Replace File" button to the Edit Media screen.
 *
 * @param array $fields Fields on the screen.
 * @param WP_Post $attachment Attachment post being edited.
 * @return array
 */
function add_replace_button_to_fields( $fields, WP_Post $attachment ) {
	if ( ! can_replace( $attachment ) ) {
		return $fields;
	}

	$fields['sc_replace_file'] = [
		'label' => __( 'Replace File', 'sc' ),
		'input' => 'html',
		'value' => '',
		'html' => sprintf(
			'<a class="button-secondary" href="%s">%s</a>',
			esc_url( get_page_url( $attachment ) ),
			esc_html__( 'Upload replacement file', 'sc' )
		),
	];

	return $fields;
}

/**
 * Get URL for the replacement page.
 *
 * @param WP_Post $attachment Attachment post to replace.
 * @return string URL for the replacement page.
 */
function get_page_url( WP_Post $attachment ) {
	$base = admin_url( 'upload.php' );
	$args = [
		'page' => 'sc-replace-file',
		'id'   => $attachment->ID,
	];
	$url = add_query_arg( urlencode_deep( $args ), $base );
	return $url;
}

/**
 * Can the current user replace the attachment?
 *
 * @param WP_Post $attachment Attachment post to replace.
 * @return bool
 */
function can_replace( WP_Post $attachment ) {
	return current_user_can( 'edit_post', $attachment->ID );
}

/**
 * Prepare page before actually rendering.
 *
 * Loads before the header is loaded.
 */
function prepare_page() {
	// Double-check cap.
	if ( empty( $_GET['id'] ) ) {
		wp_die( esc_html__( 'Missing ID parameter.', 'sc' ), 400 );
	}

	if ( ! current_user_can( UPLOAD_CAP ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'sc' ), 403 );
	}

	$id = absint( wp_unslash( $_GET['id'] ) );
	$attachment = get_post( $id );
	if ( ! can_replace( $attachment ) || $attachment->post_type !== 'attachment' ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to replace this file.', 'sc' ), 403 );
	}

	// Set page title.
	$GLOBALS['title'] = __( 'Replace Existing File', 'sc' );
	wp_enqueue_script( 'plupload-handlers' );
}

/**
 * Render page.
 */
function render_page() {
	$id = absint( wp_unslash( $_GET['id'] ) );
	$attachment = get_post( $id );

	$path = wp_get_attachment_url( $attachment->ID );
	$filename = basename( $path );

	printf( '<h1>%s</h1>', esc_html__( 'Replace Existing File', 'sc' ) );
	echo wp_kses( sprintf( '<p>' . __( 'Replacing <code>%s</code>', 'sc' ) . '</p>', $filename ), 'data' );

	printf(
		'<form enctype="multipart/form-data" method="post" action="%s" class="%s" id="file-form">',
		esc_url( admin_url( 'media-new.php' ) ),
		'media-upload-form type-form validate'
	);
	printf( '<input type="hidden" name="post_id" id="post_id" value="%d" />', $attachment->ID );
	wp_nonce_field( 'media-form' );

	// Make the attachment a child of the existing attachment.
	$callback = function ( $params ) use ( $attachment ) {
		$params['post_id'] = $attachment->ID;
		$params['sc_replace_file_override_status'] = wp_create_nonce( 'sc_replace_file_override_status' );
		return $params;
	};
	add_filter( 'upload_post_params', $callback );
	media_upload_form();
	remove_filter( 'upload_post_params', $callback );

	echo '<div id="media-items" class="hide-if-no-js"></div>';

	echo '</form>';

	echo 'render';
}

function override_attachment_status( $data ) {
	if ( ! is_admin() || ! current_user_can( 'upload_files' ) ) {
		return $data;
	}

	if ( empty( $_REQUEST['sc_replace_file_override_status'] ) ) {
		return $data;
	}

	$nonce = wp_unslash( $_REQUEST['sc_replace_file_override_status'] );
	if ( ! wp_verify_nonce( $nonce, 'sc_replace_file_override_status' ) ) {
		return $data;
	}

	$data['post_status'] = 'edit-pending';
	return $data;
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
