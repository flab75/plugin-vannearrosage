/* Vanne d'arrosage — rendu de la tuile. Recopié dans chaque tuile par vannearrosage::toHtml(),
   chargé une seule fois par page. Textes entre doubles accolades traduits côté PHP (core/i18n). */
if (!window.vannearrosage) (function () {
  var css = document.createElement('style')
  css.textContent =
    '.tu{box-sizing:border-box;width:100%;display:flex;flex-direction:column;align-items:center;gap:3px;padding:6px 8px 12px;text-align:center;min-width:174px}' +
    '.tu-ic{font-size:28px;opacity:.55;margin:4px 0 2px}.tu-ok .tu-ic{opacity:1;color:#e8a23a}' +
    '.tu-big{font-size:22px;font-weight:300}' +
    '.tu-l{font-size:12px;opacity:.75;white-space:pre-wrap}.tu-l:empty,.tu-al:empty{display:none}' +
    '.tu-al{font-size:11px;font-weight:600;color:#fff;background:#b3261e;border-radius:9px;padding:1px 9px;margin-top:2px}' +
    '.tu-jg{display:grid;grid-template-columns:auto 1fr auto;gap:3px 6px;align-items:center;width:160px;font-size:11px;margin-top:4px}' +
    '.tu-jg span,.tu-jg em{opacity:.8}.tu-jg i{height:6px;background:rgba(128,128,128,.3);border-radius:3px;overflow:hidden}' +
    '.tu-jg b{display:block;height:100%}.tu-jg em{font-style:normal;text-align:right}' +
    '.tu-bt{cursor:pointer;border-radius:12px;padding:2px 10px;font-size:12px;background:rgba(128,128,128,.2);border:0;color:inherit}'
  document.head.appendChild(css)
  function esc(s) { return String(s === undefined || s === null ? '' : s).replace(/[&<>"']/g, function (x) { return '&#' + x.charCodeAt(0) + ';' }) }
  function f1(x) { return x === undefined || x === '' || x === null ? '–' : (+x).toFixed(1).replace('.', ',') }
  function barre(nom, p, t, coul) { return '<span>' + esc(nom) + '</span><i><b style="width:' + Math.max(0, Math.min(100, p)) + '%;background:' + coul + '"></b></i><em>' + esc(t) + '</em>' }

  var R = {}

  R.vanne = {ic: 'faucet', rendu: function (b, a) {
    var DUREE = 'json::{"manual_irrigation_duration":"#slider#"}'
    var durees = String(a.cf.durees).split(',').map(Number).filter(Boolean), rapide = +a.cf.rapide || 0
    function x(k, o) { var c = a.c(k); if (c) jeedom.cmd.execute({id: c.id, value: o || {}}) }
    b.querySelector('.tu-al').insertAdjacentHTML('beforebegin',
      '<div style="display:flex;gap:6px;flex-wrap:wrap;justify-content:center;margin-top:4px">' +
      '<span class="tu-bt tu-ov"></span>' +
      '<select class="tu-bt tu-du"><option hidden></option>' + durees.map(function (m) { return '<option value="' + m + '" style="color:#fff;background:#333">' + m + ' min</option>' }).join('') + '</select>' +
      (rapide ? '<span class="tu-bt tu-ra">' + "{{Arroser}} " + rapide + ' min</span>' : '') +
      '<span class="tu-bt tu-pg"><i class="fas fa-calendar-alt"></i> ' + "{{Programme}}" + '</span></div>')
    // la tuile Jeedom réagit aux clics (déplacement en mode édition) : on les garde pour nous
    ;['click', 'mousedown', 'pointerdown'].forEach(function (e) { b.addEventListener(e, function (ev) { ev.stopPropagation() }) })
    var du = b.querySelector('.tu-du')
    // ouvrir / fermer passe par le plugin, qui vérifie chaque minute que la vanne a suivi (renvoi, puis alerte)
    function ordre(etat, duree) {
      var f = new FormData()
      f.append('action', 'commande'); f.append('id', a.id); f.append('etat', etat); f.append('duree', duree || 0); f.append('jeedom_token', window.JEEDOM_AJAX_TOKEN)
      fetch('plugins/vannearrosage/core/ajax/vannearrosage.ajax.php', {method: 'POST', body: f}).then(function (r) { return r.json() }).then(function (r) {
        if (r.state != 'ok') throw new Error(r.result)
        setTimeout(function () { // 1re vérification à l'écran ; le plugin renvoie l'ordre lui-même
          if (b.isConnected && (a.v('state') == 1) != (etat == 1)) alerte("{{La vanne n'a pas encore confirmé : le plugin renvoie l'ordre chaque minute.}}", 'warning')
        }, 20000)
      }).catch(function (e) { alerte(e.message, 'danger') })
    }
    b.querySelector('.tu-ov').onclick = function () { ordre(a.v('state') == 1 ? 0 : 1) } // ouvre pour la durée réglée
    du.onchange = function () { x(DUREE, {slider: du.value}) }
    if (rapide) b.querySelector('.tu-ra').onclick = function () { ordre(1, rapide) }
    b.querySelector('.tu-pg').onclick = function () { prog(a) }
    function maj() {
      var o = a.v('state') == 1, mn = a.v('real_time_irrigation_duration'), l = a.v('real_time_irrigation_volume'), ab = a.v('valve_abnormal_state')
      b.classList.toggle('tu-ok', o)
      b.querySelector('.tu-ic').style.color = o ? '#2b8be6' : ''
      a.txt('.tu-big', o ? "{{Ouverte}}" : "{{Fermée}}")
      a.txt('.tu-ov', o ? "{{Fermer}}" : "{{Ouvrir}}")
      a.txt('.tu-l', mn === undefined || mn === null ? '' : "{{Arrosé}} " + mn + ' min' + (l === undefined || l === null ? '' : ' · ' + l + ' L'))
      a.txt('.tu-l2', a.v('battery') === undefined || a.v('battery') === null ? '' : "{{Batterie}} " + a.v('battery') + ' %')
      a.txt('.tu-al', a.alerte || (ab && ab != 'normal' ? "{{Vanne}} : " + ab : ''))
      du.options[0].textContent = (a.v('manual_irrigation_duration') || '–') + ' min ▾'; du.selectedIndex = 0
    }
    a.s(['state', 'real_time_irrigation_duration', 'real_time_irrigation_volume', 'valve_abnormal_state', 'battery', 'manual_irrigation_duration'], maj)
  }}

  // même règle que vannearrosage::progOk() (à garder identiques)
  function match(p, d) {
    var h = d.getHours(), m = d.getMinutes(), hm = p.heure.split(':'), H = +hm[0], M = +hm[1]
    if (p.fin && d.getTime() > p.fin) return false
    if (p.freq == 'h') { var F = p.heureFin.split(':'); return m == M && h >= H && h * 60 + m <= +F[0] * 60 + +F[1] }
    if (h != H || m != M) return false
    return p.freq == 'j' || p.jours.indexOf(((d.getDay() + 6) % 7) + 1) >= 0
  }
  function prochain(p) {
    if (!p.actif) return "{{aucun (programme désactivé)}}"
    var d = new Date(); d.setSeconds(0, 0)
    for (var i = 1; i < 8 * 1440; i++) {
      d.setTime(d.getTime() + 60000)
      if (match(p, d)) return d.toLocaleString(undefined, {weekday: 'long', day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit'})
    }
    return "{{aucun dans les 7 jours}}"
  }
  function alerte(m, l) { try { jeedomUtils.showAlert({message: m, level: l}) } catch (e) { alert(m) } } // mobile : showAlert sans this
  // fenêtre « Programme » de la vanne ; enregistré dans la configuration de l'équipement, exécuté par vannearrosage::cron()
  function prog(a) {
    var J = ["{{L}}", "{{Ma}}", "{{Me}}", "{{J}}", "{{V}}", "{{S}}", "{{D}}"]
    var p = Object.assign({actif: 0, freq: 'j', heure: '07:00', heureFin: '20:00', jours: [1, 3, 5], duree: 10, fin: 0}, a.prog || {})
    var old = document.getElementById('tuProgBox')
    if (old) old.remove()
    var css = 'background:#2a2a2a;color:#eee;border:1px solid #555;border-radius:4px;padding:2px 4px;'
    var box = document.createElement('div')
    box.id = 'tuProgBox'
    box.style.cssText = 'position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center'
    box.innerHTML =
      '<div style="background:#1e1e1e;color:#eee;padding:16px 20px;border-radius:10px;width:320px;max-width:92vw;font-size:14px;line-height:2;text-align:left">' +
      '<div style="font-size:17px;font-weight:bold;margin-bottom:6px"><i class="fas fa-calendar-alt" style="color:#2b8be6"></i> ' + "{{Programme d'arrosage}}" + '</div>' +
      '<label><input type="checkbox" id="tpActif"> ' + "{{Programme actif}}" + '</label><br>' +
      "{{Répéter}}" + ' : <select id="tpFreq" style="' + css + '"><option value="h">' + "{{toutes les heures}}" + '</option><option value="j">' + "{{tous les jours}}" + '</option><option value="s">' + "{{certains jours}}" + '</option></select><br>' +
      '<span id="tpLblHeure"></span> <input type="time" id="tpHeure" style="' + css + '"> <span id="tpPlage">' + "{{jusqu'à}}" + ' <input type="time" id="tpHeureFin" style="' + css + '"></span><br>' +
      '<div id="tpJours">' + J.map(function (j, i) { return '<label style="margin-right:6px"><input type="checkbox" value="' + (i + 1) + '"> ' + j + '</label>' }).join('') + '</div>' +
      "{{Durée}}" + ' : <select id="tpDuree" style="' + css + '">' + [1, 2, 5, 10, 15, 20, 30, 45, 60].map(function (m) { return '<option value="' + m + '">' + m + ' min</option>' }).join('') + '</select><br>' +
      "{{Pendant}}" + ' : <select id="tpPendant" style="' + css + '"><option value="0">' + "{{toujours}}" + '</option><option value="3600000">' + "{{1 heure}}" + '</option><option value="86400000">' + "{{1 jour}}" + '</option><option value="604800000">' + "{{1 semaine}}" + '</option><option value="2592000000">' + "{{1 mois}}" + '</option><option value="garder">' + "{{(inchangé)}}" + '</option></select>' +
      '<div id="tpFin" style="font-size:12px;color:#aaa"></div>' +
      '<div style="margin-top:6px;font-size:13px">' + "{{Prochain arrosage}}" + ' : <b id="tpNext"></b></div>' +
      '<div style="text-align:right;margin-top:10px"><button id="tpAnnuler" class="btn btn-default btn-sm">' + "{{Annuler}}" + '</button> <button id="tpOk" class="btn btn-success btn-sm">' + "{{Enregistrer}}" + '</button></div></div>'
    document.body.appendChild(box)
    var $ = function (id) { return document.getElementById(id) }
    $('tpActif').checked = !!p.actif
    $('tpFreq').value = p.freq
    $('tpHeure').value = p.heure
    $('tpHeureFin').value = p.heureFin
    $('tpDuree').value = p.duree
    $('tpJours').querySelectorAll('input').forEach(function (c) { c.checked = p.jours.indexOf(+c.value) >= 0 })
    if (p.fin) { $('tpPendant').value = 'garder'; $('tpFin').textContent = "{{Fin}} : " + new Date(p.fin).toLocaleString() }
    function lire() {
      var q = {
        actif: $('tpActif').checked ? 1 : 0, freq: $('tpFreq').value, heure: $('tpHeure').value || '07:00',
        heureFin: $('tpHeureFin').value || '20:00', duree: +$('tpDuree').value,
        jours: [].slice.call($('tpJours').querySelectorAll('input:checked')).map(function (c) { return +c.value }),
        fin: $('tpPendant').value == 'garder' ? p.fin : (+$('tpPendant').value ? Date.now() + +$('tpPendant').value : 0)
      }
      $('tpLblHeure').textContent = q.freq == 'h' ? "{{De}}" : "{{À}}"
      $('tpPlage').style.display = q.freq == 'h' ? '' : 'none'
      $('tpJours').style.display = q.freq == 's' ? '' : 'none'
      $('tpNext').textContent = prochain(q)
      return q
    }
    box.addEventListener('input', lire)
    box.addEventListener('change', lire)
    lire()
    box.addEventListener('click', function (e) { e.stopPropagation(); if (e.target === box) box.remove() })
    $('tpAnnuler').onclick = function () { box.remove() }
    $('tpOk').onclick = function () {
      var q = lire(), f = new FormData()
      f.append('action', 'saveProg'); f.append('id', a.id); f.append('prog', JSON.stringify(q)); f.append('jeedom_token', window.JEEDOM_AJAX_TOKEN)
      fetch('plugins/vannearrosage/core/ajax/vannearrosage.ajax.php', {method: 'POST', body: f}).then(function (r) { return r.json() }).then(function (r) {
        if (r.state != 'ok') throw new Error(r.result)
        a.prog = r.result
        box.remove()
        alerte("{{Programme enregistré, prochain arrosage}} : " + prochain(r.result), 'success')
      }).catch(function (e) { alerte(e.message, 'danger') })
    }
  }

  var SUBS = {} // id de commande → [{b, f}] ; jeedom.cmd.addUpdateFunction écarte une fonction au même texte qu'une déjà inscrite
  function suivre(id, b, f) {
    if (!SUBS[id]) {
      SUBS[id] = []
      jeedom.cmd.addUpdateFunction(id, function (o) {
        SUBS[id] = SUBS[id].filter(function (s) { return s.b.isConnected })
        SUBS[id].forEach(function (s) { s.f(o.value) })
      })
    }
    SUBS[id].push({b: b, f: f})
  }

  window.vannearrosage = {init: function (b, d) {
    var r = R[d.t]
    if (!b || !r) { if (b) b.textContent = "{{Choisissez le type de la tuile dans sa configuration.}}"; return }
    b.innerHTML = '<i class="tu-ic fas fa-' + r.ic + '"></i><b class="tu-big"></b><span class="tu-l"></span><span class="tu-l tu-l2"></span><span class="tu-al"></span>'
    function c(k) { return d.k[k] ? d.c[d.k[k]] : undefined }
    r.rendu(b, {
      id: d.id, cf: d.cf, prog: d.prog, alerte: d.alerte,
      c: c,                                                      // commande : {id, n: nom, u: unité, v: valeur}
      v: function (k) { var x = c(k); return x ? x.v : undefined },
      s: function (ks, fn) { // suit les commandes ks en direct
        ks.forEach(function (k) {
          var x = c(k)
          if (x) suivre(x.id, b, function (v) { x.v = v; fn() })
        })
        fn()
      },
      txt: function (sel, t) { var e = b.querySelector(sel); if (e) e.textContent = t }
    })
  }}
})()
