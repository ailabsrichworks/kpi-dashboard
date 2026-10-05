<!doctype html>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Talent Tracker</title>
@php
    $ff = session('theme_font_family') ?: 'Inter';
    $zoom = ['sm' => 0.9, 'md' => 1, 'lg' => 1.15][session('theme_font_size') ?: 'md'];
@endphp
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family={{ str_replace(' ', '+', $ff) }}:wght@400;500;600;700;800;900&display=swap">
<style>
    :root {
        --th-bg:     {{ session('theme_bg')      ?: '#F5F5F3' }};
        --th-card:   {{ session('theme_card')    ?: '#FFFFFF' }};
        --th-border: {{ session('theme_border')  ?: '#6B9080' }};
        --th-accent: {{ session('theme_accent')  ?: '#D4AF37' }};
        --th-accent2:{{ session('theme_accent2') ?: '#6B9080' }};
        --th-text:   {{ session('theme_text')    ?: '#0F172A' }};
        --th-font:   '{{ $ff }}';
        --th-zoom:   {{ $zoom }};
    }
</style>
@verbatim
<style>
/* Look & feel follows the KPI system: warm page background, white rounded-2xl cards with a soft sage border,
   10px uppercase "label" headings, deep-green table headers, gold highlights. Colours come from the
   user's Appearance theme (see --th-* above), so changing the theme in Settings restyles this page too. */
:root{
  --bg:var(--th-bg); --surface:var(--th-card); --ink:var(--th-text); --muted:#64748b;
  --sage:var(--th-accent2); --gold:var(--th-accent); --deep:#1a3d34;
  --line:color-mix(in srgb,var(--th-border) 22%,white); --surface2:color-mix(in srgb,var(--th-accent2) 7%,white);
  --accent:var(--deep); --accent-ink:#fff; --danger:#c0392b;
  --c-attended:#3b6fb6; --c-speaker:color-mix(in srgb,var(--th-accent) 75%,#5a4300); --c-qna:#7c5cbf; --c-other:#64748b;
  --shadow:0 1px 2px rgba(15,23,42,.04);
}
*{box-sizing:border-box}
[hidden]{display:none!important}
html{zoom:var(--th-zoom)}
body{margin:0;background:var(--bg);color:var(--ink);font-family:var(--th-font),'Inter',system-ui,sans-serif;font-size:13px;line-height:1.5;padding:20px max(16px,2vw) 48px}
h1,h2,h3{margin:0;letter-spacing:-.01em;font-weight:800}
h1{font-size:22px}h2{font-size:17px}h3{font-size:14px}
button,input,select,textarea{font:inherit;color:inherit}
button{cursor:pointer}
:focus-visible{outline:2px solid var(--gold);outline-offset:2px}
.wrap{max-width:1240px;margin:0 auto;display:flex;flex-direction:column;gap:16px}

/* page header: label + title, then tabs and actions on one row */
.hero{max-width:1240px;margin:0 auto 14px}
.eyebrow{display:block;font-size:10px;font-weight:900;letter-spacing:.14em;text-transform:uppercase;color:var(--sage)}
.hero h1{margin:2px 0 2px}
.hero p{margin:0;color:var(--muted);max-width:70ch}
.topbar{max-width:1240px;margin:0 auto 16px}
.topbar-in{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.tabs{display:flex;gap:6px;flex:1}
.tab{border:1px solid var(--line);background:var(--surface);border-radius:12px;padding:7px 16px;font-weight:700;font-size:12px;color:var(--muted)}
.tab:hover{border-color:var(--sage);color:var(--ink)}
.tab[aria-selected="true"]{background:var(--deep);border-color:var(--deep);color:#fff}
.row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}

.btn{border:1px solid var(--line);background:var(--surface);border-radius:12px;padding:7px 14px;font-weight:700;font-size:12px}
.btn:hover{border-color:var(--sage)}
.btn.primary{background:var(--deep);color:#fff;border-color:var(--deep)}
.btn.primary:hover{filter:brightness(1.15)}
.btn.danger{color:var(--danger);border-color:color-mix(in srgb,var(--danger) 30%,white)}
.btn.small{padding:3px 10px;font-size:11px;border-radius:9px}
.btn[disabled]{opacity:.5;cursor:not-allowed}

.viewonly{background:color-mix(in srgb,var(--gold) 14%,white);border:1px solid color-mix(in srgb,var(--gold) 45%,white);color:color-mix(in srgb,var(--gold) 45%,black);border-radius:14px;padding:8px 14px;font-weight:700;font-size:12px}

/* stat cards */
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px}
.stat{background:var(--surface);border:1px solid var(--line);border-radius:16px;box-shadow:var(--shadow);padding:14px 16px}
.stat b{display:block;font-size:28px;line-height:1.1;font-weight:900;font-variant-numeric:tabular-nums;color:var(--c,var(--ink))}
.stat span{display:block;margin-top:3px;font-size:10px;font-weight:900;letter-spacing:.1em;text-transform:uppercase;color:var(--muted)}

/* workspace */
.work{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(0,1fr);gap:16px;align-items:start}
@media (max-width:900px){.work{grid-template-columns:minmax(0,1fr)}}
.panel{background:var(--surface);border:1px solid var(--line);border-radius:16px;box-shadow:var(--shadow);min-width:0;overflow:hidden}
.panel-h{padding:14px 16px;border-bottom:1px solid var(--line);display:flex;gap:8px;align-items:center;justify-content:space-between;flex-wrap:wrap}
.panel-b{padding:16px}
.tools{display:flex;flex-direction:column;gap:10px;padding:14px 16px;border-bottom:1px solid var(--line)}
input[type=text],input[type=date],select,textarea{width:100%;background:var(--surface);border:1.5px solid var(--line);border-radius:10px;padding:8px 12px}
input:focus,select:focus,textarea:focus{outline:none;border-color:var(--sage)}
textarea{resize:vertical;min-height:80px}
label{display:flex;flex-direction:column;gap:4px;font-size:10px;font-weight:900;letter-spacing:.1em;text-transform:uppercase;color:var(--sage)}
label input,label select,label textarea{text-transform:none;letter-spacing:0;font-weight:400;color:var(--ink);font-size:13px}
.chips{display:flex;gap:6px;flex-wrap:wrap}
.chip{border:1px solid var(--line);background:var(--surface);border-radius:999px;padding:3px 12px;font-size:11px;font-weight:600;color:var(--muted)}
.chip:hover{border-color:var(--sage)}
.chip[aria-pressed="true"]{background:var(--deep);border-color:var(--deep);color:#fff}

/* table */
.scroll{overflow-x:auto}
table{border-collapse:collapse;width:100%}
th{background:var(--deep);color:#fff;font-size:9px;font-weight:800;letter-spacing:.09em;text-transform:uppercase;text-align:left;padding:9px 11px;white-space:nowrap}
td{padding:9px 11px;border-bottom:1px solid color-mix(in srgb,var(--th-border) 12%,white);vertical-align:top}
th.n,td.n{text-align:center;font-variant-numeric:tabular-nums}
tr.person{cursor:pointer}
tr.person:hover td{background:var(--surface2)}
tr.person[aria-selected="true"] td{background:color-mix(in srgb,var(--gold) 12%,white)}
tr.person[aria-selected="true"] td:first-child{box-shadow:inset 3px 0 0 var(--gold)}
.name{font-weight:700}
.sub{color:var(--muted);font-size:11px}
.cnt{display:inline-block;min-width:22px;padding:1px 7px;border-radius:6px;font-size:11px;font-weight:800;font-variant-numeric:tabular-nums}
.cnt.zero{color:#cbd5e1;font-weight:400}
.cnt:not(.zero){background:color-mix(in srgb,var(--c) 14%,white);color:var(--c)}
.tag{display:inline-block;padding:1px 8px;border-radius:6px;font-size:10px;font-weight:800;white-space:nowrap;background:color-mix(in srgb,var(--c) 14%,white);color:var(--c)}
.t-speaker{--c:var(--c-speaker)}.t-attended{--c:var(--c-attended)}.t-qna{--c:var(--c-qna)}.t-other{--c:var(--c-other)}

/* selected person */
.d-summary{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:14px}
@media (max-width:420px){.d-summary{grid-template-columns:repeat(1,1fr)}}
.mini{border:1px solid var(--line);border-radius:12px;padding:8px 10px;border-top:3px solid var(--c,var(--line));background:var(--surface)}
.mini b{display:block;font-size:20px;line-height:1.1;font-weight:900;font-variant-numeric:tabular-nums;color:var(--c)}
.mini span{color:var(--muted);font-size:9px;font-weight:900;letter-spacing:.1em;text-transform:uppercase}
.span-line{color:var(--muted);font-size:11px;margin:-4px 0 14px}
.tl-wrap{border-top:1px solid var(--line)}
.tl-h{font-size:10px;font-weight:900;letter-spacing:.14em;text-transform:uppercase;color:var(--sage,var(--muted));margin:0 0 12px}
.timeline{list-style:none;margin:0;padding:0}
.timeline .yr{display:flex;align-items:center;gap:10px;margin:14px 0 8px;font-size:10px;font-weight:900;letter-spacing:.14em;color:var(--sage)}
.timeline .yr:first-child{margin-top:0}
.timeline .yr::after{content:"";flex:1;height:1px;background:var(--line)}
.tl-item{position:relative;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;margin:0 0 10px 24px;padding:10px 12px;background:var(--surface);border:1px solid var(--line);border-left:3px solid var(--c);border-radius:12px}
.tl-item::before{content:"";position:absolute;left:-24px;top:-11px;bottom:-11px;width:2px;background:var(--line)}
.tl-item::after{content:"";position:absolute;left:-29px;top:15px;width:12px;height:12px;border-radius:50%;background:var(--c);border:3px solid var(--bg);box-shadow:0 0 0 1px var(--c)}
.timeline li.yr + .tl-item::before{top:15px}
.tl-item:last-child::before{bottom:auto;height:26px}
.tl-item .when{color:var(--muted);font-size:11px;font-variant-numeric:tabular-nums;margin-left:8px}
.timeline .ti{font-weight:700;margin-top:3px;overflow-wrap:anywhere}
.timeline .no{color:var(--muted);font-size:12px;margin-top:2px;overflow-wrap:anywhere}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
@media (max-width:520px){.grid2{grid-template-columns:1fr}}
.empty{padding:28px 16px;text-align:center;color:var(--muted)}
.empty b{display:block;color:var(--ink);font-size:14px;font-weight:800;margin-bottom:4px}
.form{display:flex;flex-direction:column;gap:10px;padding:16px;border-top:1px solid var(--line);background:var(--surface2)}
.note{color:var(--muted);font-size:11px}

/* dialogs */
.overlay{position:fixed;inset:0;background:rgba(15,23,42,.45);display:flex;align-items:flex-start;justify-content:center;padding:6vh 16px;overflow:auto;z-index:10}
.modal{background:var(--surface);border:1px solid var(--line);border-radius:20px;width:min(640px,100%);padding:20px;display:flex;flex-direction:column;gap:12px;box-shadow:0 20px 60px rgba(15,23,42,.25)}
.toast{position:fixed;left:50%;bottom:20px;transform:translateX(-50%);background:var(--deep);color:#fff;padding:8px 16px;border-radius:12px;font-weight:700;z-index:20;max-width:90vw}
.ok-banner{background:#e8f5ee;color:#1f7a5a;border-radius:10px;padding:6px 10px;font-weight:700;font-size:12px}
.hist{border:1.5px dashed var(--line);border-radius:12px;padding:10px;display:flex;flex-direction:column;gap:8px}
.hist-row{display:grid;grid-template-columns:150px minmax(0,1fr) 140px auto;gap:6px;align-items:center}
@media (max-width:560px){.hist-row{grid-template-columns:1fr 1fr}.hist-row input[type=text]{grid-column:1/-1}}
@media (prefers-reduced-motion:no-preference){.btn,.chip,.tab,tr.person td{transition:background .12s,border-color .12s}}
</style>
@endverbatim

<header class="hero">
  <span class="eyebrow">People Development</span>
  <h1>Talent Tracker</h1>
  <p>Track who at Richworks has attended training, served as a speaker, and answered questions. For management to review and follow up.</p>
</header>

<nav class="topbar"><div class="topbar-in">
    <div class="tabs" role="tablist">
    <button class="tab" role="tab" id="tab-staff" aria-selected="true">Staff list</button>
    <button class="tab" role="tab" id="tab-records" aria-selected="false">All records</button>
  </div>
  <div class="row actions">
    <button class="btn" id="btn-export" hidden>Export CSV</button>
    <button class="btn" id="btn-bulk" hidden>Paste list of names</button>
    <button class="btn primary" id="btn-add-person" hidden>+ Add staff</button>
  </div>
</div></nav>

<div class="wrap">
  <div class="viewonly" id="viewonly" hidden>View only: only SLT and BTS can add, edit or delete records.</div>
  <section class="stats" id="stats" aria-label="Summary"></section>

  <!-- STAFF VIEW -->
  <div class="work" id="view-staff">
    <div class="panel">
      <div class="tools">
        <input type="text" id="q" placeholder="Search name, ID, position or department" aria-label="Search staff">
        <div class="chips" id="role-chips"></div>
      </div>
      <div class="scroll"><table>
        <thead><tr><th>Name</th><th class="n" title="Has attended training">Attended</th><th class="n">Speaker</th><th class="n" title="Answered questions">Q&amp;A</th></tr></thead>
        <tbody id="people-body"></tbody>
      </table></div>
      <div id="people-empty"></div>
    </div>

    <div class="panel" id="detail">
      <div id="detail-empty" class="empty"><b>Select a staff member</b>Click a name on the left to see their training, speaker and Q&A history.</div>
      <div id="detail-main" hidden>
        <div class="panel-h">
          <div><h2 id="d-name"></h2><div class="sub" id="d-sub"></div></div>
          <div class="row" id="d-actions">
            <button class="btn small" id="btn-edit-person">Edit</button>
            <button class="btn small danger" id="btn-del-person">Delete</button>
          </div>
        </div>
        <div class="panel-b"><div class="d-summary" id="d-summary"></div><div class="span-line" id="d-span"></div></div>
        <form class="form" id="rec-form" hidden>
          <h3>Add record</h3>
          <div class="grid2">
            <label>Type<select id="r-type"></select></label>
            <label>Date<input type="date" id="r-date" required></label>
          </div>
          <label>Topic / session title<input type="text" id="r-title" required placeholder="e.g. Customer Service Workshop"></label>
          <label>Notes (optional)<textarea id="r-notes" placeholder="e.g. Organiser, questions answered, duration"></textarea></label>
          <div class="row"><button class="btn primary" type="submit">Save record</button></div>
        </form>
        <div class="panel-b tl-wrap"><h3 class="tl-h">Timeline</h3><ul class="timeline" id="timeline"></ul></div>
              </div>
    </div>
  </div>

  <!-- RECORDS VIEW -->
  <div class="panel" id="view-records" hidden>
    <div class="tools">
      <div class="grid2">
        <input type="text" id="rq" placeholder="Search name or title" aria-label="Search records">
        <select id="rtype" aria-label="Filter by type"></select>
      </div>
    </div>
    <div class="scroll"><table>
      <thead><tr><th>Date</th><th>Name</th><th>Type</th><th>Title</th><th>Notes</th><th></th></tr></thead>
      <tbody id="rec-body"></tbody>
    </table></div>
    <div id="rec-empty"></div>
  </div>
</div>

<div id="overlay" class="overlay" hidden></div>

@verbatim
<script>
const TYPES = [
  {k:'attended', label:'Attended training', short:'Attended'},
  {k:'speaker',  label:'Speaker',        short:'Speaker'},
  {k:'qna',      label:'Answered questions', short:'Q&A'},
  {k:'other',    label:'Other',      short:'Other'}
];
const TYPE = Object.fromEntries(TYPES.map(t=>[t.k,t]));
const MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

const $ = id => document.getElementById(id);
const state = {people:[], records:[], sel:null, tab:'staff', roleFilter:'all', q:'', rq:'', rtype:'all', canWrite:true, loaded:false};
let db = null;

function h(tag, attrs, ...kids){
  const e = document.createElement(tag);
  for (const [k,v] of Object.entries(attrs||{})){
    if (k==='class') e.className=v;
    else if (k.startsWith('on')) e.addEventListener(k.slice(2), v);
    else if (v===true) e.setAttribute(k,'');
    else if (v!==false && v!=null) e.setAttribute(k,v);
  }
  for (const c of kids.flat()) if (c!=null && c!==false) e.append(c.nodeType? c : document.createTextNode(c));
  return e;
}
function fmtDate(s){ if(!s) return ''; const [y,m,d]=s.split('-').map(Number); return `${d} ${MONTHS[m-1]} ${y}`; }
function today(){ const d=new Date(); return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`; }
function toast(msg){ const t=h('div',{class:'toast',role:'status'},msg); document.body.append(t); setTimeout(()=>t.remove(),2600); }
function pById(id){ return state.people.find(p=>p.id===id); }

function counts(pid){
  const c = {attended:0,speaker:0,qna:0,other:0,total:0,last:''};
  for (const r of state.records) if (r.personId===pid){ c[r.type]=(c[r.type]||0)+1; c.total++; if(r.date>c.last) c.last=r.date; }
  return c;
}
const cntEl = (n,type)=> h('span',{class:'cnt t-'+type+(n?'':' zero')}, n||'–');
const tag = type => h('span',{class:'tag t-'+type}, TYPE[type]?.label || type);

/* ---------- render ---------- */
function renderStats(){
  const ids = t => new Set(state.records.filter(r=>r.type===t).map(r=>r.personId)).size;
  const items = [
    ['Staff in list', state.people.length, 'var(--ink)'],
    ['Attended training', ids('attended'), 'var(--c-attended)'],
    ['Been a speaker', ids('speaker'), 'var(--c-speaker)'],
    ['Answered questions', ids('qna'), 'var(--c-qna)'],
  ];
  $('stats').replaceChildren(...items.map(([l,n,c])=>h('div',{class:'stat',style:'--c:'+c}, h('b',{},n), h('span',{},l))));
}

function renderChips(){
  const opts = [['all','All'],...TYPES.filter(t=>t.k!=='other').map(t=>[t.k,t.label]),['none','No records yet']];
  $('role-chips').replaceChildren(...opts.map(([k,l])=>
    h('button',{class:'chip','aria-pressed':String(state.roleFilter===k),onclick:()=>{state.roleFilter=k;renderChips();renderPeople();}},l)));
}

function filteredPeople(){
  const q = state.q.trim().toLowerCase();
  return state.people.filter(p=>{
    if (q && !(`${p.name} ${p.staffId||''} ${p.company||''} ${p.department||''} ${p.position||''}`.toLowerCase().includes(q))) return false;
    const c = counts(p.id);
    if (state.roleFilter==='none') return c.total===0;
    if (state.roleFilter!=='all') return c[state.roleFilter]>0;
    return true;
  }).sort((a,b)=>a.name.localeCompare(b.name));
}

function renderPeople(){
  const list = filteredPeople();
  const body = $('people-body');
  body.replaceChildren(...list.map(p=>{
    const c = counts(p.id);
    return h('tr',{class:'person',tabindex:'0','aria-selected':String(state.sel===p.id),
        onclick:()=>select(p.id), onkeydown:e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();select(p.id);}}},
      h('td',{}, h('div',{class:'name'},p.name), h('div',{class:'sub'},[p.staffId,p.position,p.company,p.department].filter(Boolean).join(' · ')||'No details')),
      h('td',{class:'n'},cntEl(c.attended,'attended')),
      h('td',{class:'n'},cntEl(c.speaker,'speaker')),
      h('td',{class:'n'},cntEl(c.qna,'qna')));
  }));
  const em = $('people-empty');
  if (!state.loaded) em.replaceChildren(h('div',{class:'empty'},'Loading list…'));
  else if (!state.people.length) em.replaceChildren(h('div',{class:'empty'}, h('b',{},'No names in the list yet'),
      'No active staff found for this company.'));
  else if (!list.length) em.replaceChildren(h('div',{class:'empty'}, h('b',{},'No matches'),'Try changing the search or filter.'));
  else em.replaceChildren();
}

let confirmDel = null;
function renderDetail(){
  const p = pById(state.sel);
  $('detail-empty').hidden = !!p;
  $('detail-main').hidden = !p;
  if (!p) return;
  const c = counts(p.id);
  $('d-name').textContent = p.name;
  $('d-sub').textContent = [p.staffId,p.position,p.company,p.department,p.remarks].filter(Boolean).join(' · ') || 'No details';
  $('d-actions').hidden = true;
  $('rec-form').hidden = !state.canWrite;
  $('btn-del-person').textContent = confirmDel==='person' ? 'Yes, delete this staff member' : 'Delete';
  const recs = state.records.filter(r=>r.personId===p.id).sort((a,b)=>(b.date||'').localeCompare(a.date||''));
  $('d-summary').replaceChildren(...[['attended','Attended'],['speaker','Speaker'],['qna','Q&A']].map(([k,l])=>
    h('div',{class:'mini t-'+k},h('b',{},c[k]),h('span',{},l))));
  const dates = recs.map(r=>r.date).filter(Boolean);
  $('d-span').textContent = dates.length ? `${c.total} activit${c.total===1?'y':'ies'} · first ${fmtDate(dates[dates.length-1])} · latest ${fmtDate(dates[0])}` : '';
  const tl = $('timeline');
  if (!recs.length){
    tl.replaceChildren(h('li',{style:'display:block'}, h('div',{class:'empty',style:'padding:12px'}, h('b',{},'No records yet'), 'Add the first record above.')));
    return;
  }
  const items = []; let yr = null;
  for (const r of recs){
    const y = (r.date||'').slice(0,4) || 'No date';
    if (y!==yr){ yr=y; items.push(h('li',{class:'yr'},y)); }
    items.push(h('li',{class:'tl-item t-'+r.type},
      h('div',{}, h('div',{}, tag(r.type), h('span',{class:'when'},fmtDate(r.date))), h('div',{class:'ti'},r.title), r.notes? h('div',{class:'no'},r.notes):null),
      state.canWrite ? h('div',{class:'row',style:'flex-wrap:nowrap;align-self:start'}, h('button',{class:'btn small',onclick:()=>recordModal(r)},'Edit'), h('button',{class:'btn small'+(confirmDel===r.id?' danger':''),onclick:()=>delRecord(r.id)}, confirmDel===r.id?'Confirm delete':'Delete')) : null));
  }
  tl.replaceChildren(...items);
}

function renderRecords(){
  const q = state.rq.trim().toLowerCase();
  const list = state.records.filter(r=>{
    if (state.rtype!=='all' && r.type!==state.rtype) return false;
    const p = pById(r.personId);
    return !q || `${p?.name||''} ${r.title}`.toLowerCase().includes(q);
  }).sort((a,b)=>(b.date||'').localeCompare(a.date||''));
  $('rec-body').replaceChildren(...list.map(r=>{
    const p = pById(r.personId);
    return h('tr',{},
      h('td',{style:'white-space:nowrap'},fmtDate(r.date)),
      h('td',{}, p? h('button',{class:'btn small',onclick:()=>{state.tab='staff';state.sel=p.id;renderAll();}},p.name) : h('span',{class:'sub'},'(staff deleted)')),
      h('td',{},tag(r.type)),
      h('td',{style:'overflow-wrap:anywhere'},r.title),
      h('td',{class:'sub',style:'overflow-wrap:anywhere'},r.notes||''),
      h('td',{}, state.canWrite? h('div',{class:'row',style:'flex-wrap:nowrap'}, h('button',{class:'btn small',onclick:()=>recordModal(r)},'Edit'), h('button',{class:'btn small'+(confirmDel===r.id?' danger':''),onclick:()=>delRecord(r.id)}, confirmDel===r.id?'Confirm':'Delete')):null));
  }));
  $('rec-empty').replaceChildren(list.length?'' : h('div',{class:'empty'}, h('b',{},state.records.length?'No matches':'No records yet'), state.records.length?'Try changing the filter.':'Select a staff member and add a training, speaker or Q&A record.'));
}

function renderTabs(){
  $('tab-staff').setAttribute('aria-selected',String(state.tab==='staff'));
  $('tab-records').setAttribute('aria-selected',String(state.tab==='records'));
  $('view-staff').hidden = state.tab!=='staff';
  $('view-records').hidden = state.tab!=='records';
}
function renderAll(){
  $('viewonly').hidden = state.canWrite;
  $('btn-add-person').hidden = $('btn-bulk').hidden = true;
  $('btn-export').hidden = !state.people.length;
  renderTabs(); renderStats(); renderChips(); renderPeople(); renderDetail(); renderRecords();
}

/* ---------- actions ---------- */
function select(id){ state.sel=id; confirmDel=null; renderPeople(); renderDetail();
  if (matchMedia('(max-width:860px)').matches) $('detail').scrollIntoView({behavior:'smooth',block:'start'}); }

async function guard(fn){
  try { await fn(); }
  catch(e){ toast((e?.code==='invalid_argument'||e?.code==='forbidden') ? 'Only SLT and BTS can add or edit records.' : e?.code==='quota_exceeded' ? 'Storage limit reached.' : 'Could not save. Please try again.'); }
}
async function delRecord(id){
  if (confirmDel!==id){ confirmDel=id; renderDetail(); renderRecords(); return; }
  confirmDel=null;
  await guard(()=>db.doc('records/'+id).delete());
}
$('btn-del-person').onclick = async ()=>{
  if (confirmDel!=='person'){ confirmDel='person'; renderDetail(); return; }
  const id = state.sel; confirmDel=null;
  await guard(async()=>{
    await db.doc('people/'+id).delete();
    state.sel=null; toast('Staff deleted');
  });
};

$('rec-form').onsubmit = async e=>{
  e.preventDefault();
  const title = $('r-title').value.trim(); if(!title||!state.sel) return;
  const rec = {personId:state.sel, type:$('r-type').value, title, date:$('r-date').value||today(), notes:$('r-notes').value.trim(), createdAt:Date.now()};
  await guard(async()=>{ await db.collection('records').add(rec); $('r-title').value=''; $('r-notes').value=''; toast('Record saved'); });
};

function openModal(content){ const o=$('overlay'); o.replaceChildren(h('div',{class:'modal',role:'dialog','aria-modal':'true'},content)); o.hidden=false; }
function closeModal(){ $('overlay').hidden=true; $('overlay').replaceChildren(); }
$('overlay').addEventListener('mousedown',e=>{ if(e.target===$('overlay')) closeModal(); });
document.addEventListener('keydown',e=>{ if(e.key==='Escape') closeModal(); });

function histRow(list,box){
  const type=h('select',{},TYPES.filter(t=>t.k!=='other').map(t=>h('option',{value:t.k},t.label)));
  const title=h('input',{type:'text',placeholder:'Topic / session title'});
  const date=h('input',{type:'date',value:today()});
  const row={get:()=>title.value.trim()?{type:type.value,title:title.value.trim(),date:date.value||today()}:null};
  const el=h('div',{class:'hist-row'},type,title,date,h('button',{class:'btn small',type:'button','aria-label':'Remove row',onclick:()=>{ el.remove(); list.splice(list.indexOf(row),1); }},'\u2715'));
  list.push(row); box.append(el); return row;
}
function personModal(p){
  const name=h('input',{type:'text',id:'pm-name',required:true,value:p?.name||''});
  const sid=h('input',{type:'text',id:'pm-sid',value:p?.staffId||'',placeholder:'e.g. ID #0009'});
  const rem=h('input',{type:'text',id:'pm-rem',value:p?.remarks||'',placeholder:'e.g. VP'});
  const com=h('input',{type:'text',id:'pm-com',value:p?.company||'',placeholder:'e.g. Richworks Sdn Bhd'});
  const dep=h('input',{type:'text',id:'pm-dep',value:p?.department||'',placeholder:'e.g. Operations'});
  const pos=h('input',{type:'text',id:'pm-pos',value:p?.position||'',placeholder:'e.g. Executive'});
  const form=h('form',{style:'display:flex;flex-direction:column;gap:12px',onsubmit:async e=>{
    e.preventDefault();
    const data={name:name.value.trim(),staffId:sid.value.trim(),remarks:rem.value.trim(),company:com.value.trim(),department:dep.value.trim(),position:pos.value.trim()};
    if(!data.name) return;
    await guard(async()=>{
      if(p){ await db.doc('people/'+p.id).update(data); closeModal(); toast('Saved'); return; }
      const ref=await db.collection('people').add({...data,createdAt:Date.now()});
      state.sel=ref.id; renderAll();
      trainingModal(ref.id,data.name);
    });
  }},
    h('h2',{},p?'Edit staff':'Add staff'),
    h('label',{},'Name',name),
    h('div',{class:'grid2'},h('label',{},'Staff ID',sid),h('label',{},'Remarks (optional)',rem)),
    h('div',{class:'grid2'},h('label',{},'Company',com),h('label',{},'Department',dep)),
    h('label',{},'Position',pos),
    h('div',{class:'row'},h('button',{class:'btn primary',type:'submit'},p?'Save':'Save & continue'),h('button',{class:'btn',type:'button',onclick:closeModal},'Cancel')));
  openModal(form); name.focus();
}
/* Step 2 after adding a staff member: record the trainings they attended / sessions they spoke at */
function trainingModal(personId,personName){
  const rows=[]; const box=h('div',{style:'display:flex;flex-direction:column;gap:8px'});
  const form=h('form',{style:'display:flex;flex-direction:column;gap:12px',onsubmit:async e=>{
    e.preventDefault();
    const recs=rows.map(r=>r.get()).filter(Boolean);
    if(!recs.length){ closeModal(); return; }
    await guard(async()=>{
      for(const r of recs) await db.collection('records').add({personId,...r,notes:'',createdAt:Date.now()});
      closeModal(); toast(recs.length+(recs.length===1?' activity added':' activities added'));
    });
  }},
    h('div',{class:'ok-banner'},'\u2713 '+personName+' added'),
    h('h2',{},'Add training details'),
    h('p',{class:'note',style:'margin:0'},'Which trainings has '+personName+' attended, or spoken at? Enter the topic and date. You can add more later from their timeline.'),
    box,
    h('div',{},h('button',{class:'btn small',type:'button',onclick:()=>histRow(rows,box)},'+ Add another')),
    h('div',{class:'row'},h('button',{class:'btn primary',type:'submit'},'Save training'),h('button',{class:'btn',type:'button',onclick:closeModal},'Skip for now')));
  histRow(rows,box);
  openModal(form); box.querySelector('input[type=text]').focus();
}
function recordModal(r){
  const type=h('select',{id:'rm-type'},TYPES.map(t=>h('option',{value:t.k,selected:t.k===r.type},t.label)));
  const date=h('input',{type:'date',id:'rm-date',required:true,value:r.date||''});
  const title=h('input',{type:'text',id:'rm-title',required:true,value:r.title||''});
  const notes=h('textarea',{id:'rm-notes'},r.notes||'');
  const form=h('form',{style:'display:flex;flex-direction:column;gap:12px',onsubmit:async e=>{
    e.preventDefault();
    const data={type:type.value,date:date.value||today(),title:title.value.trim(),notes:notes.value.trim()};
    if(!data.title) return;
    await guard(async()=>{ await db.doc('records/'+r.id).update(data); closeModal(); toast('Record updated'); });
  }},
    h('h2',{},'Edit record'),
    h('div',{class:'grid2'},h('label',{},'Type',type),h('label',{},'Date',date)),
    h('label',{},'Topic / session title',title), h('label',{},'Notes (optional)',notes),
    h('div',{class:'row'},h('button',{class:'btn primary',type:'submit'},'Save'),h('button',{class:'btn',type:'button',onclick:closeModal},'Cancel')));
  openModal(form); title.focus();
}
function bulkModal(){
  const ta=h('textarea',{id:'bulk-text',style:'min-height:180px',placeholder:'Ahmad bin Ali, Richworks, Operations\nSiti Nur, Richworks, Finance\nLim Wei Jie'});
  const form=h('form',{style:'display:flex;flex-direction:column;gap:12px',onsubmit:async e=>{
    e.preventDefault();
    const have=new Set(state.people.map(p=>p.name.toLowerCase()));
    const rows=ta.value.split('\n').map(l=>l.split(/[,\t]/).map(s=>s.trim())).filter(r=>r[0]).filter(r=>!have.has(r[0].toLowerCase()));
    if(!rows.length){ toast('No new names to add'); return; }
    await guard(async()=>{
      for(const [name,company='',department='',position=''] of rows) await db.collection('people').add({name,company,department,position,createdAt:Date.now()});
      closeModal(); toast(rows.length+' staff added');
    });
  }},
    h('h2',{},'Paste list of names'),
    h('p',{class:'note',style:'margin:0'},'One name per line. Add company, department and position after commas, in that order. Names that already exist are skipped.'),
    ta,
    h('div',{class:'row'},h('button',{class:'btn primary',type:'submit'},'Add all'),h('button',{class:'btn',type:'button',onclick:closeModal},'Cancel')));
  openModal(form); ta.focus();
}
$('btn-add-person').onclick=()=>personModal(null);
$('btn-edit-person').onclick=()=>personModal(pById(state.sel));
$('btn-bulk').onclick=bulkModal;

/* CSV export */
const csvCell=v=>'"'+String(v??'').replace(/"/g,'""')+'"';
$('btn-export').onclick=()=>{
  const rows=[['Name','Staff ID','Company','Department','Position','Remarks','Attended training','Speaker','Answered questions','Other','Latest record']];
  for(const p of [...state.people].sort((a,b)=>a.name.localeCompare(b.name))){ const c=counts(p.id);
    rows.push([p.name,p.staffId,p.company,p.department,p.position,p.remarks,c.attended,c.speaker,c.qna,c.other,c.last]); }
  rows.push([]); rows.push(['Name','Type','Title','Date','Notes']);
  for(const r of [...state.records].sort((a,b)=>(b.date||'').localeCompare(a.date||''))) rows.push([pById(r.personId)?.name||'',TYPE[r.type]?.label||r.type,r.title,r.date,r.notes]);
  const csv='﻿'+rows.map(r=>r.map(csvCell).join(',')).join('\r\n');
  const a=h('a',{href:URL.createObjectURL(new Blob([csv],{type:'text/csv'})),download:'richworks-talent-tracker-'+today()+'.csv'});
  document.body.append(a); a.click(); a.remove(); toast('File saved');
};

/* filters, tabs */
$('q').oninput=e=>{state.q=e.target.value;renderPeople();};
$('rq').oninput=e=>{state.rq=e.target.value;renderRecords();};
$('rtype').onchange=e=>{state.rtype=e.target.value;renderRecords();};
$('tab-staff').onclick=()=>{state.tab='staff';renderTabs();};
$('tab-records').onclick=()=>{state.tab='records';renderTabs();renderRecords();};
$('r-type').replaceChildren(...TYPES.map(t=>h('option',{value:t.k},t.label)));
$('rtype').replaceChildren(h('option',{value:'all'},'All types'),...TYPES.map(t=>h('option',{value:t.k},t.label)));
$('r-date').value=today();

/* ---------- data: REST client for server.js, exposing a small collection/doc API ---------- */
function makeDb(){
  const listeners = [];
  const csrf = document.querySelector('meta[name=csrf-token]').content;
  async function call(method, path, body){
    const res = await fetch('/talent-tracker/'+path, {method, headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf}, body: body?JSON.stringify(body):undefined});
    if (res.status===403) throw {code:'forbidden'};
    if (!res.ok) throw new Error('HTTP '+res.status);
    return res.json();
  }
  async function load(){
    try {
      const d = await call('GET','data');
      state.canWrite = !!d.canEdit;
      listeners.forEach(l=>l.ok({docs:(d[l.name]||[]).map(({id,...rest})=>({id, data:()=>rest}))}));
    } catch(e){ listeners.forEach(l=>l.err&&l.err(e)); }
  }
  const wrote = async p => { const r = await p; await load(); return r; };
  setInterval(load, 15000); // pick up changes made by other users
  return {
    collection: name => ({
      add: data => wrote(call('POST','records',data)),
      onSnapshot(ok, err){ listeners.push({name, ok, err}); if (listeners.length===2) load(); }
    }),
    doc: path => { const [, id] = path.split('/'); return {
      update: data => wrote(call('PATCH','records/'+id, data)),
      delete: () => wrote(call('DELETE','records/'+id))
    }; }
  };
}

/* ---------- boot ---------- */
(async function boot(){
  renderAll();
  db = makeDb();
  const fail=()=>{ state.loaded=true; renderAll(); };
  let gotP=false, gotR=false;
  const done=()=>{ if(gotP&&gotR) state.loaded=true; };
  db.collection('people').onSnapshot(s=>{ state.people=s.docs.map(d=>({id:d.id,...d.data()})); gotP=true; done();
    if(state.sel && !pById(state.sel)) state.sel=null; renderAll(); }, fail);
  db.collection('records').onSnapshot(s=>{ state.records=s.docs.map(d=>({id:d.id,...d.data()})); gotR=true; done(); renderAll(); }, fail);
})();
</script>
@endverbatim
