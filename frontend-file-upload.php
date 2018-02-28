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
		'filetype' => false,
		'delete' => false 
	), $atts );
	
	$content = '';

	$attachment_type = esc_attr($a['type']);

	$attachment_filetype = esc_attr($a['filetype']);

	if ( $attachment_filetype ) {
		$allowed_file_types_array = explode(',', $attachment_filetype);
		foreach ( $allowed_file_types_array as $allowed_file_type ) {
			if ( $allowed_file_type == 'jpg' ) {
				$allowed_file_types['jpg'] = 'image/jpg';
			} else if ( $allowed_file_type == 'jpeg' ) {
				$allowed_file_types['jpeg'] = 'image/jpeg';
			} else if ( $allowed_file_type == 'gif' ) {
				$allowed_file_types['gif'] = 'image/gif';
			} else if ( $allowed_file_type == 'png' ) {
				$allowed_file_types['png'] = 'image/png';
			} else if ( $allowed_file_type == 'psd' ) {
				$allowed_file_types['psd'] = 'image/psd';
			} else if ( $allowed_file_type == 'svg' ) {
				$allowed_file_types['svg'] = 'image/svg+xml';
			} else if ( $allowed_file_type == 'ai' ) {
				$allowed_file_types['ai'] = 'application/illustrator';
			} else if ( $allowed_file_type == 'eps' ) {
				$allowed_file_types['eps'] = 'application/postscript';
			} else if ( $allowed_file_type == 'pdf' ) {
				$allowed_file_types['pdf'] = 'application/pdf';
			} else if ( $allowed_file_type == 'zip' ) {
				$allowed_file_types['zip'] = 'application/zip';
			} else if ( $allowed_file_type == 'tif' ) {
				$allowed_file_types['tif'] = 'image/tiff';
			}
		}
	} else {
		$allowed_file_types = array( 
			'jpg'  =>  'image/jpg',
			'jpeg' =>  'image/jpeg',
			'gif'  =>  'image/gif',
			'png'  =>  'image/png',
			'psd'  =>  'image/psd',
			'svg'  =>  'image/svg+xml',
			'ai'   =>  'application/illustrator',
			'pdf'  =>  'application/pdf'
		);
	}

	// Check that the nonce is valid, and the user can edit this post. Then upload the file.
	if ( isset( $_POST['file_upload_nonce'], $_POST['post_id'] ) && wp_verify_nonce( $_POST['file_upload_nonce'], 'file_upload' ) && current_user_can( 'read' ) ) {

		$current_user = wp_get_current_user();

		// Custom filename
		function mm_custom_upload_filter( $file ) {
			$file_info = new SplFileInfo($file['name']);
			$extension = $file_info->getExtension();
			// Rename uploaded file with random md5 hash to prevent guessing of filenames
			$file['name'] = 'file-' . get_current_user_id() . '-' . md5(uniqid(rand(), true)) . '.' . $extension;
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
		$overrides = array( 'test_form' => false, 'mimes' => $allowed_file_types );
		// 'file_upload' is the name of the file input in the form below.
		$attachment_id = media_handle_upload( 'file_upload', $_POST['post_id'], array( 'post_title' => $current_user->display_name, 'post_content' => $current_user->ID ), $overrides );

		// Remove custom filename filter
		remove_filter( 'wp_handle_upload_prefilter', 'custom_upload_filter' );

		if ( is_wp_error( $attachment_id ) ) {
			// There was an error uploading the image.
			$error_string = $attachment_id -> get_error_message();
			$content .= '<div id="message" class="notice notice-error"><p>' . $error_string . '</p></div>';
			do_action( 'ffu_upload_failure', $attachment_id, $error_string );
		} else {
			// The image was uploaded successfully!

			// If image is replaced, remove old file
			if ( get_user_meta( get_current_user_id(), $attachment_type ) ) {
				$the_attachment_id = get_user_meta( get_current_user_id(), $attachment_type );
				wp_delete_attachment( $the_attachment_id[0] );
			}

			update_user_meta( get_current_user_id(), $attachment_type, $attachment_id );
			$content .= '<div id="message" class="notice notice-success"><p>' . __( 'Your file has been updated', 'ffu' ) . '</p></div>';
			do_action( 'ffu_upload_success', $attachment_id, $attachment_type );
		}

	// Check that the nonce is valid, and the user can edit this post. Then delete the file.
	} else if ( isset( $_POST['file_delete_nonce'], $_POST['post_id'] ) && wp_verify_nonce( $_POST['file_delete_nonce'], 'file_delete' ) && current_user_can( 'read'  ) ) {
		$the_attachment_id = get_user_meta( get_current_user_id(), $attachment_type );
		wp_delete_attachment( $the_attachment_id[0] );
		delete_user_meta( get_current_user_id(), $attachment_type );
		$content .= '<div id="message" class="notice notice-success"><p>' . __( 'Your file has been removed', 'ffu' ) . '</p></div>';
		do_action( 'ffu_upload_deleted', $attachment_id, $attachment_type );
	} else {
		// The security check failed, maybe show the user an error.
	}

	$content .= '<script>
	jQuery(document).ready( function($) {
		$( "form[name=file_upload_form]" ).submit(function() {
			$( "input[type=submit]" ).addClass( "uploading" );
		});
		$( "#message" ).delay(8000).fadeOut( "slow" );
	});
	</script>
	<p>';

	if ( esc_attr($a['thumbnail']) === 'true' ) {
		
		if ( get_user_meta( get_current_user_id(), $attachment_type ) ) {
			$the_attachment_id = get_user_meta( get_current_user_id(), $attachment_type );
			$content .= wp_get_attachment_image( $the_attachment_id[0], 'full', true );
		}

	} else {
		if ( get_user_meta( get_current_user_id(), $attachment_type ) ) {
			$the_attachment_id = get_user_meta( get_current_user_id(), $attachment_type );
			if ( !empty( $the_attachment_id[0] ) ) {
				$file_metadata = ( wp_get_attachment_metadata( $the_attachment_id[0] ) );

				$content .= '<a href="' . wp_get_attachment_url( $the_attachment_id[0] ) . '" download>';
				$content .= basename( wp_get_attachment_url( $the_attachment_id[0] ) );
				$content .= '</a>';
				$file_size = size_format( filesize( get_attached_file( $the_attachment_id[0] ) ) );
				if ( $file_size ) {
					$content .=  ' - ' . $file_size;
				}
				if ( $file_metadata ) {
					if ( $file_metadata['width'] && $file_metadata['height'] ) {
						$content .= ' - ' . $file_metadata['width'] . ' x ' . $file_metadata['height'] . ' PX';
					}
				}
			}
		}
	}

	$content .= '</p>';

	$content .= '<form name="file_upload_form" id="file-upload-form" class="file-upload-form ' . $attachment_type . '" method="post" action="" enctype="multipart/form-data">';

	do_action( 'ffu_form_start', $attachment_type );

	$content .= '<p>
			<label for="file-upload">';
				if ( get_user_meta( get_current_user_id(), $attachment_type ) ) {
					$the_attachment_id = get_user_meta( get_current_user_id(), $attachment_type );
					if ( !empty( $the_attachment_id[0] ) ) {
						$content .= __( 'Change file', 'ffu' );
					} else {
						$content .= __( 'Upload file', 'ffu' );
					}
				}
			$content .= '</label>
			<input type="file" name="file_upload" id="file-upload" /><br />
		</p>
		<input type="submit" name="submit" id="submit" value="' . __( 'Save', 'ffu' ) . '" class="button-medium" />
		<input type="hidden" name="post_id" value="' . get_the_ID() . '" />' . wp_nonce_field( 'file_upload', 'file_upload_nonce', false );

		do_action( 'ffu_form_end', $attachment_type );

		$content .= '</form>';

	if ( esc_attr($a['delete']) === 'true' ) {
		if ( get_user_meta ( get_current_user_id(), esc_attr ( $a['type'] ) ) ) { ?>
			<form name="file_delete_form" id="file-delete-form" class="file-delete-form" method="post" action="">
				<input type="submit" name="post_id" value="<?php _e( 'Delete file', 'ffu' ); ?>" />
				<?php wp_nonce_field( 'file_delete', 'file_delete_nonce' ); ?>
			</form>
		<?php }
	}

	return $content;

}

function frontend_file_upload_shortcode() {
	add_shortcode( 'file_upload', 'frontend_file_upload' );
}
add_action('init', 'frontend_file_upload_shortcode');