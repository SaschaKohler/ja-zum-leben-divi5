(function(){
  'use strict';

  var SETTINGS = window.JZL_CONSENT_SETTINGS || {};
  var STORAGE_KEY = SETTINGS.storageKey || 'jzl_consent';

  // Helpers
  function getStored() {
    try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null'); } catch(e){ return null; }
  }
  function setStored(v) {
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify(v)); } catch(e){}
  }
  function hasConsent(){ return !!getStored(); }

  // Map categories to Consent Mode v2 keys
  function categoriesToConsent(cats){
    // Default denied for non-essential
    var update = {
      ad_storage: 'denied',
      analytics_storage: 'denied',
      ad_user_data: 'denied',
      ad_personalization: 'denied',
      functionality_storage: 'granted', // allow basic site functionality
      security_storage: 'granted'
    };
    if (cats.analytics) update.analytics_storage = cats.analytics ? 'granted' : 'denied';
    if (cats.marketing) {
      update.ad_storage = cats.marketing ? 'granted' : 'denied';
      update.ad_user_data = cats.marketing ? 'granted' : 'denied';
      update.ad_personalization = cats.marketing ? 'granted' : 'denied';
    }
    if (cats.functional) update.functionality_storage = cats.functional ? 'granted' : 'denied';
    return update;
  }

  function gtag() { (window.dataLayer = window.dataLayer || []).push(arguments); }

  function pushConsentUpdate(cats){
    var update = categoriesToConsent(cats);
    gtag('consent', 'update', update);
    gtag('event', 'jzl_consent_update', { consent: update });
  }

  // UI
  function el(tag, attrs, children){
    var n = document.createElement(tag);
    if (attrs) Object.keys(attrs).forEach(function(k){
      if (k === 'class') n.className = attrs[k];
      else if (k === 'for') n.htmlFor = attrs[k];
      else if (k === 'html') n.innerHTML = attrs[k];
      else n.setAttribute(k, attrs[k]);
    });
    (children || []).forEach(function(c){ if (typeof c === 'string') n.appendChild(document.createTextNode(c)); else if (c) n.appendChild(c); });
    return n;
  }

  function renderBanner() {
    var root = document.getElementById('jzl-consent-banner-root');
    if (!root) return;

    // Apply UI options (colors, position)
    var UI = SETTINGS.ui || {};
    if (UI.primaryColor) root.style.setProperty('--jzl-primary', UI.primaryColor);
    if (UI.textColor) root.style.setProperty('--jzl-text', UI.textColor);
    if (UI.backgroundColor) root.style.setProperty('--jzl-bg', UI.backgroundColor);

    var I = (SETTINGS.i18n || {});
    var title = I.title || 'Wir respektieren deine Privatsphäre';
    var text = I.text || 'Wir verwenden Cookies, um unsere Website zu verbessern. Du kannst selbst entscheiden, welche Kategorien du zulassen möchtest.';
    var btnAcceptAll = I.btnAcceptAll || 'Alle akzeptieren';
    var btnRejectAll = I.btnRejectAll || 'Nur Notwendige';
    var btnSave = I.btnSave || 'Auswahl speichern';
    var linkPolicy = I.linkPolicy || 'Datenschutzerklärung';
    var linkImprint = I.linkImprint || 'Impressum';

    var catLabels = I.categories || { necessary: 'Notwendig', analytics: 'Statistiken', marketing: 'Marketing', functional: 'Funktional' };
    var catDesc = I.desc || {
      necessary: 'Erforderlich für die Grundfunktionen der Website.',
      analytics: 'Hilft uns zu verstehen, wie Besucher die Website nutzen (z. B. GA4).',
      marketing: 'Wird verwendet, um personalisierte Werbung anzuzeigen.',
      functional: 'Verbessert Funktionen, z. B. Einbettungen.'
    };

    var current = getStored();
    var state = current && current.categories ? current.categories : { necessary: true, analytics: false, marketing: false, functional: true };

    function catToggle(key, disabled){
      var id = 'jzl-cat-' + key;
      var input = el('input', { type:'checkbox', id:id, checked: state[key] ? 'checked' : null, disabled: disabled ? 'disabled' : null});
      input.addEventListener('change', function(){ state[key] = !!input.checked; });
      return el('div', { class:'jzl-consent-cat' }, [
        el('div', { class:'jzl-consent-toggle' }, [ input, el('label', { for:id }, [ catLabels[key] || key ]) ]),
        el('p', null, [ catDesc[key] || '' ])
      ]);
    }

    var tpl = (UI.template && typeof UI.template === 'string') ? UI.template : 'template1';
    var containerClass = 'jzl-consent-container' + (UI.position === 'top' ? ' top' : '') + ' tpl-' + tpl;
    var container = el('div', { class: containerClass, role:'dialog', 'aria-modal':'true', 'aria-labelledby':'jzl-consent-title' }, [
      el('div', { class:'jzl-consent-inner' }, [
        el('h3', { id:'jzl-consent-title', class:'jzl-consent-title' }, [ title ]),
        el('p', { class:'jzl-consent-text' }, [ text ]),
        el('div', { class:'jzl-consent-links' }, [
          el('a', { href: SETTINGS.policyUrl || '#', target:'_blank', rel:'noopener' }, [ linkPolicy ]), document.createTextNode(' · '),
          el('a', { href: SETTINGS.imprintUrl || '#', target:'_blank', rel:'noopener' }, [ linkImprint ])
        ]),
        el('div', { class:'jzl-consent-categories' }, [
          catToggle('necessary', true),
          catToggle('functional', false),
          catToggle('analytics', false),
          catToggle('marketing', false)
        ]),
        el('div', { class:'jzl-consent-actions' }, [
          el('button', { class:'jzl-btn jzl-btn-outline', type:'button' }, [ btnRejectAll ]),
          el('button', { class:'jzl-btn', type:'button' }, [ btnSave ]),
          el('button', { class:'jzl-btn jzl-btn-primary', type:'button' }, [ btnAcceptAll ])
        ])
      ])
    ]);

    var btnReject = container.querySelector('.jzl-btn-outline');
    var btnSaveSel = container.querySelector('.jzl-btn:not(.jzl-btn-primary):not(.jzl-btn-outline)');
    var btnAccept = container.querySelector('.jzl-btn-primary');

    function saveAndClose(newState){
      var toSave = { categories: newState || state, ts: Date.now(), version: 'v1' };
      setStored(toSave);
      pushConsentUpdate(toSave.categories);
      close();
    }
    btnReject.addEventListener('click', function(){
      saveAndClose({ necessary: true, functional: true, analytics: false, marketing: false });
    });
    btnSaveSel.addEventListener('click', function(){ saveAndClose(state); });
    btnAccept.addEventListener('click', function(){
      saveAndClose({ necessary: true, functional: true, analytics: true, marketing: true });
    });

    var backdrop = el('div', { class:'jzl-consent-backdrop', 'aria-hidden':'true' });

    function open(){
      root.classList.remove('jzl-hidden');
      root.classList.add('jzl-visible');
      root.appendChild(backdrop);
      root.appendChild(container);
    }
    function close(){
      root.classList.add('jzl-hidden');
      root.classList.remove('jzl-visible');
      try { root.innerHTML = ''; } catch(e){}
    }

    // Expose preferences API
    window.JZLConsent = window.JZLConsent || {};
    window.JZLConsent.openPreferences = open;

    open();
  }

  // Expose a lazy global so external buttons can always open preferences
  window.JZLConsent = window.JZLConsent || {};
  window.JZLConsent.openPreferences = function(){
    // Always render a fresh banner; renderBanner() will open it
    renderBanner();
  };

  function init(){
    // If no stored consent, render banner; if stored, push update to gtag on load
    if (!hasConsent()) {
      renderBanner();
    } else {
      var stored = getStored();
      if (stored && stored.categories) {
        pushConsentUpdate(stored.categories);
      }
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
