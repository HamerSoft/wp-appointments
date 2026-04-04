---
name: Always add tests when applicable
description: User expects unit tests to be written alongside every build step, not deferred
type: feedback
---

Always write tests alongside each build step — do not defer them.

**Why:** User explicitly stated tests are essential and should be added whenever applicable. Confirmed after asking whether tests made sense for Step 1–2, then instructed to "always add tests when applicable" going forward.

**How to apply:** At the end of every build step, write unit tests for the new code before marking the step complete. At minimum cover: pure logic (no WP deps), security-critical paths, and any method that could fail silently.
