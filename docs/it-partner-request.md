# Request to the IT partner — new funnel (sub)domain

Fill in the `{placeholders}` and send. Claude can fill this in for you from `config/funnel.json`
("Fill in the IT partner request for this funnel").

> **One-time improvement worth asking for:** a wildcard such as `*.go.yourcompany.nl` (DNS + wildcard
> SSL certificate) pointing to one hosting account where each funnel gets its own folder. After that,
> new funnels need no ticket at all. See the last section.

---

## Nederlands

**Onderwerp:** Aanvraag nieuw subdomein `{subdomain}` voor funnel "{funnel name}"

Hallo {naam},

Voor een nieuwe campagne willen we graag een landingspagina (funnel) live zetten. Kunnen jullie het volgende voor ons inrichten?

1. **Subdomein:** `{subdomain}` (bijv. `voorjaar.jouwbedrijf.nl`)
2. **SSL-certificaat** (Let's Encrypt is prima), inclusief automatische verlenging en redirect van http naar https.
3. **Document root** van het subdomein naar de map `public/` binnen de funnelmap (bijv. `/domains/{subdomain}/public`).
   Lukt dat niet, dan werkt de hoofdmap ook; wij leveren een `.htaccess` die alles afschermt.
4. **PHP 8.1 of hoger** met de extensies `curl`, `json`, `mbstring` en `openssl`; Apache met `mod_rewrite` en `mod_headers`.
5. **SFTP-account** dat alleen toegang heeft tot de funnelmap (gebruikersnaam/wachtwoord of SSH-key).
6. **Schrijfrechten** voor PHP op de map `storage/` (logs en een back-up van ingezonden formulieren).
7. **E-mail versturen:** SMTP-gegevens voor een afzenderadres, bijv. `no-reply@{domein}`, met **SPF, DKIM en DMARC** ingesteld voor dat domein, zodat bevestigingsmails niet in spam belanden.
8. *(Alleen indien van toepassing)* **WordPress-toegang** voor `{wordpress site}`: een account met rol {rol}.

Gewenste livegang: **{datum}**.

Alvast bedankt!

{naam}

---

## English

**Subject:** New subdomain `{subdomain}` for funnel "{funnel name}"

Hi {name},

We'd like to launch a new landing page (funnel) for a campaign. Could you set up the following?

1. **Subdomain:** `{subdomain}`
2. **SSL certificate** (Let's Encrypt is fine) with auto-renewal and an http → https redirect.
3. **Document root** pointing to the `public/` folder inside the funnel folder (e.g. `/domains/{subdomain}/public`).
   If that isn't possible, the main folder works too; our `.htaccess` blocks everything that must not be public.
4. **PHP 8.1 or newer** with `curl`, `json`, `mbstring`, `openssl`; Apache with `mod_rewrite` and `mod_headers`.
5. An **SFTP account** limited to the funnel folder (password or SSH key).
6. **Write access** for PHP to the `storage/` folder (logs and a backup of form submissions).
7. **Sending e-mail:** SMTP credentials for a sender address such as `no-reply@{domain}`, with **SPF, DKIM and DMARC** configured.
8. *(Only if applicable)* **WordPress access** to `{wordpress site}` with role {role}.

Desired go-live: **{date}**.

Thanks!

{name}

---

## What we need back (checklist)

| Item | Goes into |
|---|---|
| SFTP host, port, user, password/key, remote folder | `.env.deploy` (local, never committed) |
| SMTP host, port, user, password, security | production `.env` on the server |
| Confirmation that DNS + SSL are live | go-live checklist |
| Document root confirmed (`public/` or main folder) | `SFTP_REMOTE_DIR` in `.env.deploy` |

## One-time wildcard setup (recommended)

Ask once for:

- DNS: `*.go.yourcompany.nl` → hosting account (A/CNAME record)
- Wildcard SSL certificate for `*.go.yourcompany.nl`
- Vhost rule: `{name}.go.yourcompany.nl` → `/funnels/{name}/public`
- One SFTP account for `/funnels/`

From then on a new funnel is: create folder → deploy → live, without waiting for a ticket.
