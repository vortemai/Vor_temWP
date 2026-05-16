# Setup Wizard UI/UX Redesign — Design Spec

**Date:** 2026-05-06
**Owner:** Vortem AI plugin team
**Affected files:** `includes/class-vortem-setup-wizard.php`, `assets/css/wizard.css`, `assets/js/wizard.js`

## 1. Goal

Rework the WordPress admin setup wizard's UI/UX to feel like a modern SaaS onboarding flow rather than a dashboard form. The 4-step flow stays the same; what changes is the layout, visual hierarchy, and micro-interactions.

## 2. Scope

### In scope
- Replace the horizontal-progress card with a **split-panel layout**: persistent left rail (brand + vertical step nav) and right pane (step content).
- Refresh the per-step layout, copy hierarchy, and micro-interactions for all four steps:
  1. Welcome
  2. Configuration (currency)
  3. Terms & Consent
  4. Complete
- Refine the currency picker, consent checkboxes, and primary/secondary buttons.
- Improve loading, empty, error, and disabled states.
- Maintain RTL support and keyboard accessibility.
- Refine (not remove) the existing AliExpress info text on the Welcome step.

### Out of scope (will NOT change)
- The 4-step flow itself — no new steps, no merged steps.
- AliExpress connection inside the wizard (stays in Settings; the wizard only mentions it).
- Server-side data model, AJAX endpoints, or option keys.
- Compliance behavior (the data-processing consent gate stays exactly as-is — no external HTTP before terms acceptance).
- License / feature gating (must remain absent — the plugin stays fully functional without an account).

## 3. Architecture

The wizard is server-rendered PHP with a small jQuery layer for AJAX and progressive enhancement. The redesign keeps that shape:

- **`class-vortem-setup-wizard.php`** — `wizard_page()` outputs the new shell (rail + pane). Per-step `render_*_step()` methods output the new pane content. AJAX handlers and option logic are unchanged.
- **`assets/css/wizard.css`** — replaced wholesale (the file is currently 2.4k LOC of the prior design). New CSS is structured around a small set of design tokens and component blocks.
- **`assets/js/wizard.js`** — extended for the new interactions (rail step focus, currency-pill expand/collapse, consent-row toggling). No framework introduced; remains jQuery + plain JS.

No new PHP classes, AJAX routes, or REST endpoints. No new options.

## 4. Layout

### 4.1 Shell (desktop, ≥ 960px)

```
┌──────────────────────────────────────────────────────────────┐
│  ┌──────────────┐  ┌────────────────────────────────────────┐│
│  │              │  │                                        ││
│  │   LEFT RAIL  │  │   RIGHT PANE                           ││
│  │   (sticky)   │  │   (step content + nav buttons)         ││
│  │              │  │                                        ││
│  │              │  │                                        ││
│  └──────────────┘  └────────────────────────────────────────┘│
└──────────────────────────────────────────────────────────────┘
```

- Outer container: full-bleed, soft gradient background (existing tokens).
- Card: max-width `960px`, centered, white bg, `border-radius: 20px`, soft elevation shadow.
- Grid: `grid-template-columns: 260px 1fr` on desktop, single column on `< 960px`.

### 4.2 Left rail

- Background: existing teal gradient (`#0E626B → #1B7A7F → #4D9E7B`), 160deg.
- Padding: `28px 22px`.
- Contents (top → bottom):
  1. **Brand block** — small logo tile (rounded square, `40px`), wordmark "Vortem", subtitle "Setup".
  2. **Step counter** — eyebrow "STEP 2 OF 4" in uppercase 10px / 0.08em tracking.
  3. **Vertical step nav** — one row per step (4 rows). Each row:
     - State indicator: `18px` circle. Done → filled white with teal check. Active → filled white with teal numeral. Pending → 15% white-on-glass with white numeral at 55% opacity.
     - Label (current step title).
     - Active row has a subtle `rgba(255,255,255,.14)` pill background.
  4. **Footer** (rail bottom, `margin-top: auto`):
     - On steps 1–3: small "Need help?" link → docs URL.
     - On step 4: "Restart wizard" button (replaces the standalone restart card on Complete).

- Decorative blur: a soft white-on-glass orb in the bottom-right corner of the rail (purely cosmetic, `pointer-events: none`).

### 4.3 Right pane

- Padding: `40px 44px`.
- Layout: vertical flex with `gap: 16px`. Bottom action row pinned with `margin-top: auto`.
- Internal sections (top → bottom):
  1. **Eyebrow** — small uppercase teal label that contextualises the step (e.g. "Welcome", "Store basics", "Almost there", "All done").
  2. **H1 title** — 24px, weight 700.
  3. **Subtitle** — 14px, muted.
  4. **Step body** — varies per step (see §5).
  5. **Action row** — Back (secondary, left) + Next/Accept/Complete (primary, right). On step 1 the Back button is omitted.

### 4.4 Mobile / narrow (`< 960px`)

- Card collapses to single column.
- Rail moves to top, height `auto`. Content compresses to:
  - Brand row (logo + wordmark) on the left.
  - Horizontal step dots on the right (back to the existing horizontal pattern, but smaller — used as a fallback only when there isn't room for the vertical list).
- Right pane padding reduces to `24px`.

### 4.5 RTL

- Grid order reverses (rail on the right, pane on the left) via `direction: rtl` on the card when `Vortem_Translation_Manager::is_rtl()` is true.
- Step-row indicators stay leading-side; chevrons in buttons mirror.
- The existing `vortem-wizard-rtl` class is reused as the hook.

## 5. Per-step design

### 5.1 Step 1 · Welcome

**Purpose:** Set expectations, surface the AliExpress prerequisite, motivate the user to start.

**Eyebrow:** `WELCOME`
**H1:** "Let's get you started"
**Subtitle:** "Complete setup in about 2 minutes."

**Body:**
- **Info callout** (existing green box, refined copy):
  > "Vortem's product import features need an AliExpress account. You'll connect it later under Settings → Vortem → AliExpress Integration. No account yet? You can sign up from there too."

  - Visual: green-tinted background (`#ECFDF5`), 3px solid green left border, rounded right, internal icon (info circle) on the leading side.
- **Feature grid** (3 cards, single row on desktop, stacks on mobile):
  - "Quick Setup" — bolt icon, "Up and running in minutes."
  - "Secure & Reliable" — shield icon, "Built with WordPress security best practices."
  - "Optimized Performance" — gauge icon, "Lightweight and tuned for WordPress."
  - Each card: `1px` light border, `12px` radius, `16px` padding, hover lifts `2px` and the icon background tints teal.

**Action:** Next Step (primary).

### 5.2 Step 2 · Configuration

**Purpose:** Collect the user's currency preference. Single field — frame it richly so it doesn't feel sparse.

**Eyebrow:** `STORE BASICS`
**H1:** "Pick your store currency"
**Subtitle:** "This is what your customers see at checkout. You can change it later."

**Body:**
- **Currency picker** (replacement for the current bare select):
  - Closed state: a pill-shaped row, full-width, `14px` padding, light-gray bg, light border. Contents: country flag (24px circle) · two-line label (`bold` currency name + muted `country · symbol`) · chevron on the trailing side.
  - Hover/focus: border becomes teal, background turns white, subtle `2px` teal glow.
  - Open state: dropdown drops from below with search input (placeholder "Search by country or currency"), then a scrollable list of currencies (max-height capped, native overflow scroll). Each row: flag · name · code · symbol. Selected row marked with a teal check on the trailing side.
  - Loading state: dropdown shows three skeleton rows while currencies load.
  - Error state: dropdown shows a polite error block with a "Retry" link that re-calls `vortem_wizard_get_currencies`.
- **Helper line** beneath the picker: a small `•` bullet + muted text — "Used on product cards, cart, and checkout."

**Action:** Back (secondary) + Next Step (primary).

### 5.3 Step 3 · Terms

**Purpose:** Capture two consent decisions (Terms acceptance + Data processing consent) without forcing the user to read 800 words of legalese.

**Eyebrow:** `ALMOST THERE`
**H1:** "Review and accept"
**Subtitle:** (none — keep the pane tight; the H1 is enough.)

**Body:**
- **Terms scroll box:**
  - Background `#F9FAFB`, `1px` border, `12px` radius, `14px` padding, `max-height: 220px`, internal scroll, fade-out gradient at the bottom edge.
  - Three sections (Service Availability, Support, Updates), each with an `h5` title (12px, semibold) and a `p` body (12px, line-height 1.5, muted).
  - A "Read full terms" link below the scroll box opens `Vortem_Config::get_terms_url()` in a new tab.
- **Consent rows** (two stacked, full-width):
  - Each row is a `<label>` wrapping a hidden checkbox + a custom `14px` rounded square + two-line text (bold title + muted explanation).
  - Default state: light border, white background.
  - Checked state: border turns teal, background tints `rgba(14,98,107,.04)`, the box fills teal with a white check.
  - Disabled / required state: when the user tries to advance without consent, the unchecked rows pulse a `1px` red glow once and the primary button shake-animates `4px`.
- **Row 1:** "I accept the Terms & Conditions" — explanation: "You agree to be bound by these terms."
- **Row 2:** "I consent to data processing" — explanation re-uses the existing legally-reviewed `wp_kses_post` block verbatim (it lists the exact data flows and parallels Wordfence/Patchstack/Jetpack patterns). The visual restructuring does not edit the legal copy.

**Action:** Back (secondary) + Accept & Continue (primary, disabled until both checkboxes are on).

### 5.4 Step 4 · Complete

**Purpose:** Celebrate, summarise, and route the user to the next action.

**Rail change:** Step counter eyebrow text becomes "ALL DONE". The rail footer shows the Restart Wizard button (replaces the standalone restart card on the previous design's Complete step).

**Eyebrow:** (omitted — the success burst takes its place)
**H1:** "You're all set!"
**Subtitle:** "Vortem is configured and ready. Connect AliExpress from Settings whenever you're ready."

**Body:**
- **Success burst** — `64px` circle, gradient `#4D9E7B → #08B83B`, white check icon, soft drop-shadow. Animated on mount: scale `0.6 → 1.0` and a subtle ring pulse over `400ms`.
- **Summary card** — light-gray rounded card with three rows:
  - "Currency" → bold `USD · $`
  - "Terms" → `✓ Accepted` (success green)
  - "Data processing" → `✓ Consented` (success green)
- **Action row** (full-width, two equal buttons):
  - Documentation (secondary, opens `Vortem_Config::get_docs_url()` in a new tab).
  - Go to Dashboard (primary, navigates to `admin.php?page=vortem-owerview`).

The Restart Wizard control moves from a standalone card on this page to the rail footer, so it's available from any step that has it (currently only step 4 — but the placement is consistent).

## 6. Visual system

### 6.1 Tokens (extends the existing CSS variables in `wizard.css`)

| Token | Value | Use |
| --- | --- | --- |
| `--vortem-primary` | `#0E626B` | already present, primary teal |
| `--vortem-primary-hover` | `#0B4F55` | already present |
| `--vortem-secondary` | `#4D9E7B` | already present, end of gradients |
| `--vortem-success` | `#08B83B` | already present |
| `--vortem-rail-grad` | `linear-gradient(160deg,#0E626B 0%,#1B7A7F 55%,#4D9E7B 100%)` | new |
| `--vortem-bg-gradient-*` | unchanged | page bg |
| `--vortem-text-primary` / `secondary` / `muted` | unchanged | typography |
| `--vortem-border-color` / `light` / `medium` | unchanged | borders |
| `--vortem-radius-card` | `20px` | new — card radius |
| `--vortem-radius-pill` | `12px` | new — input/pill radius |
| `--vortem-shadow-card` | `0 24px 48px -16px rgba(14,98,107,.22)` | new — card lift |

### 6.2 Typography

- Family: stay with WordPress admin's system stack (no custom fonts; review-friendly).
- Scale (right pane):
  - H1 — `24px / 1.2 / 700`
  - Subtitle — `14px / 1.5 / 400` muted
  - Eyebrow — `11px / 1 / 600`, uppercase, `letter-spacing: 0.08em`, color `--vortem-primary`
  - Body — `14px / 1.5 / 400`
  - Helper — `12px / 1.4 / 400` muted
- Scale (rail):
  - Wordmark — `15px / 1 / 700`
  - Step label — `13px / 1.2 / 500`
  - Step counter eyebrow — `10px / 1 / 600`, uppercase white-70%

### 6.3 Motion

All durations short and rounded; all motion respects `prefers-reduced-motion`.

- Step transition (clicking Next) — the wizard remains server-rendered (each step is `?step=N`), so the actual transition is a page nav. Before navigation, the right pane fades to `opacity: 0.6` over `120ms` to acknowledge the click; on the new page the pane fades in `0 → 1` over `160ms` via a one-shot CSS animation triggered on initial paint.
- Rail step "complete" tick — scale `0.7 → 1.0` over `200ms`.
- Buttons — `transform: translateY(-1px)` on hover, `100ms`.
- Currency dropdown — open with `opacity 0 → 1` and `translateY(4px → 0)` over `120ms`.
- Consent row check — box fill + check stroke draw, `160ms`.
- Success burst (step 4) — described above.
- `@media (prefers-reduced-motion: reduce)` overrides all transforms and animations to `none` / `opacity` only.

## 7. Accessibility

- All interactive elements reachable by keyboard with visible focus rings (`2px` teal outline, `2px` offset).
- Currency picker: `role="combobox"`, `aria-expanded`, arrow-key navigation in dropdown, `Enter` selects, `Esc` closes, type-to-search.
- Consent rows: real `<input type="checkbox">` underneath the custom box; the `<label>` wraps both, so the click target is the whole row and screen readers read the title + description.
- Rail step nav: not a `<nav>` of links (steps are not directly clickable — wizard order is enforced server-side). Rendered as a list (`<ol>`), with `aria-current="step"` on the active row.
- Color contrast verified at WCAG AA for body and headings (teal on white, white on teal gradient).
- Loading skeletons announce via `aria-busy="true"` on the relevant region.
- Error blocks use `role="alert"` so the message is announced.

## 8. Compliance constraints (from `CLAUDE.md`)

These are reaffirmed; nothing in this design relaxes them:

- **No external HTTP before consent.** The currency dropdown calls `fetch_currency_codes_public()` (the documented exemption — public, no PII). Every other AJAX endpoint hit by the wizard is local-only (option writes). External calls that depend on consent (the API client's `update_currency`) are still gated by `Vortem_Api_Client::has_consent()` and only fire after the user finishes step 3 and proceeds.
- **No license / trialware gating.** The wizard remains skippable; the plugin remains fully functional without completing it. The Skip / Restart affordances continue to behave as today.
- **All output escaped, all input sanitized.** New PHP follows the existing `esc_*` / `wp_kses_post` / `wp_unslash` patterns. No raw `echo $var`.
- **No new `wp_remote_*` call sites.** The redesign is presentation-only.
- **No `update_option('woocommerce_*')` writes.** Currency stays in `vortem_currency` / `vortem_customer_currency`.
- **All paths via `plugin_dir_url` / `plugins_url`.**
- **i18n** — every new user-facing string is wrapped in `__('...', 'vortem-ai')` or its `esc_html__` / `esc_attr__` variant.

## 9. Edge cases & states

- **Currency API unreachable** — dropdown shows a friendly "Couldn't load currencies. [Retry]" block; the wizard remains advanceable using the saved/default currency. Failure does not block step 2.
- **User reloads on a step** — the existing `?step=N` query param drives state; nothing to change.
- **Restart mid-flow** — the existing modal stays. The trigger moves from the Complete step to the rail footer (visible only on step 4 in this iteration).
- **Disabled primary button** — primary buttons in disabled state use 40% opacity and `cursor: not-allowed`. Step 3's button is the only disabled-by-default case.
- **Long currency names** — the closed currency pill truncates with ellipsis; full name is shown in the dropdown row.

## 10. Testing

- **Manual visual check** at three widths: `1280px`, `960px`, `768px`. Verify rail-to-top collapse, button stacking, scroll-box behaviour.
- **Keyboard pass** through all four steps: Tab order should be eyebrow → field/checkbox → action buttons → rail footer link. No focus traps. `Esc` closes the currency dropdown.
- **RTL pass** with `WPLANG=ar` (or the existing translation manager set to `ar` / `fa` / `he`): rail flips to the right, chevrons mirror, scroll fades flip.
- **Reduced-motion pass** with macOS "Reduce motion" toggled on — animations should resolve to instant.
- **Plugin Check** must remain clean — the redesign is CSS/HTML/JS-heavy, but PHP changes are limited to template output, so no new escaping / nonce / SQL surface area is introduced.

## 11. Out-of-scope items captured for later

- A "Connect AliExpress" step inside the wizard (deferred — see flow scope decision in this session; user explicitly excluded it).
- Animated illustrations on the rail (kept abstract; consider for a future iteration).
- Saved drafts / resume-where-you-left-off (current behaviour — `?step=N` from URL — is sufficient).
