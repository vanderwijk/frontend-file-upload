<?php
/*
* Plugin Name: Frontend File Upload
* Description: Shortcode to allow logged-in users to upload files
* Version: 1.0
* Author: Johan van der Wijk
* Author URI: https://thewebworks.nl
*/

function ffu_load_textdomain() {
  load_plugin_textdomain( 'ffu', false, basename( dirname( __FILE__ ) ) . '/languages' ); 
}
add_action( 'plugins_loaded', 'ffu_load_textdomain' );

function frontend_file_upload( $atts ) {

	$a = shortcode_atts( array(
		'type' => 'logo',
		'thumbnail' => false,
		'custom_filename' => false,
		'filetype' => '',
		'delete' => true 
    ), $atts );

	// Check that the nonce is valid, and the user can edit this post. Then upload the file.
	if ( isset( $_POST['file_upload_nonce'], $_POST['post_id'] ) && wp_verify_nonce( $_POST['file_upload_nonce'], 'file_upload' ) && current_user_can( 'read' ) ) {

		$current_user = wp_get_current_user();

		// Custom filename
		function mm_custom_upload_filter( $file ) {
			$file_info = new SplFileInfo($file['name']);
			$extension = $file_info->getExtension();
			$user_id = get_current_user_id();
			// Rename uploaded file with random md5 hash to prevent guessing of filenames
			$file['name'] = 'file-' . $user_id . '-' . md5(uniqid(rand(), true)) . '.' . $extension;
			return $file;
		}
		if ( esc_attr($a['custom_filename']) === 'true' ) {
			add_filter( 'wp_handle_upload_prefilter', 'mm_custom_upload_filter' );
		}

		// Custom upload folder
		function mm_custom_upload_directory( $dir ) {
			return array(
				'path'  => $dir['basedir'] . '/files',
				'url'    => $dir['baseurl'] . '/files',
			) + $dir;
		}
		add_filter( 'upload_dir', 'mm_custom_upload_directory' );

		// These files need to be included as dependencies when on the front end.
		require_once( ABSPATH . 'wp-admin/includes/image.php' );
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		require_once( ABSPATH . 'wp-admin/includes/media.php' );

		// Let WordPress handle the upload.
		$allowed_file_types = array( 
			'jpg' =>'image/jpg',
			'jpeg' =>'image/jpeg',
			'gif' => 'image/gif',
			'png' => 'image/png',
			'psd' => 'image/psd',
			'svg' => 'image/svg+xml',
			'ai' => 'application/illustrator'
		);
		$overrides = array( 'test_form' => false, 'mimes' => $allowed_file_types );
		// 'file_upload' is the name of the file input in the form below.
		$attachment_id = media_handle_upload( 'file_upload', $_POST['post_id'], array( 'post_title' => $current_user->display_name, 'post_content' => $current_user->ID ), $overrides );

		// Remove custom filename filter
		remove_filter( 'wp_handle_upload_prefilter', 'custom_upload_filter' );

		ob_start();

		if ( is_wp_error( $attachment_id ) ) {
			// There was an error uploading the image.
			$error_string = $attachment_id -> get_error_message();
			echo '<div id="message" class="notice notice-error"><p>' . $error_string . '</p></div>';
			$upload_status = 'error';
			$button_upload_class = "button primary";
		} else {
			// The image was uploaded successfully!

			// If image is replaced, remove old file
			if ( get_user_meta( get_current_user_id(), esc_attr($a['type']) ) ) {
				$the_attachment_id = get_user_meta( get_current_user_id(), esc_attr($a['type']) );
				wp_delete_attachment( $the_attachment_id[0] );
			}

			update_user_meta( get_current_user_id(), esc_attr($a['type']), $attachment_id );
			echo '<div id="message" class="notice notice-success"><p>' . __( 'Your file has been updated', 'ffu' ) . '</p></div>';
			$upload_status = 'success';
			$button_upload_class = "button secondary";
		}

	// Check that the nonce is valid, and the user can edit this post. Then delete the file.
	} else if ( isset( $_POST['file_delete_nonce'], $_POST['post_id'] ) && wp_verify_nonce( $_POST['file_delete_nonce'], 'file_delete' ) && current_user_can( 'read'  ) ) {
		$the_attachment_id = get_user_meta( get_current_user_id(), esc_attr($a['type']) );
		wp_delete_attachment( $the_attachment_id[0] );
		delete_user_meta( get_current_user_id(), esc_attr($a['type']) );
		echo '<div id="message" class="notice notice-success"><p>' . __( 'Your file has been removed', 'ffu' ) . '</p></div>';
	} else {
		// The security check failed, maybe show the user an error.
	} ?>

	<script>
	jQuery(document).ready( function($) {
		$('form[name="file_upload_form"]').submit(function() {
			$( 'input[type="submit"]').addClass( 'uploading' );
		});
		$( '#message' ).delay(5000).fadeOut( 'slow' );
	});
	</script>

	<p><?php 

	if ( esc_attr($a['thumbnail']) === 'true' ) {
		
		if ( get_user_meta( get_current_user_id(), esc_attr($a['type']) ) ) {
			$the_attachment_id = get_user_meta( get_current_user_id(), esc_attr($a['type']) );
			echo wp_get_attachment_image( $the_attachment_id[0], 'full', true );
		}

	} else {
		if ( get_user_meta( get_current_user_id(), esc_attr($a['type']) ) ) {
			$the_attachment_id = get_user_meta( get_current_user_id(), esc_attr($a['type']) );
			$file_metadata = ( wp_get_attachment_metadata( $the_attachment_id[0] ) );

			echo '<a href="' . wp_get_attachment_url( $the_attachment_id[0] ) . '" download>';
			echo basename( wp_get_attachment_url( $the_attachment_id[0] ) );
			echo '</a>';
			$file_size = size_format( filesize( get_attached_file( $the_attachment_id[0] ) ) );
			echo ' - ' . $file_size;
			if ( $file_metadata ) {
				echo ' - ' . $file_metadata['width'] . ' x ' . $file_metadata['height'] . ' PX';
			}
		}
	}
	
	?></p>

	<form name="file_upload_form" id="file-upload-form" class="file-upload-form <?php echo esc_attr($a['type']); ?>" method="post" action="" enctype="multipart/form-data">
		<p>
			<label for="file-upload">
				<?php if ( get_user_meta( get_current_user_id(), esc_attr($a['type']) ) ) {
					_e( 'Change file', 'ffu' );
				} else {
					_e( 'Upload file', 'ffu' );
				} ?>
				<img src="/wp-content/plugins/frontend-file-upload/img/spinner.gif" class="spinner" width="15" height="15" style="display:none;" />
			</label>
			<input type="file" name="file_upload" id="file-upload" /><br />
		</p>
		<input type="submit" name="submit" id="submit" value="<?php _e( 'Upload', 'ffu' ); ?>" class="button-medium" />
		<input type="hidden" name="post_id" value="<?php echo get_the_ID(); ?>" />
		<?php wp_nonce_field( 'file_upload', 'file_upload_nonce' ); ?>
	</form>

	<?php 

	if ( esc_attr($a['delete']) === 'true' ) {
		if ( get_user_meta ( get_current_user_id(), esc_attr ( $a['type'] ) ) ) { ?>
			<form name="file_delete_form" id="file-delete-form" class="file-delete-form" method="post" action="">
				<input type="submit" name="post_id" value="<?php _e( 'Delete file', 'ffu' ); ?>" />
				<?php wp_nonce_field( 'file_delete', 'file_delete_nonce' ); ?>
			</form>
		<?php }
	}

	return ob_get_clean();

}

function frontend_file_upload_shortcode() {
	add_shortcode( 'file_upload', 'frontend_file_upload' );
}
add_action('init', 'frontend_file_upload_shortcode');