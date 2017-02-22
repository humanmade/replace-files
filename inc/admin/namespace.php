<?php

namespace SC\Replace_Files\Admin;

use SC\Publish_Workflow\Approval;
use SC\Replace_Files;
use WP_Post;

const PAGE_SLUG = 'sc-replace-file';
const PENDING_STATUS = 'replace-pending';
const UPLOAD_CAP = 'upload_files';

/**
 * Bootstrap.
 */
function bootstrap() {
	add_action( 'admin_menu', __NAMESPACE__ . '\\register_page' );
	add_filter( 'attachment_fields_to_edit', __NAMESPACE__ . '\\add_replace_button_to_fields', 10, 2 );
	add_filter( 'wp_insert_attachment_data', __NAMESPACE__ . '\\override_attachment_status' );
}

/**
 * Register our admin page.
 *
 * Registers the page without adding to the admin menu.
 */
function register_page() {
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

	$pending = get_posts([
		'post_type'   => 'attachment',
		'post_parent' => $attachment->ID,
		'post_status' => [ 'edit-draft', 'edit-pending', 'edit-approve' ],
	]);
	if ( ! empty( $pending ) ) {
		$items = array_map( function ( $post ) {
			$action = '';
			switch ( $post->post_status ) {
				case 'edit-draft':
					$action = sprintf(
						'<a class="button" href="%s">%s</a>',
						esc_url( get_submit_url( $post ) ),
						esc_html__( 'Submit for Approval', 'sc' )
					);
					break;

				case 'edit-pending':
					$action = esc_html__( 'Awaiting approval', 'sc' ) . '<br />';
					if ( current_user_can( 'preview_post', $post->ID ) ) {
						$action .= sprintf(
							'<a href="%s" class="button">%s</a>',
							Approval\get_action_url( $post->ID, 'preview' ),
							esc_html__( 'Preview', 'sc' )
						);
					}
					break;
			}

			$data = wp_get_attachment_metadata( $post->ID );
			$url = wp_get_attachment_url( $post->ID );
			if ( wp_attachment_is( 'image', $post->ID ) ) {
				$preview = sprintf(
					'<img src="%s" style="max-width: 100px; height: auto;" />',
					esc_url( $url )
				);
			}  else {
				$preview = sprintf(
					'<p><img src="%s" style="width: 16px; height: auto;" /> %s</p>',
					wp_mime_type_icon( $post->post_mime_type ),
					basename( $url )
				);
			}

			$author = get_userdata( $post->post_author );
			return sprintf(
				'<tr><td style="width: 150px;"><a href="%s">%s</a></td><td><p>%s</p><p>%s</p></td></tr>',
				$url,
				$preview,
				sprintf(
					esc_html__( 'Uploaded by %s', 'sc' ),
					esc_html( $author->display_name )
				),
				$action
			);
		}, $pending );

		// Add into `_final`.
		if ( empty( $fields['_final'] ) ) {
			$fields['_final'] = '';
		}
		$fields['_final'] .= sprintf( '<h3>%s</h3>', esc_html__( 'Pending Changes', 'sc' ) );
		$fields['_final'] .= '<table>' . implode( '', $items ) . '</table>';
	}

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
 * Get "Submit for Approval" URL for a replacement.
 *
 * @param WP_Post $attachment Replacement attachment post.
 * @return string URL for the submit approval page.
 */
function get_submit_url( WP_Post $attachment ) {
	$parent = get_post( $attachment->post_parent );
	$base = get_page_url( $parent );
	$args = [
		'action' => 'submit-approval',
		'new-id' => $attachment->ID,
		'_wpnonce' => wp_create_nonce( 'sc-replace-submit-approval' ),
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

	if ( ! empty( $_REQUEST['action'] ) ) {
		$action = wp_unslash( $_REQUEST['action'] );
		if ( $action !== 'submit-approval' ) {
			wp_die( esc_html__( 'Invalid action.', 'sc' ), 400 );
		}

		check_admin_referer( 'sc-replace-submit-approval' );
		if ( empty( $_REQUEST['new-id'] ) ) {
			wp_die( esc_html__( 'Missing new ID parameter.', 'sc' ), 400 );
		}

		$replacement = absint( wp_unslash( $_REQUEST['new-id'] ) );
		handle_submit_approval( $replacement );
	}

	$id = absint( wp_unslash( $_GET['id'] ) );
	$attachment = get_post( $id );
	if ( ! can_replace( $attachment ) || $attachment->post_type !== 'attachment' ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to replace this file.', 'sc' ), 403 );
	}

	// Set page title.
	$GLOBALS['title'] = __( 'Replace Existing File', 'sc' );

	$url = plugins_url( 'assets/upload.js', Replace_Files\FILE );
	$deps = [
		'wp-backbone',
		'wp-plupload',
	];
	wp_enqueue_script( 'sc-replace-files-uploader', $url, $deps, false, true );
}

/**
 * Render page.
 */
function render_page() {
	$id = absint( wp_unslash( $_GET['id'] ) );
	$attachment = get_post( $id );

	set_uploader_settings( $attachment );

	$path = wp_get_attachment_url( $attachment->ID );
	$filename = basename( $path );

	// Header.
	printf( '<h1>%s</h1>', esc_html__( 'Replace Existing File', 'sc' ) );
	echo wp_kses( sprintf( '<p>' . __( 'Replacing <code>%s</code>', 'sc' ) . '</p>', $filename ), 'data' );

	// Upload form.
	$url = get_page_url( $attachment );
	require Replace_Files\DIR . '/templates/upload.php';
}

/**
 * Set settings for uploader JS.
 *
 * @param WP_Post $attachment Attachment to replace.
 */
function set_uploader_settings( WP_Post $attachment ) {
	wp_plupload_default_settings();
	$settings = [
		'plupload' => array(
			'multipart_params' => array(
				'post_id' => $attachment->ID,
				'sc_replace_file_override_status' => wp_create_nonce( 'sc_replace_file_override_status' ),
			),
		),
	];
	wp_localize_script( 'sc-replace-files-uploader', 'scReplaceFilesSettings', $settings );
}

/**
 * Override the attachment status when inserting.
 *
 * wp_insert_post() whitelists the post statuses, so we need to hook in later
 * and override it back.
 *
 * @param array $data Post data to insert into the database.
 * @return array
 */
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

	$data['post_status'] = 'edit-draft';
	return $data;
}

/**
 * Handle submit for approval.
 */
function handle_submit_approval( $id ) {
	$attachment = get_post( $id );
	if ( ! $attachment || ! current_user_can( 'edit_post', $attachment->ID ) || $attachment->post_type !== 'attachment' ) {
		wp_die( esc_html__( 'Invalid attachment ID.', 'sc' ), 403 );
	}

	Approval\update_post_status( $attachment->ID, 'edit-pending' );
	exit;
}
