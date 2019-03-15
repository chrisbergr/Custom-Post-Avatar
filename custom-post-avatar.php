<?php
/*
Plugin Name: Custom Post Avatar
Plugin URI:  https://github.com/chrisbergr/Custom-Post-Avatar
Description: This Plugin gives you the possibility to replace your default avatar by a custom image on each post individually.
Version:     0.9.1
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

add_action( 'admin_enqueue_scripts', 'custom_post_avatar_script' );
add_action( 'save_post', 'custom_post_avatar_save', 10, 1 );
add_action( 'add_meta_boxes', 'custom_post_avatar_add_metabox' );
add_filter( 'get_avatar', 'custom_post_avatar', 1, 5 );

function custom_post_avatar_script() {
	wp_enqueue_script( 'custom_post_avatar_script', plugin_dir_url( __FILE__ ) . '/custom-post-avatar.js' );
}

function custom_post_avatar_metabox ( $post ) {
	$image_id = get_post_meta( $post->ID, '_custom_post_avatar_id', true );
	if ( $image_id && get_post( $image_id ) ) {
		$avatar_html = wp_get_attachment_image( $image_id, 'thumbnail' );
		if ( ! empty( $avatar_html ) ) {
			$content = $avatar_html;
			$content .= '<p class="hide-if-no-js"><a href="javascript:;" id="remove_custom_post_avatar_button" >' . esc_html__( 'Remove custom post avatar', 'custom-post-avatar' ) . '</a></p>';
			$content .= '<input type="hidden" id="upload_custom_post_avatar" name="_custom_post_avatar" value="' . esc_attr( $image_id ) . '" />';
		}
	} else {
		$content = '<img src="" style="width:auto;height:auto;border:0;display:none;" />';
		$content .= '<p class="hide-if-no-js"><a title="' . esc_attr__( 'Set custom post avatar', 'custom-post-avatar' ) . '" href="javascript:;" id="upload_custom_post_avatar_button" id="set-custom-post-avatar" data-uploader_title="' . esc_attr__( 'Choose an image', 'custom-post-avatar' ) . '" data-uploader_button_text="' . esc_attr__( 'Set custom post avatar', 'custom-post-avatar' ) . '">' . esc_html__( 'Set custom post avatar', 'custom-post-avatar' ) . '</a></p>';
		$content .= '<input type="hidden" id="upload_custom_post_avatar" name="_custom_post_avatar" value="" />';
	}
	echo $content;
}

function custom_post_avatar_save ( $post_id ) {
	if( isset( $_POST['_custom_post_avatar'] ) ) {
		$image_id = (int) $_POST['_custom_post_avatar'];
		update_post_meta( $post_id, '_custom_post_avatar_id', $image_id );
	}
}

function custom_post_avatar_add_metabox () {
	add_meta_box(
		'custompostavatardiv',
		__( 'Custom Post Avatar', 'custom-post-avatar' ),
		'custom_post_avatar_metabox',
		'post',
		'side',
		'low'
	);
}

function custom_post_avatar( $avatar, $id_or_email, $size, $default, $alt ) {
	global $post;
	if ( ! isset( $post->post_author ) ) {
		return $avatar;
	}
	$avatar_id = get_post_meta( $post->ID, '_custom_post_avatar_id', true );
	if ( ! $avatar_id ) {
		return $avatar;
	}
	$user_email = false;
	if ( is_numeric( $id_or_email ) ) {
		$user_email = get_the_author_meta( 'user_email', absint( $id_or_email ) );
	} elseif ( is_string( $id_or_email ) ) {
		if ( strpos( $id_or_email, '@md5.gravatar.com' ) ) {
			return $avatar;
		} else {
			$user_email = $id_or_email;
		}
	} elseif ( $id_or_email instanceof WP_User ) {
		$user_email = $id_or_email->user_email;
	} elseif ( $id_or_email instanceof WP_Post ) {
		$user_email = get_the_author_meta( 'user_email', get_user_by( 'id', (int) $id_or_email->post_author ) );
	} elseif ( $id_or_email instanceof WP_Comment ) {
		return $avatar;
	}
	if ( ! $user_email || $user_email !== get_the_author_meta( 'user_email', $post->post_author ) ) {
		return $avatar;
	}
	$new_avatar = wp_get_attachment_image_url( $avatar_id, 'thumbnail' );
	$avatar = preg_replace( '/src=("|\').*?("|\')/i', 'src="' . $new_avatar . '"', $avatar );
	$avatar = preg_replace( '/srcset=("|\').*?("|\')/i', 'srcset="' . $new_avatar . '"', $avatar );
	return $avatar;
}
