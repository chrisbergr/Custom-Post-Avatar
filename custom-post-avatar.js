jQuery(document).ready(
	function ( $ ) {

		//console.log( 'Hello Avatar :)' );

		jQuery(
			function() {
				jQuery( 'label img' ).on(
					'click',
					function() {
						jQuery( this ).parents( 'label' ).children( 'input' ).click();
					}
				);
			}
		);

		if( window.wpcpa_uploader_options && jQuery( '.custom-post-avatar-uploader' ).length > 0 ) {

			var options = false;
			var container = jQuery( '.custom-post-avatar-uploader' );

			options = JSON.parse( JSON.stringify( window.wpcpa_uploader_options ) );
			options['multipart_params']['_ajax_nonce'] = container.find( '.ajaxnonce' ).attr( 'id' );
			options['resize'] = {
				'width': 150,
				'height': 150,
				'quality': 100,
				'crop': true
			};
			options['filters'] = {
				'max_file_size' : '4mb',
				'mime_types': [
					{
						'title' : "Image files",
						'extensions' : "jpeg,jpg,gif,png"
					}
				],
				'prevent_duplicates': true
			};
			if( container.hasClass( 'multiple' ) ) {
				options['multi_selection'] = true;
			}

			var uploader = new plupload.Uploader( options );
			uploader.init();

			uploader.bind(
				'Init',
				function( up ) {
					//console.log( 'Init', up );
				}
			);

			uploader.bind(
				'FilesAdded',
				function( up, files ) {
					jQuery.each(
						files,
						function( i, file ) {
							//console.log( 'File Added', i, file );
						}
					);
					up.refresh();
					up.start();
				}
			);

			uploader.bind(
				'UploadProgress',
				function( up, file ) {
					//console.log( 'Progress', up, file );
					jQuery( '#custom-post-avatar-uploader' ).addClass( 'progress' );
				}
			);

			uploader.bind(
				'FileUploaded',
				function( up, file, response ) {
					response = jQuery.parseJSON( response.response );
					jQuery( '#custom-post-avatar-uploader' ).removeClass( 'progress' );
					if( response['status'] == 'success' ) {

						//console.log( 'Success', up, file, response );
						//var file_url = window.wpcpa_user_path_url + file['name'];
						var file_url = response['attachment']['src'];

						var $label = jQuery('<label>');

						var $radio = jQuery('<input type="radio" />');
						$radio.attr( 'value', file_url.split(/[\\/]/).pop() );
						$radio.attr( 'name', 'custom_post_avatar_default' );
						$radio.appendTo( $label );

						var $img = jQuery( '<img>' );
						$img.attr( 'src', file_url );
						$img.appendTo( $label );

						$label.appendTo( '#custom-post-avatar-list' );

					} else {
						console.log( 'Error', up, file, response );
					}
				}
			);
		}

	}
);
