<form action="<?php echo esc_url( $url ) ?>" method="POST">
	<div id="plupload-upload-ui" class="hide-if-no-js">
		<?php
		/**
		 * Fires before the upload interface loads.
		 *
		 * @since 2.6.0 As 'pre-flash-upload-ui'
		 * @since 3.3.0
		 */
		do_action( 'pre-plupload-upload-ui' ); ?>

		<div id="drag-drop-area" style="height: auto; min-height: 200px;">
			<div class="drag-drop-inside drag-drop-selector">
				<p class="drag-drop-info"><?php esc_html_e( 'Drop replacement file here', 'sc' ) ?></p>
				<p><?php echo esc_html_x( 'or', 'Uploader: Drop files here - or - Select Files', 'sc' ) ?></p>
				<p class="drag-drop-buttons"><input id="plupload-browse-button" type="button" value="<?php esc_attr_e( 'Select File', 'sc' ) ?>" class="button" /></p>
			</div>
			<div class="drag-drop-inside drag-drop-status"></div>
		</div>

		<?php
		/**
		 * Fires after the upload interface loads.
		 *
		 * @since 2.6.0 As 'post-flash-upload-ui'
		 * @since 3.3.0
		 */
		do_action( 'post-plupload-upload-ui' ); ?>
	</div>

	<input type="hidden" name="action" value="submit-approval" />
	<input id="scrf-new-id" type="hidden" name="new-id" value="" />
	<?php wp_nonce_field( 'sc-replace-submit-approval' ) ?>
</form>

<p class="max-upload-size"><?php printf(
	__( 'Maximum upload file size: %s.', 'sc' ),
	esc_html( size_format( wp_max_upload_size() ) )
) ?></p>

<script type="text/html" id="tmpl-scrf-upload-status">
	<# if ( data.uploading ) { #>
		<p><?php echo wp_kses( sprintf(
			__( 'Uploading %s&#8230;', 'sc' ),
			'<code>{{ data.filename }}</code>'
		), 'data' ) ?></p>

		<div class="media-item">
			<div class="progress">
				<div class="percent">0%</div>
				<div class="bar"></div>
			</div>
		</div>

	<# } else { #>

		<p><?php esc_html_e( 'Success! Replacement file uploaded.', 'sc' ) ?></p>
		<img src="{{ data.url }}" style="max-width: 250px; height: auto" />
		<p><button type="submit" class="button"><?php esc_html_e( 'Submit for Approval', 'sc' ) ?></button></p>

	<# } #>
</script>

<script type="text/html" id="tmpl-scrf-upload-error">
	<p><?php printf(
		esc_html__( 'Whoops, could not upload file: %s', 'sc' ),
		'{{ data.message }}'
	) ?></p>
	<p><button type="button" class="button"><?php esc_html_e( 'Try Again', 'sc' ) ?></button></p>
</script>
