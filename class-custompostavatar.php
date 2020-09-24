<?php
/**
 * @package custom-post-avatar
 */

class CustomPostAvatar {

	protected $custom_post_avatar_user_id = false;
	protected $user_path                  = false;
	protected $user_path_url              = false;
	protected $screen                     = false;

	public function __construct() {
		$this->hooks();
	}

	private function hooks() {
		add_action( 'admin_head', array( $this, 'admin_init_later' ) );
		add_action( 'wp_ajax_custom-post-avatar-upload-action', array( $this, 'upload_ajax' ) );
		add_action( 'show_user_profile', array( $this, 'profile_fields' ), 10, 1 );
		add_action( 'edit_user_profile', array( $this, 'profile_fields' ), 10, 1 );
		add_action( 'personal_options_update', array( $this, 'save_custom_post_avatar_profile_fields' ), 10, 1 );
		add_action( 'edit_user_profile_update', array( $this, 'save_custom_post_avatar_profile_fields' ), 10, 1 );
		/* --- */
		add_filter( 'posts_where', array( $this, 'exclude_from_media_library' ) );
	}

	public function admin_init_later() {
		$screen       = get_current_screen();
		$this->screen = $screen->id;
		if ( 'user-edit' === $this->screen ) {
			if ( isset( $_GET['user_id'] ) ) {
				$this->custom_post_avatar_user_id = (int) $_GET['user_id'];
			}
		} else {
			$this->custom_post_avatar_user_id = get_current_user_id();
		}
		$this->user_path     = $this->get_user_path( $this->custom_post_avatar_user_id );
		$this->user_path_url = $this->get_user_path_url( $this->custom_post_avatar_user_id );
		if ( 'profile' === $this->screen ) {
			$this->admin_head();
		}
	}

	public function get_user_dir( $id ) {
		if ( ! $id || ! get_userdata( $id ) ) {
			return false;
		}
		$plugin_path = '/avatars';
		$user_hash   = md5( get_userdata( $id )->user_email );
		$user_dir    = $plugin_path . '/' . $user_hash;
		return $user_dir;
	}

	private function get_user_path( $id ) {
		$user_dir = $this->get_user_dir( $id );
		if ( ! $user_dir ) {
			return false;
		}
		$user_path = wp_get_upload_dir()['basedir'] . $user_dir;
		if ( ! file_exists( $user_path ) ) {
			wp_mkdir_p( $user_path );
		}
		return $user_path;
	}

	private function get_user_path_url( $id ) {
		$user_path = $this->get_user_path( $id );
		if ( ! $user_path ) {
			return false;
		}
		$user_path_url = str_replace( wp_get_upload_dir()['basedir'], wp_get_upload_dir()['baseurl'], $user_path );
		return $user_path_url;
	}

	public function upload_dir_filter( $upload_dir = array() ) {
		$curr_user_id = get_current_user_id();
		$user_dir     = $this->get_user_dir( $curr_user_id );
		if ( ! $user_dir ) {
			return $upload_dir;
		}
		return apply_filters(
			'custom_post_avatar_upload_dir',
			array(
				'path'    => $upload_dir['basedir'] . $user_dir,
				'url'     => $upload_dir['baseurl'] . $user_dir,
				'subdir'  => $user_dir,
				'basedir' => $upload_dir['basedir'] . $user_dir,
				'baseurl' => $upload_dir['baseurl'] . $user_dir,
				'error'   => false,
			),
			$upload_dir
		);
	}

	public function upload_filename_filter( $filename, $filename_raw ) {
		$info         = pathinfo( $filename );
		$extension    = empty( $info['extension'] ) ? '' : '.' . $info['extension'];
		$new_filename = time() . $extension;
		if ( $new_filename !== $filename_raw ) {
			$new_filename = sanitize_file_name( $new_filename );
		}
		return $new_filename;
	}

	public function admin_head() {
		$uploader_options = array(
			'runtimes'            => 'html5,silverlight,flash,html4',
			'browse_button'       => 'custom-post-avatar-uploader-button',
			'container'           => 'custom-post-avatar-uploader',
			'drop_element'        => 'custom-post-avatar-uploader-inner',
			'file_data_name'      => 'async-upload',
			'multiple_queues'     => true,
			'max_file_size'       => wp_max_upload_size() . 'b',
			'url'                 => admin_url( 'admin-ajax.php' ),
			'flash_swf_url'       => includes_url( 'js/plupload/plupload.flash.swf' ),
			'silverlight_xap_url' => includes_url( 'js/plupload/plupload.silverlight.xap' ),
			'filters'             => array(
				array(
					'title'      => __( 'Allowed Files', 'custom-post-avatar' ),
					'extensions' => '*',
				),
			),
			'multipart'           => true,
			'urlstream_upload'    => true,
			'multi_selection'     => true,
			'multipart_params'    => array(
				'_ajax_nonce' => wp_create_nonce( __FILE__ ),
				'action'      => 'custom-post-avatar-upload-action',
			),
		);
		?>
		<script type="text/javascript">
			window.wpcpa_uploader_options = <?php echo wp_json_encode( $uploader_options ); ?>;
			window.wpcpa_user_path_url = '<?php echo esc_url_raw( $this->user_path_url ); ?>/';
		</script>
		<?php
	}

	public function upload_ajax() {
		check_ajax_referer( __FILE__ );
		if ( current_user_can( 'upload_files' ) ) {
			$upload_dir_filter = array( $this, 'upload_dir_filter' );
			add_filter( 'upload_dir', $upload_dir_filter, 10 );
			$upload_filename_filter = array( $this, 'upload_filename_filter' );
			add_filter( 'sanitize_file_name', $upload_filename_filter, 10, 2 );
			$response = array();
			$id       = media_handle_upload(
				'async-upload',
				0,
				array(
					'test_form' => true,
					'action'    => 'custom-post-avatar-upload-action',
				)
			);
			if ( is_wp_error( $id ) ) {
				$response['status'] = 'error';
				$response['error']  = $id->get_error_messages();
			} else {
				$src                           = esc_url( get_post( $id )->guid );
				$response['status']            = 'success';
				$response['attachment']        = array();
				$response['attachment']['id']  = $id;
				$response['attachment']['src'] = $src;
			}
			remove_filter( 'upload_dir', $upload_dir_filter, 10 );
			remove_filter( 'sanitize_file_name', $upload_filename_filter, 10, 2 );
		}
		echo wp_json_encode( $response );
		exit;
	}

	public function profile_fields( $user ) {
		wp_nonce_field( basename( __FILE__ ), '_custom_post_avatar_nonce' );
		?>
		<table class="form-table">
			<tr>
				<th>
					<label for="user_custom_post_avatars"><?php esc_html_e( 'Custom Post Avatar', 'custom-post-avatar' ); ?></label>
				</th>
				<td>
					<div id="custom-post-avatar-uploader" class="custom-post-avatar-uploader multiple">
						<div id="custom-post-avatar-uploader-inner" class="custom-post-avatar-uploader-inner">
							<div id="custom-post-avatar-list" class="custom-post-avatar-list">
								<?php
									echo wp_kses(
										$this->get_all_avatars_radio(),
										array(
											'input' => array(
												'type'    => array(),
												'name'    => array(),
												'value'   => array(),
												'class'   => array(),
												'style'   => array(),
												'checked' => array(),
											),
											'label' => array(),
											'img'   => array(
												'src'   => array(),
												'class' => array(),
												'alt'   => array(),
											),
										)
									);
								?>
							</div>
							<?php if ( 'profile' === get_current_screen()->id ) : ?>
							<input id="custom-post-avatar-uploader-button" type="button" value="<?php esc_attr_e( 'Select Files', 'custom-post-avatar' ); ?>" class="custom-post-avatar-uploader-button button browser button-hero">
							<span class="ajaxnonce" id="<?php echo esc_attr( wp_create_nonce( __FILE__ ) ); ?>"></span>
							<?php endif; ?>
						</div>
					</div>
					<br><span class="description"><?php esc_html_e( 'Upload new avatars and select your default (blue border).', 'custom-post-avatar' ); ?></span>
				</td>
			</tr>
		</table>
		<?php
	}

	public function get_all_avatars() {
		if ( function_exists( 'list_files' ) ) {
			$files = list_files( $this->user_path );
		} else {
			$files = list_files_copy( $this->user_path );
		}
		$count_files = count( $files );
		for ( $i = 0; $i < $count_files; ++$i ) {
			$files[ $i ] = str_replace( wp_get_upload_dir()['basedir'], wp_get_upload_dir()['baseurl'], $files[ $i ] );
		}
		return $files;
	}

	public function get_all_avatars_images() {
		$files       = $this->get_all_avatars();
		$default     = $this->get_user_default_avatar_filename( $this->custom_post_avatar_user_id );
		$return      = '';
		$count_files = count( $files );
		for ( $i = 0; $i < $count_files; ++$i ) {
			$class = '';
			$guid  = basename( $files[ $i ] );
			if ( $default === $guid ) {
				$class = ' default';
			}
			$return .= '<img src="' . $files[ $i ] . '" title="' . $guid . '" class="' . $class . '">';
		}
		return $return;
	}

	public function get_all_avatars_radio( $name = 'custom_post_avatar_default', $guid = 'filename', $default = true ) {
		$files       = $this->get_all_avatars();
		$return      = '';
		$value       = '';
		$count_files = count( $files );
		if ( true === $default ) {
			$default = $this->get_user_default_avatar_filename( $this->custom_post_avatar_user_id );
		}
		for ( $i = 0; $i < $count_files; ++$i ) {
			$class   = '';
			$checked = '';
			if ( 'filename' === $guid ) {
				$value = basename( $files[ $i ] );
			}
			if ( 'url' === $guid ) {
				$value = $files[ $i ];
			}
			if ( $default === $value ) {
				$class   = ' default';
				$checked = 'checked="checked"';
			}
			//$return .= '<img src="' . $files[ $i ] . '" title="' . $guid . '" class="' . $class . '">';
			$return .= '<label>';
			$return .= '<input type="radio" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" ' . $checked . ' class="' . esc_attr( $class ) . '">';
			$return .= '<img src="' . esc_attr( $files[ $i ] ) . '">';
			$return .= '</label>';
		}
		return $return;
	}

	public function get_default_avatar_url() {
		$default = plugin_dir_url( __FILE__ ) . 'default.png';
		return $default;
	}

	public function get_user_default_avatar_url( $id ) {
		$user_path       = $this->get_user_path( $id );
		$user_path_url   = $this->get_user_path_url( $id );
		$default_by_meta = get_user_meta( $id, 'custom_post_avatar_default', true );
		if ( isset( $default_by_meta ) && '' !== $default_by_meta ) {
			$default_by_meta = $user_path . '/' . $default_by_meta;
			if ( file_exists( $default_by_meta ) ) {
				$default_by_meta = str_replace( $user_path, $user_path_url, $default_by_meta );
				return $default_by_meta;
			}
		}
		if ( function_exists( 'list_files' ) ) {
			$list = list_files( $user_path );
		} else {
			$list = list_files_copy( $user_path );
		}
		if ( isset( $list ) && 1 <= count( $list ) ) {
			$default_by_list = $list[0];
			if ( file_exists( $default_by_list ) ) {
				$default_by_list = str_replace( $user_path, $user_path_url, $default_by_list );
				return $default_by_list;
			}
		}
		return $this->get_default_avatar_url();
	}

	public function get_user_default_avatar_filename( $id ) {
		$src = $this->get_user_default_avatar_url( $id );
		return basename( $src );
	}

	public function get_user_default_avatar( $id ) {
		$src = $this->get_user_default_avatar_url( $id );
		return '<img src="' . $src . '">';
	}

	public function save_custom_post_avatar_profile_fields( $user_id ) {
		if ( ! isset( $_POST['_custom_post_avatar_nonce'] ) || ! wp_verify_nonce( $_POST['_custom_post_avatar_nonce'], basename( __FILE__ ) ) ) {
			return $user_id;
		}
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return false;
		}

		if ( isset( $_POST['custom_post_avatar_default'] ) ) {
			update_user_meta( $user_id, 'custom_post_avatar_default', sanitize_text_field( wp_unslash( $_POST['custom_post_avatar_default'] ) ) );
		} else {
			delete_user_meta( $user_id, 'custom_post_avatar_default' );
		}

	}

	public function exclude_from_media_library( $where ) {
		if ( isset( $_POST['action'] ) && ( 'query-attachments' === $_POST['action'] ) ) {
			$where .= ' AND guid NOT LIKE "%wp-content/uploads/avatars%"';
		}
		return $where;
	}

}
