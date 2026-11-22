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
    const mcForm = $('.mc-form', box);
    const applyTrip = () => {
      const t = ($('input[name=trip]:checked', box) || {}).value || 'rt';
      const ow = t === 'ow';
      retField.style.opacity = ow ? .4 : 1; ret.disabled = ow;
      if (mcForm) { mcForm.hidden = t !== 'mc'; form.hidden = t === 'mc'; }
    };
    $$('input[name=trip]', box).forEach(r => r.addEventListener('change', applyTrip));
    applyTrip();
    if (mcForm) initMulti(mcForm, form);

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
    const pax = { adults: +($('[name=adults]', form).value || 1), children: +($('[name=children]', form).value || 0), infants: +($('[name=infants]', form).value || 0) };
    const pop = $('.pax-pop', form), label = $('.pax-label', form), cabin = $('[name=cabin]', form);
    const CAB = { '0': 'Economy', '1': 'Business', '2': 'First', '3': 'Premium economy' };
    const paxText = () => {
      const n = pax.adults + pax.children + pax.infants, txt = `${n} traveller${n > 1 ? 's' : ''}, ${CAB[cabin.value] || 'Economy'}`;
      label.value = txt;
      if (mcForm) { ['adults', 'children', 'infants'].forEach(k => $(`[name=${k}]`, mcForm).value = pax[k]); $('[name=cabin]', mcForm).value = cabin.value; }
    };
    $$('.stepper', pop).forEach(s => { s.children[1].textContent = pax[s.dataset.k]; });
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
    paxText();

    form.addEventListener('submit', e => {
      if (!$('[name=from]', form).value) { e.preventDefault(); $('[data-ac=from] input', form).focus(); return; }
      if (!$('[name=to]', form).value) { e.preventDefault(); $('[data-ac=to] input', form).focus(); }
    });
  }

  /* Airport autocomplete on any .field[data-ac2] holding a visible input + hidden [data-code] */
  function airportField(wrap) {
    const input = $('input:not([type=hidden])', wrap), list = $('.ac', wrap), hint = $('small', wrap), hidden = $('[data-code]', wrap);
    let idx = -1, items = [];
    const render = q => {
      q = q.trim().toLowerCase();
      items = !q ? AIRPORTS.slice(0, 8) : AIRPORTS.filter(a => a[0].toLowerCase() === q || a[1].toLowerCase().startsWith(q) || a[2].toLowerCase().includes(q) || a[3].toLowerCase().startsWith(q)).slice(0, 8);
      if (!items.length && /^[a-z]{3}$/i.test(q)) items = [[q.toUpperCase(), q.toUpperCase(), 'Airport code', '']];
      list.innerHTML = items.map((a, i) => `<div data-i="${i}"><b>${a[1]} <small style="color:var(--muted);font-weight:400">${a[2]}</small></b><span>${a[0]}</span></div>`).join('');
      list.style.display = items.length ? 'block' : 'none'; idx = -1;
    };
    const pick = a => { hidden.value = a[0]; input.value = a[1]; hint.textContent = `${a[0]} · ${a[2]}`; list.style.display = 'none'; wrap.dispatchEvent(new CustomEvent('picked', { bubbles: true, detail: a })); };
    input.addEventListener('focus', () => { input.select(); render(''); });
    input.addEventListener('input', () => { hidden.value = ''; render(input.value); });
    input.addEventListener('keydown', e => {
      const els = [...list.children];
      if (e.key === 'ArrowDown') { idx = Math.min(idx + 1, els.length - 1); e.preventDefault(); }
      else if (e.key === 'ArrowUp') { idx = Math.max(idx - 1, 0); e.preventDefault(); }
      else if (e.key === 'Enter' && list.style.display === 'block') { e.preventDefault(); pick(items[Math.max(idx, 0)]); return; }
      els.forEach((el, i) => el.classList.toggle('hi', i === idx));
    });
    list.addEventListener('mousedown', e => { const d = e.target.closest('[data-i]'); if (d) pick(items[+d.dataset.i]); });
    input.addEventListener('blur', () => setTimeout(() => { list.style.display = 'none'; if (!hidden.value && items[0] && input.value) pick(items[0]); }, 150));
    return { pick };
  }

  function initMulti(form, main) {
    const rows = $('.mc-rows', form), MAX = 5;
    const today = fmt(new Date());
    const renumber = () => {
      $$('.mc-row', rows).forEach((r, i) => {
        $('.mc-n', r).textContent = 'Flight ' + (i + 1);
        $$('[name^="legs["]', r).forEach(inp => inp.name = inp.name.replace(/legs\[\d+\]/, `legs[${i}]`));
        const d = $('input[type=date]', r), prev = r.previousElementSibling && $('input[type=date]', r.previousElementSibling);
        d.min = prev && prev.value ? prev.value : today;
        if (!d.value) d.value = prev && prev.value ? fmt(addDays(3, prev.value)) : fmt(addDays(14));
        $('.mc-del', r).style.visibility = $$('.mc-row', rows).length > 2 ? 'visible' : 'hidden';
      });
      $('.mc-add', form).disabled = $$('.mc-row', rows).length >= MAX;
    };
    const wire = r => { $$('[data-ac2]', r).forEach(airportField); $('.mc-del', r).addEventListener('click', () => { r.remove(); renumber(); }); };
    $$('.mc-row', rows).forEach(wire);
    rows.addEventListener('picked', e => {
      const row = e.target.closest('.mc-row'), fields = $$('[data-ac2]', row);
      if (e.target === fields[1]) { const next = row.nextElementSibling; if (next) { const nf = $('[data-ac2]', next), nh = $('[data-code]', nf); if (!nh.value) { nh.value = e.detail[0]; $('input:not([type=hidden])', nf).value = e.detail[1]; $('small', nf).textContent = `${e.detail[0]} · ${e.detail[2]}`; } } }
    });
    rows.addEventListener('change', e => { if (e.target.type === 'date') renumber(); });
    $('.mc-add', form).addEventListener('click', () => {
      const last = $$('.mc-row', rows).pop(), clone = last.cloneNode(true);
      $$('input', clone).forEach(i => { if (i.type !== 'date') i.value = ''; else i.value = ''; });
      $$('[data-ac2] small', clone).forEach(sm => sm.textContent = 'City or airport');
      const lastTo = $$('[data-ac2]', last)[1], nf = $('[data-ac2]', clone);
      if ($('[data-code]', lastTo).value) { $('[data-code]', nf).value = $('[data-code]', lastTo).value; $('input:not([type=hidden])', nf).value = $('input:not([type=hidden])', lastTo).value; $('small', nf).textContent = $('small', lastTo).textContent; }
      rows.appendChild(clone); wire(clone); renumber();
    });
    form.addEventListener('submit', e => {
      const bad = $$('[data-code]', form).find(h => !h.value);
      if (bad) { e.preventDefault(); $('input:not([type=hidden])', bad.closest('.field')).focus(); }
    });
    renumber();
  }

  function initHotels(form) {
    const hin = $('[name=checkin]', form), hout = $('[name=checkout]', form);
    hin.min = hout.min = fmt(new Date());
    hin.value = hin.value || fmt(addDays(14));
    hout.value = hout.value || fmt(addDays(17));
    hin.addEventListener('change', () => { if (hout.value <= hin.value) hout.value = fmt(addDays(3, hin.value)); hout.min = hin.value; });
    form.addEventListener('submit', e => { const c = $('[name=city]', form) || $('[name=q]', form); if (c && !c.value.trim()) { e.preventDefault(); c.focus(); } });
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

/* Hotel destination autocomplete + date sync */
(function(){
  var forms=document.querySelectorAll('.hs-form');
  forms.forEach(function(f){
    var box=f.querySelector('[data-hac]'); if(!box)return;
    var input=box.querySelector('input'), list=box.querySelector('.ac'), small=box.querySelector('small'), pid=f.querySelector('[name=placeId]'), t, items=[], hi=-1;
    function render(){ list.innerHTML=items.map(function(p,i){return '<div class="'+(i===hi?'hi':'')+'" data-i="'+i+'"><b>'+esc(p.name)+'</b><span>'+esc(p.address||'')+'</span></div>'}).join(''); list.style.display=items.length?'block':'none'; }
    function esc(s){return String(s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
    function pick(i){ var p=items[i]; if(!p)return; input.value=p.name; pid.value=p.placeId||''; small.textContent=p.address||''; items=[]; render(); }
    input.addEventListener('input',function(){ pid.value=''; clearTimeout(t); var q=input.value.trim(); if(q.length<2){items=[];render();return;}
      t=setTimeout(function(){ fetch('/hotels/places?q='+encodeURIComponent(q)).then(function(r){return r.json()}).then(function(d){items=d||[];hi=-1;render()}).catch(function(){}); },300); });
    input.addEventListener('keydown',function(e){ if(!items.length)return; if(e.key==='ArrowDown'){hi=Math.min(items.length-1,hi+1);render();e.preventDefault()} else if(e.key==='ArrowUp'){hi=Math.max(0,hi-1);render();e.preventDefault()} else if(e.key==='Enter'&&hi>=0){pick(hi);e.preventDefault()} });
    list.addEventListener('mousedown',function(e){ var d=e.target.closest('[data-i]'); if(d){pick(+d.dataset.i);e.preventDefault()} });
    input.addEventListener('blur',function(){ setTimeout(function(){items=[];render()},150) });
    var ci=f.querySelector('[name=checkin]'), co=f.querySelector('[name=checkout]');
    if(ci&&co) ci.addEventListener('change',function(){ var d=new Date(ci.value); if(isNaN(d))return; d.setDate(d.getDate()+1); var n=d.toISOString().slice(0,10); co.min=n; if(!co.value||co.value<=ci.value) co.value=n; });
  });
})();

/* Currency picker */
(function(){
  var w=document.getElementById('curPick'); if(!w)return;
  var btn=w.querySelector('button'), pop=w.querySelector('.curpop'), q=pop.querySelector('input');
  btn.addEventListener('click',function(e){e.stopPropagation();pop.hidden=!pop.hidden;btn.setAttribute('aria-expanded',String(!pop.hidden));if(!pop.hidden){q.value='';filter();setTimeout(function(){q.focus()},30)}});
  document.addEventListener('click',function(e){if(!w.contains(e.target))pop.hidden=true});
  document.addEventListener('keydown',function(e){if(e.key==='Escape')pop.hidden=true});
  function filter(){var v=q.value.trim().toLowerCase();pop.querySelectorAll('.curgrid a').forEach(function(a){a.hidden=v&&a.dataset.s.indexOf(v)<0})}
  q.addEventListener('input',filter);
})();
