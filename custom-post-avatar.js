jQuery(document).ready(function ($) {

	var file_frame;

	jQuery.fn.upload_custom_post_avatar = function (button) {
		var button_id = button.attr('id');
		var field_id = button_id.replace('_button', '');
		if (file_frame) {
			file_frame.open();
			return;
		}
		file_frame = wp.media.frames.file_frame = wp.media({
			title: jQuery(this).data('uploader_title'),
			button: {
				text: jQuery(this).data('uploader_button_text'),
			},
			multiple: false
		});
		file_frame.on('select', function () {
			var attachment = file_frame.state().get('selection').first().toJSON();
			jQuery('#' + field_id).val(attachment.id);
			jQuery('#custompostavatardiv img').attr('src', attachment.url);
			jQuery('#custompostavatardiv img').show();
			jQuery('#' + button_id).attr('id', 'remove_custom_post_avatar_button');
			jQuery('#remove_custom_post_avatar_button').text('Remove custom post avatar');
		});
		file_frame.open();
	};

	jQuery('#custompostavatardiv').on('click', '#upload_custom_post_avatar_button', function (event) {
		event.preventDefault();
		jQuery.fn.upload_custom_post_avatar(jQuery(this));
	});

	jQuery('#custompostavatardiv').on('click', '#remove_custom_post_avatar_button', function (event) {
		event.preventDefault();
		jQuery('#upload_custom_post_avatar').val('');
		jQuery('#custompostavatardiv img').attr('src', '');
		jQuery('#custompostavatardiv img').hide();
		jQuery(this).attr('id', 'upload_custom_post_avatar_button');
		jQuery('#upload_custom_post_avatar_button').text('Set custom post avatar');
	});

});
