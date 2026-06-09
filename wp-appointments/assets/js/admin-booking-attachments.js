( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {

		document.querySelectorAll( '.wpappt-attach-btn' ).forEach( function ( btn ) {
			var frame;

			btn.addEventListener( 'click', function ( e ) {
				e.preventDefault();

				var container = btn.closest( '.wpappt-attachment-picker' );
				var input     = container.querySelector( '.wpappt-attachment-id' );
				var label     = container.querySelector( '.wpappt-attachment-name' );
				var clear     = container.querySelector( '.wpappt-attachment-clear' );

				if ( frame ) {
					frame.open();
					return;
				}

				frame = wp.media( {
					title:    'Select file to attach',
					button:   { text: 'Attach' },
					multiple: false,
				} );

				frame.on( 'select', function () {
					var attachment    = frame.state().get( 'selection' ).first().toJSON();
					input.value       = attachment.id;
					label.textContent = attachment.filename;
					label.style.display = 'inline';
					clear.style.display = 'inline';
				} );

				frame.open();
			} );
		} );

		document.querySelectorAll( '.wpappt-attachment-clear' ).forEach( function ( clearBtn ) {
			clearBtn.addEventListener( 'click', function ( e ) {
				e.preventDefault();

				var container = clearBtn.closest( '.wpappt-attachment-picker' );
				var input     = container.querySelector( '.wpappt-attachment-id' );
				var label     = container.querySelector( '.wpappt-attachment-name' );

				input.value            = '';
				label.textContent      = '';
				label.style.display    = 'none';
				clearBtn.style.display = 'none';
			} );
		} );

	} );

}() );
