# MNCHGPT Conversational Redesign — Design

## Context

MNCHGPT (`app/Filament/Resources/MentorshipResource/Pages/MnchGptSetup.php`) currently mixes a chat transcript with separate bespoke card/button/widget UIs (`chat-turn.blade.php`, plus `chat-modules-turn.blade.php` and `chat-mentees-turn.blade.php`) rendered below the input. It also opens by immediately asking the first mentorship-setup question, with no entry point for "something else."

This redesign makes MNCHGPT feel like a real conversational assistant (ChatGPT/DeepSeek-style): a pure scrollable chat + textarea, a friendly greeting that asks what the user wants to do, options presented as numbered/lettered text the user can reply to by number, and fuzzy matching so a partial or slightly-off name still finds the right facility/county.

**Scope:** `MnchGptSetup` only. `ChatMentorshipSetup` (the older, fully deterministic/rule-based page) is untouched — no shared behavior changes, no shared blade changes that would alter its rendering, no test changes to its suite. Modules (including EmONC per-track dates) and mentee enrollment keep their existing card/checkbox UI — explicitly out of scope for this redesign; they remain reachable exactly as they work today.

## Architecture

Three cleanly separated responsibilities:

1. **Backend (deterministic)** decides which slot is next (reusing the existing `nextUnfilledSlot()`/stage-order machinery, unchanged), resolves the user's input against real data (exact → substring → fuzzy shortlist → not-found), and renders any numbered/lettered option list as plain formatted text from real DB labels.
2. **LLM (bounded)** only ever writes the warm acknowledgment + question sentence, given the facts the backend hands it (what was just filled, what's next, whether a list follows). It is explicitly instructed never to invent or restate an option list itself — the backend appends any list verbatim after the LLM's sentence.
3. **Fast path (no LLM)**: when the backend just showed a numbered list, a bare numeric/letter reply next resolves instantly server-side, no DeepSeek call at all.

This preserves every safety property already built this session — no hallucinated options, no silent wrong guesses — while adding the actual "look and feel" of a real conversation.

## 1. Greeting & Intent Routing

`MnchGptSetup` overrides `mount()` (does **not** touch `HasMentorshipChatSlots::mount()`, so `ChatMentorshipSetup` is unaffected). On first load (no existing transcript to resume):

```
Hello {first name}, welcome back! I'm MNCHGPT. How can I help today — would you like
to start creating a mentorship, or is there something else I can help with?
```

No `Training` record exists yet and the checklist shows nothing outstanding until the user actually starts describing a mentorship. There is no special "start mentorship" gating tool — `fill_mentorship_setup_slots` remains registered exactly as it is today, so if a user's very first message already describes a mentorship ("live mentorship in Kiambu, 6 mentees"), the model can extract from it immediately without a forced two-step handshake. If the user instead asks an analytics/query question, the existing query tools handle it — those tools remain available at every point in the conversation, not gated behind this greeting.

On resume (existing transcript), behavior is unchanged from today (replays the transcript, lands on the next question).

## 2. The Conversational Step: computing "what happens next"

After every turn (a normal LLM tool-calling round *or* the fast numeric path below), a single new method on `MnchGptSetup`, `determineNextStep(): ?array`, computes what the assistant should present next:

- If the last tool execution returned **ambiguous fuzzy candidates** for a slot (see §3), that slot's candidate shortlist is the step's option list — the flow is still waiting on this same slot, it doesn't advance to the next one.
- Otherwise, next = `nextUnfilledSlot()` (unchanged logic). If that slot is `Render::CARDS` and its real option count is `<= self::MAX_PROACTIVE_OPTIONS` (10), its **full** option list becomes the step's option list (e.g. `is_pilot`'s 2 options, `recipients`'s 2 options).
- Otherwise (a large CARDS slot like `county_id`/`facility_id`, or a `FREE_TEXT`/`WIDGET` slot, or no next slot at all — e.g. moving into modules/mentees or fully done) — no option list; the assistant just asks its open question as it does today.

`determineNextStep()` returns `null` (no list) or `['slot' => string, 'options' => [1 => ['id' => mixed, 'label' => string], 2 => ...]]`.

This result does two things:
1. Feeds into the `context` passed to `LlmMentorshipAssistantService::respond()` (extends today's `['remaining_requirements' => ...]` with `['next_options' => ...]`), so the system prompt can tell the model such a list is coming and it should reference it naturally without repeating it.
2. Gets **deterministically rendered** (`1. Label`, `2. Label`, ...) and appended by `MnchGptSetup::sendMessage()` directly to the bot message text, after the LLM's own sentence — never generated by the LLM.
3. Is stored in a new public property `$this->pendingOptions` (the same shape) for the fast path in §4, and is cleared whenever a *different* step is computed (so a stale number from an earlier list can never be misapplied).

Example rendered bot bubble (LLM sentence + appended list, one message):
```
Great, live mentorship it is! Which county are you interested in?
```
```
I found a few facilities matching "chuka" in Tharaka Nithi — which one did you mean?

1. Chuka County Referral Hospital
2. Chuka Sub-District Hospital
3. Chuka Cottage Hospital
```

The partial-date ambiguity case ("only one date given — which is it?") needs no new logic: `end_date` already `dependsOn('start_date')` (this session's dependency-exclusion fix), so a lone date is always unambiguous — whichever slot is currently open dictates which one it is.

## 3. Fuzzy Matching & Resolution

`MentorshipSetupToolProvider::resolveValue()` (and the equivalent in `MentorshipModulesToolProvider`) gains a third tier, using a new Composer dependency, **`loilo/fuse`** (verified: real, actively maintained — v7.1.1 released 2025-02, 2M+ downloads, PHP port of the well-known Fuse.js, PHP ^7.4/^8.0 compatible, MIT-style fuzzy Bitap-algorithm search — purpose-built for "fuzzy-search a small/medium list of labeled items"):

1. **Exact match** (id or label, case-insensitive) — resolves silently, no list.
2. **Unique substring match** (existing safety net for prefixed labels like `"MFL012 — Chuka County Referral Hospital"`) — resolves silently, no list.
3. **Fuzzy search (new)** — Fuse scores the input against every option's label using Fuse's default relevance threshold (`0.6` — Fuse's own well-known default, where `0` is a perfect match and `1` is a complete mismatch); the top-scoring results at or under that threshold become a **candidate shortlist**, capped at 8, never an auto-pick — even a clearly-best top score still shows the shortlist and waits for confirmation. If nothing scores at or under the threshold, the result is "not found," and the assistant says so and asks the user to try a different name/spelling.

The tool's `execute()` return shape gains a `candidates` key alongside today's `filled`/`rejected`: `candidates: [slotId => [1 => ['id' => .., 'label' => ..], ...]]`, populated only for slots that hit tier 3. `MnchGptSetup::sendMessage()` reads this (from the last relevant tool call in the loop's `tool_calls` result) to drive `determineNextStep()`.

## 4. Fast-Path Number/Letter Selection

At the top of `sendMessage()`, before any LLM call: if `$this->pendingOptions` is set and the trimmed user message is *only* a bare number (`"2"`) or single letter (`"B"`, case-insensitive, `A`→1, `B`→2, ...) that indexes into it, resolve directly against the stored candidate — apply the answer via the existing `answer()`/slot-fill path, no DeepSeek call. The acknowledgment + next question for this specific case is a short static template (`"Got it — {label}! {next question}"` plus any new list), not an LLM call, since there's nothing to creatively interpret.

Anything less exact (`"the second one"`, a fresh unrelated message) falls through to the normal LLM loop; the pending candidates are still exposed in that turn's tool schema (scoped to just those candidates, not the full underlying list) so looser phrasing still resolves correctly through the model.

## 5. UI/Blade Changes

- `mnchgpt-setup.blade.php` drops its `@include('filament.pages.partials.chat-turn', ...)` and the accompanying `nextUnfilledSlot()` conditional entirely — no more separate card buttons, date-picker widgets, or per-slot text forms for the generic slot flow. Only the transcript (`mnchgpt-transcript.blade.php`, unchanged) and a single textarea remain for that path.
- The textarea (`mnchgpt-input.blade.php`) is restyled toward a ChatGPT/DeepSeek feel: a wider bubble column, softer background, auto-growing textarea instead of a single-line input. The old hint-heavy placeholder is simplified since the greeting now does that guidance.
- `chat-modules-turn.blade.php` / `chat-emonc-modules-turn.blade.php` / `chat-mentees-turn.blade.php` are **unchanged** — those stages still render exactly as they do today (out of scope, per §"Scope" above).
- `mnchgpt-checklist.blade.php` gets a collapsed-by-default state (small Alpine `x-data` toggle, e.g. "3 of 12 done ▸" that expands on click) instead of always showing the full list.

## 6. Testing

- Unit tests for the three-tier resolver (exact / substring / fuzzy-shortlist / not-found) against realistic option sets, including a genuine typo case.
- Unit tests for `determineNextStep()`'s three branches (ambiguous-candidates override, small-enum proactive list, no-list/open-question).
- Unit tests for the fast-path number resolution, including: valid index, out-of-range index, stale `pendingOptions` after a different step was computed.
- Feature test for the new greeting message and for an analytics question asked before any mentorship intent is expressed.
- Feature test walking a full multi-turn conversation: a paragraph that fills several slots at once, an ambiguous facility name resolved via the shown shortlist, and a bare-number reply.
- `ChatMentorshipSetupTest.php` (21 tests) must remain green, unmodified, since none of its code paths change.

## Non-Goals (explicitly out of scope)

- Making `ChatMentorshipSetup` conversational, LLM-driven, or visually consistent with this redesign.
- Making module selection (including EmONC per-track dates) or mentee search/enrollment conversational — they keep their current UI.
- Any change to slot ordering/validation rules in `MentorshipChatScript.php` — the canonical order is reused as-is, only how it's *presented* changes.
