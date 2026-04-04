# WP Appointments — Project Reference

A custom WordPress booking plugin for a solo massage therapist, replacing the youcanbookme third-party service.

## Docs
- `docs/feature-spec.md` — full feature requirements
- `docs/technical-architecture.md` — architecture decisions, DB schema, folder structure
- `docs/build-plan.md` — step-by-step build order with milestones

## Stack
- **Platform:** WordPress plugin
- **Frontend:** Vanilla JS (IIFE), embeds as a Divi module + `[wpappt_booking]` shortcode fallback
- **API:** WP REST API (`/wp-json/wpappt/v1/`)
- **Database:** Four custom tables (no CPTs)
- **Email:** `wp_mail` with PHP templates

## Custom Tables
- `{prefix}appointments_services` — service catalog
- `{prefix}appointments_availability` — weekly availability template
- `{prefix}appointments_blocked_slots` — one-off date/time overrides
- `{prefix}appointments_bookings` — bookings with status, token, and customer fields

## Booking States
`pending` → `confirmed` → `cancelled`
Customers can self-reschedule via a token link in their email (resets to `pending`).

## Key Constraints
- Solo practitioner — no multi-user or team features
- No payment integration — customers pay in person
- No external calendar sync — plugin is the single source of truth
