/* Travelety front-end. All outbound booking links go through /go/* on our own domain. */
(function () {
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => [...el.querySelectorAll(s)];
  const AIRPORTS = window.TRV_AIRPORTS || [];
  const byCode = Object.fromEntries(AIRPORTS.map(a => [a[0], a]));
  const fmt = d => { const z = new Date(d.getTime() - d.getTimezoneOffset() * 60000); return z.toISOString().slice(0, 10); };
  const addDays = (n, from) => { const d = from ? new Date(from) : new Date(); d.setDate(d.getDate() + n); return d; };

  // Mobile menu
  const burger = $('.burger');
  if (burger) burger.addEventListener('click', () => $('#navLinks').classList.toggle('open'));
  const yr = $('#yr'); if (yr) yr.textContent = new Date().getFullYear();

  $$('.search').forEach(initSearch);

  function initSearch(box) {
    // Tabs
    const openTab = name => {
      $$('.tab', box).forEach(t => t.classList.toggle('active', t.dataset.panel === name));
      $$('.panel', box).forEach(p => p.classList.toggle('active', p.dataset.name === name));
    };
    $$('.tab', box).forEach(t => t.addEventListener('click', () => openTab(t.dataset.panel)));
    $$('[data-open-tab]').forEach(a => a.addEventListener('click', () => openTab(a.dataset.openTab)));

    const ff = $('.flight-form', box);
    if (ff) initFlights(ff, box);
    const hf = $('.hotel-form', box);
    if (hf) initHotels(hf);
  }

  function initFlights(form, box) {
    const dep = $('[name=depart]', form), ret = $('[name=return]', form);
    const today = fmt(new Date());
    dep.min = ret.min = today;
    dep.value = dep.value || fmt(addDays(14));
    ret.value = ret.value || fmt(addDays(21));
    dep.addEventListener('change', () => { if (ret.value < dep.value) ret.value = fmt(addDays(7, dep.value)); ret.min = dep.value; });

    const retField = ret.closest('.field');
    $$('input[name=trip]', box).forEach(r => r.addEventListener('change', () => {
      const ow = $('input[name=trip]:checked', box).value === 'ow';
      retField.style.opacity = ow ? .4 : 1; ret.disabled = ow;
    }));

    const sel = {};
    const pickers = {};
    ['from', 'to'].forEach(key => {
      const wrap = $(`[data-ac=${key}]`, form);
      const input = $('input', wrap), box = $('.ac', wrap), hint = $('small', wrap), hidden = $(`[name=${key}]`, form);
      let idx = -1, items = [];
      const render = q => {
        q = q.trim().toLowerCase();
        items = !q ? AIRPORTS.slice(0, 8) : AIRPORTS.filter(a =>
          a[0].toLowerCase() === q || a[1].toLowerCase().startsWith(q) || a[2].toLowerCase().includes(q) || a[3].toLowerCase().startsWith(q)).slice(0, 8);
        if (!items.length && /^[a-z]{3}$/i.test(q)) items = [[q.toUpperCase(), q.toUpperCase(), 'Airport code', '']];
        box.innerHTML = items.map((a, i) => `<div data-i="${i}"><b>${a[1]} <small style="color:var(--muted);font-weight:400">${a[2]}</small></b><span>${a[0]}</span></div>`).join('');
        box.style.display = items.length ? 'block' : 'none'; idx = -1;
      };
      const pick = a => { sel[key] = a[0]; hidden.value = a[0]; input.value = a[1]; hint.textContent = `${a[0]} · ${a[2]}`; box.style.display = 'none'; };
      pickers[key] = pick;
      if (hidden.value && byCode[hidden.value]) pick(byCode[hidden.value]);
      input.addEventListener('focus', () => { input.select(); render(''); });
      input.addEventListener('input', () => { sel[key] = null; hidden.value = ''; render(input.value); });
      input.addEventListener('keydown', e => {
        const els = [...box.children];
        if (e.key === 'ArrowDown') { idx = Math.min(idx + 1, els.length - 1); e.preventDefault(); }
        else if (e.key === 'ArrowUp') { idx = Math.max(idx - 1, 0); e.preventDefault(); }
        else if (e.key === 'Enter' && box.style.display === 'block') { e.preventDefault(); pick(items[Math.max(idx, 0)]); return; }
        els.forEach((el, i) => el.classList.toggle('hi', i === idx));
      });
      box.addEventListener('mousedown', e => { const d = e.target.closest('[data-i]'); if (d) pick(items[+d.dataset.i]); });
      input.addEventListener('blur', () => setTimeout(() => { box.style.display = 'none'; if (!sel[key] && items[0] && input.value) pick(items[0]); }, 150));
    });

    const swap = $('.swap', form);
    if (swap) swap.addEventListener('click', () => {
      const f = sel.from && byCode[sel.from], t = sel.to && byCode[sel.to];
      if (t) pickers.from(t); if (f) pickers.to(f);
    });

    // Destination cards on the page can fill the "to" field
    $$('[data-fill-to]').forEach(c => c.addEventListener('click', e => {
      const a = byCode[c.dataset.fillTo]; if (!a) return;
      e.preventDefault(); pickers.to(a); window.scrollTo({ top: 0, behavior: 'smooth' });
    }));

    // Passengers
    const pax = { adults: 1, children: 0, infants: 0 };
    const pop = $('.pax-pop', form), label = $('.pax-label', form), cabin = $('[name=cabin]', form);
    const paxText = () => { const n = pax.adults + pax.children + pax.infants; label.value = `${n} traveller${n > 1 ? 's' : ''}, ${cabin.value === '1' ? 'Business' : 'Economy'}`; };
    label.addEventListener('click', e => { e.stopPropagation(); pop.style.display = pop.style.display === 'block' ? 'none' : 'block'; });
    pop.addEventListener('click', e => e.stopPropagation());
    document.addEventListener('click', () => pop.style.display = 'none');
    $$('.stepper', pop).forEach(s => {
      const k = s.dataset.k, [minus, val, plus] = s.children;
      const upd = () => { val.textContent = pax[k]; $(`[name=${k}]`, form).value = pax[k]; paxText(); };
      minus.onclick = () => { pax[k] = Math.max(k === 'adults' ? 1 : 0, pax[k] - 1); upd(); };
      plus.onclick = () => { if (pax.adults + pax.children + pax.infants < 9 && !(k === 'infants' && pax.infants >= pax.adults)) pax[k]++; upd(); };
    });
    cabin.addEventListener('change', paxText);

    form.addEventListener('submit', e => {
      if (!$('[name=from]', form).value) { e.preventDefault(); $('[data-ac=from] input', form).focus(); return; }
      if (!$('[name=to]', form).value) { e.preventDefault(); $('[data-ac=to] input', form).focus(); }
    });
  }

  function initHotels(form) {
    const hin = $('[name=checkin]', form), hout = $('[name=checkout]', form);
    hin.min = hout.min = fmt(new Date());
    hin.value = hin.value || fmt(addDays(14));
    hout.value = hout.value || fmt(addDays(17));
    hin.addEventListener('change', () => { if (hout.value <= hin.value) hout.value = fmt(addDays(3, hin.value)); hout.min = hin.value; });
    form.addEventListener('submit', e => { const c = $('[name=city]', form); if (!c.value.trim()) { e.preventDefault(); c.focus(); } });
  }

  // Fare month bars: click to search that month
  $$('.months .bar[data-href]').forEach(b => b.addEventListener('click', () => location.href = b.dataset.href));
})();

(function(){
  var h=document.getElementById('hdr');
  if(h)addEventListener('scroll',function(){h.classList.toggle('stuck',scrollY>40)},{passive:true});
  var els=document.querySelectorAll('.rv');
  if(!('IntersectionObserver' in window)){els.forEach(function(e){e.classList.add('in')});return}
  var io=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){e.target.classList.add('in');io.unobserve(e.target)}})},{threshold:.08});
  els.forEach(function(e){io.observe(e)});
})();
