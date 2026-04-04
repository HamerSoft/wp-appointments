# Booking Plugin — Feature Spec

## Overview

A custom WordPress booking plugin for a solo massage therapist, replacing the third-party youcanbookme service. Delivered as an embeddable Divi widget that can be placed on any page or a dedicated booking page.

---

## Service Catalog

- Configurable list of massage services
- Each service has: **name**, **duration**, **price**
- Admin can add, edit, and remove services via the WordPress admin panel

---

## Availability Management

- Default weekly availability template (open every day by default)
- Admin can block specific time slots or recurring patterns (e.g. evenings, school pickup hours)
- No external calendar sync — the plugin is the single source of truth

---

## Booking Flow

```
Customer submits form → [Pending] → Admin confirms → [Confirmed]
                                  ↘ Admin/customer cancels → [Cancelled]
Customer reschedules → [Pending] (notifications sent)
```

**Booking states:** `pending`, `confirmed`, `cancelled`

---

## Booking Form Fields

1. Name
2. Email
3. Phone
4. Injury notes
5. General comments

---

## Email Notifications

| Event                  | Customer | Admin |
|------------------------|----------|-------|
| Request submitted      | ✅       | ✅    |
| Booking confirmed      | ✅       | —     |
| Booking cancelled      | ✅       | —     |
| Customer reschedules   | ✅       | ✅    |

---

## Admin Panel

- View all bookings (with status)
- Confirm or cancel bookings
- Send follow-up emails to customers

---

## Frontend

- Embeddable Divi widget
- No online payment — customers pay in person
- No external calendar integration (planned for a future phase)
