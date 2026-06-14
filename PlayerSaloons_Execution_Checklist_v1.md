# PlayerSaloons — Execution Checklist (Post-MVP)

**Status**: Active Backlog
**Target**: Future Implementations

## 🎯 Head-to-Head (H2H) Production Realization

- [ ] **Database Schema Design**
    - [ ] Create `head_to_head_challenges` table (`id`, `user_id`, `game_id`, `stake_amount`, `status`, `created_at`).
    - [ ] Create `head_to_head_matches` table (linking two players, outcome, stake resolution).
- [ ] **Matchmaking Engine**
    - [ ] Implement `MatchmakerService` for querying waiting challenges.
    - [ ] Implement logic for ELO/Skill Level matching (optional for v1).
    - [ ] Implement stake validation (check balance/lock amount in wallet).
- [x] **Match Resolution Automation**
    - [x] Implement `ConfirmMatchResultAction` for two-way confirmation.
    - [x] Develop `AutoForfeitJob` that uses `tournament.waiting_result_time` to auto-resolve `WAITING_FOR_CONFIRMATION` matches.
    - [x] Update `MatchStateMachine` to allow transition to `WAITING_FOR_CONFIRMATION`.
    - [ ] Create/Update unit tests for confirmation flow and forfeit timeouts.
- [ ] **Escrow/Wallet Integration**
    - [ ] Develop `LockStakeAction` to reserve funds during queueing.
    - [ ] Develop `ResolveStakeAction` to release funds to the winner or handle refunds on match failure.

---

## 🛠️ Other Post-MVP Tasks (Reference)
*(Copied from New Admin Features & Missing Requirements Plan)*

- CMS Module (Blog/News)
- Compliance/Blacklisting (Middleware + Admin Page)
- Contact Inquiries (Admin Page)
- Newsletter Management (Admin Page)
- Translation Management Panel
- Notification Broadcast Panel
