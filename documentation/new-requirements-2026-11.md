# PlayerSaloons — New Requirements Proposal

Date: 2026-11-24  
Status: Proposed / Not implemented

## Purpose

Add game categories, improve tournament discovery, integrate PlayStrategy,
and introduce optional membership. Preserve existing tournament, wallet,
and prize workflows. This document is a requirements handoff for later work;
creating it does not implement the features below.

## 1. Game Categories

Create a `game_categories` table with:

- Name
- Unique slug
- Display order
- Active/inactive status
- Timestamps

Initial categories:

- Video Games
- Card & Board Games

Each game belongs to one category through a required
`games.game_category_id` foreign key.

Assign all existing games, including soft-deleted games, to Video Games.
Update game creation, factories, and seeders to require category assignment.
Tournaments inherit their category through their assigned game.

Admin can create, rename, reorder, activate, and deactivate categories.
Prevent deletion while any game, including archived games, references
the category.

### Inactive category behavior

- Hide inactive categories and their games/tournaments from player
  discovery, game selectors, All Games, and featured results.
- Block new registrations through shared registration actions,
  including API requests.
- Preserve accepted registrations, authorized match access,
  tournament history, and existing cancellation rights.
- Continue scheduling, match processing, bracket advancement, and payouts.
- Keep inactive records accessible to authorized admins.
- Do not implement inactivity as a global query filter that could
  interrupt background processing.

## 2. Category Tabs and Game Search

Add category tabs to:

- Admin Games
- Homepage game section
- Public tournament page
- Logged-in tournament page

Include an All Games tab.

Selecting a category filters the game cards, game selector,
tournament listings, featured results, and relevant counts.
Selecting a game further narrows the tournaments.

- Place category and game controls prominently.
- Preserve selections in URL parameters.
- Homepage selection opens the filtered tournament listing.
- Reset pagination when selection changes.
- Clear game selections incompatible with the selected category.
- Preserve compatible search, platform, and status filters.
- Apply the same behavior to legacy and V2 discovery.
- Provide clear loading and empty states.

## 3. PlayStrategy Integration

PlayerSaloons manages registration, brackets, wallet, and prizes.
Actual gameplay happens on PlayStrategy.

### Accounts and games

- Link verified PlayStrategy accounts using OAuth.
- Encrypt tokens and restrict access to authorized backend operations.
- Keep gameplay provider separate from game category.
- Store the external game ID against the correct match attempt.
- Enforce unique provider/game mapping.
- Provide a Play Match link to participants.

### Safe game creation

- Dispatch external game creation after database commit.
- Allow one creation operation per match attempt.
- If a request times out with an uncertain outcome, reconcile first.
- If reconciliation cannot identify the created game, require admin
  review before creating another.
- Do not blindly retry game creation.

### Results

- Retrieve results through the PlayStrategy API.
- Validate game ID, participants, settings, final status, and current
  match attempt before accepting a result.
- Provider results are primary for provider-managed matches.
- Prevent existing manual-result and timeout workflows from
  automatically resolving those matches inconsistently.
- Preserve authorized admin adjudication.
- Apply completion atomically using existing lock ordering,
  state-machine rules, and completion events.
- Duplicate or stale responses must not cause duplicate advancement
  or payouts.
- Draws create a new attempt and a new external game.
- Aborted or ambiguous results require admin review.
- API outages trigger coordinated retries and rate-limit handling.
- Missing provider data must not automatically award a winner.

Validate OAuth, game creation, result retrieval, and partnership usage
conditions before production enablement.

## 4. Optional Membership

Start with one optional paid tier.

- Currency: EUR.
- Validity: 30 days.
- Payment: explicit wallet purchase.
- Renewal: manual, available after expiry.
- No automatic deduction or stacked terms during the pilot.

Benefits:

- Member badge while membership is active.
- Included entry to published member-only events, subject to capacity.

Regular tournaments retain their existing entry fees.
Pilot member-only events support individual entries and zero entry fees.

### Payment and eligibility safeguards

- Store purchase price, payment reference, start, and expiry.
- Debit the wallet and activate membership in one transaction.
- Serialize purchases per user and prevent duplicate charges
  using a stable purchase reference.
- Use a dedicated membership ledger type and financial reporting.
- Enforce eligibility inside shared registration actions,
  including API requests.
- Determine expiry server-side using consistently stored timestamps.
- Honor accepted event registrations after membership expiry.
- Disabling new sales must preserve existing paid terms and benefits.

### Business launch gate

Membership sales start disabled.

Before launch, approve:

- EUR price
- Published event schedule
- Prize funding
- Operating-cost estimate

Use existing sponsored-prize funding for member events.
Block publication when required funding is insufficient.

Evaluate:

```text
Membership contribution = membership sales
                        - event funding
                        - payment costs
                        - refunds
                        - incremental operating costs
```

Wallet deposits are not membership revenue.
Membership does not guarantee a tournament slot or winnings.

## 5. UI Design Requirements

Use `taste-skill:design-taste-frontend` for the affected UI.

- Preserve the existing PlayerSaloons visual identity and components.
- Use consistent typography, spacing, cards, buttons, and colors.
- Present category tabs, game selector, and results in a clear order.
- Show active/inactive status clearly in admin.
- Explain the effect of deactivation before confirmation.
- Include selected, loading, empty, success, and error states.
- Support keyboard navigation, visible focus, readable contrast,
  reduced motion, and mobile-friendly controls.
- Use restrained interactions that support navigation.
- Verify affected screens in desktop, tablet, and mobile browsers.

This is a focused UI improvement, not a full-site redesign.

## 6. Verification

Test:

- Existing-game category backfill and all game-creation paths.
- Admin permissions and category deletion protection.
- Filtering across public, player, admin, featured, and V2 results.
- Inactive-category registration rejection.
- Continued access and processing for existing matches.
- Concurrent and duplicate provider operations.
- Uncertain game creation, stale results, draws, and API outages.
- Conflicts with existing timeout recovery.
- Membership duplicate purchases, insufficient balance, and rollback.
- Exact membership expiry and registration eligibility.
- Prize funding and disabled sales with active members.

Run focused PHPUnit tests, Pint, relevant PHPStan checks,
frontend build, and browser verification during implementation.

## 7. Suggested Delivery Order

1. Game categories, visibility, tabs, and game search.
2. PlayStrategy integration.
3. Membership pilot.

## Limitations

- This document records proposed requirements, not completed work.
- PlayLounge's exact features were not independently verified.
- PlayStrategy capabilities were researched against official documentation;
  authenticated end-to-end testing is pending.
- Membership profitability requires approved pricing and cost data.
