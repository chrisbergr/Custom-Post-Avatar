<?php
/*
Plugin Name: Custom Post Avatar
Plugin URI:  https://wordpress.org/plugins/custom-post-avatar
Description: This Plugin gives you the possibility to replace your default avatar by a custom image on each post individually.
Version:     0.9.6
Text Domain: custom-post-avatar
Author:      Christian Hockenberger
Author URI:  https://christian.hockenberger.us
License:     GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Copyright 2019 Christian Hockenberger (christian@hockenberger.us)
Custom Post Avatar is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

Custom Post Avatar is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Custom Post Avatar. If not, see http://www.gnu.org/licenses/gpl-2.0.html.
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

require_once 'class-custompostavatar.php';

add_action( 'admin_enqueue_scripts', 'custom_post_avatar_script' );
add_action( 'save_post', 'custom_post_avatar_save', 10, 1 );
add_action( 'add_meta_boxes', 'custom_post_avatar_add_metabox' );
add_filter( 'get_avatar', 'custom_post_avatar', 1, 5 );



function run_custom_post_avatar() {
	return new CustomPostAvatar();
}

$plugin_custom_post_avatar = run_custom_post_avatar();


function custom_post_avatar_script() {
	if ( 'profile' === get_current_screen()->id || 'user-edit' === get_current_screen()->id || 'post' === get_current_screen()->post_type ) {
		wp_enqueue_style( 'custom_post_avatar_style', plugin_dir_url( __FILE__ ) . '/custom-post-avatar.css', null, '0.9.4' );
		wp_enqueue_script( 'custom_post_avatar_script', plugin_dir_url( __FILE__ ) . '/custom-post-avatar.js', array( 'jquery', 'plupload-all' ), '0.9.4', true );
	}
}

/**/

function custom_post_avatar_metabox( $post ) {
	global $plugin_custom_post_avatar;
	$content  = '';
	$image_id = get_post_meta( $post->ID, '_custom_post_avatar_id', true );
	wp_nonce_field( basename( __FILE__ ), '_custom_post_avatar_nonce' );
	?>

	<?php
		echo wp_kses(
			$plugin_custom_post_avatar->get_all_avatars_radio( '_custom_post_avatar', 'url', $image_id ),
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

	<?php
}

function custom_post_avatar_save( $post_id ) {
	if ( ! isset( $_POST['_custom_post_avatar_nonce'] ) || ! wp_verify_nonce( $_POST['_custom_post_avatar_nonce'], basename( __FILE__ ) ) ) {
		return $post_id;
	}

	if ( isset( $_POST['_custom_post_avatar'] ) ) {
		$image_id = sanitize_text_field( wp_unslash( $_POST['_custom_post_avatar'] ) );
		update_post_meta( $post_id, '_custom_post_avatar_id', $image_id );
	}
}

function custom_post_avatar_add_metabox() {
	add_meta_box(
		'custompostavatardiv',
		__( 'Custom Post Avatar', 'custom-post-avatar' ),
		'custom_post_avatar_metabox',
		'post',
		'side',
		'low'
	);
}

/**/

function custom_post_avatar_userid( $id_or_email ) {
	if ( is_numeric( $id_or_email ) ) {
		return (int) $id_or_email;
	}
	if ( is_object( $id_or_email ) ) {
		if ( ! empty( $id_or_email->user_id ) ) {
			return (int) $id_or_email->user_id;
		}
	}
	$user = get_user_by( 'email', $id_or_email );
	return (int) $user->user_id;
}

/**/

function custom_post_avatar( $avatar, $id_or_email, $size, $default, $alt ) {

	if( $id_or_email instanceof WP_Comment ) {
		return $avatar;
	}

	global $plugin_custom_post_avatar;
	global $post;

	$new_avatar = false;

	if ( isset( $post->ID ) ) {
		$avatar_id = trim( get_post_meta( $post->ID, '_custom_post_avatar_id', true ) );
	}

	if ( isset( $avatar_id ) ) {
		if ( filter_var( $avatar_id, FILTER_VALIDATE_URL ) ) {
			$new_avatar = $avatar_id;
		}
		if ( get_post( $avatar_id ) ) {
			$new_avatar = wp_get_attachment_image_url( $avatar_id, 'thumbnail' );
		}
	}

	if ( ! $new_avatar ) {
		if ( $id_or_email instanceof WP_User ) {
			$userid = (int) $id_or_email->user_id;
		} elseif ( $id_or_email instanceof WP_Post ) {
			$userid = (int) $id_or_email->post_author;
		} else {
			$userid = custom_post_avatar_userid( $id_or_email );
		}
		$new_avatar = $plugin_custom_post_avatar->get_user_default_avatar_url( $userid );
	}

	$avatar = preg_replace( '/src=("|\').*?("|\')/i', 'src="' . $new_avatar . '"', $avatar );
	$avatar = preg_replace( '/srcset=("|\').*?("|\')/i', 'srcset="' . $new_avatar . '"', $avatar );
	return $avatar;

}

// phpcs:disable
// Copy from core ( Sometimes the function is not available )
function list_files_copy( $folder = '', $levels = 100, $exclusions = array() ) {
	if ( empty( $folder ) ) {
		return false;
	}
	$folder = trailingslashit( $folder );
	if ( ! $levels ) {
		return false;
	}
	$files = array();
	$dir = @opendir( $folder );
	if ( $dir ) {
		while ( ( $file = readdir( $dir ) ) !== false ) {
			if ( in_array( $file, array( '.', '..' ), true ) ) {
				continue;
			}
			if ( '.' === $file[0] || in_array( $file, $exclusions, true ) ) {
				continue;
			}
			if ( is_dir( $folder . $file ) ) {
				$files2 = list_files( $folder . $file, $levels - 1 );
				if ( $files2 ) {
					$files = array_merge( $files, $files2 );
				} else {
					$files[] = $folder . $file . '/';
				}
			} else {
				$files[] = $folder . $file;
			}
		}
		closedir( $dir );
	}
	return $files;
}
// phpcs:enable
