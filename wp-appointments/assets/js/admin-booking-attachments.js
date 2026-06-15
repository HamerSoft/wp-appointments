( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {

		document.querySelectorAll( '.wpappt-attach-btn' ).forEach( function ( btn ) {
			var frame;

			btn.addEventListener( 'click', function ( e ) {
				e.preventDefault();

				var container  = btn.closest( '.wpappt-attachment-picker' );
				var input      = container.querySelector( '.wpappt-attachment-id' );
				var label      = container.querySelector( '.wpappt-attachment-name' );
				var clear      = container.querySelector( '.wpappt-attachment-clear' );
				var form       = btn.closest( 'form' );
				var submitBtn  = form ? form.querySelector( '[type="submit"]' ) : null;

				function lockSubmit() {
					if ( submitBtn ) {
						submitBtn.disabled = true;
					}
				}

				function unlockSubmit() {
					if ( submitBtn ) {
						submitBtn.disabled = false;
					}
				}

				if ( frame ) {
					lockSubmit();
					frame.open();
					return;
				}

				frame = wp.media( {
					title:    'Select file to attach',
					button:   { text: 'Attach' },
					multiple: false,
				} );

				frame.on( 'open', lockSubmit );

				frame.on( 'select', function () {
					var attachment    = frame.state().get( 'selection' ).first().toJSON();
					input.value       = attachment.id;
					label.textContent = attachment.filename;
					label.style.display = 'inline';
					clear.style.display = 'inline';
					unlockSubmit();
				} );

				frame.on( 'close', unlockSubmit );

				lockSubmit();
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
