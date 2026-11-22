/* Travelety hotel results: client-side filters, sorting and map (Leaflet + OpenStreetMap tiles). */
(function () {
  var app = document.getElementById('hsApp');
  if (!app) return;
  var endpoint = app.dataset.endpoint, qs = JSON.parse(app.dataset.qs || '{}'), nights = +app.dataset.nights || 1;
  var fac = JSON.parse(app.dataset.fac || '[]');
  var all = [], view = [], shown = 0, PAGE = 20, centre = null;
  var st = { name: '', min: null, max: null, refundable: false, meal: [], stars: [], score: 0, dist: 0, sort: 'rec' };
  var $ = function (s, el) { return (el || document).querySelector(s); }, $$ = function (s, el) { return [].slice.call((el || document).querySelectorAll(s)); };
  var list = $('#hsList'), more = $('#hsMore'), count = $('#hsCount');
  var NODEC = ['JPY','KRW','IDR','VND','HUF','PKR','LKR','NPR','BDT','NGN','KES','UZS','KZT','INR','PHP'];
  var cur = '', fmt = function (n, d) { if (NODEC.indexOf(cur) >= 0 || n >= 10000) d = 0; return cur + ' ' + Number(n).toLocaleString('en-US', { minimumFractionDigits: d === 0 ? 0 : 2, maximumFractionDigits: d === 0 ? 0 : 2 }); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var word = function (r) { return r >= 9 ? 'Wonderful' : r >= 8 ? 'Very good' : r >= 7 ? 'Good' : r >= 6 ? 'Pleasant' : 'Review score'; };
  var MEAL = { bb: 'Breakfast included', hb: 'Half board', fb: 'Full board', ai: 'All inclusive', ro: 'Room only' };

  function km(a, b) { var R = 6371, dLat = (b[0] - a[0]) * Math.PI / 180, dLng = (b[1] - a[1]) * Math.PI / 180;
    var x = Math.sin(dLat / 2) * Math.sin(dLat / 2) + Math.cos(a[0] * Math.PI / 180) * Math.cos(b[0] * Math.PI / 180) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
    return R * 2 * Math.atan2(Math.sqrt(x), Math.sqrt(1 - x)); }
  function median(a) { a = a.slice().sort(function (x, y) { return x - y; }); var m = Math.floor(a.length / 2); return a.length % 2 ? a[m] : (a[m - 1] + a[m]) / 2; }

  /* ---------- load ---------- */
  function load() {
    list.innerHTML = skel(4); more.hidden = true;
    count.textContent = 'Searching live prices…';
    var params = new URLSearchParams(qs); fac.forEach(function (f) { params.append('fac[]', f); });
    fetch(endpoint + '?' + params.toString(), { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        all = d.hotels || [];
        if (d.placeId && !qs.placeId) qs.placeId = d.placeId;
        if (d.error && !all.length) { list.innerHTML = empty("We couldn't load hotels right now", esc(d.error)); count.textContent = ''; return; }
        if (!all.length) { list.innerHTML = empty('No rooms available', 'Try different dates, remove a facility filter or search a nearby area.'); count.textContent = '0 properties'; updateMaps(); return; }
        cur = all[0].price.currency || '';
        var pts = all.filter(function (h) { return h.lat && h.lng; });
        centre = pts.length ? [median(pts.map(function (h) { return h.lat; })), median(pts.map(function (h) { return h.lng; }))] : null;
        all.forEach(function (h, i) { h.km = centre && h.lat ? km(centre, [h.lat, h.lng]) : null; h.rec = i; });
        setupBudget(); counts(); apply();
      })
      .catch(function () { list.innerHTML = empty('Connection problem', 'Please check your connection and try again.'); });
  }
  function skel(n) { var s = ''; for (var i = 0; i < n; i++) s += '<div class="hcard skel"><div class="hcard-img"></div><div class="hcard-bd"><i></i><i></i><i class="s"></i></div></div>'; return s; }
  function empty(h, p) { return '<div class="hs-empty"><h3>' + h + '</h3><p>' + p + '</p></div>'; }

  /* ---------- budget slider + histogram ---------- */
  var bMin = 0, bMax = 0;
  function setupBudget() {
    var n = all.map(function (h) { return h.night; }).sort(function (a, b) { return a - b; });
    bMin = Math.floor(n[0]); bMax = Math.ceil(n[Math.min(n.length - 1, Math.floor(n.length * 0.97))]);
    if (bMax <= bMin) bMax = bMin + 1;
    var bins = new Array(24).fill(0);
    n.forEach(function (v) { var i = Math.min(23, Math.floor((Math.min(v, bMax) - bMin) / (bMax - bMin) * 24)); bins[i]++; });
    var top = Math.max.apply(null, bins) || 1;
    $('#fHist').innerHTML = bins.map(function (b, i) { return '<i style="height:' + Math.max(4, b / top * 100) + '%" data-i="' + i + '"></i>'; }).join('');
    ['#fMin', '#fMax'].forEach(function (s) { var el = $(s); el.min = bMin; el.max = bMax; el.step = Math.max(1, Math.round((bMax - bMin) / 100)); });
    $('#fMin').value = bMin; $('#fMax').value = bMax; st.min = null; st.max = null; budgetLabels();
  }
  function budgetLabels() {
    var lo = +$('#fMin').value, hi = +$('#fMax').value;
    $('#fMinL').textContent = fmt(lo, 0); $('#fMaxL').textContent = fmt(hi, 0) + (hi >= bMax ? '+' : '');
    $$('#fHist i').forEach(function (b) { var i = +b.dataset.i, v = bMin + (i + .5) / 24 * (bMax - bMin); b.classList.toggle('on', v >= lo && v <= hi); });
  }
  function onBudget(e) {
    var lo = $('#fMin'), hi = $('#fMax');
    if (+lo.value > +hi.value) { if (e.target === lo) lo.value = hi.value; else hi.value = lo.value; }
    st.min = +lo.value > bMin ? +lo.value : null; st.max = +hi.value < bMax ? +hi.value : null;
    budgetLabels(); counts(); apply();
  }

  /* ---------- filtering ---------- */
  function pass(h, skip) {
    if (st.name && skip !== 'name' && (h.name || '').toLowerCase().indexOf(st.name) < 0) return false;
    if (skip !== 'budget') { if (st.min !== null && h.night < st.min) return false; if (st.max !== null && h.night > st.max) return false; }
    if (st.refundable && skip !== 'refundable' && !h.price.refundable) return false;
    if (st.meal.length && skip !== 'meal' && st.meal.indexOf(h.meal) < 0) return false;
    if (st.stars.length && skip !== 'stars' && (!h.stars || st.stars.indexOf(Math.round(h.stars)) < 0)) return false;
    if (st.score && skip !== 'score' && !(h.rating >= st.score)) return false;
    if (st.dist && skip !== 'dist' && !(h.km !== null && h.km <= st.dist)) return false;
    return true;
  }
  function counts() {
    $$('[data-c]').forEach(function (em) {
      var k = em.dataset.c.split(':'), f = k[0], v = k[1], n = 0;
      all.forEach(function (h) {
        if (!pass(h, f === 'score' ? 'score' : f)) return;
        if (f === 'refundable' ? h.price.refundable : f === 'meal' ? h.meal === v : f === 'stars' ? Math.round(h.stars || 0) === +v : f === 'score' ? h.rating >= +v : f === 'dist' ? (h.km !== null && h.km <= +v) : false) n++;
      });
      em.textContent = n; em.parentNode.classList.toggle('zero', n === 0);
    });
  }
  function apply() {
    view = all.filter(function (h) { return pass(h); });
    var s = st.sort;
    view.sort(function (a, b) {
      if (s === 'price') return a.price.amount - b.price.amount;
      if (s === 'price_d') return b.price.amount - a.price.amount;
      if (s === 'rating') return (b.rating || 0) - (a.rating || 0);
      if (s === 'stars') return (b.stars || 0) - (a.stars || 0) || a.price.amount - b.price.amount;
      if (s === 'dist') return (a.km == null ? 999 : a.km) - (b.km == null ? 999 : b.km);
      var sc = function (h) { return (h.rating || 6.5) * 12 + (h.stars || 3) * 2 - Math.log(h.night + 1) * 6 + Math.min(4, Math.log((h.reviews || 1) + 1)) - (h.km || 0) * .4; };
      return sc(b) - sc(a);
    });
    count.textContent = view.length + ' of ' + all.length + ' properties · prices for ' + nights + ' night' + (nights > 1 ? 's' : '') + ', taxes included';
    shown = 0; list.innerHTML = '';
    if (!view.length) list.innerHTML = empty('No properties match your filters', 'Try removing a filter to see more results.');
    page(); chips(); updateMaps();
  }
  function page() {
    var next = view.slice(shown, shown + PAGE);
    list.insertAdjacentHTML('beforeend', next.map(card).join(''));
    shown += next.length; more.hidden = shown >= view.length;
    if (!more.hidden) more.textContent = 'Show more hotels (' + (view.length - shown) + ' left)';
  }
  function card(h) {
    var p = h.price, deal = p.public && p.public > p.amount * 1.03;
    return '<a class="hcard" href="' + esc(h.url) + '" data-id="' + esc(h.id) + '">' +
      '<div class="hcard-img">' + (h.photo ? '<img src="' + esc(h.photo) + '" alt="" loading="lazy">' : '') + (deal ? '<span class="deal">Deal</span>' : '') + '</div>' +
      '<div class="hcard-bd"><div class="hcard-top"><div><h3>' + esc(h.name) + '</h3>' +
      (h.stars ? '<span class="stars">' + '★'.repeat(Math.round(h.stars)) + '</span>' : '') +
      '<p class="hcard-addr">' + esc([h.address, h.city].filter(Boolean).join(', ')) + (h.km !== null && h.km !== undefined ? ' · <b>' + (h.km < 1 ? Math.round(h.km * 1000) + ' m' : h.km.toFixed(1) + ' km') + ' from centre</b>' : '') + '</p></div>' +
      (h.rating ? '<div class="rbadge"><b>' + h.rating.toFixed(1) + '</b><span>' + word(h.rating) + (h.reviews ? '<small>' + Number(h.reviews).toLocaleString() + ' reviews</small>' : '') + '</span></div>' : '') +
      '</div><div class="hcard-foot"><div class="hcard-tags">' +
      (p.room ? '<span class="t">' + esc(p.room) + '</span>' : '') +
      '<span class="t ' + (h.meal !== 'ro' ? 'g' : '') + '">' + MEAL[h.meal] + '</span>' +
      '<span class="t ' + (p.refundable ? 'g' : '') + '">' + (p.refundable ? 'Free cancellation' : 'Non-refundable') + '</span>' +
      '</div><div class="hcard-price">' + (deal ? '<s>' + fmt(p.public) + '</s>' : '') + '<b>' + fmt(p.amount) + '</b><small>' + fmt(h.night) + ' per night</small><span class="hbtn hbtn-blue">See rooms</span></div></div></div></a>';
  }

  /* ---------- active-filter chips ---------- */
  function chips() {
    var c = [];
    if (st.name) c.push(['name', '"' + st.name + '"']);
    if (st.min !== null || st.max !== null) c.push(['budget', fmt(st.min !== null ? st.min : bMin, 0) + ' – ' + fmt(st.max !== null ? st.max : bMax, 0)]);
    if (st.refundable) c.push(['refundable', 'Free cancellation']);
    st.meal.forEach(function (m) { c.push(['meal:' + m, MEAL[m]]); });
    st.stars.forEach(function (s) { c.push(['stars:' + s, s + ' stars']); });
    if (st.score) c.push(['score', st.score + '+ rating']);
    if (st.dist) c.push(['dist', '< ' + st.dist + ' km']);
    $('#chipsOn').innerHTML = c.map(function (x) { return '<button type="button" data-x="' + x[0] + '">' + esc(x[1]) + ' ✕</button>'; }).join('');
  }
  $('#chipsOn').addEventListener('click', function (e) {
    var b = e.target.closest('[data-x]'); if (!b) return; var k = b.dataset.x.split(':');
    if (k[0] === 'name') { st.name = ''; $('#fName').value = ''; }
    if (k[0] === 'budget') { $('#fMin').value = bMin; $('#fMax').value = bMax; st.min = st.max = null; budgetLabels(); }
    if (k[0] === 'refundable') st.refundable = false;
    if (k[0] === 'meal') st.meal = st.meal.filter(function (m) { return m !== k[1]; });
    if (k[0] === 'stars') st.stars = st.stars.filter(function (s) { return s !== +k[1]; });
    if (k[0] === 'score') st.score = 0;
    if (k[0] === 'dist') st.dist = 0;
    syncInputs(); counts(); apply();
  });
  function syncInputs() {
    $$('[data-f]').forEach(function (i) {
      var f = i.dataset.f;
      if (f === 'refundable') i.checked = st.refundable;
      if (f === 'meal') i.checked = st.meal.indexOf(i.value) >= 0;
      if (f === 'stars') i.checked = st.stars.indexOf(+i.value) >= 0;
      if (f === 'score' || f === 'scoreR') i.checked = st.score === +i.value;
      if (f === 'dist') i.checked = st.dist === +i.value;
    });
  }

  /* ---------- inputs ---------- */
  $$('[data-f]').forEach(function (i) {
    i.addEventListener('change', function () {
      var f = i.dataset.f, v = i.value;
      if (f === 'refundable') st.refundable = i.checked;
      if (f === 'meal') st.meal = i.checked ? st.meal.concat(v).filter(function (x, j, a) { return a.indexOf(x) === j; }) : st.meal.filter(function (m) { return m !== v; });
      if (f === 'stars') st.stars = i.checked ? st.stars.concat(+v).filter(function (x, j, a) { return a.indexOf(x) === j; }) : st.stars.filter(function (s) { return s !== +v; });
      if (f === 'score' || f === 'scoreR') st.score = i.checked ? +v : 0;
      if (f === 'dist') st.dist = i.checked ? +v : 0;
      syncInputs(); counts(); apply();
    });
  });
  var nt; $('#fName').addEventListener('input', function (e) { clearTimeout(nt); nt = setTimeout(function () { st.name = e.target.value.trim().toLowerCase(); counts(); apply(); }, 200); });
  $('#fMin').addEventListener('input', onBudget); $('#fMax').addEventListener('input', onBudget);
  $('#hsSort').addEventListener('change', function (e) { st.sort = e.target.value; apply(); });
  more.addEventListener('click', page);
  $('#fReset').addEventListener('click', function () {
    st = { name: '', min: null, max: null, refundable: false, meal: [], stars: [], score: 0, dist: 0, sort: st.sort };
    $('#fName').value = ''; $$('[data-f]').forEach(function (i) { i.checked = false; });
    if (all.length) { $('#fMin').value = bMin; $('#fMax').value = bMax; budgetLabels(); }
    if (fac.length) { fac = []; $$('.ffac').forEach(function (i) { i.checked = false; }); load(); } else { counts(); apply(); }
  });
  $$('.ffac').forEach(function (i) {
    i.addEventListener('change', function () {
      fac = $$('.ffac').filter(function (x) { return x.checked; }).map(function (x) { return +x.value; });
      var u = new URL(location.href); u.searchParams.delete('fac[]'); fac.forEach(function (f) { u.searchParams.append('fac[]', f); }); history.replaceState(null, '', u);
      load();
    });
  });
  var side = $('#hsSide');
  $('#mFilter').addEventListener('click', function () { side.classList.toggle('open'); });

  /* ---------- maps ---------- */
  var tiles = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
  var attr = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';
  var mini = null, big = null, bigLayer = null, markers = {};
  function pin(h, active) {
    return L.divIcon({ className: 'pmk' + (active ? ' on' : ''), html: '<span>' + fmt(h.night, 0) + '</span>', iconSize: null });
  }
  function updateMaps() {
    if (!window.L) return;
    var pts = view.filter(function (h) { return h.lat && h.lng; });
    if (!mini) {
      mini = L.map('miniMap', { zoomControl: false, attributionControl: false, dragging: false, scrollWheelZoom: false, doubleClickZoom: false, boxZoom: false, keyboard: false, touchZoom: false });
      L.tileLayer(tiles, { maxZoom: 19 }).addTo(mini);
      mini.miniLayer = L.layerGroup().addTo(mini);
    }
    mini.miniLayer.clearLayers();
    pts.slice(0, 150).forEach(function (h) { L.circleMarker([h.lat, h.lng], { radius: 4, color: '#fff', weight: 1, fillColor: '#1463ff', fillOpacity: 1 }).addTo(mini.miniLayer); });
    if (pts.length) mini.fitBounds(L.latLngBounds(pts.map(function (h) { return [h.lat, h.lng]; })).pad(0.1));
    else if (centre) mini.setView(centre, 12);
    if (big && !$('#mapOv').hidden) drawBig();
  }
  function drawBig(fit) {
    if (!big) {
      big = L.map('bigMap', { zoomControl: true }); L.tileLayer(tiles, { maxZoom: 19, attribution: attr }).addTo(big);
      bigLayer = L.layerGroup().addTo(big);
    }
    bigLayer.clearLayers(); markers = {};
    var pts = view.filter(function (h) { return h.lat && h.lng; });
    pts.forEach(function (h) {
      var m = L.marker([h.lat, h.lng], { icon: pin(h), riseOnHover: true }).addTo(bigLayer);
      m.bindPopup('<a class="mpop" href="' + esc(h.url) + '">' + (h.photo ? '<img src="' + esc(h.photo) + '" alt="">' : '') + '<div><b>' + esc(h.name) + '</b>' + (h.stars ? '<span class="stars">' + '★'.repeat(Math.round(h.stars)) + '</span>' : '') +
        (h.rating ? '<p><em>' + h.rating.toFixed(1) + '</em> ' + word(h.rating) + '</p>' : '') + '<p class="mp">' + fmt(h.price.amount) + ' <small>for ' + nights + ' night' + (nights > 1 ? 's' : '') + '</small></p></div></a>', { maxWidth: 260, minWidth: 240 });
      m.on('click', function () { hl(h.id, true); });
      markers[h.id] = m;
    });
    $('#mapCount').textContent = pts.length + ' properties on map';
    $('#mapList').innerHTML = view.slice(0, 80).map(function (h) {
      return '<a class="mrow" href="' + esc(h.url) + '" data-id="' + esc(h.id) + '">' + (h.photo ? '<img src="' + esc(h.photo) + '" alt="" loading="lazy">' : '<i></i>') +
        '<div><b>' + esc(h.name) + '</b>' + (h.stars ? '<span class="stars">' + '★'.repeat(Math.round(h.stars)) + '</span>' : '') +
        (h.rating ? '<p><em>' + h.rating.toFixed(1) + '</em> ' + word(h.rating) + '</p>' : '') + '<p class="mp">' + fmt(h.price.amount) + '</p></div></a>';
    }).join('');
    if (fit !== false && pts.length) big.fitBounds(L.latLngBounds(pts.map(function (h) { return [h.lat, h.lng]; })).pad(0.05));
  }
  var lastHl = null;
  function hl(id, scroll) {
    if (lastHl && markers[lastHl]) markers[lastHl].setIcon(pin(all.find(function (h) { return h.id === lastHl; })));
    if (markers[id]) { markers[id].setIcon(pin(all.find(function (h) { return h.id === id; }), true)); markers[id].setZIndexOffset(1000); }
    lastHl = id;
    $$('.mrow').forEach(function (r) { r.classList.toggle('on', r.dataset.id === id); });
    if (scroll) { var r = $('.mrow[data-id="' + CSS.escape(id) + '"]'); if (r) r.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); }
  }
  $('#mapList').addEventListener('mouseover', function (e) { var r = e.target.closest('.mrow'); if (r && r.dataset.id !== lastHl) hl(r.dataset.id); });
  function openMap() { $('#mapOv').hidden = false; document.body.classList.add('noscroll'); setTimeout(function () { drawBig(); big.invalidateSize(); }, 30); }
  $('#openMap').addEventListener('click', openMap); $('#mMap').addEventListener('click', openMap);
  $('#mapClose').addEventListener('click', function () { $('#mapOv').hidden = true; document.body.classList.remove('noscroll'); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !$('#mapOv').hidden) $('#mapClose').click(); });

  load();
})();
