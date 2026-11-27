# Match Disputes, AI Triage & AI/API Settings (Admin Guide)

## Reviewing Disputes in Match & Dispute Control
- Open **Matches & Disputes** (`/admin/matches`) and toggle **Disputes Only** to inspect active conflicts.
- Each disputed match row displays an **AI review badge** (such as `AI Reviewed · Inconclusive`, `AI Reviewed · Red Flags`, `AI Reviewed · Final`, `AI Reviewed · Low Confidence`, `AI Auto-Resolved`, or `AI Failed · Escalated`).
- Clicking **View Details** or **Resolve Dispute** shows the **AI Dispute Assistant** panel right at the top:
  - **Reviewed by AI · Escalated to Admin** badge plus provider/model/version.
  - **Why Escalated to Admin:** Plain-English explanation of why AI did not auto-resolve (e.g. inconclusive evidence, red flags detected, confidence below threshold, tournament final match, exceeds prize cap, shadow mode active, or opponent response window still open).
  - **AI Result & Confidence:** Suggested winner (with player username), confidence percentage, summary, evidence findings, and any red flags.
  - **Re-run AI Analysis:** Admins/Super Admins can trigger a fresh AI analysis run (up to the configured max runs per dispute).
- Review the uploaded private screenshots below the AI summary, then select the **Admin Ruling** (Award Player A, Award Player B, or Draw/Rematch) and optionally apply a dishonest-result strike / balance penalty if deliberate fraud is proven.

## Configuring AI Providers & Guardrails
- Open **System Settings → AI & API management** (`/admin/system-settings?section=ai_disputes`).
- **Step 01 — AI Provider & API Credentials:** Choose the active provider (**Google Gemini**, **OpenAI**, **Anthropic Claude**, or **OpenRouter**), save your API key (stored encrypted in the database, never exposed in logs), choose the model (recommended Gemini default: `gemini-3.5-flash-lite`), and click **Test Connection** to verify live connectivity.
- **Step 02 — AI Dispute Management:** Toggle AI dispute analysis on/off, select **Shadow / Advisory Only** (recommended when rolling out) or **Live Auto-Resolve (With Guardrails)**, and configure minimum confidence (`>= 0.95` default), max prize cap, opponent evidence wait window, and max runs per dispute.
  - Safety guardrails ensure AI NEVER auto-resolves tournament finals, matches above the prize cap, single-sided uploads before the wait window expires, matches with red flags, or dishonest-result strikes/bans.
- **Step 03 — AI Support Assistant:** Toggle the role-scoped floating AI Support Assistant on/off across the platform (uses the same active AI provider key configured in Step 01).
