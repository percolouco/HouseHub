// HouseHub — Todo module
const API = '/modules/todo/api.php';

// ─── Helpers ─────────────────────────────────────────────────────────────────
function escHtml(s){const d=document.createElement('div');d.textContent=String(s??'');return d.innerHTML;}
function fmtDate(d){if(!d)return null;const dt=new Date(d+'T00:00:00');return dt.toLocaleDateString('fr-FR',{day:'2-digit',month:'2-digit'});}
function fmtTime(t){if(!t)return null;return t.slice(0,5);}
function isToday(d){if(!d)return false;return d===new Date().toISOString().slice(0,10);}
function isOverdue(d){if(!d)return false;return d<new Date().toISOString().slice(0,10);}
function toast(msg,type='success'){
  const c=document.getElementById('todo-toasts');
  const t=document.createElement('div');t.className='todo-toast'+(type==='error'?' error':'');
  t.textContent=msg;c.appendChild(t);setTimeout(()=>t.remove(),3000);
}
async function api(action,method='GET',data=null,extra=''){
  const opts={method,credentials:'same-origin',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}};
  if(data){opts.headers['Content-Type']='application/json';opts.body=JSON.stringify(data);}
  const r=await fetch(API+'?action='+action+extra,opts);
  const text=await r.text();
  let j;
  try{j=JSON.parse(text);}catch(e){console.error('Bad JSON from API:',text.slice(0,200));throw new Error('Erreur serveur');}
  if(!j.ok)throw new Error(j.error||'Erreur');
  return j.data;
}

// ─── State ───────────────────────────────────────────────────────────────────
let currentFilter='all';
let showDone=false;
let lists=[];
let editTodoId=null;
let editListId=null;
let selectedPriority='none';
let selectedColor='#3b82f6';
let selectedIcon='📋';

const COLORS=['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899','#06b6d4','#84cc16','#f97316','#64748b'];
const ICONS=['📋','🏠','🛒','💼','💪','🎯','📚','✈️','🎮','❤️','⭐','🔧'];

// ─── Sidebar ─────────────────────────────────────────────────────────────────
async function loadSidebar(){
  try{
    const[data,stats]=await Promise.all([api('lists'),api('stats')]);
    lists=data;
    const el=document.getElementById('todo-sidebar-lists');

    let html=`
      <div class="todo-nav-section">Vues</div>
      <div class="todo-nav-item${currentFilter==='all'?' active':''}" onclick="setFilter('all')">
        <span>📋</span> Toutes
        <span class="todo-nav-badge">${stats.pending||0}</span>
      </div>
      <div class="todo-nav-item${currentFilter==='done'?' active':''}" onclick="setFilter('done')">
        <span>✅</span> Terminées
        <span class="todo-nav-badge">${stats.done||0}</span>
      </div>`;

    html+=`<div class="todo-nav-section" style="display:flex;align-items:center;justify-content:space-between;padding-right:.75rem">
      Listes <button class="btn btn-ghost btn-icon btn-sm" onclick="openListModal()" title="Nouvelle liste" style="padding:.1rem .3rem;font-size:.85rem">+</button>
    </div>`;
    lists.forEach(l=>{
      const act=currentFilter==='list_'+l.id?' active':'';
      html+=`<div class="todo-nav-item${act}" onclick="setFilter('list_${l.id}')">
        <span class="todo-list-dot" style="background:${escHtml(l.color)}"></span>
        <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${escHtml(l.icon)} ${escHtml(l.name)}</span>
        <span class="todo-nav-badge">${l.pending||0}</span>
        <button class="btn btn-ghost btn-icon" onclick="event.stopPropagation();openListModal(${l.id})" title="Modifier" style="opacity:.5;padding:.1rem .2rem;font-size:.8rem">✏️</button>
      </div>`;
    });

    el.innerHTML=html;
  }catch(e){console.error(e);}
}

function setFilter(f){
  currentFilter=f;showDone=f==='done';
  loadSidebar();loadTodos();
}

// ─── Todo list ────────────────────────────────────────────────────────────────
async function loadTodos(){
  try{
    let extra='';
    if(currentFilter==='done')extra='&list_id=done&show_done=1';
    else if(currentFilter.startsWith('list_'))extra='&list_id='+currentFilter.slice(5);
    if(showDone&&currentFilter!=='done')extra+='&show_done=1';

    const todos=await api('todos','GET',null,extra);
    renderTodos(todos);
    updateHeader();
  }catch(e){toast(e.message,'error');}
}

function updateHeader(){
  const el=document.getElementById('todo-header-title');
  if(!el)return;
  const map={all:'Toutes les tâches',done:'Tâches terminées'};
  if(map[currentFilter]){el.textContent=map[currentFilter];return;}
  if(currentFilter.startsWith('list_')){
    const l=lists.find(x=>x.id==currentFilter.slice(5));
    el.textContent=l?(l.icon+' '+l.name):'Liste';
  }
}

function toggleShowDone(){
  showDone=!showDone;
  const btn=document.getElementById('show-done-btn');
  if(btn)btn.innerHTML=showDone?'🙈 Masquer terminées':'👁 Afficher terminées';
  loadTodos();
}

function renderTodos(todos){
  const el=document.getElementById('todo-list');
  if(!todos.length){
    el.innerHTML=`<div class="todo-empty"><div class="icon">${currentFilter==='done'?'✅':'📋'}</div>
      <p>${currentFilter==='done'?'Aucune tâche terminée.':'Aucune tâche pour le moment.'}</p></div>`;
    return;
  }
  const pending=todos.filter(t=>!parseInt(t.done));
  const done=todos.filter(t=>parseInt(t.done));
  let html='';
  pending.forEach(t=>{html+=todoItemHtml(t);});
  if(done.length&&showDone){
    html+=`<div class="todo-group-label">Terminées (${done.length})</div>`;
    done.forEach(t=>{html+=todoItemHtml(t);});
  }else if(done.length&&!showDone&&currentFilter!=='done'){
    html+=`<div style="padding:.5rem 0"><button class="show-done-btn" onclick="showDone=true;loadTodos()">
      ✅ Afficher ${done.length} tâche${done.length>1?'s':''} terminée${done.length>1?'s':''}</button></div>`;
  }
  el.innerHTML=html;
}

function todoItemHtml(t){
  const isDone=parseInt(t.done);
  const checkClass='todo-check'+(isDone?' checked':'');
  const itemClass='todo-item'+(isDone?' done':'');
  const dueCls=isOverdue(t.due_date)&&!isDone?' overdue':isToday(t.due_date)&&!isDone?' today':'';
  const dueLabel=t.due_time?('🔔 '+fmtTime(t.due_time)):null;
  const priBadge=t.priority&&t.priority!=='none'?
    `<span class="todo-priority-badge pri-${t.priority}">${t.priority==='high'?'Urgent':t.priority==='medium'?'Normal':'Bas'}</span>`:'';
  const listBadge=t.list_name?
    `<span class="todo-list-badge" style="background:${escHtml(t.list_color||'#3b82f6')}22;color:${escHtml(t.list_color||'#3b82f6')}">${escHtml(t.list_icon||'')} ${escHtml(t.list_name)}</span>`:'';
  return `<div class="${itemClass}" data-id="${t.id}" data-priority="${escHtml(t.priority||'none')}">
    <div class="${checkClass}" onclick="toggleDone(event,${t.id},${isDone?0:1})"></div>
    <div class="todo-content" onclick="openEditTodo(${t.id})">
      <div class="todo-title">${escHtml(t.title)}</div>
      ${t.notes?`<div class="todo-notes-preview">${escHtml(t.notes)}</div>`:''}
      <div class="todo-meta">
        ${dueLabel?`<span class="todo-due">${escHtml(dueLabel)}</span>`:''}
        ${priBadge}${listBadge}
      </div>
    </div>
    <div class="todo-item-actions">
      <button class="btn btn-ghost btn-icon" onclick="openEditTodo(${t.id})" title="Modifier">✏️</button>
      <button class="btn btn-ghost btn-icon" onclick="deleteTodo(event,${t.id})" title="Supprimer">🗑️</button>
    </div>
  </div>`;
}

async function toggleDone(e,id,done){
  e.stopPropagation();
  try{
    await api('todos','PUT',{done:done===1},'&id='+id);
    const item=document.querySelector(`.todo-item[data-id="${id}"]`);
    if(item){
      const check=item.querySelector('.todo-check');
      check.classList.add('just-done');
      setTimeout(()=>{check.classList.remove('just-done');loadTodos();loadSidebar();},300);
    }
  }catch(e){toast(e.message,'error');}
}

async function deleteTodo(e,id){
  e.stopPropagation();
  if(!confirm('Supprimer cette tâche ?'))return;
  try{await api('todos','DELETE',null,'&id='+id);toast('Supprimée');loadTodos();loadSidebar();}
  catch(e){toast(e.message,'error');}
}

// ─── Todo modal ───────────────────────────────────────────────────────────────
function openAddTodo(){
  editTodoId=null;
  document.getElementById('todo-modal-title').textContent='Nouvelle tâche';
  document.getElementById('todo-form-title').value='';
  document.getElementById('todo-form-notes').value='';
  document.getElementById('todo-form-time').value='';
  document.getElementById('todo-delete-btn').style.display='none';
  const sel=document.getElementById('todo-form-list');
  sel.innerHTML='<option value="">— Aucune —</option>'+lists.map(l=>`<option value="${l.id}">${escHtml(l.icon)} ${escHtml(l.name)}</option>`).join('');
  if(currentFilter.startsWith('list_'))sel.value=currentFilter.slice(5);
  setPriority('none');
  openModal('todo-modal');
  setTimeout(()=>document.getElementById('todo-form-title').focus(),50);
}

async function openEditTodo(id){
  editTodoId=id;
  try{
    const all=await api('todos','GET',null,'&show_done=1');
    const t=all.find(x=>x.id==id);
    if(!t)return;
    document.getElementById('todo-modal-title').textContent='Modifier la tâche';
    document.getElementById('todo-form-title').value=t.title;
    document.getElementById('todo-form-notes').value=t.notes||'';
    document.getElementById('todo-form-time').value=t.due_time?t.due_time.slice(0,5):'';
    document.getElementById('todo-delete-btn').style.display='';
    const sel=document.getElementById('todo-form-list');
    sel.innerHTML='<option value="">— Aucune —</option>'+lists.map(l=>`<option value="${l.id}">${escHtml(l.icon)} ${escHtml(l.name)}</option>`).join('');
    sel.value=t.list_id||'';
    setPriority(t.priority||'none');
    openModal('todo-modal');
  }catch(e){toast(e.message,'error');}
}

function setPriority(p){
  selectedPriority=p;
  document.querySelectorAll('.priority-opt').forEach(el=>{
    el.className='priority-opt';
    if(el.dataset.p===p)el.classList.add('sel-'+p);
  });
}

async function saveTodo(){
  const title=document.getElementById('todo-form-title').value.trim();
  if(!title){toast('Titre requis','error');return;}
  const time=document.getElementById('todo-form-time').value;
  if(!time){toast('Heure de rappel requise','error');return;}
  const data={
    title,
    notes:document.getElementById('todo-form-notes').value||null,
    due_time:time,
    list_id:document.getElementById('todo-form-list').value||null,
    priority:selectedPriority
  };
  try{
    if(editTodoId){await api('todos','PUT',data,'&id='+editTodoId);toast('Tâche mise à jour');}
    else{await api('todos','POST',data);toast('Tâche ajoutée ✓');}
    closeModal('todo-modal');loadTodos();loadSidebar();
  }catch(e){toast(e.message,'error');}
}

async function deleteTodoFromModal(){
  if(!editTodoId||!confirm('Supprimer cette tâche ?'))return;
  try{await api('todos','DELETE',null,'&id='+editTodoId);closeModal('todo-modal');toast('Supprimée');loadTodos();loadSidebar();}
  catch(e){toast(e.message,'error');}
}

// ─── List modal ───────────────────────────────────────────────────────────────
function openListModal(id=null){
  editListId=id;
  let list=id?lists.find(l=>l.id==id):null;
  document.getElementById('list-modal-title').textContent=id?'Modifier la liste':'Nouvelle liste';
  document.getElementById('list-form-name').value=list?.name||'';
  document.getElementById('list-delete-btn').style.display=id?'':'none';
  selectedColor=list?.color||'#3b82f6';
  selectedIcon=list?.icon||'📋';

  document.getElementById('list-color-swatches').innerHTML=COLORS.map(c=>
    `<div class="color-swatch${c===selectedColor?' selected':''}" style="background:${c}" onclick="selectColor('${c}')"></div>`).join('');

  document.getElementById('list-icon-opts').innerHTML=ICONS.map(i=>
    `<button type="button" class="btn btn-sm btn-secondary${i===selectedIcon?' ':''}" onclick="selectIcon(this,'${i}')"
      style="${i===selectedIcon?'background:#eff6ff;border-color:var(--primary)':''}">${i}</button>`).join('');

  openModal('list-modal');
}

function selectColor(c){
  selectedColor=c;
  document.querySelectorAll('.color-swatch').forEach(s=>{
    const hex=rgbToHex(s.style.background);
    s.classList.toggle('selected',hex===c||s.style.background===c);
  });
}

function rgbToHex(rgb){
  const m=rgb.match(/\d+/g);if(!m||m.length<3)return rgb;
  return '#'+m.slice(0,3).map(x=>parseInt(x).toString(16).padStart(2,'0')).join('');
}

function selectIcon(btn,icon){
  selectedIcon=icon;
  document.querySelectorAll('#list-icon-opts button').forEach(b=>{b.style.background='';b.style.borderColor='';});
  btn.style.background='#eff6ff';btn.style.borderColor='var(--primary)';
}

async function saveList(){
  const name=document.getElementById('list-form-name').value.trim();
  if(!name){toast('Nom requis','error');return;}
  const data={name,color:selectedColor,icon:selectedIcon};
  try{
    if(editListId){await api('lists','PUT',data,'&id='+editListId);}
    else{await api('lists','POST',data);}
    closeModal('list-modal');toast(editListId?'Liste mise à jour':'Liste créée ✓');
    loadSidebar();
  }catch(e){toast(e.message,'error');}
}

async function deleteList(){
  if(!editListId||!confirm('Supprimer la liste et toutes ses tâches ?'))return;
  try{await api('lists','DELETE',null,'&id='+editListId);closeModal('list-modal');toast('Liste supprimée');setFilter('all');}
  catch(e){toast(e.message,'error');}
}

// ─── Settings modal ───────────────────────────────────────────────────────────
async function openSettings(){
  try{
    const s=await api('settings');
    document.getElementById('settings-webhook').value=s.webhook_discord||'';
    openModal('settings-modal');
  }catch(e){openModal('settings-modal');}
}

async function saveSettings(){
  const webhook=document.getElementById('settings-webhook').value.trim();
  try{
    await api('settings','PUT',{webhook_discord:webhook});
    toast('Paramètres enregistrés ✓');closeModal('settings-modal');
  }catch(e){toast(e.message,'error');}
}

// ─── Modal helpers ────────────────────────────────────────────────────────────
function openModal(id){document.getElementById(id)?.classList.add('show');}
function closeModal(id){document.getElementById(id)?.classList.remove('show');}

// ─── Init ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded',()=>{
  loadSidebar();
  loadTodos();
  document.querySelectorAll('.todo-modal-backdrop').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('show');}));
});
