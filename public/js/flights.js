/* Travelety flight results: load, filter, sort, fare selection. */
(function () {
  var app = document.getElementById('flApp');
  if (!app) return;
  var endpoint = app.dataset.endpoint, qs = JSON.parse(app.dataset.qs || '{}'), selectUrl = app.dataset.select, csrf = app.dataset.csrf;
  var pax = +app.dataset.pax || 1, back = app.dataset.back;
  var $ = function (s, el) { return (el || document).querySelector(s); }, $$ = function (s, el) { return [].slice.call((el || document).querySelectorAll(s)); };
  var list = $('#flList'), more = $('#flMore'), count = $('#flCount'), prog = $('#flProg');
  var all = [], view = [], shown = 0, PAGE = 15, hash = '', cur = '';
  var st = { stops: [], maxPrice: null, bag: false, refund: false, change: false, slots: [[], []], maxDur: null, air: [], sort: 'best' };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var fmt = function (n, d) { return cur + ' ' + Number(n).toLocaleString('en-US', { minimumFractionDigits: d === 0 ? 0 : 2, maximumFractionDigits: d === 0 ? 0 : 2 }); };
  var hm = function (m) { if (m == null) return '–'; return Math.floor(m / 60) + 'h ' + (m % 60 ? (m % 60) + 'm' : ''); };
  var tm = function (iso) { return iso ? iso.substr(11, 5) : ''; };
  var dshort = function (iso) { var d = new Date(iso.substr(0, 10) + 'T00:00:00'); return d.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' }); };
  var slot = function (iso) { var h = +iso.substr(11, 2); return h < 6 ? 0 : h < 12 ? 1 : h < 18 ? 2 : 3; };

  /* ---------- load ---------- */
  function load() {
    var w = 0; prog.hidden = false; var bar = prog.querySelector('i'); bar.style.width = '5%';
    var t = setInterval(function () { w = Math.min(92, w + (92 - w) * .08); bar.style.width = w + '%'; }, 250);
    fetch(endpoint + '?' + new URLSearchParams(qs).toString(), { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        clearInterval(t); bar.style.width = '100%'; setTimeout(function () { prog.hidden = true; }, 300);
        hash = d.hash || ''; all = d.journeys || [];
        if (!all.length) { list.innerHTML = empty(d.error ? 'Something went wrong' : 'No flights found', esc(d.error || 'Try different dates or a nearby airport.')); count.textContent = ''; return; }
        cur = all[0].currency;
        all.forEach(function (j, i) {
          j.idx = i;
          j.bag = j.offers.some(function (o) { return o.checked; });
          j.refund = j.offers.some(function (o) { return o.refundable; });
          j.change = j.offers.some(function (o) { return o.changeable; });
          j.codes = j.airlines.map(function (a) { return a.code; });
        });
        var cheap = Math.min.apply(null, all.map(function (j) { return j.price; })), fast = Math.min.apply(null, all.map(function (j) { return j.min; }));
        all.forEach(function (j) { j.score = j.price / cheap * 0.6 + j.min / fast * 0.4 + (j.stops * 0.08) - (j.best ? 0.15 : 0); });
        setupRanges(); airlines(); counts(); apply();
      })
      .catch(function () { clearInterval(t); prog.hidden = true; list.innerHTML = empty('Connection problem', 'Please check your connection and try again.'); });
  }
  function empty(h, p) { return '<div class="hs-empty"><h3>' + h + '</h3><p>' + p + '</p></div>'; }

  /* ---------- ranges + airlines ---------- */
  var pMin = 0, pMax = 0, dMin = 0, dMax = 0;
  function setupRanges() {
    var ps = all.map(function (j) { return j.price; }), ds = all.map(function (j) { return j.min; });
    pMin = Math.floor(Math.min.apply(null, ps)); pMax = Math.ceil(Math.max.apply(null, ps));
    dMin = Math.min.apply(null, ds); dMax = Math.max.apply(null, ds);
    var fp = $('#fPrice'); fp.min = pMin; fp.max = pMax; fp.step = Math.max(1, Math.round((pMax - pMin) / 100)); fp.value = pMax;
    var fd = $('#fDur'); fd.min = dMin; fd.max = dMax; fd.step = 15; fd.value = dMax;
    $('#fPriceL').textContent = fmt(pMax, 0); $('#fDurL').textContent = hm(dMax);
  }
  function airlines() {
    var m = {};
    all.forEach(function (j) { j.airlines.forEach(function (a) { if (!m[a.code] || j.price < m[a.code].p) m[a.code] = { n: a.name, l: a.logo, p: Math.min(j.price, m[a.code] ? m[a.code].p : Infinity) }; }); });
    var keys = Object.keys(m).sort(function (a, b) { return m[a].p - m[b].p; });
    $('#fAir').innerHTML = keys.map(function (k) {
      return '<label class="fchk"><input type="checkbox" data-f="air" value="' + esc(k) + '">' + (m[k].l ? '<img class="alogo-s" src="' + esc(m[k].l) + '" alt="">' : '') + esc(m[k].n) + '<em>' + fmt(m[k].p, 0) + '</em></label>';
    }).join('');
    $$('#fAir input').forEach(bind);
  }

  /* ---------- filter ---------- */
  function pass(j, skip) {
    if (st.stops.length && skip !== 'stops' && st.stops.indexOf(Math.min(2, j.stops)) < 0) return false;
    if (st.maxPrice !== null && skip !== 'price' && j.price > st.maxPrice) return false;
    if (st.bag && skip !== 'bag' && !j.bag) return false;
    if (st.refund && skip !== 'refund' && !j.refund) return false;
    if (st.change && skip !== 'change' && !j.change) return false;
    if (st.maxDur !== null && skip !== 'dur' && j.min > st.maxDur) return false;
    if (st.air.length && skip !== 'air' && !j.codes.some(function (c) { return st.air.indexOf(c) >= 0; })) return false;
    for (var l = 0; l < 2; l++) if (st.slots[l].length && j.legs[l] && st.slots[l].indexOf(slot(j.legs[l].dep)) < 0) return false;
    return true;
  }
  function counts() {
    $$('[data-c]').forEach(function (em) {
      var k = em.dataset.c.split(':'), f = k[0], v = +k[1], n = 0;
      all.forEach(function (j) { if (!pass(j, f)) return; if (f === 'stops' ? Math.min(2, j.stops) === v : j[f]) n++; });
      em.textContent = n; em.parentNode.classList.toggle('zero', n === 0);
    });
  }
  function apply() {
    view = all.filter(function (j) { return pass(j); });
    var by = {
      best: function (a, b) { return a.score - b.score; },
      cheap: function (a, b) { return a.price - b.price || a.min - b.min; },
      fast: function (a, b) { return a.min - b.min || a.price - b.price; }
    };
    ['best', 'cheap', 'fast'].forEach(function (s) {
      var top = view.slice().sort(by[s])[0], el = $('#flSorts [data-s="' + s + '"] span');
      el.textContent = top ? fmt(top.price, 0) + ' · ' + hm(top.min) : '–';
    });
    view.sort(by[st.sort]);
    count.textContent = view.length + ' of ' + all.length + ' flights · prices for ' + pax + ' traveller' + (pax > 1 ? 's' : '') + ', taxes included';
    shown = 0; list.innerHTML = view.length ? '' : empty('No flights match your filters', 'Try removing a filter.');
    page(); chips();
  }
  function page() {
    var next = view.slice(shown, shown + PAGE);
    list.insertAdjacentHTML('beforeend', next.map(card).join(''));
    shown += next.length; more.hidden = shown >= view.length;
    if (!more.hidden) more.textContent = 'Show more flights (' + (view.length - shown) + ' left)';
  }

  /* ---------- cards ---------- */
  function legRow(l) {
    var logos = l.carriers.slice(0, 2).map(function (c) { return c.logo ? '<img src="' + esc(c.logo) + '" alt="' + esc(c.name) + '">' : ''; }).join('');
    var stops = l.stops === 0 ? '<span class="dir">Direct</span>' : '<span class="st">' + l.stops + ' stop' + (l.stops > 1 ? 's' : '') + ' · ' + l.layovers.map(function (x) { return esc(x.code); }).join(', ') + '</span>';
    return '<div class="leg"><div class="alogo">' + logos + '</div>' +
      '<div class="lt"><b>' + tm(l.dep) + '</b><span>' + esc(l.from) + ' · ' + dshort(l.dep) + '</span></div>' +
      '<div class="lmid"><small>' + hm(l.min) + '</small><div class="line">' + (l.stops ? '<i></i>'.repeat(Math.min(3, l.stops)) : '') + '</div>' + stops + '</div>' +
      '<div class="lt r"><b>' + tm(l.arr) + (l.dayChange ? '<sup>+' + l.dayChange + '</sup>' : '') + '</b><span>' + esc(l.to) + '</span></div>' +
      '<div class="lair">' + esc(l.carriers.map(function (c) { return c.name; }).join(', ')) + '</div></div>';
  }
  function card(j) {
    var o = j.offers[0];
    var tags = (o.checked ? '<span class="t g">🧳 ' + o.checked.pieces + '×' + (o.checked.kg ? o.checked.kg + 'kg' : 'bag') + ' included</span>' : (j.bag ? '<span class="t">Bags on higher fares</span>' : '<span class="t">Cabin bag only</span>')) +
      (o.refundable ? '<span class="t g">Refundable</span>' : '') + (o.seats && o.seats <= 4 ? '<span class="t o">' + o.seats + ' seats left</span>' : '');
    return '<div class="fcard' + (j.best && st.sort === 'best' ? ' bestc' : '') + '" data-k="' + esc(j.key) + '">' +
      '<div class="fc-main"><div class="fc-legs">' + j.legs.map(legRow).join('') +
      '<div class="fc-tags">' + tags + '<button type="button" class="linkbtn fc-det">Flight details</button></div></div>' +
      '<div class="fc-price"><b>' + fmt(j.price) + '</b><small>' + (pax > 1 ? fmt(o.adult) + ' per adult' : 'total, taxes included') + '</small>' +
      (j.offers.length > 1 ? '<small class="ff">' + j.offers.length + ' fare options</small>' : '<small class="ff">' + esc(o.family) + '</small>') +
      '<button type="button" class="hbtn hbtn-orange fc-sel">Select</button></div></div>' +
      '<div class="fc-x" hidden></div></div>';
  }
  function details(j) {
    return '<div class="fdet">' + j.legs.map(function (l) {
      return '<div class="fdl"><h5>' + (l.dir === 'INBOUND' ? 'Return' : 'Outbound') + ' · ' + dshort(l.dep) + ' · ' + hm(l.min) + '</h5>' +
        l.segments.map(function (s, i) {
          var lay = l.layovers[i] ? '<div class="lay">⏱ ' + hm(l.layovers[i].min) + ' layover in ' + esc(l.layovers[i].name || l.layovers[i].code) + (l.layovers[i].changeAirport ? ' · <b>change airport</b>' : '') + (l.layovers[i].overnight ? ' · overnight' : '') + '</div>' : '';
          return '<div class="seg">' + (s.logo ? '<img src="' + esc(s.logo) + '" alt="">' : '') + '<div><p><b>' + tm(s.dep) + '</b> ' + esc(s.fromName) + ' (' + esc(s.from) + ')</p><p class="note">' + esc(s.airline) + ' · ' + esc(s.flight) + ' · ' + hm(s.min) + (s.operatedBy ? ' · operated by ' + esc(s.operatedBy) : '') + '</p><p><b>' + tm(s.arr) + '</b> ' + esc(s.toName) + ' (' + esc(s.to) + ')</p></div></div>' + lay;
        }).join('') + '</div>';
    }).join('') + '</div>';
  }
  function fares(j) {
    return '<div class="fares2">' + j.offers.map(function (o) {
      return '<form method="post" action="' + esc(selectUrl) + '" class="fare">' +
        '<input type="hidden" name="_token" value="' + esc(csrf) + '"><input type="hidden" name="hash" value="' + esc(hash) + '"><input type="hidden" name="key" value="' + esc(j.key) + '"><input type="hidden" name="i" value="' + o.i + '"><input type="hidden" name="back" value="' + esc(back) + '">' +
        '<h5>' + esc(o.family) + '</h5><ul>' +
        '<li class="' + (o.cabinBag ? 'g' : 'r') + '">' + (o.cabinBag ? '✓ Cabin bag' + (o.cabinBag.kg ? ' ' + o.cabinBag.kg + ' kg' : '') : '✕ No cabin bag') + '</li>' +
        '<li class="' + (o.checked ? 'g' : 'r') + '">' + (o.checked ? '✓ ' + o.checked.pieces + ' checked bag' + (o.checked.kg ? ' ' + o.checked.kg + ' kg' : '') : '✕ No checked bag') + '</li>' +
        '<li class="' + (o.changeable ? 'g' : 'r') + '">' + (o.changeable ? '✓ Changes allowed' : '✕ No changes') + '</li>' +
        '<li class="' + (o.refundable ? 'g' : 'r') + '">' + (o.refundable ? '✓ Refundable' : '✕ Non-refundable') + '</li>' +
        '</ul><p class="note">' + o.terms.filter(function (t) { return !/^Book by/.test(t) && !/^(Non-)?[Rr]efundable$/.test(t); }).map(esc).join('<br>') + '</p>' +
        '<div class="fare-b"><b>' + fmt(o.total) + '</b><button class="hbtn hbtn-orange" type="submit">Continue</button></div></form>';
    }).join('') + '</div>';
  }
  list.addEventListener('click', function (e) {
    var c = e.target.closest('.fcard'); if (!c) return;
    var j = all.find(function (x) { return x.key === c.dataset.k; }), x = c.querySelector('.fc-x');
    if (e.target.closest('.fc-det')) { var open = x.dataset.v === 'det' && !x.hidden; x.innerHTML = details(j); x.dataset.v = 'det'; x.hidden = open; }
    else if (e.target.closest('.fc-sel')) { var open2 = x.dataset.v === 'fare' && !x.hidden; x.innerHTML = '<h4 class="fx-h">Choose your fare</h4>' + fares(j) + details(j); x.dataset.v = 'fare'; x.hidden = open2; }
  });

  /* ---------- chips ---------- */
  function chips() {
    var c = [];
    st.stops.forEach(function (s) { c.push(['stops:' + s, s === 0 ? 'Direct' : s === 1 ? '1 stop' : '2+ stops']); });
    if (st.maxPrice !== null) c.push(['price', 'Up to ' + fmt(st.maxPrice, 0)]);
    if (st.bag) c.push(['bag', 'Checked bag']); if (st.refund) c.push(['refund', 'Refundable']); if (st.change) c.push(['change', 'Changeable']);
    if (st.maxDur !== null) c.push(['dur', 'Up to ' + hm(st.maxDur)]);
    st.air.forEach(function (a) { c.push(['air:' + a, a]); });
    [0, 1].forEach(function (l) { if (st.slots[l].length) c.push(['slot:' + l, (l ? 'Return' : 'Outbound') + ' time']); });
    $('#chipsOn').innerHTML = c.map(function (x) { return '<button type="button" data-x="' + esc(x[0]) + '">' + esc(x[1]) + ' ✕</button>'; }).join('');
  }
  $('#chipsOn').addEventListener('click', function (e) {
    var b = e.target.closest('[data-x]'); if (!b) return; var k = b.dataset.x.split(':');
    if (k[0] === 'stops') st.stops = st.stops.filter(function (s) { return s !== +k[1]; });
    if (k[0] === 'price') { st.maxPrice = null; $('#fPrice').value = pMax; $('#fPriceL').textContent = fmt(pMax, 0); }
    if (k[0] === 'dur') { st.maxDur = null; $('#fDur').value = dMax; $('#fDurL').textContent = hm(dMax); }
    if (['bag', 'refund', 'change'].indexOf(k[0]) >= 0) st[k[0]] = false;
    if (k[0] === 'air') st.air = st.air.filter(function (a) { return a !== k[1]; });
    if (k[0] === 'slot') st.slots[+k[1]] = [];
    sync(); counts(); apply();
  });
  function sync() {
    $$('[data-f]').forEach(function (i) {
      var f = i.dataset.f;
      if (f === 'stops') i.checked = st.stops.indexOf(+i.value) >= 0;
      if (f === 'air') i.checked = st.air.indexOf(i.value) >= 0;
      if (['bag', 'refund', 'change'].indexOf(f) >= 0) i.checked = st[f];
    });
    $$('.tslots').forEach(function (g) { var l = +g.dataset.leg; $$('button', g).forEach(function (b) { b.classList.toggle('on', st.slots[l].indexOf(+b.dataset.t) >= 0); }); });
  }

  /* ---------- inputs ---------- */
  function bind(i) {
    i.addEventListener('change', function () {
      var f = i.dataset.f, v = i.value;
      if (f === 'stops') st.stops = i.checked ? st.stops.concat(+v) : st.stops.filter(function (s) { return s !== +v; });
      if (f === 'air') st.air = i.checked ? st.air.concat(v) : st.air.filter(function (a) { return a !== v; });
      if (['bag', 'refund', 'change'].indexOf(f) >= 0) st[f] = i.checked;
      counts(); apply();
    });
  }
  $$('.hs-filters [data-f]').forEach(bind);
  $$('.tslots button').forEach(function (b) {
    b.addEventListener('click', function () {
      var l = +b.parentNode.dataset.leg, t = +b.dataset.t, a = st.slots[l];
      st.slots[l] = a.indexOf(t) >= 0 ? a.filter(function (x) { return x !== t; }) : a.concat(t);
      sync(); counts(); apply();
    });
  });
  $('#fPrice').addEventListener('input', function (e) { var v = +e.target.value; st.maxPrice = v < pMax ? v : null; $('#fPriceL').textContent = fmt(v, 0); counts(); apply(); });
  $('#fDur').addEventListener('input', function (e) { var v = +e.target.value; st.maxDur = v < dMax ? v : null; $('#fDurL').textContent = hm(v); counts(); apply(); });
  $$('#flSorts button').forEach(function (b) { b.addEventListener('click', function () { st.sort = b.dataset.s; $$('#flSorts button').forEach(function (x) { x.classList.toggle('on', x === b); }); apply(); }); });
  more.addEventListener('click', page);
  $('#fReset').addEventListener('click', function () {
    st = { stops: [], maxPrice: null, bag: false, refund: false, change: false, slots: [[], []], maxDur: null, air: [], sort: st.sort };
    if (all.length) { $('#fPrice').value = pMax; $('#fPriceL').textContent = fmt(pMax, 0); $('#fDur').value = dMax; $('#fDurL').textContent = hm(dMax); }
    sync(); counts(); apply();
  });
  $('#mFilter').addEventListener('click', function () { $('#flSide').classList.toggle('open'); });

  load();
})();
