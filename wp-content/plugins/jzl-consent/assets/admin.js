(function($){
  'use strict';

  function collectOptions(){
    // Read current (unsaved) form values
    function val(name){ return ($('[name="jzl_consent_options['+name+']"]').val() || '').trim(); }
    function checked(name){ return $('[name="jzl_consent_options['+name+']"]').is(':checked'); }
    function pick(name, allowed, fallback){ var v = val(name); return allowed.indexOf(v) >= 0 ? v : fallback; }

    var avatarUrl = $('#jzl-fab-avatar-field .jzl-fab-avatar-preview img').attr('src') || '';
    return {
      policyUrl: val('policy_url') || '#',
      imprintUrl: val('imprint_url') || '#',
      i18n: {
        title: val('txt_title') || 'Wir respektieren deine Privatsphäre',
        text: val('txt_text') || 'Wir verwenden Cookies, um unsere Website zu verbessern. Du kannst selbst entscheiden, welche Kategorien du zulassen möchtest.',
        btnAcceptAll: val('txt_btn_accept_all') || 'Alle akzeptieren',
        btnRejectAll: val('txt_btn_reject_all') || 'Nur Notwendige',
        btnSave: val('txt_btn_save') || 'Auswahl speichern',
        linkPolicy: val('txt_link_policy') || 'Datenschutzerklärung',
        linkImprint: val('txt_link_imprint') || 'Impressum',
        categories: {
          necessary: val('lbl_necessary') || 'Notwendig',
          analytics: val('lbl_analytics') || 'Statistiken',
          marketing: val('lbl_marketing') || 'Marketing',
          functional: val('lbl_functional') || 'Funktional'
        },
        desc: {
          necessary: val('desc_necessary') || 'Erforderlich für die Grundfunktionen der Website.',
          analytics: val('desc_analytics') || 'Hilft uns zu verstehen, wie Besucher die Website nutzen (z. B. GA4).',
          marketing: val('desc_marketing') || 'Wird verwendet, um personalisierte Werbung anzuzeigen.',
          functional: val('desc_functional') || 'Verbessert Funktionen, z. B. Einbettungen.'
        }
      },
      ui: {
        position: pick('ui_position', ['bottom','top'], 'bottom'),
        primaryColor: val('ui_primary_color') || '#7C3AED',
        textColor: val('ui_text_color') || '#111111',
        backgroundColor: val('ui_background_color') || '#ffffff',
        template: pick('ui_template', ['template1','template2','template3','template4'], 'template1')
      },
      fab: {
        show: checked('show_fab'),
        label: val('fab_label') || 'Cookie-Einstellungen',
        position: pick('fab_position', ['left','right'], 'left'),
        avatar: avatarUrl
      },
      showFooterLink: checked('show_footer_link')
    };
  }

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

  function renderPreview(){
    var settings = collectOptions();
    var holder = document.getElementById('jzl-consent-admin-preview');
    if (!holder) return;
    holder.innerHTML = '';

    var root = el('div', { id:'jzl-consent-banner-root', class:'jzl-visible' });
    // Apply colors to preview root
    root.style.setProperty('--jzl-primary', settings.ui.primaryColor);
    root.style.setProperty('--jzl-text', settings.ui.textColor);
    root.style.setProperty('--jzl-bg', settings.ui.backgroundColor);

    var containerClass = 'jzl-consent-container' + (settings.ui.position === 'top' ? ' top' : '') + ' tpl-' + settings.ui.template;
    var container = el('div', { class: containerClass, role:'dialog', 'aria-modal':'true', 'aria-labelledby':'jzl-consent-title' }, [
      el('div', { class:'jzl-consent-inner' }, [
        el('h3', { id:'jzl-consent-title', class:'jzl-consent-title' }, [ settings.i18n.title ]),
        el('p', { class:'jzl-consent-text' }, [ settings.i18n.text ]),
        el('div', { class:'jzl-consent-links' }, [
          el('a', { href: settings.policyUrl || '#', target:'_blank', rel:'noopener' }, [ settings.i18n.linkPolicy ]), document.createTextNode(' · '),
          el('a', { href: settings.imprintUrl || '#', target:'_blank', rel:'noopener' }, [ settings.i18n.linkImprint ])
        ]),
        el('div', { class:'jzl-consent-categories' }, [
          // not interactive in preview
          el('div', { class:'jzl-consent-cat' }, [
            el('div', { class:'jzl-consent-toggle' }, [ el('input', { type:'checkbox', disabled:'disabled', checked:'checked' }), el('label', {}, [ settings.i18n.categories.necessary ]) ]),
            el('p', null, [ settings.i18n.desc.necessary ])
          ]),
          el('div', { class:'jzl-consent-cat' }, [
            el('div', { class:'jzl-consent-toggle' }, [ el('input', { type:'checkbox', disabled:'disabled', checked:'checked' }), el('label', {}, [ settings.i18n.categories.functional ]) ]),
            el('p', null, [ settings.i18n.desc.functional ])
          ]),
          el('div', { class:'jzl-consent-cat' }, [
            el('div', { class:'jzl-consent-toggle' }, [ el('input', { type:'checkbox', disabled:'disabled' }), el('label', {}, [ settings.i18n.categories.analytics ]) ]),
            el('p', null, [ settings.i18n.desc.analytics ])
          ]),
          el('div', { class:'jzl-consent-cat' }, [
            el('div', { class:'jzl-consent-toggle' }, [ el('input', { type:'checkbox', disabled:'disabled' }), el('label', {}, [ settings.i18n.categories.marketing ]) ]),
            el('p', null, [ settings.i18n.desc.marketing ])
          ])
        ]),
        el('div', { class:'jzl-consent-actions' }, [
          el('button', { class:'jzl-btn jzl-btn-outline', type:'button' }, [ settings.i18n.btnRejectAll ]),
          el('button', { class:'jzl-btn', type:'button' }, [ settings.i18n.btnSave ]),
          el('button', { class:'jzl-btn jzl-btn-primary', type:'button' }, [ settings.i18n.btnAcceptAll ])
        ])
      ])
    ]);

    root.appendChild(container);
    holder.appendChild(root);

    // FAB preview
    if (settings.fab.show) {
      var fabChildren = [];
      if (settings.fab.avatar) {
        fabChildren.push(el('img', { class:'jzl-fab-avatar', src: settings.fab.avatar, alt:'' }));
        fabChildren.push(document.createTextNode(' '));
      }
      fabChildren.push(document.createTextNode(settings.fab.label));
      var fab = el('div', { class:'jzl-consent-fab ' + (settings.fab.position === 'right' ? 'jzl-consent-fab-right' : 'jzl-consent-fab-left') }, [
        el('button', { class:'jzl-fab-primary', type:'button' }, fabChildren)
      ]);
      holder.appendChild(fab);
    }
  }

  function bindLivePreview(){
    var deb;
    function queue(){ clearTimeout(deb); deb = setTimeout(renderPreview, 50); }
    // All inputs in our form
    $(document).on('input change', '[name^="jzl_consent_options["]', queue);
    // Color picker events
    if (typeof $.fn.wpColorPicker === 'function') {
      $('.jzl-color').wpColorPicker({
        change: function(){ queue(); },
        clear: function(){ queue(); }
      });
    }
  }

  $(function(){
    bindLivePreview();
    renderPreview();

    // Media uploader for FAB avatar
    var frame;
    $(document).on('click', '.jzl-upload-avatar', function(e){
      e.preventDefault();
      if (frame) { frame.open(); return; }
      frame = wp.media({
        title: 'Avatar auswählen',
        button: { text: 'Verwenden' },
        multiple: false
      });
      frame.on('select', function(){
        var attachment = frame.state().get('selection').first().toJSON();
        var id = attachment.id;
        var url = (attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url);
        var holder = $('#jzl-fab-avatar-field');
        holder.find('input[type="hidden"]').val(id);
        holder.find('.jzl-fab-avatar-preview').html('<img src="'+url+'" alt="" style="width:48px;height:48px;border-radius:50%;object-fit:cover;border:1px solid #ccc;" />');
        holder.find('.jzl-remove-avatar').prop('disabled', false);
        renderPreview();
      });
      frame.open();
    });

    $(document).on('click', '.jzl-remove-avatar', function(e){
      e.preventDefault();
      var holder = $('#jzl-fab-avatar-field');
      holder.find('input[type="hidden"]').val('');
      holder.find('.jzl-fab-avatar-preview').html('<span style="display:inline-block;width:48px;height:48px;border-radius:50%;background:#eee;border:1px dashed #ccc;"></span>');
      $(this).prop('disabled', true);
      renderPreview();
    });
  });
})(jQuery);
