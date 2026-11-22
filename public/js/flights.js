/* Travelety flight results: load, filter, sort, fare selection. */
(function () {
  var app = document.getElementById('flApp');
  if (!app) return;
  var endpoint = app.dataset.endpoint, qs = JSON.parse(app.dataset.qs || '{}'), selectUrl = app.dataset.select, csrf = app.dataset.csrf;
  var pax = +app.dataset.pax || 1, back = app.dataset.back;
  var $ = function (s, el) { return (el || document).querySelector(s); }, $$ = function (s, el) { return [].slice.call((el || document).querySelectorAll(s)); };
  var list = $('#flList'), more = $('#flMore'), count = $('#flCount'), prog = $('#flProg');
  var all = [], view = [], shown = 0, PAGE = 15, hash = '', cur = '';
  var NL = 0; // number of legs
  function fresh(sort) { var o = { stops: [], maxPrice: null, bag: false, cabinbag: false, refund: false, change: false, seat: false, noOvernight: false, noChange: false, maxLay: null, maxDur: null, air: [], all: [], via: [], aps: [], dep: [], arr: [], sort: sort || 'best', sort2: '' }; for (var i = 0; i < 6; i++) { o.dep.push([]); o.arr.push([]); } return o; }
  var st = fresh();
  var ALLY = { 'Star Alliance': 'LH UA AC NH SQ TK LX OS SN TP LO OZ ET MS A3 CA BR TG AI NZ SA CM AV ZH OU', 'oneworld': 'AA BA QR CX JL QF IB AY MH RJ AS UL AT WY', 'SkyTeam': 'AF KL DL KE AM AR CI MU SV VN GA ME KQ RO SK VS UX' };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var NODEC = ['JPY','KRW','IDR','VND','HUF','PKR','LKR','NPR','BDT','NGN','KES','UZS','KZT','INR','PHP'];
  var fmt = function (n, d) { if (NODEC.indexOf(cur) >= 0 || n >= 10000) d = 0; return cur + ' ' + Number(n).toLocaleString('en-US', { minimumFractionDigits: d === 0 ? 0 : 2, maximumFractionDigits: d === 0 ? 0 : 2 }); };
  var hm = function (m) { if (m == null) return '–'; return Math.floor(m / 60) + 'h ' + (m % 60 ? (m % 60) + 'm' : ''); };
  var tm = function (iso) { return iso ? iso.substr(11, 5) : ''; };
  var dshort = function (iso) { var d = new Date(iso.substr(0, 10) + 'T00:00:00'); return d.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' }); };
  var slot = function (iso) { var h = +iso.substr(11, 2); return h < 6 ? 0 : h < 12 ? 1 : h < 18 ? 2 : 3; };

  /* ---------- load ---------- */
  function load() {
    var w = 0; prog.hidden = false; var bar = prog.querySelector('i'); bar.style.width = '5%';
    var t = setInterval(function () { w = Math.min(92, w + (92 - w) * .08); bar.style.width = w + '%'; }, 250);
    fetch(endpoint + '?' + (app.dataset.query || new URLSearchParams(qs).toString()), { headers: { Accept: 'application/json' } })
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
          j.cabinbag = j.offers.some(function (o) { return o.cabinBag; });
          j.seat = j.offers.some(function (o) { return o.seatSelect; });
          var lays = [].concat.apply([], j.legs.map(function (l) { return l.layovers; }));
          j.maxLay = lays.length ? Math.max.apply(null, lays.map(function (x) { return x.min; })) : 0;
          j.noOvernight = !lays.some(function (x) { return x.overnight; });
          j.noChange = !lays.some(function (x) { return x.changeAirport; });
          j.via = lays.map(function (x) { return x.code; });
          j.aps = [];
          j.legs.forEach(function (l) { j.aps.push(l.from, l.to); });
          j.ally = Object.keys(ALLY).filter(function (k) { return j.codes.some(function (c) { return ALLY[k].split(' ').indexOf(c) >= 0; }); });
        });
        NL = Math.max.apply(null, all.map(function (j) { return j.legs.length; }));
        var cheap = Math.min.apply(null, all.map(function (j) { return j.price; })), fast = Math.min.apply(null, all.map(function (j) { return j.min; }));
        all.forEach(function (j) { j.score = j.price / cheap * 0.6 + j.min / fast * 0.4 + (j.stops * 0.08) - (j.best ? 0.15 : 0); });
        all.forEach(function (j) { j.aps.forEach(function (c) { apsAll[c] = 1; }); });
        // airports filter only matters where a city has several airports in results
        setupRanges(); airlines(); counts(); apply();
      })
      .catch(function () { clearInterval(t); prog.hidden = true; list.innerHTML = empty('Connection problem', 'Please check your connection and try again.'); });
  }
  function empty(h, p) { return '<div class="hs-empty"><h3>' + h + '</h3><p>' + p + '</p></div>'; }

  /* ---------- ranges + airlines ---------- */
  var pMin = 0, pMax = 0, dMin = 0, dMax = 0, lMax = 0;
  function setupRanges() {
    var ps = all.map(function (j) { return j.price; }), ds = all.map(function (j) { return j.min; });
    pMin = Math.floor(Math.min.apply(null, ps)); pMax = Math.ceil(Math.max.apply(null, ps));
    dMin = Math.min.apply(null, ds); dMax = Math.max.apply(null, ds);
    var fp = $('#fPrice'); fp.min = pMin; fp.max = pMax; fp.step = Math.max(1, Math.round((pMax - pMin) / 100)); fp.value = pMax;
    var fd = $('#fDur'); fd.min = dMin; fd.max = dMax; fd.step = 15; fd.value = dMax;
    $('#fPriceL').textContent = fmt(pMax, 0); $('#fDurL').textContent = hm(dMax);
    lMax = Math.max.apply(null, all.map(function (j) { return j.maxLay; })) || 0;
    var fl = $('#fLay'); fl.min = 30; fl.max = Math.max(60, lMax); fl.step = 15; fl.value = fl.max; $('#fLayL').textContent = lMax ? hm(lMax) : 'none';
  }
  function airlines() {
    var m = {};
    all.forEach(function (j) { j.airlines.forEach(function (a) { if (!m[a.code] || j.price < m[a.code].p) m[a.code] = { n: a.name, l: a.logo, p: Math.min(j.price, m[a.code] ? m[a.code].p : Infinity) }; }); });
    var keys = Object.keys(m).sort(function (a, b) { return m[a].p - m[b].p; });
    $('#fAir').innerHTML = keys.map(function (k) {
      return '<label class="fchk"><input type="checkbox" data-f="air" value="' + esc(k) + '">' + (m[k].l ? '<img class="alogo-s" src="' + esc(m[k].l) + '" alt="">' : '') + esc(m[k].n) + '<em>' + fmt(m[k].p, 0) + '</em></label>';
    }).join('');
    $$('#fAir input').forEach(bind);
    listFilter('#fVia', 'via', function (j) { return j.via; }, 'Layover airports', function (c) { var x = null; all.some(function (j) { return j.legs.some(function (l) { return l.layovers.some(function (y) { if (y.code === c) { x = y.name; return true; } }); }); }); return (x ? x + ' ' : '') + '(' + c + ')'; });
    airportsFilter();
    listFilter('#fAll', 'all', function (j) { return j.ally; }, '', function (c) { return c; }, 1);
    $('#fAirportsG').hidden = !$('#fAirports').innerHTML; $('#fAllG').hidden = !$('#fAll').innerHTML;
  }
  function airportsFilter() {
    var html = '';
    for (var l = 0; l < NL; l++) ['from', 'to'].forEach(function (pos) {
      var m = {}, nm = {};
      all.forEach(function (j) { var lg = j.legs[l]; if (!lg) return; var c = lg[pos]; m[c] = Math.min(m[c] || Infinity, j.price); nm[c] = lg[pos + 'Name']; });
      var keys = Object.keys(m); if (keys.length < 2) return;
      html += '<p class="fsub">' + (NL > 1 ? 'Flight ' + (l + 1) + ' · ' : '') + (pos === 'from' ? 'Departure airport' : 'Arrival airport') + '</p>' + keys.sort(function (a, b) { return m[a] - m[b]; }).map(function (k) {
        return '<label class="fchk"><input type="checkbox" data-f="aps" value="' + l + ':' + pos + ':' + esc(k) + '">' + esc((nm[k] || k) + ' (' + k + ')') + '<em>' + fmt(m[k], 0) + '</em></label>';
      }).join('');
    });
    $('#fAirports').innerHTML = html; $$('#fAirports input').forEach(bind);
  }
  function listFilter(sel, key, get, title, label, minCount) {
    var m = {};
    all.forEach(function (j) { get(j).filter(function (v, i, a) { return a.indexOf(v) === i; }).forEach(function (c) { m[c] = Math.min(m[c] || Infinity, j.price); }); });
    var keys = Object.keys(m).sort(function (a, b) { return m[a] - m[b]; });
    if (keys.length < (minCount || 1) + (key === 'aps' ? 1 : 0)) { $(sel).innerHTML = ''; return; }
    $(sel).innerHTML = (title ? '<p class="fsub">' + title + '</p>' : '') + keys.slice(0, 15).map(function (k) {
      return '<label class="fchk"><input type="checkbox" data-f="' + key + '" value="' + esc(k) + '">' + esc(label(k)) + '<em>' + fmt(m[k], 0) + '</em></label>';
    }).join('');
    $$(sel + ' input').forEach(bind);
  }

  /* ---------- filter ---------- */
  function inter(a, b) { return a.some(function (x) { return b.indexOf(x) >= 0; }); }
  function pass(j, skip) {
    if (st.stops.length && skip !== 'stops' && st.stops.indexOf(Math.min(2, j.stops)) < 0) return false;
    if (st.maxPrice !== null && skip !== 'price' && j.price > st.maxPrice) return false;
    var flags = ['bag', 'cabinbag', 'refund', 'change', 'seat', 'noOvernight', 'noChange'];
    for (var f = 0; f < flags.length; f++) if (st[flags[f]] && skip !== flags[f] && !j[flags[f]]) return false;
    if (st.maxDur !== null && skip !== 'dur' && j.min > st.maxDur) return false;
    if (st.maxLay !== null && skip !== 'lay' && j.maxLay > st.maxLay) return false;
    if (st.air.length && skip !== 'air' && !inter(j.codes, st.air)) return false;
    if (st.all.length && skip !== 'all' && !inter(j.ally, st.all)) return false;
    if (st.via.length && skip !== 'via' && j.via.length && !j.via.every(function (v) { return st.via.indexOf(v) >= 0; })) return false;
    if (st.aps.length && skip !== 'aps') {
      var groups = {};
      st.aps.forEach(function (x) { var p = x.split(':'); (groups[p[0] + ':' + p[1]] = groups[p[0] + ':' + p[1]] || []).push(p[2]); });
      for (var g in groups) { var q = g.split(':'), lg = j.legs[+q[0]]; if (lg && groups[g].indexOf(lg[q[1]]) < 0) return false; }
    }
    for (var l = 0; l < NL; l++) {
      if (st.dep[l].length && j.legs[l] && st.dep[l].indexOf(slot(j.legs[l].dep)) < 0) return false;
      if (st.arr[l].length && j.legs[l] && st.arr[l].indexOf(slot(j.legs[l].arr)) < 0) return false;
    }
    return true;
  }
  var apsAll = {};
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
    var by2 = {
      dep: function (a, b) { return a.legs[0].dep < b.legs[0].dep ? -1 : 1; },
      dep_d: function (a, b) { return a.legs[0].dep > b.legs[0].dep ? -1 : 1; },
      arr: function (a, b) { return a.legs[0].arr < b.legs[0].arr ? -1 : 1; },
      stops: function (a, b) { return a.stops - b.stops || a.price - b.price; },
      lay: function (a, b) { return a.maxLay - b.maxLay || a.price - b.price; }
    };
    view.sort(st.sort2 ? by2[st.sort2] : by[st.sort]);
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
      return '<div class="fdl"><h5>' + (l.dir === 'INBOUND' ? 'Return' : (/^LEG/.test(l.dir) ? 'Flight ' + l.dir.substr(3) : 'Outbound')) + ' · ' + dshort(l.dep) + ' · ' + hm(l.min) + '</h5>' +
        l.segments.map(function (s, i) {
          var lay = l.layovers[i] ? '<div class="lay">⏱ ' + hm(l.layovers[i].min) + ' layover in ' + esc(l.layovers[i].name || l.layovers[i].code) + (l.layovers[i].changeAirport ? ' · <b>change airport</b>' : '') + (l.layovers[i].overnight ? ' · overnight' : '') + '</div>' : '';
          return '<div class="seg">' + (s.logo ? '<img src="' + esc(s.logo) + '" alt="">' : '') + '<div><p><b>' + tm(s.dep) + '</b> ' + esc(s.fromName) + ' (' + esc(s.from) + ')</p><p class="note">' + esc(s.airline) + ' · ' + esc(s.flight) + ' · ' + hm(s.min) + (s.operatedBy ? ' · operated by ' + esc(s.operatedBy) : '') + (s.aircraft ? ' · ' + esc(s.aircraft) : '') + '</p><p><b>' + tm(s.arr) + '</b> ' + esc(s.toName) + ' (' + esc(s.to) + ')</p></div></div>' + lay;
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
  var LBL = { bag: 'Checked bag', cabinbag: 'Cabin bag', refund: 'Refundable', change: 'Changeable', seat: 'Seat selection', noOvernight: 'No overnight layovers', noChange: 'No airport changes' };
  function chips() {
    var c = [];
    st.stops.forEach(function (s) { c.push(['stops:' + s, s === 0 ? 'Direct' : s === 1 ? '1 stop' : '2+ stops']); });
    if (st.maxPrice !== null) c.push(['price', 'Up to ' + fmt(st.maxPrice, 0)]);
    Object.keys(LBL).forEach(function (k) { if (st[k]) c.push([k, LBL[k]]); });
    if (st.maxDur !== null) c.push(['dur', 'Journey up to ' + hm(st.maxDur)]);
    if (st.maxLay !== null) c.push(['lay', 'Layover up to ' + hm(st.maxLay)]);
    ['air', 'all', 'via', 'aps'].forEach(function (k) { st[k].forEach(function (v) { c.push([k + ':' + v, (k === 'via' ? 'via ' : '') + (k === 'aps' ? v.split(':')[2] : v)]); }); });
    for (var l = 0; l < NL; l++) { if (st.dep[l].length) c.push(['dep:' + l, 'Flight ' + (l + 1) + ' departure']); if (st.arr[l].length) c.push(['arr:' + l, 'Flight ' + (l + 1) + ' arrival']); }
    $('#chipsOn').innerHTML = c.map(function (x) { return '<button type="button" data-x="' + esc(x[0]) + '">' + esc(x[1]) + ' ✕</button>'; }).join('');
  }
  $('#chipsOn').addEventListener('click', function (e) {
    var b = e.target.closest('[data-x]'); if (!b) return; var k = b.dataset.x.split(':'), v = k.slice(1).join(':');
    if (k[0] === 'stops') st.stops = st.stops.filter(function (s) { return s !== +v; });
    if (k[0] === 'price') { st.maxPrice = null; $('#fPrice').value = pMax; $('#fPriceL').textContent = fmt(pMax, 0); }
    if (k[0] === 'dur') { st.maxDur = null; $('#fDur').value = dMax; $('#fDurL').textContent = hm(dMax); }
    if (k[0] === 'lay') { st.maxLay = null; $('#fLay').value = $('#fLay').max; $('#fLayL').textContent = hm(lMax); }
    if (LBL[k[0]]) st[k[0]] = false;
    if (['air', 'all', 'via', 'aps'].indexOf(k[0]) >= 0) st[k[0]] = st[k[0]].filter(function (x) { return x !== v; });
    if (k[0] === 'dep' || k[0] === 'arr') st[k[0]][+v] = [];
    sync(); counts(); apply();
  });
  function sync() {
    $$('[data-f]').forEach(function (i) {
      var f = i.dataset.f;
      if (f === 'stops') i.checked = st.stops.indexOf(+i.value) >= 0;
      else if (['air', 'all', 'via', 'aps'].indexOf(f) >= 0) i.checked = st[f].indexOf(i.value) >= 0;
      else if (LBL[f]) i.checked = st[f];
    });
    $$('.tslots').forEach(function (g) { var l = +g.dataset.leg, kind = g.dataset.kind; $$('button', g).forEach(function (b) { b.classList.toggle('on', st[kind][l].indexOf(+b.dataset.t) >= 0); }); });
  }

  /* ---------- inputs ---------- */
  function bind(i) {
    i.addEventListener('change', function () {
      var f = i.dataset.f, v = i.value;
      if (f === 'stops') st.stops = i.checked ? st.stops.concat(+v) : st.stops.filter(function (s) { return s !== +v; });
      else if (['air', 'all', 'via', 'aps'].indexOf(f) >= 0) st[f] = i.checked ? st[f].concat(v) : st[f].filter(function (a) { return a !== v; });
      else if (LBL[f]) st[f] = i.checked;
      counts(); apply();
    });
  }
  $$('.hs-filters > .fgrp [data-f]').forEach(bind);
  $$('.tslots button').forEach(function (b) {
    b.addEventListener('click', function () {
      var g = b.parentNode, l = +g.dataset.leg, kind = g.dataset.kind, t = +b.dataset.t, a = st[kind][l];
      st[kind][l] = a.indexOf(t) >= 0 ? a.filter(function (x) { return x !== t; }) : a.concat(t);
      sync(); counts(); apply();
    });
  });
  $('#fPrice').addEventListener('input', function (e) { var v = +e.target.value; st.maxPrice = v < pMax ? v : null; $('#fPriceL').textContent = fmt(v, 0); counts(); apply(); });
  $('#fDur').addEventListener('input', function (e) { var v = +e.target.value; st.maxDur = v < dMax ? v : null; $('#fDurL').textContent = hm(v); counts(); apply(); });
  $('#fLay').addEventListener('input', function (e) { var v = +e.target.value; st.maxLay = v < +e.target.max ? v : null; $('#fLayL').textContent = hm(v); counts(); apply(); });
  $$('#flSorts button').forEach(function (b) { b.addEventListener('click', function () { st.sort = b.dataset.s; st.sort2 = ''; $('#flSort2').value = ''; $$('#flSorts button').forEach(function (x) { x.classList.toggle('on', x === b); }); apply(); }); });
  $('#flSort2').addEventListener('change', function (e) { st.sort2 = e.target.value; $$('#flSorts button').forEach(function (x) { x.classList.toggle('on', !st.sort2 && x.dataset.s === st.sort); }); apply(); });
  more.addEventListener('click', page);
  $('#fReset').addEventListener('click', function () {
    st = fresh(st.sort);
    if (all.length) { $('#fPrice').value = pMax; $('#fPriceL').textContent = fmt(pMax, 0); $('#fDur').value = dMax; $('#fDurL').textContent = hm(dMax); $('#fLay').value = $('#fLay').max; $('#fLayL').textContent = hm(lMax); }
    sync(); counts(); apply();
  });
  $('#mFilter').addEventListener('click', function () { $('#flSide').classList.toggle('open'); });

  load();
})();
