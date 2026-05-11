// GarageManager - SPA Frontend
const API = '/modules/garage/api.php';
const UPLOADS = '/modules/garage/api.php?action=photo&file=';

function $(s,ctx=document){return ctx.querySelector(s);}
function $$(s,ctx=document){return [...ctx.querySelectorAll(s)];}
function fmt(n){return n!=null?Number(n).toLocaleString('fr-FR'):'--';}
function fmtPrice(n){return n!=null?Number(n).toFixed(2)+' €':'--';}
function fmtDate(d){if(!d)return '--';return new Date(d).toLocaleDateString('fr-FR');}
function ago(d){if(!d)return '';const diff=Math.floor((Date.now()-new Date(d))/86400000);if(diff===0)return "aujourd'hui";if(diff===1)return 'hier';return 'il y a '+diff+'j';}
function escHtml(s){const d=document.createElement('div');d.textContent=String(s??'');return d.innerHTML;}
function toast(msg,type='success'){
  const c=document.getElementById('toasts');const t=document.createElement('div');
  t.className='toast '+type;t.textContent=msg;c.appendChild(t);setTimeout(()=>t.remove(),3000);
}

// ─── STATE ────────────────────────────────────────────────────────────────────
let currentPage='dashboard';
let currentVehicleId=null;
let currentMaintenanceId=null;

// ─── NAV ──────────────────────────────────────────────────────────────────────
function navigate(page,params={}){
  currentPage=page;
  $$('.page').forEach(p=>p.classList.remove('active'));
  $$('.nav-link').forEach(n=>n.classList.remove('active'));
  document.getElementById('page-'+page)?.classList.add('active');
  const navTarget=page==='maintenance'?'maintenances-all':page;
  document.querySelector('.nav-link[data-page="'+navTarget+'"]')?.classList.add('active');
  if(page==='dashboard')loadDashboard();
  else if(page==='vehicles')loadVehicles();
  else if(page==='vehicle')loadVehicleDetail(params.id);
  else if(page==='maintenance')loadMaintenanceDetail(params.id);
  else if(page==='parts')loadParts();
  else if(page==='maintenances-all')loadAllMaintenances();
}

// ─── API ──────────────────────────────────────────────────────────────────────
async function api(action,method='GET',data=null,extra=''){
  const opts={method,headers:{}};
  if(data&&!(data instanceof FormData)){opts.headers['Content-Type']='application/json';opts.body=JSON.stringify(data);}
  else if(data instanceof FormData){opts.body=data;}
  const r=await fetch(API+'?action='+action+extra,opts);
  const j=await r.json();
  if(!j.ok)throw new Error(j.error||'Erreur API');
  return j.data;
}

// ─── DASHBOARD ────────────────────────────────────────────────────────────────
async function loadDashboard(){
  try{
    const[stats,vehicles,reminders]=await Promise.all([api('stats'),api('vehicles'),api('maintenances')]);
    $('#stat-vehicles').textContent=stats.vehicles;
    $('#stat-maintenances').textContent=stats.maintenances;
    $('#stat-parts').textContent=stats.parts;
    $('#stat-cost').textContent=fmtPrice(parseFloat(stats.total_cost)+parseFloat(stats.total_parts_cost));
    renderDashboardVehicles(vehicles);
    renderReminders(reminders);
  }catch(e){toast(e.message,'error');}
}

function renderDashboardVehicles(list){
  const el=$('#dashboard-vehicles');
  if(!list.length){el.innerHTML='<div class="empty-state"><div class="icon">🚗</div><p>Aucun véhicule</p></div>';return;}
  el.innerHTML=list.slice(0,6).map(v=>vehicleCardHTML(v)).join('');
  $$('.vehicle-card',el).forEach(c=>c.addEventListener('click',()=>navigate('vehicle',{id:c.dataset.id})));
}

function vehicleCardHTML(v){
  const photoHTML=v.photo?'<img src="'+UPLOADS+v.photo+'" alt="">':'<span class="no-photo">🚗</span>';
  const fuel=v.fuel_type||'Essence';
  const fuelClass='fuel-'+fuel.replace(/\s/g,'');
  return '<div class="vehicle-card" data-id="'+v.id+'">'+
    '<div class="vehicle-card-img">'+photoHTML+'<span class="fuel-badge '+fuelClass+'">'+escHtml(fuel)+'</span></div>'+
    '<div class="vehicle-card-body">'+
      '<div class="vehicle-card-title">'+escHtml(v.name)+'</div>'+
      '<div class="vehicle-card-sub">'+escHtml(v.brand)+' '+escHtml(v.model)+(v.year?' &middot; '+v.year:'')+'</div>'+
      '<div class="vehicle-card-stats">'+
        '<div class="vc-stat">🔧 <strong>'+(v.maintenance_count||0)+'</strong> entretiens</div>'+
        '<div class="vc-stat">💶 <strong>'+fmtPrice(v.total_cost)+'</strong></div>'+
      '</div>'+
    '</div>'+
    '<div class="vehicle-card-footer">'+
      (v.license_plate?'<span class="plate">'+escHtml(v.license_plate)+'</span>':'<span></span>')+
      '<span class="vc-stat">'+(v.current_km?fmt(v.current_km)+' km':'--')+'</span>'+
    '</div></div>';
}

function renderReminders(list){
  const el=$('#reminders-list');const today=new Date();
  if(!list.length){el.innerHTML='<div class="empty-state" style="padding:1.5rem"><p style="color:var(--muted)">Aucun rappel configuré</p></div>';return;}
  el.innerHTML='<div class="table-wrap"><table><thead><tr><th>Véhicule</th><th>Type</th><th>Prochaine date</th><th>Prochain km</th><th>Écart km</th></tr></thead><tbody>'+
    list.map(r=>{
      const diff=r.next_km&&r.current_km?r.next_km-r.current_km:null;
      const dateOk=r.next_date?new Date(r.next_date)>today:true;
      const kmOk=diff==null||diff>0;
      const cls=(!dateOk||!kmOk)?'badge-red':diff!=null&&diff<2000?'badge-amber':'badge-green';
      return '<tr><td><strong>'+escHtml(r.vehicle_name)+'</strong>'+(r.license_plate?' <span class="plate">'+escHtml(r.license_plate)+'</span>':'')+'</td>'+
        '<td>'+escHtml(r.type)+'</td>'+
        '<td>'+(r.next_date?'<span class="badge '+cls+'">'+fmtDate(r.next_date)+'</span>':'--')+'</td>'+
        '<td>'+(r.next_km?fmt(r.next_km)+' km':'--')+'</td>'+
        '<td>'+(diff!=null?'<span class="badge '+(diff<0?'badge-red':diff<2000?'badge-amber':'badge-green')+'">'+(diff>=0?'+':'')+fmt(diff)+' km</span>':'--')+'</td></tr>';
    }).join('')+
  '</tbody></table></div>';
}

// ─── VEHICLES LIST ────────────────────────────────────────────────────────────
async function loadVehicles(){
  try{
    const list=await api('vehicles');
    const el=$('#vehicles-grid');
    if(!list.length){el.innerHTML='<div class="empty-state"><div class="icon">🚗</div><p>Aucun véhicule. Ajoutez-en un !</p></div>';return;}
    el.innerHTML=list.map(v=>vehicleCardHTML(v)).join('');
    $$('.vehicle-card',el).forEach(c=>c.addEventListener('click',()=>navigate('vehicle',{id:c.dataset.id})));
  }catch(e){toast(e.message,'error');}
}

// ─── VEHICLE DETAIL ───────────────────────────────────────────────────────────
async function loadVehicleDetail(id){
  currentVehicleId=id;
  try{
    const[v,maintenances,parts]=await Promise.all([
      api('vehicles','GET',null,'&id='+id),
      api('maintenances','GET',null,'&vehicle_id='+id),
      api('parts','GET',null,'&vehicle_id='+id)
    ]);
    renderVehicleHeader(v);
    renderMaintenances(maintenances);
    renderVehicleParts(parts);
    document.title='GarageManager · '+v.name;
  }catch(e){toast(e.message,'error');}
}

function renderVehicleHeader(v){
  const photoHTML=v.photo?'<img src="'+UPLOADS+v.photo+'" alt="">':'<div class="no-photo">🚗</div>';
  const totalCost=(parseFloat(v.stats?.total||0)+parseFloat(v.parts_stats?.total||0)).toFixed(2);
  $('#vehicle-header').innerHTML=
    '<div class="vehicle-detail-header">'+
      '<div class="vehicle-detail-img">'+photoHTML+'</div>'+
      '<div class="vehicle-meta">'+
        '<h2>'+escHtml(v.name)+'</h2>'+
        '<p class="sub">'+escHtml(v.brand)+' '+escHtml(v.model)+(v.year?' · '+v.year:'')+'</p>'+
        '<div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:.75rem">'+
          (v.license_plate?'<span class="plate">'+escHtml(v.license_plate)+'</span>':'')+
          '<span class="badge badge-blue">'+(v.fuel_type||'Essence')+'</span>'+
          (v.color?'<span class="badge badge-gray">🎨 '+escHtml(v.color)+'</span>':'')+
        '</div>'+
        '<div class="meta-grid">'+
          '<div class="meta-item"><span class="k">Kilométrage</span><br><span class="v">'+fmt(v.current_km)+' km</span></div>'+
          '<div class="meta-item"><span class="k">Entretiens</span><br><span class="v">'+(v.stats?.cnt||0)+'</span></div>'+
          '<div class="meta-item"><span class="k">Coût total</span><br><span class="v">'+fmtPrice(totalCost)+'</span></div>'+
          (v.purchase_date?'<div class="meta-item"><span class="k">Achat</span><br><span class="v">'+fmtDate(v.purchase_date)+'</span></div>':'')+
          (v.vin?'<div class="meta-item"><span class="k">VIN</span><br><span class="v" style="font-size:.75rem;font-family:monospace">'+escHtml(v.vin)+'</span></div>':'')+
        '</div>'+
      '</div>'+
      '<div style="display:flex;gap:.5rem;flex-wrap:wrap;align-self:flex-start">'+
        '<button class="btn btn-secondary btn-sm" onclick="openEditVehicle('+v.id+')">✏️ Modifier</button>'+
        '<button class="btn btn-secondary btn-sm" onclick="openUploadPhoto('+v.id+')">📷 Photo</button>'+
        '<button class="btn btn-danger btn-sm" onclick="deleteVehicle('+v.id+')">🗑️ Supprimer</button>'+
      '</div>'+
    '</div>';
}

function renderMaintenances(list){
  const el=$('#maintenance-list');
  const totalCost=list.reduce((s,m)=>s+parseFloat(m.cost||0)+parseFloat(m.parts_cost||0),0);
  $('#maintenance-total').textContent=fmtPrice(totalCost);
  if(!list.length){el.innerHTML='<div class="empty-state"><div class="icon">🔧</div><p>Aucun entretien enregistré</p></div>';return;}
  el.innerHTML='<div class="table-wrap"><table><thead><tr><th>Date</th><th>Type</th><th>Description</th><th>Kilométrage</th><th>Coût MO</th><th>Pièces</th><th>Prochain</th><th></th></tr></thead><tbody>'+
    list.map(m=>'<tr style="cursor:pointer" onclick="navigate(\'maintenance\',{id:'+m.id+'})">'+
      '<td><strong>'+fmtDate(m.date)+'</strong><br><span style="color:var(--muted);font-size:.75rem">'+ago(m.date)+'</span></td>'+
      '<td><span class="badge badge-blue">'+escHtml(m.type)+'</span></td>'+
      '<td style="max-width:200px;color:var(--muted)">'+(m.description?escHtml(m.description):'--')+'</td>'+
      '<td>'+(m.km?fmt(m.km)+' km':'--')+'</td>'+
      '<td>'+fmtPrice(m.cost)+'</td>'+
      '<td>'+(m.parts_count>0?'<span class="badge badge-purple">🔩 '+m.parts_count+' ('+fmtPrice(m.parts_cost)+')</span>':'--')+'</td>'+
      '<td style="font-size:.78rem">'+(m.next_date?'📅 '+fmtDate(m.next_date):'')+' '+(m.next_km?'<br>🛣️ '+fmt(m.next_km)+' km':'')+'</td>'+
      '<td onclick="event.stopPropagation()">'+
        '<button class="btn btn-secondary btn-sm btn-icon" onclick="openEditMaintenance('+m.id+')" title="Modifier">✏️</button> '+
        '<button class="btn btn-danger btn-sm btn-icon" onclick="deleteMaintenance('+m.id+')" title="Supprimer">🗑️</button>'+
      '</td>'+
    '</tr>').join('')+
  '</tbody></table></div>';
}

function renderVehicleParts(list){
  const el=$('#parts-list-vehicle');
  const total=list.reduce((s,p)=>s+parseFloat(p.price||0)*parseInt(p.quantity||1),0);
  $('#parts-total-vehicle').textContent=fmtPrice(total);
  if(!list.length){el.innerHTML='<div class="empty-state"><div class="icon">🔩</div><p>Aucune pièce enregistrée</p></div>';return;}
  el.innerHTML='<div class="table-wrap"><table><thead><tr><th>Photo</th><th>Nom</th><th>Marque</th><th>Référence</th><th>Catégorie</th><th>Prix unit.</th><th>Qté</th><th>Total</th><th>Entretien</th><th></th></tr></thead><tbody>'+
    list.map(p=>'<tr>'+
      '<td>'+(p.photo?'<img class="part-thumb" src="'+UPLOADS+p.photo+'" alt="">':'<span style="color:var(--muted)">--</span>')+'</td>'+
      '<td><strong>'+escHtml(p.name)+'</strong></td>'+
      '<td>'+(p.brand?escHtml(p.brand):'--')+'</td>'+
      '<td><code style="font-size:.75rem;color:var(--muted)">'+(p.reference?escHtml(p.reference):'--')+'</code></td>'+
      '<td><span class="badge badge-gray">'+escHtml(p.category)+'</span></td>'+
      '<td>'+fmtPrice(p.price)+'</td>'+
      '<td>'+p.quantity+' '+escHtml(p.unit||'pièce')+'</td>'+
      '<td><strong>'+fmtPrice(parseFloat(p.price||0)*parseInt(p.quantity||1))+'</strong></td>'+
      '<td style="font-size:.78rem">'+(p.maintenance_type?escHtml(p.maintenance_type)+' ('+fmtDate(p.maintenance_date)+')':'--')+'</td>'+
      '<td>'+
        '<button class="btn btn-secondary btn-sm btn-icon" onclick="openEditPart('+p.id+')" title="Modifier">✏️</button> '+
        '<button class="btn btn-danger btn-sm btn-icon" onclick="deletePart('+p.id+')" title="Supprimer">🗑️</button>'+
      '</td>'+
    '</tr>').join('')+
  '</tbody></table></div>';
}

// ─── MAINTENANCE DETAIL ───────────────────────────────────────────────────────
async function loadMaintenanceDetail(id){
  currentMaintenanceId=id;
  try{
    const[m,parts]=await Promise.all([
      api('maintenances','GET',null,'&id='+id),
      api('parts','GET',null,'&maintenance_id='+id)
    ]);
    currentVehicleId=m.vehicle_id;
    renderMaintenanceDetailHeader(m);
    renderMaintenanceParts(parts,id,m.vehicle_id);
    loadKnownParts(m.vehicle_id);
    const f=document.getElementById('inline-part-form');
    if(f)f.style.display='none';
  }catch(e){toast(e.message,'error');}
}

function renderMaintenanceDetailHeader(m){
  document.getElementById('maintenance-detail-header').innerHTML=
    '<div class="vehicle-detail-header" style="align-items:flex-start">'+
      '<div class="vehicle-meta" style="flex:1">'+
        '<div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;margin-bottom:.5rem">'+
          '<span class="badge badge-blue" style="font-size:.95rem;padding:.35rem .8rem">'+escHtml(m.type)+'</span>'+
          '<span style="color:var(--muted)">'+fmtDate(m.date)+' · '+ago(m.date)+'</span>'+
          (m.vehicle_name?'<span style="color:var(--muted);font-size:.85rem">🚗 <a href="#" onclick="navigate(\'vehicle\',{id:'+m.vehicle_id+'});return false" style="color:inherit;text-decoration:underline">'+escHtml(m.vehicle_name)+'</a></span>':'')+
        '</div>'+
        (m.description?'<p style="margin-bottom:.75rem">'+escHtml(m.description)+'</p>':'')+
        '<div class="meta-grid">'+
          (m.km?'<div class="meta-item"><span class="k">Kilométrage</span><br><span class="v">'+fmt(m.km)+' km</span></div>':'')+
          '<div class="meta-item"><span class="k">Coût M.O.</span><br><span class="v">'+fmtPrice(m.cost)+'</span></div>'+
          (m.mechanic?'<div class="meta-item"><span class="k">Mécanicien</span><br><span class="v">'+escHtml(m.mechanic)+'</span></div>':'')+
          (m.garage_name?'<div class="meta-item"><span class="k">Garage</span><br><span class="v">'+escHtml(m.garage_name)+'</span></div>':'')+
          (m.next_date?'<div class="meta-item"><span class="k">Prochain entretien</span><br><span class="v">📅 '+fmtDate(m.next_date)+'</span></div>':'')+
          (m.next_km?'<div class="meta-item"><span class="k">Prochain km</span><br><span class="v">🛣️ '+fmt(m.next_km)+' km</span></div>':'')+
        '</div>'+
        (m.notes?'<p style="margin-top:.75rem;color:var(--muted);font-size:.85rem;font-style:italic">'+escHtml(m.notes)+'</p>':'')+
      '</div>'+
      '<div style="display:flex;gap:.5rem;flex-wrap:wrap;align-self:flex-start">'+
        '<button class="btn btn-secondary btn-sm" onclick="openEditMaintenance('+m.id+')">✏️ Modifier</button>'+
        '<button class="btn btn-danger btn-sm" onclick="deleteMaintenance('+m.id+')">🗑️ Supprimer</button>'+
      '</div>'+
    '</div>';
}

function renderMaintenanceParts(list,mid,vid){
  const el=document.getElementById('maintenance-parts-list');
  const cnt=document.getElementById('maint-parts-count');
  if(cnt)cnt.textContent=list.length?list.length+' pièce'+(list.length>1?'s':''):'';
  if(!list.length){el.innerHTML='<div class="empty-state" style="padding:2rem"><div class="icon">🔩</div><p>Aucune pièce utilisée</p></div>';return;}
  const total=list.reduce((s,p)=>s+parseFloat(p.price||0)*parseInt(p.quantity||1),0);
  el.innerHTML='<div class="table-wrap"><table><thead><tr><th>Photo</th><th>Nom</th><th>Marque</th><th>Réf.</th><th>Catégorie</th><th>Prix unit.</th><th>Qté</th><th>Total</th><th></th></tr></thead><tbody>'+
    list.map(p=>'<tr>'+
      '<td>'+(p.photo?'<img class="part-thumb" src="'+UPLOADS+p.photo+'" alt="">':'--')+'</td>'+
      '<td><strong>'+escHtml(p.name)+'</strong></td>'+
      '<td>'+(p.brand?escHtml(p.brand):'--')+'</td>'+
      '<td><code style="font-size:.75rem;color:var(--muted)">'+(p.reference?escHtml(p.reference):'--')+'</code></td>'+
      '<td><span class="badge badge-gray">'+escHtml(p.category)+'</span></td>'+
      '<td>'+fmtPrice(p.price)+'</td>'+
      '<td>'+p.quantity+' '+escHtml(p.unit||'pièce')+'</td>'+
      '<td><strong>'+fmtPrice(parseFloat(p.price||0)*parseInt(p.quantity||1))+'</strong></td>'+
      '<td>'+
        '<button class="btn btn-secondary btn-sm btn-icon" onclick="openEditPart('+p.id+')" title="Modifier">✏️</button> '+
        '<button class="btn btn-danger btn-sm btn-icon" onclick="deleteMaintPart('+p.id+','+mid+','+vid+')" title="Supprimer">🗑️</button>'+
      '</td>'+
    '</tr>').join('')+
    '<tr style="background:rgba(0,0,0,.02)"><td colspan="7" style="text-align:right;font-weight:600;color:var(--muted)">Total pièces</td><td><strong>'+fmtPrice(total)+'</strong></td><td></td></tr>'+
  '</tbody></table></div>';
}

async function deleteMaintPart(id,mid,vid){
  if(!confirm('Supprimer cette pièce ?'))return;
  try{
    await api('parts','DELETE',null,'&id='+id);
    toast('Pièce supprimée');
    const parts=await api('parts','GET',null,'&maintenance_id='+mid);
    renderMaintenanceParts(parts,mid,vid);
  }catch(e){toast(e.message,'error');}
}

// ─── INLINE PART FORM ────────────────────────────────────────────────────────
function toggleInlinePartForm(){
  const f=document.getElementById('inline-part-form');
  if(!f)return;
  const visible=f.style.display==='block';
  f.style.display=visible?'none':'block';
  if(!visible)document.getElementById('ipart-name')?.focus();
}

async function loadKnownParts(vehicleId){
  try{
    const parts=await api('parts','GET',null,'&vehicle_id='+vehicleId);
    const el=document.getElementById('known-parts-list');
    if(!el)return;
    if(!parts.length){el.innerHTML='<span style="color:var(--muted);font-size:.8rem">Aucune pièce connue</span>';return;}
    const seen=new Set();
    const unique=parts.filter(p=>{if(seen.has(p.name))return false;seen.add(p.name);return true;}).slice(0,15);
    el.innerHTML=unique.map(p=>
      '<button class="badge badge-gray" style="cursor:pointer;border:none;padding:.3rem .6rem;font-size:.78rem" '+
      'data-name="'+escHtml(p.name)+'" data-brand="'+escHtml(p.brand||'')+'" data-ref="'+escHtml(p.reference||'')+'" data-price="'+p.price+'">'+escHtml(p.name)+'</button>'
    ).join('');
    $$('button[data-name]',el).forEach(btn=>btn.addEventListener('click',()=>{
      const n=document.getElementById('ipart-name');const b=document.getElementById('ipart-brand');
      const r=document.getElementById('ipart-reference');const pr=document.getElementById('ipart-price');
      if(n)n.value=btn.dataset.name;
      if(b)b.value=btn.dataset.brand;
      if(r)r.value=btn.dataset.ref;
      if(pr){pr.value=btn.dataset.price;updateIPriceTTC();}
    }));
  }catch(e){}
}

function updateIPriceTTC(){
  const ht=document.getElementById('ipart-prix-ht')?.checked;
  const price=parseFloat(document.getElementById('ipart-price')?.value)||0;
  const preview=document.getElementById('ipart-ttc-preview');
  if(!preview)return;
  if(ht&&price>0){preview.textContent='→ Prix TTC : '+(price*1.2).toFixed(2)+' €';preview.style.display='block';}
  else{preview.style.display='none';}
}

async function saveInlinePart(){
  const name=document.getElementById('ipart-name')?.value.trim();
  if(!name){toast('Nom requis','error');return;}
  let price=parseFloat(document.getElementById('ipart-price')?.value)||0;
  if(document.getElementById('ipart-prix-ht')?.checked)price=parseFloat((price*1.2).toFixed(2));
  const fd=new FormData();
  fd.append('name',name);
  fd.append('vehicle_id',currentVehicleId||'');
  fd.append('maintenance_id',currentMaintenanceId||'');
  fd.append('price',price);
  fd.append('quantity',parseInt(document.getElementById('ipart-quantity')?.value)||1);
  fd.append('category','Autre');
  fd.append('unit','pièce');
  const brand=document.getElementById('ipart-brand')?.value.trim();
  const ref=document.getElementById('ipart-reference')?.value.trim();
  if(brand)fd.append('brand',brand);
  if(ref)fd.append('reference',ref);
  const photoFile=document.getElementById('ipart-photo')?.files[0];
  if(photoFile)fd.append('photo',photoFile);
  try{
    await api('parts','POST',fd);
    toast('Pièce ajoutée');
    ['ipart-name','ipart-brand','ipart-reference'].forEach(id=>{const el=document.getElementById(id);if(el)el.value='';});
    const pr=document.getElementById('ipart-price');if(pr)pr.value='0';
    const qty=document.getElementById('ipart-quantity');if(qty)qty.value='1';
    const ht=document.getElementById('ipart-prix-ht');if(ht)ht.checked=false;
    const prev=document.getElementById('ipart-ttc-preview');if(prev)prev.style.display='none';
    const ph=document.getElementById('ipart-photo');if(ph)ph.value='';
    const parts=await api('parts','GET',null,'&maintenance_id='+currentMaintenanceId);
    renderMaintenanceParts(parts,currentMaintenanceId,currentVehicleId);
  }catch(e){toast(e.message,'error');}
}

// ─── ALL PARTS ────────────────────────────────────────────────────────────────
async function loadParts(){
  try{
    const list=await api('parts');
    const el=$('#all-parts-list');
    const total=list.reduce((s,p)=>s+parseFloat(p.price||0)*parseInt(p.quantity||1),0);
    $('#all-parts-total').textContent=fmtPrice(total);
    $('#all-parts-count').textContent=list.length;
    if(!list.length){el.innerHTML='<div class="empty-state"><div class="icon">🔩</div><p>Aucune pièce</p></div>';return;}
    const byVehicle={};
    list.forEach(p=>{const key=p.vehicle_name||'Sans véhicule';if(!byVehicle[key])byVehicle[key]=[];byVehicle[key].push(p);});
    let html='';
    for(const[vname,parts] of Object.entries(byVehicle)){
      const vtotal=parts.reduce((s,p)=>s+parseFloat(p.price||0)*parseInt(p.quantity||1),0);
      html+='<div style="margin-bottom:2rem">'+
        '<div class="card-header" style="margin-bottom:.5rem">'+
          '<h3 style="font-size:1rem;font-weight:700">🚗 '+escHtml(vname)+'</h3>'+
          '<span style="margin-left:auto;color:var(--muted);font-size:.85rem">'+parts.length+' pièce'+(parts.length>1?'s':'')+' · '+fmtPrice(vtotal)+'</span>'+
        '</div>'+
        '<div class="table-wrap"><table><thead><tr><th>Photo</th><th>Nom</th><th>Marque</th><th>Référence</th><th>Catégorie</th><th>Prix unit.</th><th>Qté</th><th>Total</th><th>Entretien</th><th></th></tr></thead><tbody>'+
        parts.map(p=>'<tr>'+
          '<td>'+(p.photo?'<img class="part-thumb" src="'+UPLOADS+p.photo+'" alt="">':'--')+'</td>'+
          '<td><strong>'+escHtml(p.name)+'</strong></td>'+
          '<td>'+(p.brand?escHtml(p.brand):'--')+'</td>'+
          '<td><code style="font-size:.75rem;color:var(--muted)">'+(p.reference?escHtml(p.reference):'--')+'</code></td>'+
          '<td><span class="badge badge-gray">'+escHtml(p.category)+'</span></td>'+
          '<td>'+fmtPrice(p.price)+'</td>'+
          '<td>'+p.quantity+' '+escHtml(p.unit||'pièce')+'</td>'+
          '<td><strong>'+fmtPrice(parseFloat(p.price||0)*parseInt(p.quantity||1))+'</strong></td>'+
          '<td style="font-size:.78rem">'+(p.maintenance_type?escHtml(p.maintenance_type)+' ('+fmtDate(p.maintenance_date)+')':'--')+'</td>'+
          '<td>'+
            '<button class="btn btn-secondary btn-sm btn-icon" onclick="openEditPart('+p.id+')" title="Modifier">✏️</button> '+
            '<button class="btn btn-danger btn-sm btn-icon" onclick="deletePart('+p.id+')" title="Supprimer">🗑️</button>'+
          '</td>'+
        '</tr>').join('')+
        '</tbody></table></div></div>';
    }
    el.innerHTML=html;
  }catch(e){toast(e.message,'error');}
}

// ─── ALL MAINTENANCES ─────────────────────────────────────────────────────────
async function loadAllMaintenances(){
  try{
    const vehicles=await api('vehicles');
    const el=$('#all-maintenances-list');
    if(!vehicles.length){el.innerHTML='<div class="empty-state"><div class="icon">🔧</div><p>Aucun véhicule</p></div>';return;}
    let totalAll=0,countAll=0,html='';
    for(const v of vehicles){
      const mlist=await api('maintenances',undefined,null,'&vehicle_id='+v.id);
      if(!mlist.length)continue;
      const total=mlist.reduce((s,m)=>s+parseFloat(m.cost||0)+parseFloat(m.parts_cost||0),0);
      totalAll+=total;countAll+=mlist.length;
      html+='<div style="margin-bottom:2rem">'+
        '<div class="card-header" style="margin-bottom:.5rem">'+
          '<h3 style="font-size:1rem;font-weight:700">🚗 '+escHtml(v.name)+'</h3>'+
          (v.license_plate?'<span class="plate">'+escHtml(v.license_plate)+'</span>':'')+
          '<span style="margin-left:auto;color:var(--muted);font-size:.85rem">'+mlist.length+' entretiens · '+fmtPrice(total)+'</span>'+
        '</div>'+
        '<div class="table-wrap"><table><thead><tr><th>Date</th><th>Type</th><th>Description</th><th>Km</th><th>Coût M.O.</th><th>Pièces</th><th>Total</th><th>Mécanicien</th><th></th></tr></thead><tbody>'+
        mlist.map(m=>'<tr style="cursor:pointer" onclick="navigate(\'maintenance\',{id:'+m.id+'})">'+
          '<td>'+fmtDate(m.date)+'</td>'+
          '<td><span class="badge badge-gray">'+escHtml(m.type)+'</span></td>'+
          '<td>'+(m.description?escHtml(m.description):'--')+'</td>'+
          '<td>'+(m.km?fmt(m.km)+' km':'--')+'</td>'+
          '<td>'+fmtPrice(m.cost)+'</td>'+
          '<td>'+(m.parts_count>0?m.parts_count+' ('+fmtPrice(m.parts_cost)+')':'--')+'</td>'+
          '<td><strong>'+fmtPrice(parseFloat(m.cost||0)+parseFloat(m.parts_cost||0))+'</strong></td>'+
          '<td>'+(m.mechanic?escHtml(m.mechanic):'--')+'</td>'+
          '<td onclick="event.stopPropagation()">'+
            '<button class="btn btn-danger btn-sm btn-icon" onclick="deleteMaintenance('+m.id+')" title="Supprimer">🗑️</button>'+
          '</td>'+
        '</tr>').join('')+
        '</tbody></table></div></div>';
    }
    $('#all-maint-count').textContent=countAll;
    $('#all-maint-total').textContent=fmtPrice(totalAll);
    el.innerHTML=html||'<div class="empty-state"><div class="icon">🔧</div><p>Aucun entretien</p></div>';
  }catch(e){console.error(e);}
}

// ─── MODALS ───────────────────────────────────────────────────────────────────
function openModal(id){document.getElementById(id).classList.add('show');}
function closeModal(id){document.getElementById(id).classList.remove('show');}

// ─── TVA ──────────────────────────────────────────────────────────────────────
function updatePartPriceTTC(){
  const ht=document.getElementById('part-prix-ht')?.checked;
  const price=parseFloat(document.getElementById('part-price')?.value)||0;
  const preview=document.getElementById('part-ttc-preview');
  if(!preview)return;
  if(ht&&price>0){preview.textContent='→ Prix TTC : '+(price*1.2).toFixed(2)+' €';preview.style.display='block';}
  else{preview.style.display='none';}
}

// ─── VEHICLE CRUD ─────────────────────────────────────────────────────────────
function openAddVehicle(){
  $('#form-vehicle').reset();$('#form-vehicle-id').value='';
  $('#modal-vehicle-title').textContent='Ajouter un véhicule';
  $('#vehicle-photo-preview').src='';$('#vehicle-photo-preview').style.display='none';
  openModal('modal-vehicle');
}
function openEditVehicle(id){
  api('vehicles','GET',null,'&id='+id).then(v=>{
    $('#form-vehicle-id').value=v.id;
    $('#modal-vehicle-title').textContent='Modifier le véhicule';
    ['name','brand','model','year','license_plate','vin','color','purchase_date','purchase_price','current_km','notes'].forEach(f=>{
      const el=$('#vehicle-'+f.replace(/_/g,'-'));
      if(el)el.value=v[f]??'';
    });
    setSelectVal('vehicle-fuel-type', v.fuel_type);
    if(v.photo){$('#vehicle-photo-preview').src=UPLOADS+v.photo;$('#vehicle-photo-preview').style.display='block';}
    else{$('#vehicle-photo-preview').style.display='none';}
    openModal('modal-vehicle');
  }).catch(e=>toast(e.message,'error'));
}
async function saveVehicle(){
  const id=$('#form-vehicle-id').value;
  const fd=new FormData($('#form-vehicle'));
  try{
    if(id){
      const d={};for(const[k,v]of fd.entries())d[k]=v;
      await api('vehicles','PUT',d,'&id='+id);
      const photoFile=$('#vehicle-photo').files[0];
      if(photoFile){const pfd=new FormData();pfd.append('photo',photoFile);await fetch(API+'?action=upload_vehicle_photo&id='+id,{method:'POST',body:pfd});}
      toast('Véhicule modifié');
    }else{
      await api('vehicles','POST',fd);
      toast('Véhicule ajouté');
    }
    closeModal('modal-vehicle');
    if(currentPage==='vehicles')loadVehicles();
    else if(currentPage==='vehicle')loadVehicleDetail(currentVehicleId);
    else loadDashboard();
  }catch(e){toast(e.message,'error');}
}
async function deleteVehicle(id){
  if(!confirm('Supprimer ce véhicule et tout son historique ?'))return;
  try{await api('vehicles','DELETE',null,'&id='+id);toast('Véhicule supprimé');navigate('vehicles');}catch(e){toast(e.message,'error');}
}

// ─── MAINTENANCE CRUD ─────────────────────────────────────────────────────────
function openAddMaintenance(){
  $('#form-maintenance').reset();$('#form-maintenance-id').value='';
  $('#maintenance-vehicle-id').value=currentVehicleId||'';
  $('#modal-maintenance-title').textContent='Ajouter un entretien';
  $('#maintenance-date').value=new Date().toISOString().slice(0,10);
  openModal('modal-maintenance');
}
function setSelectVal(elId, val){
  const el=document.getElementById(elId); if(!el||val==null)return;
  el.value=val;
  if(el.value!==String(val)&&val!==''){const o=new Option(val,val);el.add(o);el.value=val;}
}
function openEditMaintenance(id){
  api('maintenances','GET',null,'&id='+id).then(m=>{
    $('#form-maintenance-id').value=m.id;
    $('#maintenance-vehicle-id').value=m.vehicle_id;
    $('#modal-maintenance-title').textContent="Modifier l'entretien";
    setSelectVal('maintenance-type', m.type);
    const map={description:'maintenance-description',date:'maintenance-date',
      km:'maintenance-km',cost:'maintenance-cost',mechanic:'maintenance-mechanic',
      garage_name:'maintenance-garage',next_date:'maintenance-next-date',next_km:'maintenance-next-km',notes:'maintenance-notes'};
    for(const[k,elId] of Object.entries(map)){const el=document.getElementById(elId);if(el)el.value=m[k]??'';}
    openModal('modal-maintenance');
  }).catch(e=>toast(e.message,'error'));
}
async function saveMaintenance(){
  const id=$('#form-maintenance-id').value;
  const d={};new FormData($('#form-maintenance')).forEach((v,k)=>d[k]=v);
  try{
    if(id){await api('maintenances','PUT',d,'&id='+id);toast('Entretien modifié');}
    else{await api('maintenances','POST',d);toast('Entretien ajouté');}
    closeModal('modal-maintenance');
    if(currentPage==='maintenance')loadMaintenanceDetail(currentMaintenanceId);
    else loadVehicleDetail(currentVehicleId);
  }catch(e){toast(e.message,'error');}
}
async function deleteMaintenance(id){
  if(!confirm('Supprimer cet entretien ?'))return;
  try{
    await api('maintenances','DELETE',null,'&id='+id);
    toast('Entretien supprimé');
    if(currentPage==='maintenance')navigate('vehicle',{id:currentVehicleId});
    else loadVehicleDetail(currentVehicleId);
  }catch(e){toast(e.message,'error');}
}

// ─── PARTS CRUD ───────────────────────────────────────────────────────────────
function openAddPart(){
  $('#form-part').reset();$('#form-part-id').value='';
  $('#part-vehicle-id').value=currentVehicleId||'';
  $('#part-maintenance-id').value=currentMaintenanceId||'';
  $('#modal-part-title').textContent='Ajouter une pièce';
  $('#part-photo-preview').src='';$('#part-photo-preview').style.display='none';
  const ht=document.getElementById('part-prix-ht');if(ht)ht.checked=false;
  const prev=document.getElementById('part-ttc-preview');if(prev)prev.style.display='none';
  openModal('modal-part');
}
function openEditPart(id){
  api('parts','GET',null,'&id='+id).then(p=>{
    $('#form-part-id').value=p.id;
    $('#part-vehicle-id').value=p.vehicle_id||'';
    $('#part-maintenance-id').value=p.maintenance_id||'';
    $('#modal-part-title').textContent='Modifier la pièce';
    const map={name:'part-name',brand:'part-brand',reference:'part-reference',price:'part-price',quantity:'part-quantity',notes:'part-notes'};
    for(const[k,elId] of Object.entries(map)){const el=document.getElementById(elId);if(el)el.value=p[k]??'';}
    setSelectVal('part-category', p.category);
    const ht=document.getElementById('part-prix-ht');if(ht)ht.checked=false;
    const prev=document.getElementById('part-ttc-preview');if(prev)prev.style.display='none';
    if(p.photo){$('#part-photo-preview').src=UPLOADS+p.photo;$('#part-photo-preview').style.display='block';}
    else{$('#part-photo-preview').style.display='none';}
    openModal('modal-part');
  }).catch(e=>toast(e.message,'error'));
}
async function savePart(){
  const id=$('#form-part-id').value;
  const fd=new FormData($('#form-part'));
  if(document.getElementById('part-prix-ht')?.checked){
    const htPrice=parseFloat(fd.get('price'))||0;
    fd.set('price',(htPrice*1.2).toFixed(2));
  }
  try{
    if(id){
      const d={};for(const[k,v]of fd.entries())d[k]=v;
      await api('parts','PUT',d,'&id='+id);
      const photoFile=$('#part-photo').files[0];
      if(photoFile){const pfd=new FormData();pfd.append('photo',photoFile);await fetch(API+'?action=upload_part_photo&id='+id,{method:'POST',body:pfd});}
      toast('Pièce modifiée');
    }else{
      await api('parts','POST',fd);
      toast('Pièce ajoutée');
    }
    closeModal('modal-part');
    if(currentPage==='maintenance')loadMaintenanceDetail(currentMaintenanceId);
    else if(currentPage==='vehicle')loadVehicleDetail(currentVehicleId);
    else loadParts();
  }catch(e){toast(e.message,'error');}
}
async function deletePart(id){
  if(!confirm('Supprimer cette pièce ?'))return;
  try{
    await api('parts','DELETE',null,'&id='+id);
    toast('Pièce supprimée');
    if(currentPage==='maintenance')loadMaintenanceDetail(currentMaintenanceId);
    else if(currentPage==='vehicle')loadVehicleDetail(currentVehicleId);
    else loadParts();
  }catch(e){toast(e.message,'error');}
}

// ─── UPLOAD PHOTO ─────────────────────────────────────────────────────────────
function openUploadPhoto(id){$('#upload-vehicle-id').value=id;openModal('modal-upload-photo');}
async function doUploadPhoto(){
  const id=$('#upload-vehicle-id').value;
  const file=$('#upload-photo-file').files[0];
  if(!file){toast('Choisissez une photo','error');return;}
  const fd=new FormData();fd.append('photo',file);
  try{
    await fetch(API+'?action=upload_vehicle_photo&id='+id,{method:'POST',body:fd});
    toast('Photo mise à jour');closeModal('modal-upload-photo');
    loadVehicleDetail(id);
  }catch(e){toast(e.message,'error');}
}

// ─── TABS ────────────────────────────────────────────────────────────────────
function switchTab(tab){
  $$('.tab-btn').forEach(b=>b.classList.remove('active'));
  $$('.tab-pane').forEach(p=>p.classList.remove('active'));
  document.querySelector('.tab-btn[data-tab="'+tab+'"]')?.classList.add('active');
  document.getElementById('tab-'+tab)?.classList.add('active');
}

// ─── PHOTO PREVIEW ────────────────────────────────────────────────────────────
function previewPhoto(inputId,previewId){
  const file=document.getElementById(inputId).files[0];if(!file)return;
  const reader=new FileReader();
  reader.onload=e=>{const img=document.getElementById(previewId);img.src=e.target.result;img.style.display='block';};
  reader.readAsDataURL(file);
}

// ─── INIT ────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded',()=>{
  $$('.nav-link').forEach(n=>n.addEventListener('click',()=>navigate(n.dataset.page)));
  $$('.modal-backdrop').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('show');}));
  navigate('dashboard');
});
