/* global WPAppt */
(function () {
	'use strict';

	/* =========================================================================
	 * Config (injected via wp_localize_script as window.WPAppt)
	 * ========================================================================= */
	var cfg = window.WPAppt || {};

	/* =========================================================================
	 * State
	 * ========================================================================= */
	var state = {
		step:     1,
		services: [],
		service:  null,   // { id, name, duration_mins, price }
		date:     null,   // 'YYYY-MM-DD'
		slot:     null,   // { start_time: 'H:i', end_time: 'H:i' }
		slots:    [],
		calYear:  0,
		calMonth: 0,      // 0-indexed
		form: {
			name:         '',
			email:        '',
			phone:        '',
			injury_notes: '',
			comments:     '',
		},
	};

	var container;

	/* =========================================================================
	 * Boot
	 * ========================================================================= */
	function init() {
		container = document.getElementById( 'wpappt-booking-widget' );
		if ( ! container ) return;

		var now = new Date();
		state.calYear  = now.getFullYear();
		state.calMonth = now.getMonth();

		renderFrame();
		fetchServices();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	/* =========================================================================
	 * API helpers
	 * ========================================================================= */
	function apiFetch( path, options ) {
		var url      = cfg.apiUrl + path;
		var headers  = { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce };
		var settings = Object.assign( { headers: headers }, options || {} );

		return fetch( url, settings ).then( function ( res ) {
			return res.json().then( function ( data ) {
				if ( ! res.ok ) throw data;
				return data;
			} );
		} );
	}

	function fetchServices() {
		showLoading( 'step-1-content' );
		apiFetch( 'services' )
			.then( function ( services ) {
				state.services = services;
				renderStep1();
			} )
			.catch( function () {
				showError( 'step-1-content', 'Could not load services. Please refresh the page and try again.' );
			} );
	}

	function fetchSlots( date ) {
		showLoading( 'step-3-content' );
		state.slot = null;
		apiFetch( 'availability?service_id=' + state.service.id + '&date=' + date )
			.then( function ( slots ) {
				state.slots = slots;
				renderStep3();
			} )
			.catch( function () {
				showError( 'step-3-content', 'Could not load available times. Please try again.' );
			} );
	}

	/* =========================================================================
	 * Frame
	 * ========================================================================= */
	function renderFrame() {
		container.innerHTML =
			'<div class="wpappt-widget">' +
				'<ol class="wpappt-progress" aria-label="Booking steps">' +
					progressItem( 1, 'Service' ) +
					progressItem( 2, 'Date' ) +
					progressItem( 3, 'Time' ) +
					progressItem( 4, 'Details' ) +
					progressItem( 5, 'Confirm' ) +
				'</ol>' +
				'<div class="wpappt-body">' +
					'<div id="step-1-content"></div>' +
					'<div id="step-2-content" hidden></div>' +
					'<div id="step-3-content" hidden></div>' +
					'<div id="step-4-content" hidden></div>' +
					'<div id="step-5-content" hidden></div>' +
					'<div id="step-6-content" hidden></div>' +
				'</div>' +
			'</div>';
	}

	function progressItem( n, label ) {
		return '<li class="wpappt-progress__item" data-step="' + n + '">' +
			'<span class="wpappt-progress__num">' + n + '</span>' +
			'<span class="wpappt-progress__label">' + label + '</span>' +
			'</li>';
	}

	function setStep( n ) {
		state.step = n;

		for ( var i = 1; i <= 6; i++ ) {
			var el = document.getElementById( 'step-' + i + '-content' );
			if ( el ) el.hidden = ( i !== n );
		}

		container.querySelectorAll( '.wpappt-progress__item' ).forEach( function ( item ) {
			var s = parseInt( item.getAttribute( 'data-step' ), 10 );
			item.classList.toggle( 'is-done',   s < n );
			item.classList.toggle( 'is-active', s === n );
			if ( s === n ) {
				item.setAttribute( 'aria-current', 'step' );
			} else {
				item.removeAttribute( 'aria-current' );
			}
		} );

		container.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
	}

	/* =========================================================================
	 * Step 1 — Service selection
	 * ========================================================================= */
	function renderStep1() {
		var el  = document.getElementById( 'step-1-content' );
		var html = '<h2 class="wpappt-step-title">Choose a service</h2>';

		if ( ! state.services.length ) {
			el.innerHTML = html + '<p class="wpappt-notice">No services are currently available.</p>';
			setStep( 1 );
			return;
		}

		html += '<ul class="wpappt-service-list">';
		state.services.forEach( function ( svc ) {
			var sel = state.service && state.service.id === svc.id ? ' is-selected' : '';
			html += '<li class="wpappt-service-card' + sel + '" data-id="' + svc.id + '" role="button" tabindex="0">' +
				'<span class="wpappt-service-name">' + esc( svc.name ) + '</span>' +
				'<span class="wpappt-service-meta">' + esc( formatDuration( svc.duration_mins ) ) +
				' &nbsp;&middot;&nbsp; &euro;' + Number( svc.price ).toFixed( 2 ) + '</span>' +
				'</li>';
		} );
		html += '</ul>';
		html += '<div class="wpappt-nav wpappt-nav--right">' +
			'<button class="wpappt-btn wpappt-btn--primary" id="step1-next"' + ( state.service ? '' : ' disabled' ) + '>Next &rarr;</button>' +
			'</div>';

		el.innerHTML = html;

		el.querySelectorAll( '.wpappt-service-card' ).forEach( function ( card ) {
			card.addEventListener( 'click', onServiceCardClick );
			card.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Enter' || e.key === ' ' ) { e.preventDefault(); card.click(); }
			} );
		} );

		document.getElementById( 'step1-next' ).addEventListener( 'click', function () {
			if ( ! state.service ) return;
			setStep( 2 );
			renderStep2();
		} );

		setStep( 1 );
	}

	function onServiceCardClick( e ) {
		var card = e.currentTarget;
		var el   = document.getElementById( 'step-1-content' );
		el.querySelectorAll( '.wpappt-service-card' ).forEach( function ( c ) {
			c.classList.remove( 'is-selected' );
		} );
		card.classList.add( 'is-selected' );
		var id = parseInt( card.getAttribute( 'data-id' ), 10 );
		state.service = state.services.find( function ( s ) { return s.id === id; } );
		document.getElementById( 'step1-next' ).disabled = false;
	}

	/* =========================================================================
	 * Step 2 — Date picker
	 * ========================================================================= */
	function renderStep2() {
		var el = document.getElementById( 'step-2-content' );
		el.innerHTML =
			'<h2 class="wpappt-step-title">Choose a date</h2>' +
			'<div id="wpappt-calendar"></div>' +
			'<div class="wpappt-nav">' +
				'<button class="wpappt-btn wpappt-btn--ghost" id="step2-back">&larr; Back</button>' +
				'<button class="wpappt-btn wpappt-btn--primary" id="step2-next"' + ( state.date ? '' : ' disabled' ) + '>Next &rarr;</button>' +
			'</div>';

		renderCalendar();

		document.getElementById( 'step2-back' ).addEventListener( 'click', function () {
			setStep( 1 );
		} );

		document.getElementById( 'step2-next' ).addEventListener( 'click', function () {
			if ( ! state.date ) return;
			setStep( 3 );
			fetchSlots( state.date );
		} );

		setStep( 2 );
	}

	function renderCalendar() {
		var calEl = document.getElementById( 'wpappt-calendar' );
		if ( ! calEl ) return;

		var year  = state.calYear;
		var month = state.calMonth;
		var today = new Date();
		today.setHours( 0, 0, 0, 0 );

		var firstDay   = new Date( year, month, 1 );
		var lastDay    = new Date( year, month + 1, 0 );
		var monthNames = [ 'January', 'February', 'March', 'April', 'May', 'June',
		                   'July', 'August', 'September', 'October', 'November', 'December' ];
		var dayNames   = [ 'Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa' ];

		var isPrevDisabled = ( year === today.getFullYear() && month <= today.getMonth() );

		var html = '<div class="wpappt-cal" role="group" aria-label="Date picker">';

		html += '<div class="wpappt-cal__header">' +
			'<button class="wpappt-cal__nav" id="cal-prev" aria-label="Previous month"' + ( isPrevDisabled ? ' disabled' : '' ) + '>&#8249;</button>' +
			'<span class="wpappt-cal__title" aria-live="polite">' + monthNames[ month ] + ' ' + year + '</span>' +
			'<button class="wpappt-cal__nav" id="cal-next" aria-label="Next month">&#8250;</button>' +
			'</div>';

		html += '<div class="wpappt-cal__grid wpappt-cal__grid--head" aria-hidden="true">';
		dayNames.forEach( function ( d ) { html += '<div class="wpappt-cal__day-name">' + d + '</div>'; } );
		html += '</div>';

		html += '<div class="wpappt-cal__grid wpappt-cal__grid--days">';

		for ( var empty = 0; empty < firstDay.getDay(); empty++ ) {
			html += '<div class="wpappt-cal__cell wpappt-cal__cell--empty" aria-hidden="true"></div>';
		}

		for ( var d = 1; d <= lastDay.getDate(); d++ ) {
			var cellDate = new Date( year, month, d );
			var dateStr  = formatDate( cellDate );
			var isPast   = cellDate < today;
			var isSel    = state.date === dateStr;
			var cls      = 'wpappt-cal__cell' + ( isPast ? ' is-past' : ' is-available' ) + ( isSel ? ' is-selected' : '' );
			html += '<div class="' + cls + '"' +
				( ! isPast ? ' data-date="' + dateStr + '" role="button" tabindex="0" aria-label="' + formatDisplayDate( dateStr ) + ( isSel ? ', selected' : '' ) + '"' : ' aria-hidden="true"' ) +
				'>' + d + '</div>';
		}

		html += '</div></div>';
		calEl.innerHTML = html;

		document.getElementById( 'cal-prev' ).addEventListener( 'click', function () {
			if ( isPrevDisabled ) return;
			state.calMonth === 0 ? ( state.calYear--, state.calMonth = 11 ) : state.calMonth--;
			renderCalendar();
		} );

		document.getElementById( 'cal-next' ).addEventListener( 'click', function () {
			state.calMonth === 11 ? ( state.calYear++, state.calMonth = 0 ) : state.calMonth++;
			renderCalendar();
		} );

		calEl.querySelectorAll( '.wpappt-cal__cell.is-available' ).forEach( function ( cell ) {
			cell.addEventListener( 'click', function () {
				state.date = cell.getAttribute( 'data-date' );
				var nextBtn = document.getElementById( 'step2-next' );
				if ( nextBtn ) nextBtn.disabled = false;
				renderCalendar();
			} );
			cell.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Enter' || e.key === ' ' ) { e.preventDefault(); cell.click(); }
			} );
		} );
	}

	/* =========================================================================
	 * Step 3 — Time slot selection
	 * ========================================================================= */
	function renderStep3() {
		var el       = document.getElementById( 'step-3-content' );
		var hasSlots = state.slots.length > 0;

		var slotsHtml = '';
		if ( hasSlots ) {
			slotsHtml = '<ul class="wpappt-slot-list">';
			state.slots.forEach( function ( slot ) {
				var isSel = state.slot && state.slot.start_time === slot.start_time;
				slotsHtml += '<li class="wpappt-slot' + ( isSel ? ' is-selected' : '' ) + '"' +
					' role="button" tabindex="0"' +
					' data-start="' + esc( slot.start_time ) + '"' +
					' data-end="'   + esc( slot.end_time )   + '">' +
					formatTime( slot.start_time ) + ' &ndash; ' + formatTime( slot.end_time ) +
					'</li>';
			} );
			slotsHtml += '</ul>';
		} else {
			slotsHtml = '<p class="wpappt-notice">No times are available on this date. Go back and choose another day.</p>';
		}

		el.innerHTML =
			'<h2 class="wpappt-step-title">Choose a time</h2>' +
			'<p class="wpappt-step-sub">' + formatDisplayDate( state.date ) + ' &mdash; ' + esc( state.service.name ) + '</p>' +
			slotsHtml +
			'<div class="wpappt-nav">' +
				'<button class="wpappt-btn wpappt-btn--ghost" id="step3-back">&larr; Back</button>' +
				( hasSlots ? '<button class="wpappt-btn wpappt-btn--primary" id="step3-next"' + ( state.slot ? '' : ' disabled' ) + '>Next &rarr;</button>' : '' ) +
			'</div>';

		if ( hasSlots ) {
			el.querySelectorAll( '.wpappt-slot' ).forEach( function ( li ) {
				li.addEventListener( 'click', function () {
					el.querySelectorAll( '.wpappt-slot' ).forEach( function ( s ) { s.classList.remove( 'is-selected' ); } );
					li.classList.add( 'is-selected' );
					state.slot = { start_time: li.getAttribute( 'data-start' ), end_time: li.getAttribute( 'data-end' ) };
					document.getElementById( 'step3-next' ).disabled = false;
				} );
				li.addEventListener( 'keydown', function ( e ) {
					if ( e.key === 'Enter' || e.key === ' ' ) { e.preventDefault(); li.click(); }
				} );
			} );

			document.getElementById( 'step3-next' ).addEventListener( 'click', function () {
				if ( ! state.slot ) return;
				setStep( 4 );
				renderStep4();
			} );
		}

		document.getElementById( 'step3-back' ).addEventListener( 'click', function () {
			setStep( 2 );
		} );

		setStep( 3 );
	}

	/* =========================================================================
	 * Step 4 — Customer details
	 * ========================================================================= */
	function renderStep4() {
		var el = document.getElementById( 'step-4-content' );
		var f  = state.form;

		el.innerHTML =
			'<h2 class="wpappt-step-title">Your details</h2>' +
			'<form id="wpappt-details-form" novalidate>' +
				'<div class="wpappt-field">' +
					'<label for="wpappt-name">Full name <span class="wpappt-required" aria-hidden="true">*</span></label>' +
					'<input type="text" id="wpappt-name" name="name" required autocomplete="name" value="' + esc( f.name ) + '">' +
					'<span class="wpappt-field-error" id="err-name" role="alert" hidden></span>' +
				'</div>' +
				'<div class="wpappt-field">' +
					'<label for="wpappt-email">Email address <span class="wpappt-required" aria-hidden="true">*</span></label>' +
					'<input type="email" id="wpappt-email" name="email" required autocomplete="email" value="' + esc( f.email ) + '">' +
					'<span class="wpappt-field-error" id="err-email" role="alert" hidden></span>' +
				'</div>' +
				'<div class="wpappt-field">' +
					'<label for="wpappt-phone">Phone number <span class="wpappt-required" aria-hidden="true">*</span></label>' +
					'<input type="tel" id="wpappt-phone" name="phone" required autocomplete="tel" value="' + esc( f.phone ) + '">' +
					'<span class="wpappt-field-error" id="err-phone" role="alert" hidden></span>' +
				'</div>' +
				'<div class="wpappt-field">' +
					'<label for="wpappt-injury">Injury or health notes <span class="wpappt-optional">(optional)</span></label>' +
					'<textarea id="wpappt-injury" name="injury_notes" rows="3">' + esc( f.injury_notes ) + '</textarea>' +
				'</div>' +
				'<div class="wpappt-field">' +
					'<label for="wpappt-comments">Additional comments <span class="wpappt-optional">(optional)</span></label>' +
					'<textarea id="wpappt-comments" name="comments" rows="3">' + esc( f.comments ) + '</textarea>' +
				'</div>' +
				'<div class="wpappt-nav">' +
					'<button type="button" class="wpappt-btn wpappt-btn--ghost" id="step4-back">&larr; Back</button>' +
					'<button type="submit" class="wpappt-btn wpappt-btn--primary">Review booking &rarr;</button>' +
				'</div>' +
			'</form>';

		document.getElementById( 'step4-back' ).addEventListener( 'click', function () {
			setStep( 3 );
		} );

		document.getElementById( 'wpappt-details-form' ).addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			if ( ! validateDetailsForm( el ) ) return;
			state.form.name         = el.querySelector( '#wpappt-name' ).value.trim();
			state.form.email        = el.querySelector( '#wpappt-email' ).value.trim();
			state.form.phone        = el.querySelector( '#wpappt-phone' ).value.trim();
			state.form.injury_notes = el.querySelector( '#wpappt-injury' ).value.trim();
			state.form.comments     = el.querySelector( '#wpappt-comments' ).value.trim();
			setStep( 5 );
			renderStep5();
		} );

		setStep( 4 );
	}

	function validateDetailsForm( el ) {
		var valid = true;

		function setFieldError( inputId, errId, msg ) {
			var input = el.querySelector( '#' + inputId );
			var err   = el.querySelector( '#' + errId );
			if ( msg ) {
				input.classList.add( 'has-error' );
				err.textContent = msg;
				err.hidden = false;
				valid = false;
			} else {
				input.classList.remove( 'has-error' );
				err.hidden = true;
			}
		}

		var name  = el.querySelector( '#wpappt-name' ).value.trim();
		var email = el.querySelector( '#wpappt-email' ).value.trim();
		var phone = el.querySelector( '#wpappt-phone' ).value.trim();

		setFieldError( 'wpappt-name',  'err-name',  name  ? '' : 'Please enter your name.' );
		setFieldError( 'wpappt-email', 'err-email',
			! email ? 'Please enter your email address.' :
			! isValidEmail( email ) ? 'Please enter a valid email address.' : '' );
		setFieldError( 'wpappt-phone', 'err-phone', phone ? '' : 'Please enter your phone number.' );

		return valid;
	}

	/* =========================================================================
	 * Step 5 — Confirmation summary
	 * ========================================================================= */
	function renderStep5() {
		var el = document.getElementById( 'step-5-content' );
		var f  = state.form;

		var extraRows = '';
		if ( f.injury_notes ) extraRows += summaryRow( 'Health notes', f.injury_notes );
		if ( f.comments )     extraRows += summaryRow( 'Comments',     f.comments );

		el.innerHTML =
			'<h2 class="wpappt-step-title">Review your booking</h2>' +
			'<dl class="wpappt-summary">' +
				summaryRow( 'Service', state.service.name ) +
				summaryRow( 'Date',    formatDisplayDate( state.date ) ) +
				summaryRow( 'Time',    formatTime( state.slot.start_time ) + ' &ndash; ' + formatTime( state.slot.end_time ) ) +
				summaryRow( 'Name',    f.name ) +
				summaryRow( 'Email',   f.email ) +
				summaryRow( 'Phone',   f.phone ) +
				extraRows +
			'</dl>' +
			'<p class="wpappt-notice wpappt-notice--info">Once confirmed, your booking request is sent for approval. You\'ll receive an email when it\'s confirmed.</p>' +
			'<div id="wpappt-submit-error" class="wpappt-notice wpappt-notice--error" role="alert" hidden></div>' +
			'<div class="wpappt-nav">' +
				'<button class="wpappt-btn wpappt-btn--ghost" id="step5-back">&larr; Edit details</button>' +
				'<button class="wpappt-btn wpappt-btn--primary" id="step5-submit">Confirm booking</button>' +
			'</div>';

		document.getElementById( 'step5-back' ).addEventListener( 'click', function () {
			setStep( 4 );
			renderStep4();
		} );

		document.getElementById( 'step5-submit' ).addEventListener( 'click', submitBooking );

		setStep( 5 );
	}

	function summaryRow( label, value ) {
		return '<div class="wpappt-summary__row"><dt>' + esc( label ) + '</dt><dd>' + esc( value ) + '</dd></div>';
	}

	/* =========================================================================
	 * Step 6 — Success
	 * ========================================================================= */
	function renderStep6() {
		var el = document.getElementById( 'step-6-content' );
		el.innerHTML =
			'<div class="wpappt-success">' +
				'<div class="wpappt-success__icon" aria-hidden="true">&#10003;</div>' +
				'<h2 class="wpappt-step-title">Booking request sent!</h2>' +
				'<p>Thank you, <strong>' + esc( state.form.name ) + '</strong>. Your request for <strong>' +
				esc( state.service.name ) + '</strong> on <strong>' + formatDisplayDate( state.date ) +
				'</strong> at <strong>' + formatTime( state.slot.start_time ) + '</strong> has been received.</p>' +
				'<p>You\'ll get a confirmation email at <strong>' + esc( state.form.email ) + '</strong> once your appointment is approved.</p>' +
			'</div>';
		setStep( 6 );
	}

	/* =========================================================================
	 * Submit
	 * ========================================================================= */
	function submitBooking() {
		var submitBtn = document.getElementById( 'step5-submit' );
		var errEl     = document.getElementById( 'wpappt-submit-error' );

		submitBtn.disabled    = true;
		submitBtn.textContent = 'Sending\u2026';
		errEl.hidden          = true;

		apiFetch( 'bookings', {
			method: 'POST',
			body:   JSON.stringify( {
				service_id:       state.service.id,
				appointment_date: state.date,
				start_time:       state.slot.start_time,
				customer_name:    state.form.name,
				customer_email:   state.form.email,
				customer_phone:   state.form.phone,
				injury_notes:     state.form.injury_notes,
				comments:         state.form.comments,
			} ),
		} )
			.then( function () {
				renderStep6();
			} )
			.catch( function ( err ) {
				submitBtn.disabled    = false;
				submitBtn.textContent = 'Confirm booking';
				var msg = ( err && err.message ) ? err.message : 'Something went wrong. Please try again.';
				errEl.textContent = msg;
				errEl.hidden      = false;
			} );
	}

	/* =========================================================================
	 * Helpers — loading / error placeholders
	 * ========================================================================= */
	function showLoading( id ) {
		var el = document.getElementById( id );
		if ( el ) el.innerHTML = '<div class="wpappt-loading" aria-live="polite" aria-busy="true">Loading&hellip;</div>';
	}

	function showError( id, msg ) {
		var el = document.getElementById( id );
		if ( el ) el.innerHTML = '<p class="wpappt-notice wpappt-notice--error">' + esc( msg ) + '</p>';
	}

	/* =========================================================================
	 * Helpers — formatting & sanitisation
	 * ========================================================================= */
	function esc( str ) {
		return String( str )
			.replace( /&/g,  '&amp;' )
			.replace( /</g,  '&lt;' )
			.replace( />/g,  '&gt;' )
			.replace( /"/g,  '&quot;' )
			.replace( /'/g,  '&#039;' );
	}

	function isValidEmail( email ) {
		return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( email );
	}

	function formatDate( d ) {
		return d.getFullYear() + '-' +
			String( d.getMonth() + 1 ).padStart( 2, '0' ) + '-' +
			String( d.getDate() ).padStart( 2, '0' );
	}

	function formatDisplayDate( dateStr ) {
		if ( ! dateStr ) return '';
		var parts     = dateStr.split( '-' );
		var d         = new Date( parseInt( parts[0], 10 ), parseInt( parts[1], 10 ) - 1, parseInt( parts[2], 10 ) );
		var dayNames  = [ 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ];
		var monNames  = [ 'January', 'February', 'March', 'April', 'May', 'June',
		                  'July', 'August', 'September', 'October', 'November', 'December' ];
		return dayNames[ d.getDay() ] + ', ' + d.getDate() + ' ' + monNames[ d.getMonth() ] + ' ' + d.getFullYear();
	}

	function formatTime( hhmm ) {
		if ( ! hhmm ) return '';
		var parts = hhmm.split( ':' );
		var h     = parseInt( parts[0], 10 );
		var m     = parts[1];
		var ampm  = h >= 12 ? 'PM' : 'AM';
		h = h % 12 || 12;
		return h + ':' + m + ' ' + ampm;
	}

	function formatDuration( mins ) {
		if ( mins < 60 ) return mins + ' min';
		var h = Math.floor( mins / 60 );
		var m = mins % 60;
		return h + 'h' + ( m ? ' ' + m + ' min' : '' );
	}

} )();
