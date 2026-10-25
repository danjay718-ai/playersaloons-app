# App timezone

An administrator with permission to manage system settings can change **App timezone** under **Settings**. Select an IANA timezone such as `Europe/Amsterdam`, `Europe/London`, or `Asia/Singapore`, then select **Save app timezone**. Refresh other open pages afterward.

Admin and player competition pages use this timezone for clocks, dates, and date/time filters. Summer and winter adjustments happen automatically. Player profile and browser timezones do not override it. Countdown durations always use the shared server clock.

Changing this setting does not move existing competitions, result deadlines, or recurring schedules. New competition schedules use the newly selected timezone. Existing schedule editors clearly show their original scheduling timezone; slots added to an existing recurring schedule inherit that schedule's timezone.

If a new competition form was opened before the timezone changed, submission is blocked. Refresh the form and review the slot times before submitting again.

The existing `tournament.timezone` setting is reused. The fallback is `config('app.tournament_timezone')`, normally `Europe/Amsterdam`; stored dates and API timestamps remain in UTC. No database migration or schedule conversion is required.

Deployment migrations and seeders insert the timezone default only when the setting is missing; they preserve an administrator’s saved selection, even if the deployed environment fallback is UTC. Stream competition cards and the admin competition finance list use the same setting. If an older deployment already overwrote a selection, an administrator must select the intended timezone once; its previous value cannot be inferred safely.
