# New funnel checklist

Copy into the funnel's first GitHub issue, or ask Claude to run through it.

## 1. Intake (with the requester)

- [ ] Goal and main conversion (lead form, call, download…)
- [ ] Funnel name and **subdomain**
- [ ] Languages (default first)
- [ ] Pages / steps
- [ ] Form fields (required / optional), select options
- [ ] Where leads go: mailbox(es), HubSpot, Blinqx Elements, webhook
- [ ] Confirmation e-mail to the visitor? Its text?
- [ ] GTM container ID from the marketing partner
- [ ] Privacy statement URL per language
- [ ] Brand colour / logo
- [ ] Desired go-live date
- [ ] Existing design source (ChatGPT / Codex output, Figma, artifact)

## 2. Setup

- [ ] `./tools/new-funnel.sh …` (creates the private repo, configures and pushes)
- [ ] IT partner request sent (`docs/it-partner-request.md`)
- [ ] Design converted (`docs/converting-llm-funnels.md`)
- [ ] All languages translated, `php tools/funnel.php check` green
- [ ] Preview artifact shared with the requester and approved

## 3. Before go-live

- [ ] DNS + SSL live, document root on `public/`
- [ ] Production `.env` on the server: `APP_ENV=production`, `APP_DEBUG=false`, unique `APP_KEY`,
      `MAIL_DRIVER=smtp`, SMTP credentials, `MAIL_TO`
- [ ] `site.base_url` set to the real domain (canonical/hreflang)
- [ ] `./tools/deploy.sh --dry-run`, then `./tools/deploy.sh`
- [ ] Test submission per language: notification and confirmation arrive (not in spam)
- [ ] Integrations receive the lead (HubSpot / webhook / Blinqx)
- [ ] Tracking QA (see `docs/tracking-plan.md` → QA)
- [ ] `storage/` is writable and **not** reachable from the browser (`/storage/` → 403/404)
- [ ] Mobile check (iPhone + Android)
- [ ] Bump `funnel.version`, `git tag vX.Y.Z && git push --tags`

## 4. After go-live

- [ ] Remove test leads from mailbox / CRM
- [ ] Share the live URL plus UTM conventions with the marketing partner
- [ ] Agree who removes or archives the funnel when the campaign ends
