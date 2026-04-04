/* WP Appointments — Admin JS (no dependencies) */
( function () {
	'use strict';

	/* ---------------------------------------------------------------------- */
	/* Confirmation dialogs for destructive actions                            */
	/* ---------------------------------------------------------------------- */

	document.addEventListener( 'submit', function ( e ) {
		var form = e.target;
		var message = form.getAttribute( 'data-wpappt-confirm' );
		if ( message && ! window.confirm( message ) ) {
			e.preventDefault();
		}
	} );

	/* ---------------------------------------------------------------------- */
	/* Availability — enable/disable time inputs when checkbox toggled        */
	/* ---------------------------------------------------------------------- */

	document.addEventListener( 'change', function ( e ) {
		if ( ! e.target.classList.contains( 'wpappt-avail-toggle' ) ) {
			return;
		}

		var row     = e.target.closest( '.wpappt-avail-row' );
		var inputs  = row.querySelectorAll( '.wpappt-time-input' );
		var enabled = e.target.checked;

		row.classList.toggle( 'wpappt-avail-row--disabled', ! enabled );

		inputs.forEach( function ( input ) {
			input.disabled = ! enabled;
		} );
	} );

	/* ---------------------------------------------------------------------- */
	/* Follow-up email — character counter                                     */
	/* ---------------------------------------------------------------------- */

	var followupTextarea  = document.getElementById( 'wpappt-followup-msg' );
	var remainingDisplay  = document.getElementById( 'wpappt-followup-remaining' );

	if ( followupTextarea && remainingDisplay ) {
		var maxLength = parseInt( followupTextarea.getAttribute( 'maxlength' ), 10 );

		followupTextarea.addEventListener( 'input', function () {
			var remaining = maxLength - followupTextarea.value.length;
			remainingDisplay.textContent = remaining;
			remainingDisplay.style.color = remaining < 100 ? '#b32d2e' : '';
		} );
	}

} )();
