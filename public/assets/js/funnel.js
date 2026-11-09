/*!
 * Funnel starter kit — client script (no dependencies)
 *
 * 1. Attribution: captures UTM parameters, click ids (gclid, fbclid, ...), landing page
 *    and referrer, and puts them in hidden fields on submit.
 * 2. dataLayer events for GTM (see docs/tracking-plan.md):
 *    form_start, form_submit, form_error, cta_click, faq_open, language_switch
 *    (generate_lead is pushed server-side on the thank-you page).
 * 3. Progressive form submit via fetch() with inline errors; falls back to a normal POST.
 * 4. Preview mode (static Claude artifact export): no network, hash-based page switching.
 */
(function () {
  'use strict';

  var root = document.documentElement;
  var dataLayer = (window.dataLayer = window.dataLayer || []);
  var isPreview = root.getAttribute('data-preview') === '1';
  var STORAGE_KEY = 'funnel_attribution';

  function push(event, params) {
    var payload = { event: event };
    for (var k in params) if (Object.prototype.hasOwnProperty.call(params, k)) payload[k] = params[k];
    dataLayer.push(payload);
  }

  function store() {
    try {
      return root.getAttribute('data-attribution-storage') === 'local' ? window.localStorage : window.sessionStorage;
    } catch (e) {
      return null;
    }
  }

  function readCookie(name) {
    var match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[.$?*|{}()[\]\\/+^]/g, '\\$&') + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : '';
  }

  /* 1. Attribution ------------------------------------------------------- */
  var attribution = (function () {
    var params = (root.getAttribute('data-attribution-params') || '').split(',').filter(Boolean);
    var s = store();
    var saved = {};
    try { saved = JSON.parse((s && s.getItem(STORAGE_KEY)) || '{}') || {}; } catch (e) { saved = {}; }

    var url = new URL(window.location.href);
    var fresh = {};
    var hasCampaign = false;
    params.forEach(function (p) {
      var v = url.searchParams.get(p);
      if (v) { fresh[p] = v.slice(0, 500); hasCampaign = true; }
    });

    var data = saved;
    // New campaign click = new last-touch. Otherwise keep what we have (or start fresh).
    if (hasCampaign || !saved.landing_page) {
      data = fresh;
      data.landing_page = (url.origin + url.pathname + url.search).slice(0, 500);
      var ref = document.referrer;
      if (ref && ref.indexOf(window.location.host) === -1) data.referrer = ref.slice(0, 500);
      try { s && s.setItem(STORAGE_KEY, JSON.stringify(data)); } catch (e) { /* storage blocked */ }
    }
    return data;
  })();

  function cookieAttribution() {
    var out = {};
    var fbp = readCookie('_fbp');
    var fbc = readCookie('_fbc');
    if (!fbc && attribution.fbclid) fbc = 'fb.1.' + Date.now() + '.' + attribution.fbclid;
    if (fbp) out.fbp = fbp;
    if (fbc) out.fbc = fbc;
    var ga = readCookie('_ga'); // GA1.1.123456789.1700000000 -> 123456789.1700000000
    if (ga) out.ga_client_id = ga.split('.').slice(-2).join('.');
    var hutk = readCookie('hubspotutk');
    if (hutk) out.hubspotutk = hutk;
    return out;
  }

  function fillAttribution(form) {
    var values = Object.assign({}, attribution, cookieAttribution());
    form.querySelectorAll('[data-attr]').forEach(function (input) {
      input.value = values[input.getAttribute('data-attr')] || '';
    });
    var pageUrl = form.querySelector('input[name="page_url"]');
    if (pageUrl) pageUrl.value = window.location.href.split('#')[0].slice(0, 500);
  }

  /* 2. Generic click tracking -------------------------------------------- */
  document.addEventListener('click', function (e) {
    var el = e.target.closest ? e.target.closest('[data-track]') : null;
    if (!el || el.tagName === 'DETAILS') return;
    push(el.getAttribute('data-track'), {
      label: el.getAttribute('data-track-label') || (el.textContent || '').trim().slice(0, 100),
      link_url: el.getAttribute('href') || undefined
    });
  });

  document.querySelectorAll('details[data-track]').forEach(function (el) {
    el.addEventListener('toggle', function () {
      if (el.open) push(el.getAttribute('data-track'), { label: el.getAttribute('data-track-label') });
    });
  });

  /* 3. Forms --------------------------------------------------------------- */
  function setFieldError(form, name, message) {
    var wrap = form.querySelector('[data-field="' + name + '"]');
    if (!wrap) return;
    var input = wrap.querySelector('input, select, textarea');
    var error = wrap.querySelector('.error');
    if (input) {
      if (message) input.setAttribute('aria-invalid', 'true');
      else input.removeAttribute('aria-invalid');
    }
    if (error) {
      error.textContent = message || '';
      error.hidden = !message;
    }
  }

  function setAlert(form, message) {
    var alert = form.querySelector('.form-alert');
    if (!alert) return;
    alert.textContent = message || '';
    alert.hidden = !message;
  }

  function clearErrors(form) {
    form.querySelectorAll('[data-field]').forEach(function (w) { setFieldError(form, w.getAttribute('data-field'), ''); });
    setAlert(form, '');
  }

  function showErrors(form, errors) {
    var first = null;
    Object.keys(errors).forEach(function (name) {
      if (name === '_form') return setAlert(form, errors[name]);
      setFieldError(form, name, errors[name]);
      if (!first) first = form.querySelector('[data-field="' + name + '"] input, [data-field="' + name + '"] select, [data-field="' + name + '"] textarea');
    });
    if (!errors._form && first) setAlert(form, form.getAttribute('data-msg-check'));
    if (first) first.focus();
  }

  /** Lightweight client-side check (server stays the source of truth). */
  function clientErrors(form) {
    var errors = {};
    form.querySelectorAll('[data-field]').forEach(function (wrap) {
      var name = wrap.getAttribute('data-field');
      var input = wrap.querySelector('input, select, textarea');
      if (!input) return;
      if (input.type === 'radio') {
        if (input.required && !form.querySelector('input[name="' + name + '"]:checked')) errors[name] = form.getAttribute('data-msg-required');
        return;
      }
      var empty = input.type === 'checkbox' ? !input.checked : !String(input.value).trim();
      if (input.required && empty) errors[name] = form.getAttribute('data-msg-required');
      else if (input.type === 'email' && !empty && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.value)) errors[name] = form.getAttribute('data-msg-email');
    });
    return errors;
  }

  function setLoading(form, loading) {
    var btn = form.querySelector('[type="submit"]');
    if (!btn) return;
    if (loading) {
      btn.setAttribute('data-label', btn.textContent);
      btn.textContent = btn.getAttribute('data-loading-text') || btn.textContent;
      btn.disabled = true;
    } else {
      btn.textContent = btn.getAttribute('data-label') || btn.textContent;
      btn.disabled = false;
    }
  }

  document.querySelectorAll('form[data-funnel-form]').forEach(function (form) {
    var formId = form.getAttribute('data-funnel-form');
    var started = false;

    form.addEventListener('focusin', function () {
      if (started) return;
      started = true;
      push('form_start', { form_id: formId });
    });

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      clearErrors(form);
      fillAttribution(form);

      var errors = clientErrors(form);
      if (Object.keys(errors).length) {
        showErrors(form, errors);
        push('form_error', { form_id: formId, error_fields: Object.keys(errors).join(',') });
        return;
      }

      if (isPreview) {
        push('form_submit', { form_id: formId, preview: true });
        window.location.hash = (root.getAttribute('lang') || 'nl') + '/thank-you';
        return;
      }

      if (!window.fetch || !window.FormData) return form.submit();

      setLoading(form, true);
      fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { Accept: 'application/json', 'X-Requested-With': 'fetch' },
        credentials: 'same-origin'
      })
        .then(function (res) { return res.json().then(function (body) { return { status: res.status, body: body }; }); })
        .then(function (r) {
          if (r.body && r.body.ok) {
            push('form_submit', { form_id: formId, event_id: r.body.event_id });
            window.location.href = r.body.redirect;
            return;
          }
          setLoading(form, false);
          var errs = (r.body && r.body.errors) || { _form: form.getAttribute('data-msg-network') };
          showErrors(form, errs);
          push('form_error', { form_id: formId, error_fields: Object.keys(errs).join(','), status: r.status });
        })
        .catch(function () {
          setLoading(form, false);
          setAlert(form, form.getAttribute('data-msg-network'));
          push('form_error', { form_id: formId, error_fields: 'network' });
        });
    });
  });

  /* Thank-you page: strip ?lead= so a refresh doesn't fire generate_lead twice. */
  if (window.history && window.history.replaceState && /[?&]lead=/.test(window.location.search)) {
    var clean = new URL(window.location.href);
    clean.searchParams.delete('lead');
    window.history.replaceState(null, '', clean.pathname + clean.search + clean.hash);
  }

  /* 4. Preview mode (Claude artifact): show one <section data-preview-page> at a time. */
  if (isPreview) {
    var pages = Array.prototype.slice.call(document.querySelectorAll('[data-preview-page]'));
    var current = null;
    var showPage = function (initial) {
      var id = (window.location.hash || '').replace('#', '');
      var target = pages.filter(function (p) { return p.getAttribute('data-preview-page') === id; })[0];
      if (!target) {
        // Not a page id: an in-page anchor such as #form. Scroll within the visible page.
        if (!initial && current) {
          var anchor = current.querySelector('[id="' + id + '"]');
          if (anchor) anchor.scrollIntoView({ behavior: 'smooth' });
          return;
        }
        target = pages[0];
      }
      current = target;
      pages.forEach(function (p) { p.hidden = p !== target; });
      root.setAttribute('lang', target.getAttribute('data-preview-page').split('/')[0]);
      document.querySelectorAll('[data-preview-nav] a').forEach(function (a) {
        a.setAttribute('aria-current', a.getAttribute('href') === '#' + target.getAttribute('data-preview-page') ? 'true' : 'false');
      });
      window.scrollTo(0, 0);
    };
    if (pages.length) {
      window.addEventListener('hashchange', function () { showPage(false); });
      showPage(true);
    }
  }
})();
