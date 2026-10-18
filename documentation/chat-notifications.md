# Chat notifications

Players see the number of unread, unmuted direct conversations on the desktop Chat link, the mobile Chat shortcut, and the mobile More button. The sidebar starts expanded and can still be collapsed. Badge text caps at `99+`; its accessible label reports the full count.

Direct messages also create a silent, six-second alert and one grouped notification per conversation in the bell. Clicking either opens the exact conversation. Global, squad, and tournament-team messages do not create account-wide alerts. Chat alerts never send email or browser push notifications.

Each conversation has a persistent personal **Mute notifications** switch. Muting does not block sending, remove messages, or mark them read. Muted direct conversations are excluded from the badge and new alerts; their unread indicators remain in Chat. Existing moderation mutes that restrict sending are separate. Unmuting restores unread badge counts without replaying alerts.

**Mark all as read** affects bell notifications only. **Clear read notifications** dismisses all read bell entries, including those beyond the ten displayed. Neither action marks chat messages read. Records are retained with a dismissal timestamp and excluded from notification API lists.

Messages are acknowledged by ID after loading into a focused, visible conversation. Background fetches and history pagination do not acknowledge them. The recipient account channel updates badges and bell contents; reconnect, focus, and a 30-second visible-page poll recover missed events. Reverb must be configured and running for immediate delivery.

## Deployment

Run `php artisan migrate` before serving this version. The additive migration adds personal mute preferences, backfills message read checkpoints from existing timestamps, and adds conversation grouping/dismissal fields to notifications. It does not alter existing moderation mutes or remove messages. No production migration or deployment is performed by the code change itself.

## Internal chat endpoints

All endpoints retain player authentication, compliance checks, and conversation access checks:

- `GET /chat/api/inbox`: unread direct-conversation count and safe conversation links.
- `POST /chat/api/conversations/{uuid}/read`: acknowledge through `message_id`; the checkpoint never moves backward.
- `PUT /chat/api/conversations/{uuid}/mute`: set the current player's boolean `muted` preference.
- `GET /chat/api/conversations/{uuid}/messages`: fetch messages without changing read state. Conversation payloads include `notifications_muted` and `last_read_message_id`.

Chat API validation errors return JSON with HTTP 422. Account broadcasts contain identifiers, not message text; the authenticated client fetches current state. In-app and realtime preferences control bell/brief alerts respectively; unread inbox state remains independent.
