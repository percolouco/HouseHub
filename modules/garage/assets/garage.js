// GarageManager - SPA Frontend
const API = '/modules/garage/api.php';
const UPLOADS = '/modules/garage/api.php?action=photo&file=';

function $(s, ctx=document){return ctx.querySelector(s);}
function $$(s, ctx=document){return [...ctx.querySelectorAll(s)];}
function fmt(n){return n!=null?Number(n).toLocaleString('fr-FR'):'--';}
function fmtPrice(n){return n!=null?Number(n).toFixed(2)+' \u20ac':'--';}
function fmtDate(d){if(!d)return '--';return new Date(d).toLocaleDateString('fr-FR');}
function ago(d){if(!d)return '';const diff=Math.floor((Date.now()-new Date(d))/86400000);if(diff===0)return "aujourd'hui";if(diff===1)return 'hier';return 'il y a '+diff+'j';}
function toast(msg,type='success'){
  const c=document.getElementById('toasts');const t=document.createElement('div');
  t.className='toast '+type;t.textContent=msg;c.appendChild(t);
  setTimeout(()=>t.remove(),3000);
}

// NAV
let currentPage='dashboard';
function navigate(page,params={}){
  currentPage=page;
  $$('.page').forEach(p=>p.classList.remove('active'));
  $$('.nav-link').forEach(n=>n.classList.remove('active'));
  document.getElementById('page-'+page)?.classList.add('active');
  document.querySelector('.nav-link[data-page="'+page+'"]')?.classList.add('active');
  if(page==='dashboard')loadDashboard();
  else if(page==='vehicles')loadVehicles();
  else if(page==='vehicle')loadVehicleDetail(params.id);
  else if(page==='parts')loadParts();
}

// API
async function api(action,method='GET',data=null,extra=''){
  const opts={method,headers:{}};
  if(data&&!(data instanceof FormData)){opts.headers['Content-Type']='application/json';opts.body=JSON.stringify(data);}
  else if(data instanceof FormData){opts.body=data;}
  const r=await fetch(API+'?action='+action+extra,opts);
  const j=await r.json();
  if(!j.ok)throw new Error(j.error||'Erreur API');
  return j.data;
}

// DASHBOARD
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
  if(!list.length){el.innerHTML='<div class="empty-state"><div class="icon">\ud83d\ude97</div><p>Aucun v\u00e9hicule</p></div>';return;}
  el.innerHTML=list.slice(0,6).map(v=>vehicleCardHTML(v)).join('');
  $$('.vehicle-card',el).forEach(c=>c.addEventListener('click',()=>navigate('vehicle',{id:c.dataset.id})));
}

function vehicleCardHTML(v){
  const photoHTML=v.photo?'<img src="'+UPLOADS+v.photo+'" alt="">':'<span class="no-photo">\ud83d\ude97</span>';
  const fuel=v.fuel_type||'Essence';
  const fuelClass='fuel-'+fuel.replace(/\s/g,'');
  return '<div class="vehicle-card" data-id="'+v.id+'">'+
    '<div class="vehicle-card-img">'+photoHTML+'<span class="fuel-badge '+fuelClass+'">'+fuel+'</span></div>'+
    '<div class="vehicle-card-body">'+
      '<div class="vehicle-card-title">'+v.name+'</div>'+
      '<div class="vehicle-card-sub">'+v.brand+' '+v.model+(v.year?' &middot; '+v.year:'')+'</div>'+
      '<div class="vehicle-card-stats">'+
        '<div class="vc-stat">\ud83d\udd27 <strong>'+(v.maintenance_count||0)+'</strong> entretiens</div>'+
        '<div class="vc-stat">\ud83d\udcb6 <strong>'+fmtPrice(v.total_cost)+'</strong></div>'+
      '</div>'+
    '</div>'+
    '<div class="vehicle-card-footer">'+
      (v.license_plate?'<span class="plate">'+v.license_plate+'</span>':'<span></span>')+
      '<span class="vc-stat">'+(v.current_km?fmt(v.current_km)+' km':'--')+'</span>'+
    '</div></div>';
}

function renderReminders(list){
  const el=$('#reminders-list');const today=new Date();
  if(!list.length){el.innerHTML='<div class="empty-state" style="padding:1.5rem"><p style="color:var(--muted)">Aucun rappel configur\u00e9</p></div>';return;}
  el.innerHTML='<div class="table-wrap"><table><thead><tr><th>V\u00e9hicule</th><th>Type</th><th>Prochaine date</th><th>Prochain km</th><th>\u00c9cart km</th></tr></thead><tbody>'+
    list.map(r=>{
      const diff=r.next_km&&r.current_km?r.next_km-r.current_km:null;
      const dateOk=r.next_date?new Date(r.next_date)>today:true;
      const kmOk=diff==null||diff>0;
      const cls=(!dateOk||!kmOk)?'badge-red':diff!=null&&diff<2000?'badge-amber':'badge-green';
      return '<tr><td><strong>'+r.vehicle_name+'</strong>'+(r.license_plate?' <span class="plate">'+r.license_plate+'</span>':'')+'</td>'+
        '<td>'+r.type+'</td>'+
        '<td>'+(r.next_date?'<span class="badge '+cls+'">'+fmtDate(r.next_date)+'</span>':'--')+'</td>'+
        '<td>'+(r.next_km?fmt(r.next_km)+' km':'--')+'</td>'+
        '<td>'+(diff!=null?'<span class="badge '+(diff<0?'badge-red':diff<2000?'badge-amber':'badge-green')+'">'+(diff>=0?'+':'')+fmt(diff)+' km</span>':'--')+'</td></tr>';
    }).join('')+
  '</tbody></table></div>';
}

// VEHICLES LIST
async function loadVehicles(){
  try{
    const list=await api('vehicles');
    const el=$('#vehicles-grid');
    if(!list.length){el.innerHTML='<div class="empty-state"><div class="icon">\ud83d\ude97</div><p>Aucun v\u00e9hicule. Ajoutez-en un !</p></div>';return;}
    el.innerHTML=list.map(v=>vehicleCardHTML(v)).join('');
    $$('.vehicle-card',el).forEach(c=>c.addEventListener('click',()=>navigate('vehicle',{id:c.dataset.id})));
  }catch(e){toast(e.message,'error');}
}

// VEHICLE DETAIL
let currentVehicleId=null;
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
    document.title='GarageManager \u00b7 '+v.name;
  }catch(e){toast(e.message,'error');}
}

function renderVehicleHeader(v){
  const photoHTML=v.photo?'<img src="'+UPLOADS+v.photo+'" alt="">':'<div class="no-photo">\ud83d\ude97</div>';
  const totalCost=(parseFloat(v.stats?.total||0)+parseFloat(v.parts_stats?.total||0)).toFixed(2);
  $('#vehicle-header').innerHTML=
    '<div class="vehicle-detail-header">'+
      '<div class="vehicle-detail-img">'+photoHTML+'</div>'+
      '<div class="vehicle-meta">'+
        '<h2>'+v.name+'</h2>'+
        '<p class="sub">'+v.brand+' '+v.model+(v.year?' \u00b7 '+v.year:'')+'</p>'+
        '<div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:.75rem">'+
          (v.license_plate?'<span class="plate">'+v.license_plate+'</span>':'')+
          '<span class="badge badge-blue">'+(v.fuel_type||'Essence')+'</span>'+
          (v.color?'<span class="badge badge-gray">\ud83c\udfa8 '+v.color+'</span>':'')+
        '</div>'+
        '<div class="meta-grid">'+
          '<div class="meta-item"><span class="k">Kilom\u00e9trage</span><br><span class="v">'+fmt(v.current_km)+' km</span></div>'+
          '<div class="meta-item"><span class="k">Entretiens</span><br><span class="v">'+(v.stats?.cnt||0)+'</span></div>'+
          '<div class="meta-item"><span class="k">Co\u00fbt total</span><br><span class="v">'+fmtPrice(totalCost)+'</span></div>'+
          (v.purchase_date?'<div class="meta-item"><span class="k">Achat</span><br><span class="v">'+fmtDate(v.purchase_date)+'</span></div>':'')+
          (v.vin?'<div class="meta-item"><span class="k">VIN</span><br><span class="v" style="font-size:.75rem;font-family:monospace">'+v.vin+'</span></div>':'')+
        '</div>'+
      '</div>'+
      '<div style="display:flex;gap:.5rem;flex-wrap:wrap;align-self:flex-start">'+
        '<button class="btn btn-secondary btn-sm" onclick="openEditVehicle('+v.id+')">\u270f\ufe0f Modifier</button>'+
        '<button class="btn btn-secondary btn-sm" onclick="openUploadPhoto('+v.id+')">\ud83d\udcf7 Photo</button>'+
        '<button class="btn btn-danger btn-sm" onclick="deleteVehicle('+v.id+')">\ud83d\uddd1\ufe0f Supprimer</button>'+
      '</div>'+
    '</div>';
}

function renderMaintenances(list){
  const el=$('#maintenance-list');
  const totalCost=list.reduce((s,m)=>s+parseFloat(m.cost||0)+parseFloat(m.parts_cost||0),0);
  $('#maintenance-total').textContent=fmtPrice(totalCost);
  if(!list.length){el.innerHTML='<div class="empty-state"><div class="icon">\ud83d\udd27</div><p>Aucun entretien enregistr\u00e9</p></div>';return;}
  el.innerHTML='<div class="table-wrap"><table><thead><tr><th>Date</th><th>Type</th><th>Description</th><th>Kilom\u00e9trage</th><th>Co\u00fbt MO</th><th>Pi\u00e8ces</th><th>Prochain</th><th></th></tr></thead><tbody>'+
    list.map(m=>'<tr>'+
      '<td><strong>'+fmtDate(m.date)+'</strong><br><span style="color:var(--muted);font-size:.75rem">'+ago(m.date)+'</span></td>'+
      '<td><span class="badge badge-blue">'+m.type+'</span></td>'+
      '<td style="max-width:200px;color:var(--muted)">'+(m.description||'--')+'</td>'+
      '<td>'+(m.km?fmt(m.km)+' km':'--')+'</td>'+
      '<td>'+fmtPrice(m.cost)+'</td>'+
      '<td>'+(m.parts_count>0?'<span class="badge badge-purple">\ud83d\udd29 '+m.parts_count+' ('+fmtPrice(m.parts_cost)+')</span>':'--')+'</td>'+
      '<td style="font-size:.78rem">'+(m.next_date?'\ud83d\udcc5 '+fmtDate(m.next_date):'')+' '+(m.next_km?'<br>\ud83d\udee3\ufe0f '+fmt(m.next_km)+' km':'')+'</td>'+
      '<td><button class="btn btn-danger btn-sm btn-icon" onclick="deleteMaintenance('+m.id+')" title="Supprimer">\ud83d\uddd1\ufe0f</button></td>'+
    '</tr>').join('')+
  '</tbody></table></div>';
}

function renderVehicleParts(list){
  const el=$('#parts-list-vehicle');
  const total=list.reduce((s,p)=>s+parseFloat(p.price||0)*parseInt(p.quantity||1),0);
  $('#parts-total-vehicle').textContent=fmtPrice(total);
  if(!list.length){el.innerHTML='<div class="empty-state"><div class="icon">\ud83d\udd29</div><p>Aucune pi\u00e8ce enregistr\u00e9e</p></div>';return;}
  el.innerHTML='<div class="table-wrap"><table><thead><tr><th>Photo</th><th>Nom</th><th>Marque</th><th>R\u00e9f\u00e9rence</th><th>Cat\u00e9gorie</th><th>Prix unit.</th><th>Qt\u00e9</th><th>Total</th><th>Fournisseur</th><th></th></tr></thead><tbody>'+
    list.map(p=>'<tr>'+
      '<td>'+(p.photo?'<img class="part-thumb" src="'+UPLOADS+p.photo+'" alt="">':'<span style="color:var(--muted)">--</span>')+'</td>'+
      '<td><strong>'+p.name+'</strong></td>'+
      '<td>'+(p.brand||'--')+'</td>'+
      '<td><code style="font-size:.75rem;color:var(--muted)">'+(p.reference||'--')+'</code></td>'+
      '<td><span class="badge badge-gray">'+p.category+'</span></td>'+
      '<td>'+fmtPrice(p.price)+'</td>'+
      '<td>'+p.quantity+' '+p.unit+'</td>'+
      '<td><strong>'+fmtPrice(parseFloat(p.price||0)*parseInt(p.quantity||1))+'</strong></td>'+
      '<td>'+(p.supplier||'--')+'</td>'+
      '<td><button class="btn btn-danger btn-sm btn-icon" onclick="deletePart('+p.id+')" title="Supprimer">\ud83d\uddd1\ufe0f</button></td>'+
    '</tr>').join('')+
  '</tbody></table></div>';
}

// ALL PARTS
async function loadParts(){
  try{
    const list=await api('parts');
    const el=$('#all-parts-list');
    const total=list.reduce((s,p)=>s+parseFloat(p.price||0)*parseInt(p.quantity||1),0);
    $('#all-parts-total').textContent=fmtPrice(total);
    $('#all-parts-count').textContent=list.length;
    if(!list.length){el.innerHTML='<div class="empty-state"><div class="icon">\ud83d\udd29</div><p>Aucune pi\u00e8ce</p></div>';return;}
    el.innerHTML='<div class="table-wrap"><table><thead><tr><th>Photo</th><th>Nom</th><th>V\u00e9hicule</th><th>Marque</th><th>R\u00e9f\u00e9rence</th><th>Cat\u00e9gorie</th><th>Prix unit.</th><th>Qt\u00e9</th><th>Total</th><th>Entretien</th><th></th></tr></thead><tbody>'+
      list.map(p=>'<tr>'+
        '<td>'+(p.photo?'<img class="part-thumb" src="'+UPLOADS+p.photo+'" alt="">':'--')+'</td>'+
        '<td><strong>'+p.name+'</strong></td>'+
        '<td>'+(p.vehicle_name?'<span class="badge badge-blue">'+p.vehicle_name+'</span>':'--')+'</td>'+
        '<td>'+(p.brand||'--')+'</td>'+
        '<td><code style="font-size:.75rem;color:var(--muted)">'+(p.reference||'--')+'</code></td>'+
        '<td><span class="badge badge-gray">'+p.category+'</span></td>'+
        '<td>'+fmtPrice(p.price)+'</td>'+
        '<td>'+p.quantity+' '+p.unit+'</td>'+
        '<td><strong>'+fmtPrice(parseFloat(p.price||0)*parseInt(p.quantity||1))+'</strong></td>'+
        '<td>'+(p.maintenance_type?p.maintenance_type+' ('+fmtDate(p.maintenance_date)+')':'--')+'</td>'+
        '<td><button class="btn btn-danger btn-sm btn-icon" onclick="deletePart('+p.id+')" title="Supprimer">\ud83d\uddd1\ufe0f</button></td>'+
      '</tr>').join('')+
    '</tbody></table></div>';
  }catch(e){toast(e.message,'error');}
}

// MODALS
function openModal(id){document.getElementById(id).classList.add('show');}
function closeModal(id){document.getElementById(id).classList.remove('show');}

// VEHICLE CRUD
function openAddVehicle(){
  $('#form-vehicle').reset();$('#form-vehicle-id').value='';
  $('#modal-vehicle-title').textContent='Ajouter un v\u00e9hicule';
  $('#vehicle-photo-preview').src='';$('#vehicle-photo-preview').style.display='none';
  openModal('modal-vehicle');
}
function openEditVehicle(id){
  api('vehicles','GET',null,'&id='+id).then(v=>{
    $('#form-vehicle-id').value=v.id;
    $('#modal-vehicle-title').textContent='Modifier le v\u00e9hicule';
    ['name','brand','model','year','license_plate','vin','fuel_type','color','purchase_date','purchase_price','current_km','notes'].forEach(f=>{
      const el=$('#vehicle-'+f.replace(/_/g,'-'));
      if(el)el.value=v[f]||'';
    });
    if(v.photo){$('#vehicle-photo-preview').src=UPLOADS+v.photo;$('#vehicle-photo-preview').style.display='block';}
    openModal('modal-vehicle');
  });
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
      toast('V\u00e9hicule modifi\u00e9');
    }else{
      await api('vehicles','POST',fd);
      toast('V\u00e9hicule ajout\u00e9');
    }
    closeModal('modal-vehicle');
    if(currentPage==='vehicles')loadVehicles();
    else if(currentPage==='vehicle')loadVehicleDetail(currentVehicleId);
    else loadDashboard();
  }catch(e){toast(e.message,'error');}
}
async function deleteVehicle(id){
  if(!confirm('Supprimer ce v\u00e9hicule et tout son historique ?'))return;
  try{await api('vehicles','DELETE',null,'&id='+id);toast('V\u00e9hicule supprim\u00e9');navigate('vehicles');}catch(e){toast(e.message,'error');}
}

// MAINTENANCE CRUD
function openAddMaintenance(){
  $('#form-maintenance').reset();$('#form-maintenance-id').value='';
  $('#maintenance-vehicle-id').value=currentVehicleId||'';
  $('#modal-maintenance-title').textContent='Ajouter un entretien';
  $('#maintenance-date').value=new Date().toISOString().slice(0,10);
  openModal('modal-maintenance');
}
async function saveMaintenance(){
  const id=$('#form-maintenance-id').value;
  const d={};new FormData($('#form-maintenance')).forEach((v,k)=>d[k]=v);
  try{
    if(id){await api('maintenances','PUT',d,'&id='+id);toast('Entretien modifi\u00e9');}
    else{await api('maintenances','POST',d);toast('Entretien ajout\u00e9');}
    closeModal('modal-maintenance');
    loadVehicleDetail(currentVehicleId);
  }catch(e){toast(e.message,'error');}
}
async function deleteMaintenance(id){
  if(!confirm('Supprimer cet entretien ?'))return;
  try{await api('maintenances','DELETE',null,'&id='+id);toast('Entretien supprim\u00e9');loadVehicleDetail(currentVehicleId);}catch(e){toast(e.message,'error');}
}

// PARTS CRUD
function openAddPart(){
  $('#form-part').reset();$('#form-part-id').value='';
  $('#part-vehicle-id').value=currentVehicleId||'';
  $('#modal-part-title').textContent='Ajouter une pi\u00e8ce';
  $('#part-purchase-date').value=new Date().toISOString().slice(0,10);
  $('#part-photo-preview').src='';$('#part-photo-preview').style.display='none';
  openModal('modal-part');
}
async function savePart(){
  const id=$('#form-part-id').value;
  const fd=new FormData($('#form-part'));
  try{
    if(id){
      const d={};for(const[k,v]of fd.entries())d[k]=v;
      await api('parts','PUT',d,'&id='+id);
      const photoFile=$('#part-photo').files[0];
      if(photoFile){const pfd=new FormData();pfd.append('photo',photoFile);await fetch(API+'?action=upload_part_photo&id='+id,{method:'POST',body:pfd});}
      toast('Pi\u00e8ce modifi\u00e9e');
    }else{
      await api('parts','POST',fd);
      toast('Pi\u00e8ce ajout\u00e9e');
    }
    closeModal('modal-part');
    if(currentPage==='vehicle')loadVehicleDetail(currentVehicleId);
    else loadParts();
  }catch(e){toast(e.message,'error');}
}
async function deletePart(id){
  if(!confirm('Supprimer cette pi\u00e8ce ?'))return;
  try{
    await api('parts','DELETE',null,'&id='+id);
    toast('Pi\u00e8ce supprim\u00e9e');
    if(currentPage==='vehicle')loadVehicleDetail(currentVehicleId);
    else loadParts();
  }catch(e){toast(e.message,'error');}
}

// UPLOAD PHOTO
function openUploadPhoto(id){$('#upload-vehicle-id').value=id;openModal('modal-upload-photo');}
async function doUploadPhoto(){
  const id=$('#upload-vehicle-id').value;
  const file=$('#upload-photo-file').files[0];
  if(!file){toast('Choisissez une photo','error');return;}
  const fd=new FormData();fd.append('photo',file);
  try{
    await fetch(API+'?action=upload_vehicle_photo&id='+id,{method:'POST',body:fd});
    toast('Photo mise \u00e0 jour');closeModal('modal-upload-photo');
    loadVehicleDetail(id);
  }catch(e){toast(e.message,'error');}
}

// TABS
function switchTab(tab){
  $$('.tab-btn').forEach(b=>b.classList.remove('active'));
  $$('.tab-pane').forEach(p=>p.classList.remove('active'));
  document.querySelector('.tab-btn[data-tab="'+tab+'"]')?.classList.add('active');
  document.getElementById('tab-'+tab)?.classList.add('active');
}

// PHOTO PREVIEW
function previewPhoto(inputId,previewId){
  const file=document.getElementById(inputId).files[0];if(!file)return;
  const reader=new FileReader();
  reader.onload=e=>{const img=document.getElementById(previewId);img.src=e.target.result;img.style.display='block';};
  reader.readAsDataURL(file);
}

// INIT
document.addEventListener('DOMContentLoaded',()=>{
  $$('.nav-link').forEach(n=>n.addEventListener('click',()=>navigate(n.dataset.page)));
  $$('.modal-backdrop').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('show');}));
  navigate('dashboard');
});
