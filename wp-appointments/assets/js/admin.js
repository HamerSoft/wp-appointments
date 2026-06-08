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

	/* ---------------------------------------------------------------------- */
	/* Blocked slots — recurrence type toggle                                  */
	/* ---------------------------------------------------------------------- */

	var recurrenceSelect = document.getElementById( 'wpappt-recurrence-type' );
	var blockDateInput   = document.getElementById( 'wpappt-block-date' );
	var monthlyLabel     = document.getElementById( 'wpappt-monthly-label' );

	function showRecurrenceRows( type ) {
		var dailyRow   = document.getElementById( 'wpappt-recurrence-daily' );
		var weeklyRow  = document.getElementById( 'wpappt-recurrence-weekly' );
		var monthlyRow = document.getElementById( 'wpappt-recurrence-monthly' );
		var endRow     = document.getElementById( 'wpappt-recurrence-end-row' );
		var endInput   = document.getElementById( 'wpappt-recurrence-end' );

		if ( ! dailyRow ) {
			return; // Not on availability page.
		}

		dailyRow.style.display   = type === 'daily'   ? '' : 'none';
		weeklyRow.style.display  = type === 'weekly'  ? '' : 'none';
		monthlyRow.style.display = type === 'monthly' ? '' : 'none';

		if ( type === 'none' ) {
			endRow.style.display = 'none';
			endInput.removeAttribute( 'required' );
		} else {
			endRow.style.display = '';
			endInput.setAttribute( 'required', 'required' );
		}
	}

	if ( recurrenceSelect ) {
		recurrenceSelect.addEventListener( 'change', function () {
			showRecurrenceRows( this.value );
		} );
	}

	function updateWeekdayAndMonthLabel() {
		if ( ! blockDateInput || ! blockDateInput.value ) {
			return;
		}

		// Parse as local date to avoid UTC-offset day-of-week errors.
		var parts = blockDateInput.value.split( '-' );
		var date  = new Date( parseInt( parts[0], 10 ), parseInt( parts[1], 10 ) - 1, parseInt( parts[2], 10 ) );

		if ( isNaN( date.getTime() ) ) {
			return;
		}

		// Pre-check the matching weekday checkbox.
		var dow = date.getDay(); // 0=Sun … 6=Sat
		document.querySelectorAll( '.wpappt-weekly-day' ).forEach( function ( cb ) {
			cb.checked = parseInt( cb.value, 10 ) === dow;
		} );

		// Update monthly day label.
		if ( monthlyLabel ) {
			monthlyLabel.textContent = 'Day ' + date.getDate() + ' of each month';
		}
	}

	if ( blockDateInput ) {
		blockDateInput.addEventListener( 'change', updateWeekdayAndMonthLabel );
	}

} )();
