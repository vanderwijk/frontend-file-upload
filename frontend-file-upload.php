<?php
/*
* Plugin Name: Frontend File Upload
* Description: Shortcode to allow logged-in users to upload files
* Version: 1.0
* Author: Johan van der Wijk
* Author URI: https://thewebworks.nl
*/


function frontend_file_upload(){

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
		add_filter( 'wp_handle_upload_prefilter', 'mm_custom_upload_filter' );

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
		$allowed_file_types = array( 'jpg' =>'image/jpg','jpeg' =>'image/jpeg', 'gif' => 'image/gif', 'png' => 'image/png' );
		$overrides = array( 'test_form' => false, 'mimes' => $allowed_file_types );
		// 'file_upload' is the name of the file input in the form below.
		$attachment_id = media_handle_upload( 'file_upload', $_POST['post_id'], array( 'post_title' => $current_user->display_name, 'post_content' => $current_user->ID ), $overrides );

		// Remove custom filename filter
		remove_filter( 'wp_handle_upload_prefilter', 'custom_upload_filter' );

		if ( is_wp_error( $attachment_id ) ) {
			// There was an error uploading the image.
			$error_string = $attachment_id -> get_error_message();
			echo '<div id="message" class="notice notice-error"><p>' . $error_string . '</p></div>';
			$upload_status = 'error';
			$button_upload_class = "button primary";
		} else {
			// The image was uploaded successfully!
			update_user_meta( get_current_user_id(), 'photo', $attachment_id );
			echo '<div id="message" class="notice notice-success"><p>' . __( 'Your file has been updated', 'ffu' ) . '</p></div>';
			$upload_status = 'success';
			$button_upload_class = "button secondary";
		}

	// Check that the nonce is valid, and the user can edit this post. Then delete the file.
	} else if ( isset( $_POST['file_delete_nonce'], $_POST['post_id'] ) && wp_verify_nonce( $_POST['file_delete_nonce'], 'file_delete' ) && current_user_can( 'read'  ) ) {
		delete_user_meta( get_current_user_id(), 'photo' );
		echo '<div id="message" class="notice notice-success"><p>' . __( 'Your file has been removed', 'ffu' ) . '</p></div>';
	} else {
		// The security check failed, maybe show the user an error.
	} ?>

	<script>
	jQuery(document).ready( function($) {
		$( 'input[name="file_upload"]' ).on( 'change', function() {
			$( 'form[name="file_upload_form"]' ).submit();
			$( 'label[for="file-upload"]' ).addClass( 'uploading' );
		});
		$( '#message' ).delay(5000).fadeOut( 'slow' );
	});
	</script>

	<p><?php echo get_file( get_current_user_id(), 220 ); ?></p>

	<form name="file_upload_form" id="file-upload-form" class="file-upload-form" method="post" action="" enctype="multipart/form-data">
		<p>
			<label for="file-upload">
				<?php if ( get_user_meta( get_current_user_id(), 'photo' ) ) {
					_e( 'Change file', 'ffu' );
				} else {
					_e( 'Upload file', 'ffu' );
				} ?>
			</label>
			<input type="file" name="file_upload" id="file-upload" />
		</p>
		<input type="hidden" name="post_id" value="<?php echo get_the_ID(); ?>" />
		<?php wp_nonce_field( 'file_upload', 'file_upload_nonce' ); ?>
	</form>

	<?php if ( get_user_meta( get_current_user_id(), 'photo' ) ) { ?>
		<form name="file_delete_form" id="file-delete-form" class="file-delete-form" method="post" action="">
			<input type="submit" name="post_id" value="<?php _e( 'Delete file', 'ffu' ); ?>" />
			<?php wp_nonce_field( 'file_delete', 'file_delete_nonce' ); ?>
		</form>
	<?php }

}
add_shortcode( 'file_upload', 'frontend_file_upload' );


class file_widget extends WP_Widget {

	/**
	 * Sets up the widgets name etc
	 */
	public function __construct() {
		$widget_ops = array(
			'classname' => 'file_widget',
			'description' => 'My Widget is awesome',
		);
		parent::__construct( 'file_widget', 'File Widget', $widget_ops );
	}

	/**
	 * Outputs the content of the widget
	 *
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance ) {
		// outputs the content of the widget
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
			add_filter( 'wp_handle_upload_prefilter', 'mm_custom_upload_filter' );

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
			$allowed_file_types = array( 'jpg' =>'image/jpg','jpeg' =>'image/jpeg', 'gif' => 'image/gif', 'png' => 'image/png' );
			$overrides = array( 'test_form' => false, 'mimes' => $allowed_file_types );
			// 'file_upload' is the name of the file input in the form below.
			$attachment_id = media_handle_upload( 'file_upload', $_POST['post_id'], array( 'post_title' => $current_user->display_name, 'post_content' => $current_user->ID ), $overrides );

			// Remove custom filename filter
			remove_filter( 'wp_handle_upload_prefilter', 'custom_upload_filter' );

			if ( is_wp_error( $attachment_id ) ) {
				// There was an error uploading the image.
				$error_string = $attachment_id -> get_error_message();
				echo '<div id="message" class="notice notice-error"><p>' . $error_string . '</p></div>';
				$upload_status = 'error';
				$button_upload_class = "button primary";
			} else {
				// The image was uploaded successfully!
				update_user_meta( get_current_user_id(), 'photo', $attachment_id );
				echo '<div id="message" class="notice notice-success"><p>' . __( 'Your file has been updated', 'ffu' ) . '</p></div>';
				$upload_status = 'success';
				$button_upload_class = "button secondary";
			}

		// Check that the nonce is valid, and the user can edit this post. Then delete the file.
		} else if ( isset( $_POST['file_delete_nonce'], $_POST['post_id'] ) && wp_verify_nonce( $_POST['file_delete_nonce'], 'file_delete' ) && current_user_can( 'read'  ) ) {
			delete_user_meta( get_current_user_id(), 'photo' );
			echo '<div id="message" class="notice notice-success"><p>' . __( 'Your file has been removed', 'ffu' ) . '</p></div>';
		} else {
			// The security check failed, maybe show the user an error.
		} ?>

		<script>
		jQuery(document).ready( function($) {
			$( 'input[name="file_upload"]' ).on( 'change', function() {
				$( 'form[name="file_upload_form"]' ).submit();
				$( 'label[for="file-upload"]' ).addClass( 'uploading' );
			});
			$( '#message' ).delay(5000).fadeOut( 'slow' );
		});
		</script>

		<p><?php echo get_file( get_current_user_id(), 220 ); ?></p>

		<form name="file_upload_form" id="file-upload-form" class="file-upload-form" method="post" action="" enctype="multipart/form-data">
			<p>
				<label for="file-upload">
					<?php if ( get_user_meta( get_current_user_id(), 'photo' ) ) {
						_e( 'Change file', 'ffu' );
					} else {
						_e( 'Upload file', 'ffu' );
					} ?>
				</label>
				<input type="file" name="file_upload" id="file-upload" />
			</p>
			<input type="hidden" name="post_id" value="<?php echo get_the_ID(); ?>" />
			<?php wp_nonce_field( 'file_upload', 'file_upload_nonce' ); ?>
		</form>

		<?php if ( get_user_meta( get_current_user_id(), 'photo' ) ) { ?>
			<form name="file_delete_form" id="file-delete-form" class="file-delete-form" method="post" action="">
				<input type="submit" name="post_id" value="<?php _e( 'Delete file', 'ffu' ); ?>" />
				<?php wp_nonce_field( 'file_delete', 'file_delete_nonce' ); ?>
			</form>
		<?php }
	}

	/**
	 * Outputs the options form on admin
	 *
	 * @param array $instance The widget options
	 */
	public function form( $instance ) {
		// outputs the options form on admin
	}

	/**
	 * Processing widget options on save
	 *
	 * @param array $new_instance The new options
	 * @param array $old_instance The previous options
	 */
	public function update( $new_instance, $old_instance ) {
		// processes widget options to be saved
	}
}
add_action( 'widgets_init', function(){
	register_widget( 'file_widget' );
});
