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
- [ ] **Real-Time Integration**
    - [ ] Setup Laravel Reverb (Websockets).
    - [ ] Create events (`ChallengeCreated`, `MatchFound`).
    - [ ] Frontend integration: Listen for real-time match found events to trigger UI transition.
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
