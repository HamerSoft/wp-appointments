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
	/* Availability — enable/disable time selects when checkbox toggled       */
	/* ---------------------------------------------------------------------- */

	document.addEventListener( 'change', function ( e ) {
		if ( ! e.target.classList.contains( 'wpappt-avail-toggle' ) ) {
			return;
		}

		var row     = e.target.closest( '.wpappt-avail-row' );
		var selects = row.querySelectorAll( '.wpappt-time-sel' );
		var enabled = e.target.checked;

		row.classList.toggle( 'wpappt-avail-row--disabled', ! enabled );

		selects.forEach( function ( sel ) {
			sel.disabled = ! enabled;
		} );
	} );

	/* ---------------------------------------------------------------------- */
	/* 24-hour time pickers — sync selects → hidden input                     */
	/* ---------------------------------------------------------------------- */

	document.addEventListener( 'change', function ( e ) {
		if ( ! e.target.classList.contains( 'wpappt-time-sel' ) ) {
			return;
		}

		var picker = e.target.closest( '.wpappt-time-picker' );
		if ( ! picker ) {
			return;
		}

		var hourSel = picker.querySelector( '[data-time-part="hour"]' );
		var minSel  = picker.querySelector( '[data-time-part="minute"]' );
		var hidden  = picker.querySelector( '.wpappt-time-value' );

		if ( hourSel && minSel && hidden ) {
			hidden.value = hourSel.value + ':' + minSel.value;
		}
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
