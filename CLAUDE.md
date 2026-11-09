# Instructions for Claude — funnel starter kit

This repo is a PHP funnel (marketing landing page) or the template it was created from.
Read `README.md` for the full picture. These are the house rules.

## Golden rules

1. **No copy in templates.** All visible text lives in `lang/{code}.json`. Templates call `t('key')`,
   `t_list('key')`, and escape with `e()`. Every key must exist in **every** language.
2. **Forms are configuration.** Define fields in `config/funnel.json → forms`, render with
   `partial('form', ['id' => '…', 'formErrors' => $formErrors ?? [], 'formOld' => $formOld ?? []])`.
   Never hand-write a `<form>` that posts elsewhere.
3. **Tracking only via the dataLayer.** Never add gtag, fbq, pixels or third-party scripts to pages. Add
   `data-track="event_name" data-track-label="…"` to elements. New event types must also be added to
   `docs/tracking-plan.md`.
4. **Secrets only in `.env`** (never in config, templates or commits).
5. **No build step.** Plain PHP 8.1+, vanilla JS, one CSS file. No npm, no frameworks, no CDN scripts.
   Composer only for PHPMailer.
6. After every change run `php tools/funnel.php check`. It must pass before committing.

## Common tasks

- **Convert an external/LLM funnel:** follow `docs/converting-llm-funnels.md` (section A).
- **Port a co-worker's artifact edits back:** regenerate `dist/preview.html`, diff against their
  version, and map each `data-preview-page="{lang}/{page}"` block to `templates/pages/{page}.php` +
  `lang/{lang}.json` (section B).
- **Share for review:** `php tools/funnel.php preview`, then publish `dist/preview.html` as an artifact.
- **Add a language:** `php tools/funnel.php init --languages=nl,en,de`, then translate the new
  `lang/de.json` fully (the init copies the default language as a starting point).
- **Add a page:** template in `templates/pages/`, entry in `config.pages` (slug per language,
  `funnel_step`), copy under a new top-level key in every `lang/*.json`.
- **Add an integration:** implement `Funnel\Integrations\Integration`, register it in `Runner::MAP`,
  add its config block + `.env.example` variables, and document it in the README.
- **IT partner request:** fill in `docs/it-partner-request.md` from `config/funnel.json`.

## Git

- Small, descriptive commits (`Add FAQ section`, `Translate DE`, `Port artifact edits from Sanne`).
- Bump `funnel.version` in `config/funnel.json` for every deploy, then tag `vX.Y.Z`.
- Never commit `.env`, `.env.deploy`, `storage/*` or `dist/`.

## Local testing

```bash
php tools/funnel.php serve        # http://localhost:8080  (MAIL_DRIVER=log → storage/logs/mail.log)
```
Form submissions need ≥ 3 seconds between page load and submit (bot protection).
