# Converting funnels from other LLMs, and round-tripping with Claude artifacts

Co-workers often start a funnel in ChatGPT, Codex or another tool, and later tweak it in a Claude
artifact. This kit keeps **one source of truth (this repo)** and treats artifacts as an editable *view*.

```
ChatGPT / Codex HTML ──┐
                       ├──► Claude converts into templates/ + lang/*.json + config ──► GitHub (repo)
Co-worker's artifact ──┘                                                                 │
        ▲                                                                                 ▼
        └──────── php tools/funnel.php preview  →  dist/preview.html  →  Claude artifact ──┘
```

## A. Import an external funnel (ChatGPT, Codex, v0, Lovable…)

Give Claude the source: paste it, attach the files, or share the link. Then ask:

> "Convert this funnel into our starter kit."

Claude follows `CLAUDE.md`:

1. **Pages** → one template per step in `templates/pages/`, registered in `config/funnel.json → pages`.
2. **Copy** → every visible text moves to `lang/{default}.json` (no text in templates), then is translated
   into the other configured languages.
3. **Forms** → field definitions in `config/funnel.json → forms`, rendered with `partial('form', …)`, so
   validation, spam protection, e-mail and integrations come for free. Any external form action
   (Formspree, Google Forms, `mailto:`) is dropped.
4. **Tracking** → hard-coded pixels, gtag or `fbq()` calls are **removed**. Meaningful clicks get
   `data-track` attributes, and GTM handles the rest.
5. **Styling** → reuse the kit's classes where they fit. Funnel-specific CSS goes at the bottom of
   `public/assets/css/main.css` under a `/* funnel: {slug} */` heading. Frameworks loaded from a CDN
   (Tailwind CDN, Bootstrap) are converted to plain CSS.
6. **React/JSX output** (common from v0 or Claude artifacts) is converted to PHP templates plus small
   vanilla JS where interaction is needed. No build step.
7. Run `php tools/funnel.php check` and fix every error.
8. Export a preview (see C) so the co-worker can confirm it still looks right.

## B. Bring a co-worker's artifact edits back

When a co-worker changed the shared preview artifact (in their own Claude chat or another LLM):

> "Here is the edited version of the preview, port the changes back into the repo."

Claude compares it with a fresh `php tools/funnel.php preview` and moves:

- text changes → `lang/{lang}.json` (only the languages that were edited; others get flagged for translation)
- layout and markup changes → `templates/pages/*.php` / partials
- style changes → `main.css`
- new form fields → `config/funnel.json` + labels in every language

Each `<div data-preview-page="{lang}/{page}">` in the preview maps 1:1 to a template and a language file,
which keeps this mapping reliable. Then: check, commit (`"Port artifact edits from {name}"`), push.

## C. Share the current version as an artifact

```bash
php tools/funnel.php preview      # → dist/preview.html
```

Then ask Claude: *"Publish dist/preview.html as an artifact"* and share the link. The preview:

- contains every page in every language (switch via the dark bar on top)
- is fully self-contained (CSS and JS inlined), with **no GTM and no real form posting**: submitting
  jumps to the thank-you page so the flow can be clicked through
- is safe to edit by anyone, because the repo stays the source of truth

## Tips for co-workers who use other LLMs

Ask them to:

- start from the latest **preview HTML** instead of from scratch
- keep the `data-preview-page` wrappers and form `name` attributes intact
- describe tracking wishes in words ("track clicks on the brochure button") rather than adding scripts
