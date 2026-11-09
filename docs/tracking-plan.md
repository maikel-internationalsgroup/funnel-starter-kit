# Tracking plan (for the marketing partner)

The funnel only loads **Google Tag Manager** and pushes the events below to the `dataLayer`.
GA4, Meta Pixel, LinkedIn, TikTok, Google Ads conversions and the **cookie consent (Consent Mode v2)**
are configured **in GTM by the marketing partner**. Nothing tracking-related is hard-coded in the pages.

GTM container ID: `config/funnel.json → tracking.gtm_id`

## Page state (every page, before GTM loads)

```js
{
  funnel_name: "spring-campaign",   // config funnel.slug — use as a GTM lookup key
  funnel_version: "1.2.0",
  funnel_step: 1,                   // config pages.{page}.funnel_step
  page_type: "home",                // page key: home, thank-you, ...
  language: "nl"
}
```

Use `funnel_name` + `funnel_step` for funnel reports, and `language` to split results per language.

## Events

| Event | When | Parameters | Suggested use |
|---|---|---|---|
| `form_start` | First focus in a form | `form_id` | GA4 event; drop-off analysis |
| `form_error` | Validation failed (client or server) | `form_id`, `error_fields` (comma list), `status` | GA4 event; fix friction |
| `form_submit` | Server accepted the submission (just before redirect) | `form_id`, `event_id` | Optional GA4 event |
| **`generate_lead`** | Thank-you page load after a **real** submission (signed token, once per lead, no refresh duplicates, not for spam) | `form_id`, `event_id`, `language` | **Main conversion**: GA4 `generate_lead` key event, Meta `Lead`, Google Ads / LinkedIn conversions |
| `cta_click` | Click on an element with `data-track="cta_click"` | `label`, `link_url` | GA4 event |
| `faq_open` | FAQ item opened | `label` | GA4 event |
| `language_switch` | Language switcher clicked | `label` (target language) | GA4 event |

Any element can send its own event: `<a data-track="brochure_download" data-track-label="pdf_nl">`.

## Meta Pixel + Conversions API deduplication

`event_id` on `generate_lead` is the submission ID. It is also stored with the submission and sent to
integrations. Use it as the Pixel `eventID` (and in a server-side CAPI event, if added) so Meta can
deduplicate.

The funnel also captures `_fbp`, `_fbc` (built from `fbclid` when the cookie is missing) and the GA client
ID, and stores them with each lead, for offline conversion uploads.

## Attribution captured with every lead

On landing, `funnel.js` stores these URL parameters (config `tracking.attribution_params`), plus
`landing_page` and an external `referrer`:

`utm_source, utm_medium, utm_campaign, utm_term, utm_content, utm_id, gclid, gbraid, wbraid, fbclid, msclkid, ttclid, li_fat_id`

A new campaign click overwrites the previous one (last touch). Storage is `sessionStorage` by default.
Set `tracking.attribution_storage` to `"local"` to keep it across visits, if consent allows that.
The values appear in the notification e-mail and in every integration (HubSpot field map, webhook,
Blinqx).

## UTM conventions (recommended)

| Parameter | Convention | Example |
|---|---|---|
| `utm_source` | platform, lowercase | `facebook`, `google`, `linkedin`, `newsletter` |
| `utm_medium` | channel type | `paid_social`, `cpc`, `email`, `organic_social` |
| `utm_campaign` | `{funnel-slug}_{yyyy-mm}` | `spring-campaign_2026-03` |
| `utm_content` | creative / variant | `video_a`, `carousel_b` |
| `utm_term` | keyword (search only) | `hypotheek advies` |

## QA before go-live

1. Open the funnel with `?utm_source=test&utm_medium=qa&utm_campaign=qa` in GTM **Preview** mode.
2. Check `form_start` → `form_submit` → `generate_lead` (thank-you page).
3. Refresh the thank-you page: `generate_lead` must **not** fire again.
4. Check that the notification e-mail shows the UTM values.
5. Check the consent banner blocks marketing tags until consent is given.
