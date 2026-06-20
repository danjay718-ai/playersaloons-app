# PlayerSaloons — Carry Forward Summary
**As of**: 2026-06-20 | **Current version**: v1.38 | **Branch**: `main`

---

## ✅ State ng Project

- Production deployed sa `https://app-testing.website` via Docker Compose + Coolify (Linode)
- SSL active (HTTPS via Let's Encrypt), login working, Horizon active
- PHPStan Level 5 was previously clean on recent feature work; latest v1.37 run exited with code 1 without diagnostics/output in this environment
- `predis/predis` installed — local dev gumagamit ng `REDIS_CLIENT=predis`
- Match confirmation flow now uses canonical `WAITING_FOR_CONFIRMATION`; `RESULT_SUBMITTED` remains legacy-compatible only
- Welcome page header now shows logo image only, without adjacent `PLAYERSALOONS` text

---

## ✅ Natapos ngayong session (v1.30–v1.38)

| Version | Item |
|---|---|
| v1.30 | SSL env vars updated sa Coolify |
| v1.31 | `NotifyAdminsOfKycSubmissionListener` — admin notified on KYC submit |
| v1.32 | Security tests — Join button, listing filter, viewRestrictedDetails policy |
| v1.33 | Online presence tracking (Redis middleware + `User::isOnline()` + dot indicator sa UserAdmin) |
| v1.34 | Fix: missing `last_login_at` migration |
| v1.35 | Broadcast Notification Admin Panel (`/admin/notifications`) |
| v1.36 | Player Notification Bell — DB-backed + realtime refresh |
| v1.37 | Match confirmation flow alignment — `WAITING_FOR_CONFIRMATION` canonical state |
| v1.38 | Welcome logo text cleanup — removed visible header wordmark beside logo |

---

## 📋 Full Pending Backlog

See `documentation/execution_checklist.md` for complete list. Summary:

| Priority | Item | Effort |
|---|---|---|
| 🟢 | R2 storage migration | Medium — guide ready at `documentation/guides/r2-storage-migration.md` |
| 🟢 | Referral system logic | Medium |
| 🔵 | H2H production backend | Large |
| 🔵 | 2FA | Large |
| 🔵 | External payout integration | Large + business decision |
| 🔵 | Compliance/blacklisting, contact inquiries, newsletter admin | Medium/Large |
| 🔵 | CMS Blog/News + translation management | Medium |
| 🟡 | Remaining testing debt | Tournament filters, pagination, elimination modal, N+1 checks |

### Already Done / Do Not Re-open

- `last_login_at` login update and migration — done v1.29/v1.34
- `UserKycSubmitted` admin listener — done v1.31
- Broadcast Messages admin UI — done v1.35
- Player notification bell — done v1.36
- Match confirmation state mismatch — fixed v1.37

---

## 📁 Documentation Structure

```
README.md                                  ← project intro
documentation/
  ONBOARDING.md                            ← start here, conventions, doc update format
  FEATURE_MAP.md                           ← all routes, components, backend mapping, tests
  architecture_baseline.md                 ← why things were built a certain way
  execution_checklist.md                   ← backlog, testing debt, known bugs
  project_progress.md                      ← version history, deployment notes
  guides/
    r2-storage-migration.md                ← step-by-step R2 migration
  01_identity_onboarding.md
  02_tournament_lifecycle.md
  03_financial_operations.md
  04_team_management.md
  05_admin_operations.md
```

---

## 🤖 How to Start Next Convo with AI

**For a specific feature/bug**, use this format:
```
Basahin mo ang:
- documentation/ONBOARDING.md
- documentation/FEATURE_MAP.md
- documentation/0X_module.md  ← relevant module lang

[Describe the task]

Requirements:
- PHPStan Level 5 minimum after changes
- Test case required
- Update docs per ONBOARDING.md conventions
```

**For continuing backlog work**:
```
Basahin mo ang documentation/ONBOARDING.md at documentation/execution_checklist.md.
I-implement natin ang [item from checklist].
```
