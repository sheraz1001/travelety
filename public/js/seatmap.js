/* Travelety flight extras: seat map, bags, meals. Data in window.FL_X. */
(function () {
  var X = window.FL_X; if (!X) return;
  var $ = function (s, el) { return (el || document).querySelector(s); }, $$ = function (s, el) { return [].slice.call((el || document).querySelectorAll(s)); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var money = function (n) { return n ? X.cur + ' ' + Number(n).toFixed(2) : 'Free'; };
  var pax = 0, seg = Object.keys(X.seats)[0] || null;
  var sel = {}; // sel[p] = { 'seat:<seg>': svc, 'bag:<seg>': svc, 'meal:<seg>': svc }
  X.pax.forEach(function (_, i) { sel[i] = {}; });
  var COLORS = { extra_legroom: '#00a870', exit_row: '#e5a000', standard: '#1463ff' };
  var initials = function (i) { return X.pax[i].n.split(' ').map(function (w) { return w[0]; }).join('').substr(0, 2).toUpperCase(); };

  /* ---------- seat grid (per Nuitee seat map guide) ---------- */
  function grid(list) {
    var cols = {}, rows = {}, pos = {}, look = {}, exits = {};
    list.forEach(function (s) {
      cols[s.c] = 1; rows[s.r] = 1; look[s.r + '-' + s.c] = s;
      if (s.pos) { pos[s.c] = pos[s.c] || {}; pos[s.c][s.pos] = (pos[s.c][s.pos] || 0) + 1; }
      if (s.t === 'exit_row') exits[s.r] = 1;
    });
    var C = Object.keys(cols).sort(), R = Object.keys(rows).map(Number).sort(function (a, b) { return a - b; });
    var dom = C.map(function (c) { var p = pos[c] || {}; return Object.keys(p).sort(function (a, b) { return p[b] - p[a]; })[0] || null; });
    var gaps = {};
    for (var i = 1; i < C.length; i++) if (dom[i] === 'aisle' && dom[i - 1] === 'aisle') gaps[i] = 1;
    if (!Object.keys(gaps).length && C.length >= 4) gaps[Math.floor(C.length / 2)] = 1;
    return { C: C, R: R, look: look, gaps: gaps, exits: exits };
  }
  function takenBy(id) { for (var p in sel) for (var k in sel[p]) if (sel[p][k] && sel[p][k].id === id) return +p; return -1; }
  function drawSeats() {
    var map = $('#xMap'); if (!map || !seg) return;
    var g = grid(X.seats[seg] || []), html = '<div class="sm-head"><span></span>';
    g.C.forEach(function (c, i) { html += (g.gaps[i] ? '<span class="gap"></span>' : '') + '<span>' + esc(c) + '</span>'; });
    html += '</div>';
    var lastExit = false;
    g.R.forEach(function (r) {
      if (g.exits[r] && !lastExit) html += '<div class="sm-exit">◀ EXIT ▶</div>';
      lastExit = !!g.exits[r];
      html += '<div class="sm-row"><span class="rn">' + r + '</span>';
      g.C.forEach(function (c, i) {
        if (g.gaps[i]) html += '<span class="gap">' + (i === Object.keys(g.gaps).map(Number)[0] ? r : '') + '</span>';
        var s = g.look[r + '-' + c];
        if (!s || !s.a) { html += '<button type="button" class="st x" disabled title="Unavailable">✕</button>'; return; }
        var who = takenBy(s.id), mine = who === pax;
        var cls = 'st' + (mine ? ' sel' : who >= 0 ? ' occ' : '');
        var col = COLORS[s.t] || COLORS.standard;
        html += '<button type="button" class="' + cls + '" data-id="' + esc(s.id) + '" style="--c:' + col + '" title="Seat ' + esc(s.n) + ' · ' + esc(String(s.t).replace('_', ' ')) + (s.pos ? ' · ' + s.pos : '') + ' · ' + money(s.price) + '"' + (who >= 0 && !mine ? ' disabled' : '') + '>' +
          (who >= 0 ? (mine ? esc(s.n) : initials(who)) : '<i>' + (s.price ? Math.round(s.price) : '0') + '</i>') + '</button>';
      });
      html += '</div>';
    });
    map.innerHTML = html;
    var types = {}; (X.seats[seg] || []).forEach(function (s) { types[s.t] = 1; });
    $('#xLegend').innerHTML = ['extra_legroom', 'exit_row', 'standard'].filter(function (t) { return types[t]; }).map(function (t) { return '<span><i style="background:' + COLORS[t] + '"></i>' + t.replace('_', ' ') + '</span>'; }).join('') + '<span><i class="ux">✕</i>unavailable</span>';
    var cur = sel[pax]['seat:' + seg];
    $('#xSeatNote').innerHTML = X.pax[pax].t === 2 ? 'Infants sit on an adult\'s lap and don\'t need a seat.' : (cur ? '<b>' + esc(X.pax[pax].n) + '</b>: seat ' + esc(cur.n) + ' · ' + money(cur.price) + ' · <button type="button" class="linkbtn" id="xClear">remove</button>' : 'Select a seat for <b>' + esc(X.pax[pax].n) + '</b>. Prices shown in ' + esc(X.cur) + '.');
  }
  var mapEl = $('#xMap');
  if (mapEl) mapEl.addEventListener('click', function (e) {
    var b = e.target.closest('.st[data-id]'); if (!b || X.pax[pax].t === 2) return;
    var s = (X.seats[seg] || []).find(function (x) { return x.id === b.dataset.id; });
    var k = 'seat:' + seg;
    sel[pax][k] = sel[pax][k] && sel[pax][k].id === s.id ? null : s;
    // auto-advance to next passenger without a seat on this segment
    if (sel[pax][k]) { for (var i = 1; i <= X.pax.length; i++) { var n = (pax + i) % X.pax.length; if (X.pax[n].t !== 2 && !sel[n][k]) { setPax(n); break; } } }
    render();
  });
  document.addEventListener('click', function (e) { if (e.target.id === 'xClear') { sel[pax]['seat:' + seg] = null; render(); } });
  $$('#xSegs button').forEach(function (b) { b.addEventListener('click', function () { seg = b.dataset.s; $$('#xSegs button').forEach(function (x) { x.classList.toggle('on', x === b); }); drawSeats(); }); });
  function setPax(i) { pax = i; $$('#xPax button').forEach(function (b) { b.classList.toggle('on', +b.dataset.p === i); }); }
  $$('#xPax button').forEach(function (b) { b.addEventListener('click', function () { setPax(+b.dataset.p); render(); }); });

  /* ---------- bags + meals (per passenger, per flight) ---------- */
  function listBy(arr) { var m = {}; arr.forEach(function (x) { (m[x.seg] = m[x.seg] || []).push(x); }); return m; }
  var BAGS = listBy(X.bags), MEALS = listBy(X.meals);
  function segName(k) { var s = X.segs[k]; return s ? s.from + ' → ' + s.to + ' · ' + s.flight : 'All flights'; }
  function okFor(x, p) { var t = X.pax[p].t; return x.pt === 'ALL' || (x.pt === 'ADT' && t === 0) || (x.pt === 'CHD' && t === 1) || (x.pt === 'INF' && t === 2); }
  function drawOpts(el, groups, kind) {
    if (!el) return;
    var html = '';
    Object.keys(groups).forEach(function (k) {
      var opts = groups[k].filter(function (x) { return okFor(x, pax); }).sort(function (a, b) { return a.price - b.price; });
      if (!opts.length) return;
      var cur = sel[pax][kind + ':' + k];
      html += '<div class="xopt"><p class="fsub">' + esc(segName(k)) + '</p><div class="xchips">' +
        '<button type="button" class="' + (!cur ? 'on' : '') + '" data-k="' + kind + ':' + esc(k) + '" data-id="">' + (kind === 'bag' ? 'No extra bag' : 'No meal') + '</button>' +
        opts.map(function (x) {
          return '<button type="button" class="' + (cur && cur.id === x.id ? 'on' : '') + '" data-k="' + kind + ':' + esc(k) + '" data-id="' + esc(x.id) + '">' + esc(x.name) + (x.kg ? '<small>' + x.pieces + ' × ' + x.kg + ' kg</small>' : '') + '<b>' + money(x.price) + '</b></button>';
        }).join('') + '</div></div>';
    });
    el.innerHTML = html || '<p class="note">Nothing available for this passenger.</p>';
  }
  ['#xBags', '#xMeals'].forEach(function (sid) {
    var el = $(sid); if (!el) return;
    el.addEventListener('click', function (e) {
      var b = e.target.closest('button[data-k]'); if (!b) return;
      var kind = b.dataset.k.split(':')[0], k = b.dataset.k.slice(kind.length + 1);
      var pool = (kind === 'bag' ? BAGS : MEALS)[k] || [];
      sel[pax][b.dataset.k] = b.dataset.id ? pool.find(function (x) { return x.id === b.dataset.id; }) : null;
      render();
    });
  });

  /* ---------- totals + hidden inputs ---------- */
  function render() {
    drawSeats(); drawOpts($('#xBags'), BAGS, 'bag'); drawOpts($('#xMeals'), MEALS, 'meal');
    var tot = 0, inputs = '';
    Object.keys(sel).forEach(function (p) { Object.keys(sel[p]).forEach(function (k) { var x = sel[p][k]; if (!x) return; tot += x.price; inputs += '<input type="hidden" name="svc[' + p + '][' + esc(k) + ']" value="' + esc(x.id) + '">'; }); });
    $('#xInputs').innerHTML = inputs; $('#xTotal').textContent = X.cur + ' ' + tot.toFixed(2);
    $$('#xPax button').forEach(function (b) { var p = +b.dataset.p, n = Object.keys(sel[p]).filter(function (k) { return sel[p][k]; }).length; b.classList.toggle('has', n > 0); });
  }
  var f = $('#xForm'); if (f) f.addEventListener('submit', function () { var b = $('#xGo'); b.disabled = true; b.textContent = 'Adding extras…'; });
  render();
})();
