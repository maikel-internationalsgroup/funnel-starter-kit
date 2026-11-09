# Funnel Starter Kit

A reusable PHP starter kit for marketing funnels (landing pages with forms). Every new funnel starts
from this template, so the repetitive work comes built in:

- **Multilingual (i18n):** language-prefixed URLs, translated slugs, hreflang/canonical tags, a language switcher, and all copy in JSON files
- **Forms from configuration:** validation, spam protection, accessible inline errors, and a no-JS fallback
- **E-mail:** an internal notification with all fields and campaign attribution, plus a confirmation to the visitor in their own language
- **Integrations:** mailbox (default), a generic webhook (Zapier / Make / n8n / anything), HubSpot, and Blinqx Elements (prepared)
- **Tracking:** GTM plus a clean `dataLayer` contract, UTM and click-ID capture, a refresh-safe `generate_lead` conversion, and a Meta dedup `event_id`
- **Claude workflow:** export the whole funnel as a single-file **artifact preview**, convert funnels made by other LLMs, and port co-workers' artifact edits back
- **GitHub and deployment:** a template repository, CI checks, versioning with tags, and SFTP deploys to the IT partner's server
- **Shared hosting friendly:** plain PHP 8.1+, no framework, no Node or build step, and works next to WordPress

> Status overview: ✅ ready · 🟡 prepared, needs verification · 🗺️ idea for later. See [Roadmap](#roadmap).

---

## Table of contents

1. [Workflow at a glance](#1-workflow-at-a-glance)
2. [Quick start](#2-quick-start)
3. [Project structure](#3-project-structure)
4. [Configuration (`config/funnel.json`)](#4-configuration-configfunneljson)
5. [Environment variables (`.env`)](#5-environment-variables-env)
6. [Internationalisation (i18n)](#6-internationalisation-i18n)
7. [Pages & templates](#7-pages--templates)
8. [Forms](#8-forms)
9. [E-mail](#9-e-mail)
10. [Integrations](#10-integrations)
11. [Tracking & attribution](#11-tracking--attribution)
12. [Working with Claude (artifacts & conversions)](#12-working-with-claude-artifacts--conversions)
13. [GitHub & versioning](#13-github--versioning)
14. [Hosting & deployment (IT partner)](#14-hosting--deployment-it-partner)
15. [WordPress](#15-wordpress)
16. [CLI reference](#16-cli-reference)
17. [Security & privacy (GDPR)](#17-security--privacy-gdpr)
18. [Troubleshooting](#18-troubleshooting)
19. [Roadmap](#roadmap)

---

## 1. Workflow at a glance

```
 Request from co-worker
        │
        ▼
 ① ./tools/new-funnel.sh ──► private GitHub repo from this template, configured, pushed
        │
        ├──► ② docs/it-partner-request.md ──► IT partner: subdomain, SSL, SFTP, SMTP
        │
        ▼
 ③ Design: convert ChatGPT/Codex/artifact output with Claude (CLAUDE.md rules)
        │
        ▼
 ④ php tools/funnel.php preview ──► Claude artifact ──► co-workers review / edit
        │                                    │
        │◄──── Claude ports edits back ──────┘
        ▼
 ⑤ php tools/funnel.php check ──► commit ──► push (CI runs the same check)
        │
        ▼
 ⑥ ./tools/deploy.sh ──► SFTP to IT partner ──► tag vX.Y.Z ──► go-live checklist
```

The full checklist (intake → setup → go-live → after) is in [`docs/checklist.md`](docs/checklist.md).

---

## 2. Quick start

### Requirements

| Where | Needs |
|---|---|
| Your machine | PHP 8.1+, Composer, Git, [GitHub CLI](https://cli.github.com/) (`gh`), `lftp` (for deploys) |
| Server (IT partner) | PHP 8.1+ with `curl`, `json`, `mbstring`, `openssl`; Apache with `mod_rewrite` (+ `mod_headers`) |

### One-time: make this repo a template

GitHub → this repo → **Settings → General → ✅ Template repository**.

### New funnel (automated)

```bash
./tools/new-funnel.sh spring-campaign "Spring Campaign" https://spring.yourcompany.nl nl,en GTM-ABC1234
cd spring-campaign
composer install
php tools/funnel.php serve        # → http://localhost:8080
```

This creates the **private** repo `maikel-internationalsgroup/spring-campaign` from the template, clones
it, fills in `config/funnel.json`, creates `.env` with a fresh `APP_KEY`, runs the checks, commits and
pushes. Set `FUNNEL_GITHUB_OWNER` to create repos under another account or organisation.

### New funnel (manual)

```bash
gh repo create my-org/my-funnel --private --template maikel-internationalsgroup/funnel-starter-kit --clone
cd my-funnel
php tools/funnel.php init --name="My Funnel" --url=https://my.yourcompany.nl --languages=nl,en --gtm=GTM-XXXX
php tools/funnel.php key
composer install
php tools/funnel.php serve
```

Locally, `MAIL_DRIVER=log` writes every e-mail to `storage/logs/mail.log` instead of sending it.

---

## 3. Project structure

```
config/funnel.json        ← everything about this funnel (pages, forms, tracking, integrations)
lang/nl.json, en.json     ← all copy, one file per language
templates/
  layout.php              ← <head>, GTM, hreflang, header/footer
  pages/                  ← one file per funnel step (home, thank-you, 404 …)
  partials/               ← header, footer, form renderer
  emails/                 ← notification (internal) + confirmation (visitor)
  preview.php             ← shell for the single-file artifact preview
public/                   ← DOCUMENT ROOT (the only folder reachable from the web)
  index.php, .htaccess
  assets/css/main.css, assets/js/funnel.js, assets/img/
src/                      ← PHP code (App, Tracking, Forms, Mail, Integrations, Security, Support)
storage/                  ← logs, submission backup, rate-limit data (not in git, not public)
tools/                    ← funnel.php (CLI), deploy.sh, new-funnel.sh, router.php
docs/                     ← IT partner request, tracking plan, conversion guide, checklist
.github/workflows/        ← CI (lint + check + preview) and optional SFTP deploy
CLAUDE.md                 ← house rules Claude follows when working in a funnel repo
.htaccess                 ← fallback if the document root cannot be set to public/
```

---

## 4. Configuration (`config/funnel.json`)

| Key | Meaning |
|---|---|
| `funnel.name / slug / owner / version` | Name for e-mails and previews; slug for tracking (`funnel_name`) and repo name; version for tags |
| `site.base_url` | Production URL (used for canonical and hreflang) |
| `site.base_path` | `""` on a (sub)domain, or e.g. `"/actie"` when the funnel lives in a subfolder |
| `site.brand_color` | Sets the CSS variable `--brand` (buttons, accents, e-mails) |
| `site.logo` | Path under `public/` (falls back to the brand name if the file is missing) |
| `site.privacy_url` | Per language; used in the footer and in the consent checkbox (`{privacy_url}`) |
| `site.noindex` | `true` keeps the whole funnel out of search engines (common for paid-only funnels) |
| `i18n.languages / default` | Supported languages; the first visit to `/` redirects to the browser language or the default |
| `i18n.detect_browser_language` | `false` always redirects `/` to the default language |
| `pages.{key}` | `template`, `slug` (string, or one per language), `funnel_step`, `noindex` |
| `forms.{id}` | `page`, `success_page`, `fields[]`, `integrations[]`, `confirmation_email` (see [Forms](#8-forms)) |
| `integrations.{name}` | Settings per integration; secrets are referenced by env variable name (`*_env`) |
| `mail.from_name / internal_language` | Sender name; language of the internal notification |
| `tracking.gtm_id` | GTM container from the marketing partner |
| `tracking.attribution_storage` | `session` (default) or `local` (keeps UTMs across visits) |
| `tracking.attribution_params` | URL parameters captured with every lead |
| `security.min_fill_seconds` | Minimum seconds between page load and submit (bot protection) |
| `security.rate_limit` | Maximum submissions per IP per time window |
| `storage.store_submissions / keep_days` | Backup of every lead in `storage/submissions`, deleted after N days |

---

## 5. Environment variables (`.env`)

Copy `.env.example` → `.env`. Use **one `.env` per environment**: a local one, and a production one that
you place on the server once. `.env` is never committed and never uploaded by the deploy script.

| Variable | Notes |
|---|---|
| `APP_ENV` | `local` / `production` (production requires a real `APP_KEY`) |
| `APP_DEBUG` | `true` shows errors and missing translation keys; **always `false` in production** |
| `APP_KEY` | Signs form and lead tokens. Generate with `php tools/funnel.php key` |
| `MAIL_DRIVER` | `log` (local), `smtp` (production), `mail` (PHP `mail()`, only if the host supports it) |
| `MAIL_FROM`, `MAIL_TO` | Sender address (must be allowed by SPF/DKIM) and internal recipients (comma-separated) |
| `SMTP_HOST/PORT/USER/PASS/SECURE` | From the IT partner |
| `WEBHOOK_URL`, `WEBHOOK_SECRET` | Generic webhook integration |
| `HUBSPOT_PORTAL_ID`, `HUBSPOT_FORM_GUID`, `HUBSPOT_TOKEN` | HubSpot integration (the token is optional) |
| `BLINQX_ENDPOINT`, `BLINQX_TOKEN` | Blinqx Elements integration |

Deploy credentials live separately in `.env.deploy` (see `.env.deploy.example`).

---

## 6. Internationalisation (i18n)

- **URLs:** `/nl/`, `/en/`, `/nl/bedankt`, `/en/thank-you`. Slugs can differ per language (`pages.*.slug`).
- **Copy:** `lang/{code}.json`, nested keys. In templates:
  - `<?= e(t('home.hero.title')) ?>` for a string
  - `t('emails.confirmation.heading', ['name' => 'Jan'])` for a string with a `{placeholder}`
  - `foreach (t_list('home.benefits') as $item)` for a list or a list of objects (FAQ, steps)
- **Fallback:** a missing key falls back to the default language. With `APP_DEBUG=true` a missing key
  shows as `[key]`.
- **SEO:** `hreflang` for every language plus `x-default`, a canonical URL, and `<html lang>`.
- **E-mails:** the visitor gets the confirmation in their own language. The internal notification uses
  `mail.internal_language`.
- **Add a language:** `php tools/funnel.php init --languages=nl,en,de` copies the default language to
  `lang/de.json`. Translate it (Claude can do this), then run `check`. The check flags missing keys,
  extra keys, and files that are still identical to the default language.

---

## 7. Pages & templates

Templates are plain PHP with a few helpers:

| Helper | Use |
|---|---|
| `e($value)` | HTML-escape (always, except for copy that intentionally contains a link) |
| `t($key, $params)`, `t_list($key)` | Translations |
| `page_url('thank-you')`, `page_url('home', 'en')` | Links between pages / languages |
| `asset('assets/img/hero.webp')` | Versioned asset URL (cache busting) |
| `partial('form', ['id' => 'lead'])` | Render a configured form |
| `config('site.brand_color')` | Read config |

**Add a step:** create `templates/pages/step-2.php`, add `"step-2": {"slug": {"nl": "stap-2", "en": "step-2"}, "funnel_step": 2}`
to `pages`, and add its copy to every language file.

Styling lives in `public/assets/css/main.css`. Design tokens (colours, radius, font) sit at the top, and
component classes (`.hero`, `.card`, `.btn`, `.field`, `.steps`, `.faq`) are kept stable so converted
designs can reuse them.

---

## 8. Forms

Define a form in config, add its labels to the language files, and render it with `partial('form', …)`.

```json
"forms": {
  "lead": {
    "page": "home",
    "success_page": "thank-you",
    "fields": [
      { "name": "first_name", "type": "text", "required": true, "max": 100, "autocomplete": "given-name" },
      { "name": "email", "type": "email", "required": true },
      { "name": "interest", "type": "select", "required": true, "options": ["product_a", "product_b"] },
      { "name": "consent", "type": "checkbox", "required": true }
    ],
    "integrations": ["mailbox", "hubspot"],
    "confirmation_email": true
  }
}
```

```json
"forms": { "lead": {
  "submit": "Verstuur aanvraag", "note": "…",
  "fields": {
    "first_name": { "label": "Voornaam", "placeholder": "Jan", "help": "optional help text" },
    "interest": { "label": "…", "options": { "product_a": "Product A", "product_b": "Product B" } }
  }
}}
```

**Field types:** `text`, `email`, `tel`, `textarea`, `select`, `radio`, `checkbox`, `number`, `date`,
`hidden` (with `value`). **Options:** `required`, `max`, `pattern` (regex), `autocomplete`, `options`.

**What happens on submit** (`POST /submit`):

1. **Spam checks:** a honeypot field, a signed token (stops cross-site posts and bots that submit within
   `min_fill_seconds`), and a per-IP rate limit. A bot that trips the honeypot sees a fake success and
   **does not** trigger a conversion.
2. **Validation** on the server, the source of truth. `funnel.js` also checks the basics before sending.
3. **Backup:** the lead is written to `storage/submissions/YYYY-MM.jsonl` *before* anything else, so a
   failing mailbox or CRM never loses a lead.
4. **Integrations** run in order. A failure is logged and does not block the visitor.
5. **Confirmation e-mail** to the visitor, if enabled.
6. **Response:** JSON for `fetch()` (inline errors, then a redirect), or a normal redirect / re-rendered
   page with errors when JavaScript is off.

**Export leads:** `php tools/funnel.php submissions leads.csv` (semicolon-separated, opens in Excel).

---

## 9. E-mail

| Mail | Template | Language | Notes |
|---|---|---|---|
| Notification | `templates/emails/notification.php` | `mail.internal_language` | All fields with translated labels, the campaign attribution, and the lead ID. **Reply-To = visitor**, so you can answer directly |
| Confirmation | `templates/emails/confirmation.php` | Visitor's language | Texts in `lang/*.json → emails.confirmation` |

Drivers: `log` (local file), `smtp` (PHPMailer, recommended), `mail` (PHP `mail()`).
**Deliverability:** ask the IT partner for SPF, DKIM and DMARC on the sender domain (part of the request template).

---

## 10. Integrations

Enable an integration per form by listing it under `forms.{id}.integrations`. Several can run at once,
e.g. `["mailbox", "hubspot"]`.

| Name | Status | What it does | Setup |
|---|---|---|---|
| `mailbox` | ✅ | Notification e-mail to `MAIL_TO` or `integrations.mailbox.to` | SMTP in `.env` |
| `webhook` | ✅ | POSTs the full lead as JSON. Optional HMAC signature header `X-Funnel-Signature: sha256=…` | `WEBHOOK_URL`, `WEBHOOK_SECRET` |
| `hubspot` | ✅ | HubSpot Forms API v3. Maps fields and UTMs to contact properties, passes the `hubspotutk` cookie and the consent | Create a form in HubSpot; set `HUBSPOT_PORTAL_ID`, `HUBSPOT_FORM_GUID` (+ optional `HUBSPOT_TOKEN` for the secure endpoint); adjust `field_map` |
| `blinqx` | 🟡 | Sends a mapped JSON payload with a bearer token to Blinqx Elements | **The API contract still needs to be confirmed with Blinqx.** Until then, use `webhook` → Make/Zapier/n8n → Elements |

**Webhook payload:**

```json
{
  "id": "8de7a8a1-…", "form_id": "lead", "language": "nl", "created_at": "2026-11-09T10:08:48+01:00",
  "fields": { "first_name": "Jan", "email": "jan@test.nl", "consent": true },
  "attribution": { "utm_source": "google", "utm_campaign": "spring", "landing_page": "…", "fbc": "…" },
  "meta": { "page_url": "…", "user_agent": "…", "ip_anonymized": "84.12.34.0", "funnel": "spring-campaign", "funnel_version": "1.0.0" }
}
```

**Add your own integration** (Salesforce, ActiveCampaign, Google Sheets…): implement
`Funnel\Integrations\Integration`, register it in `Runner::MAP`, add a config block and `.env` variables.
Copy `HubSpotIntegration.php` as an example of a field-mapped connector.

---

## 11. Tracking & attribution

The pages load **only GTM**. GA4, Meta, Google Ads, LinkedIn, TikTok and the **cookie consent** are
managed in GTM by the marketing partner, so nothing needs to change in the funnel when tags change.

- **Page state** on every page: `funnel_name`, `funnel_version`, `funnel_step`, `page_type`, `language`
- **Events:** `form_start`, `form_error`, `form_submit`, **`generate_lead`** (the conversion), `cta_click`,
  `faq_open`, `language_switch`, and any custom `data-track="…"`
- **`generate_lead`** fires on the thank-you page **only after a real submission** (it carries a signed
  token). It does not fire again on refresh, and not for spam.
- **`event_id`** equals the lead ID, so it can be used for Meta Pixel / Conversions API deduplication.
- **Attribution** saved with every lead: UTMs, `gclid`/`gbraid`/`wbraid`, `fbclid` (+ `_fbc`/`_fbp`),
  `msclkid`, `ttclid`, `li_fat_id`, the GA client ID, the landing page and the referrer.

Full contract for the marketing partner, UTM conventions and the QA steps: [`docs/tracking-plan.md`](docs/tracking-plan.md).

---

## 12. Working with Claude (artifacts & conversions)

The repository is the **single source of truth**. Claude artifacts are a shareable, editable *view* of it.

| You want to… | Do this |
|---|---|
| Show the funnel to co-workers | `php tools/funnel.php preview`, then ask Claude: *"Publish dist/preview.html as an artifact"* |
| Convert a ChatGPT / Codex / v0 funnel | Give Claude the source: *"Convert this funnel into our starter kit"* |
| Bring a co-worker's artifact edits back | *"Here's the edited preview, port the changes back into the repo"* |
| Translate | *"Add German and translate all copy"* |
| Write the IT request | *"Fill in the IT partner request for this funnel"* |
| Check readiness | *"Run the go-live checklist"* |

The preview is **one self-contained HTML file** with every page in every language (a switcher bar at the
top), no GTM, and a simulated form submit, so anyone can click through the whole flow.

Claude follows the house rules in [`CLAUDE.md`](CLAUDE.md) automatically when it works in a funnel repo:
no copy in templates, forms as config, tracking only through `data-track`, secrets in `.env`, and `check`
before every commit.

Details: [`docs/converting-llm-funnels.md`](docs/converting-llm-funnels.md).

---

## 13. GitHub & versioning

- **One private repo per funnel**, created from this template (`tools/new-funnel.sh`).
- **Improvements to the kit itself** go into this repo. Funnels created earlier don't update
  automatically. To pull kit updates into an existing funnel:
  ```bash
  git remote add kit https://github.com/maikel-internationalsgroup/funnel-starter-kit.git
  git fetch kit && git merge kit/main --allow-unrelated-histories   # first time; afterwards just merge
  ```
- **Versioning:** bump `funnel.version` in config for every deploy, then `git tag v1.2.0 && git push --tags`.
  The version also travels in the `dataLayer` and with every lead, so you can tell which version converted.
- **Branches (optional):** `main` = live. Use a branch for a bigger change; CI runs on every push.
- **CI** (`.github/workflows/ci.yml`): PHP lint, `funnel.php check`, and a build of the preview,
  downloadable as a build artifact from the Actions tab.

---

## 14. Hosting & deployment (IT partner)

### Request

Send [`docs/it-partner-request.md`](docs/it-partner-request.md) (NL + EN). It asks for:

- the subdomain and SSL
- the document root set to `public/`
- PHP 8.1+
- an SFTP account
- write access to `storage/`
- SMTP with SPF, DKIM and DMARC
- optional WordPress access

It also proposes a **one-time wildcard** (`*.go.yourcompany.nl`) so future funnels need no ticket.

### First deploy

1. Copy `.env.deploy.example` → `.env.deploy` and fill in the SFTP details.
2. Place a production `.env` on the server in the funnel folder (next to `public/`, **not inside it**):
   `APP_ENV=production`, `APP_DEBUG=false`, a new `APP_KEY`, `MAIL_DRIVER=smtp`, and the SMTP and integration secrets.
3. `./tools/deploy.sh --dry-run` → check the list → `./tools/deploy.sh`.

`deploy.sh` runs the checks, installs production Composer packages, and mirrors the project over SFTP
(`lftp`). It skips `.env*`, `storage` data, docs and tools, and removes files you deleted locally.

**Alternative:** `.github/workflows/deploy.yml` deploys from the GitHub Actions tab (manual trigger,
dry-run by default). It needs repository secrets `SFTP_HOST`, `SFTP_PORT`, `SFTP_USER`, `SFTP_PASS`,
`SFTP_REMOTE_DIR`.

### If the document root can't be `public/`

Upload the project as-is. The root `.htaccess` blocks `config/`, `src/`, `storage/`, `.env` and similar,
and forwards everything else to `public/`.

---

## 15. WordPress

Funnels run **next to** WordPress without touching it:

- **Own subdomain** (recommended): fully separate from the WP install.
- **Subfolder of a WP site** (`yourcompany.nl/actie`): upload the funnel into that folder, set
  `site.base_path` to `"/actie"`, and rely on the root `.htaccess` fallback. Real folders take precedence
  over WordPress's rewrite rules.
- **Same tracking:** use the same GTM container as the WP site and filter on `funnel_name`.

🗺️ *Later:* a small WordPress plugin with a `[funnel_form id="lead"]` shortcode that posts to a funnel
endpoint, for forms on existing WP pages.

---

## 16. CLI reference

```bash
php tools/funnel.php init --name="…" [--slug=…] [--url=…] [--languages=nl,en] [--default=nl] [--gtm=GTM-…] [--color=#…]
php tools/funnel.php key                  # new APP_KEY in .env
php tools/funnel.php check                # config, translations, templates, integration settings
php tools/funnel.php serve [port]         # local server (default 8080)
php tools/funnel.php preview [--out=…]    # dist/preview.html for a Claude artifact
php tools/funnel.php submissions [file]   # CSV export of stored leads
./tools/new-funnel.sh <repo> "<name>" <url> [langs] [gtm]
./tools/deploy.sh [--dry-run]
```

---

## 17. Security & privacy (GDPR)

- Only `public/` is web-accessible. Config, code, logs and leads live outside it, and `storage/` has its
  own deny rule as a second layer.
- Secrets only in `.env`. Forms are protected by a signed token, a honeypot, a minimum fill time and a
  rate limit.
- Input is validated server-side and stripped of control characters (no e-mail header injection), and
  output is HTML-escaped.
- IP addresses are stored **anonymised** (last octet zeroed).
- Lead backups are deleted automatically after `storage.keep_days` (default 90). Set
  `store_submissions: false` if leads may only live in the CRM or mailbox.
- Security headers (`nosniff`, `Referrer-Policy`, `X-Frame-Options`, `Permissions-Policy`), forced HTTPS,
  and long caching for assets.
- **Cookie consent** is handled by the marketing partner via GTM. Attribution uses `sessionStorage` by
  default (no cross-visit storage).
- Mention the funnel and its processors (mailbox, HubSpot, Blinqx) in the privacy statement.

---

## 18. Troubleshooting

| Symptom | Cause / fix |
|---|---|
| "The page has expired" when submitting | Submitted within `min_fill_seconds`, the page was open longer than 24 hours, or `APP_KEY` changed. Refresh the page |
| `[home.hero.title]` visible on the page | Missing translation key (shown with `APP_DEBUG=true`). Run `php tools/funnel.php check` |
| No e-mails arrive | Check `MAIL_DRIVER`, the SMTP settings, and `storage/logs/funnel-YYYY-MM.log`. The lead is still in `storage/submissions/` |
| Mails land in spam | SPF/DKIM/DMARC missing for the `MAIL_FROM` domain (IT partner) |
| "PHPMailer missing" | Run `composer install --no-dev` before deploying (`deploy.sh` does this) |
| 404 on every page except `/` | `mod_rewrite` is off or `.htaccess` is ignored (`AllowOverride`). Ask the IT partner |
| `generate_lead` missing in GTM preview | The thank-you page was opened directly, without a submission. That's expected |
| Integration failed | The visitor still sees success. Check the log and resend from `storage/submissions` |

---

## Roadmap

| Idea | Status |
|---|---|
| Blinqx Elements: confirm the API contract and finish the connector | 🟡 |
| Server-side Meta Conversions API / GA4 Measurement Protocol for `generate_lead` | 🗺️ |
| WordPress shortcode plugin for funnel forms on existing WP pages | 🗺️ |
| Multi-step forms (wizard) with a `form_step` event per step | 🗺️ |
| A/B variants via config (`variant` in the dataLayer) | 🗺️ |
| Resend failed integrations from the CLI (`funnel.php retry`) | 🗺️ |
| Playwright smoke test in CI (submit + dataLayer assertions) | 🗺️ |
| A "funnel-builder" Claude skill so artifact-only sessions follow the same rules | 🗺️ |
