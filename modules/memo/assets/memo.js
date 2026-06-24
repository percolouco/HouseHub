// HouseHub — Memo module
const API = '/modules/memo/api.php';

// ─── Helpers ─────────────────────────────────────────────────────────────────
function escHtml(s){ const d=document.createElement('div');d.textContent=String(s??'');return d.innerHTML; }
function fmtDate(d){ if(!d)return'--'; try{return new Date(d).toLocaleDateString('fr-FR',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'});}catch{return d;} }
function fmtSize(b){ if(!b)return''; if(b<1024)return b+'o'; if(b<1048576)return Math.round(b/1024)+'ko'; return (b/1048576).toFixed(1)+'Mo'; }
function toast(msg,type='success'){
  const c=document.getElementById('memo-toasts')||document.body;
  const t=document.createElement('div');t.className='memo-toast'+(type==='error'?' error':'');t.textContent=msg;
  c.appendChild(t);setTimeout(()=>t.remove(),3000);
}
async function api(action,method='GET',data=null,extra=''){
  const opts={method,headers:{}};
  if(data&&!(data instanceof FormData)){opts.headers['Content-Type']='application/json';opts.body=JSON.stringify(data);}
  else if(data instanceof FormData){opts.body=data;}
  const r=await fetch(API+'?action='+action+extra,opts);
  const j=await r.json();
  if(!j.ok)throw new Error(j.error||'Erreur API');
  return j.data;
}

// ─── State ───────────────────────────────────────────────────────────────────
let currentNoteId = null;
let currentPage   = 'list';
let currentQ      = '';
let currentTag    = '';
let allTagsList   = [];
let pendingFiles  = [];
let pendingUrls   = [];

// ─── Navigation ──────────────────────────────────────────────────────────────
function showPage(name){
  document.querySelectorAll('.memo-page').forEach(p=>p.classList.remove('active'));
  const el=document.getElementById('memo-page-'+name);
  if(el)el.classList.add('active');
  currentPage=name;
}

// ─── Sidebar ─────────────────────────────────────────────────────────────────
async function loadSidebar(){
  try{
    allTagsList=await api('tags');
    const el=document.getElementById('memo-tags-list');
    if(!el)return;
    const allActive=currentTag===''&&currentQ==='';
    let html=`<div class="memo-tag-item${allActive?' active':''}" onclick="filterTag('')">
      📝 Toutes les notes <span class="memo-tag-count" id="memo-all-count">…</span></div>`;
    allTagsList.forEach(t=>{
      const act=currentTag===t.tag?' active':'';
      html+=`<div class="memo-tag-item${act}" onclick="filterTag('${escHtml(t.tag)}')">
        <span># ${escHtml(t.tag)}</span><span class="memo-tag-count">${t.count}</span></div>`;
    });
    el.innerHTML=html;
  }catch(e){}
}

// ─── Note list ────────────────────────────────────────────────────────────────
async function loadList(q='',tag='',page=1){
  currentQ=q;currentTag=tag;showPage('list');
  loadSidebar();
  try{
    let extra='&page='+page;
    if(q)extra+='&q='+encodeURIComponent(q);
    if(tag)extra+='&tag='+encodeURIComponent(tag);
    const data=await api('notes','GET',null,extra);
    const el=document.getElementById('memo-notes-grid');
    const count=document.getElementById('memo-all-count');
    const title=document.getElementById('memo-list-title');
    const subtitle=document.getElementById('memo-list-subtitle');
    if(count)count.textContent=data.total;
    if(title){
      title.textContent = q?`🔍 "${q}"` : tag?`# ${tag}` : '📝 Notes';
    }
    if(subtitle)subtitle.textContent=data.total+' note'+(data.total!==1?'s':'');
    if(!data.notes.length){
      el.innerHTML='<div class="memo-empty"><div class="icon">📝</div><p>'+(q||tag?'Aucune note trouvée.':'Aucune note. Créez-en une !')+'</p></div>';
      return;
    }
    el.innerHTML=data.notes.map(n=>noteCardHtml(n)).join('');
    // Pagination
    const total_pages=Math.ceil(data.total/data.per_page);
    const pag=document.getElementById('memo-pagination');
    if(pag){
      pag.innerHTML=total_pages>1?
        (page>1?`<button class="btn btn-secondary btn-sm" onclick="loadList('${escHtml(q)}','${escHtml(tag)}',${page-1})">← Précédent</button>`:'<span></span>')+
        `<span style="color:var(--text-muted);font-size:.82rem">Page ${page} / ${total_pages}</span>`+
        (page<total_pages?`<button class="btn btn-secondary btn-sm" onclick="loadList('${escHtml(q)}','${escHtml(tag)}',${page+1})">Suivant →</button>`:'<span></span>')
      : '';
    }
  }catch(e){toast(e.message,'error');}
}

function noteCardHtml(n){
  const tags=(n.tags||'').split(',').filter(t=>t.trim()).map(t=>
    `<span class="note-tag-chip" onclick="event.stopPropagation();filterTag('${escHtml(t.trim())}')">#${escHtml(t.trim())}</span>`).join('');
  const snippet=n.snippet?(escHtml(n.snippet.replace(/\n/g,' '))):'';
  return `<div class="note-card" onclick="viewNote(${n.id})">
    <div class="note-card-title">${escHtml(n.title)}</div>
    ${snippet?`<div class="note-card-snippet">${snippet}</div>`:''}
    <div class="note-card-meta">${tags}<span class="note-card-date">${fmtDate(n.updated_at)}</span></div>
  </div>`;
}

function filterTag(tag){
  currentTag=tag;currentQ='';
  document.getElementById('memo-search-input')?.value!=null&&(document.getElementById('memo-search-input').value='');
  loadList('',tag);
}

function doSearch(){
  const q=document.getElementById('memo-search-input')?.value.trim()||'';
  loadList(q,'');
}

// ─── Note view ────────────────────────────────────────────────────────────────
async function viewNote(id){
  currentNoteId=id;showPage('view');
  try{
    const note=await api('notes','GET',null,'&id='+id);
    document.getElementById('view-title').textContent=note.title;
    document.getElementById('view-date').textContent='Modifié '+fmtDate(note.updated_at);
    // Tags
    const tagEl=document.getElementById('view-tags');
    tagEl.innerHTML=(note.tags||'').split(',').filter(t=>t.trim()).map(t=>
      `<span class="note-tag-chip" onclick="filterTag('${escHtml(t.trim())}')">#${escHtml(t.trim())}</span>`).join('');
    // Markdown rendering
    const mdEl=document.getElementById('view-md');
    if(note.content&&window.marked){
      mdEl.innerHTML=marked.parse(note.content);
      mdEl.classList.remove('empty');
    } else if(note.content){
      mdEl.textContent=note.content;mdEl.classList.remove('empty');
    } else {
      mdEl.innerHTML='<em>Aucun contenu.</em>';mdEl.classList.add('empty');
    }
    // Attachments
    renderAttachmentsView(note.attachments||[],id);
  }catch(e){toast(e.message,'error');}
}

function renderAttachmentsView(atts,noteId){
  const images=atts.filter(a=>a.type==='image');
  const files =atts.filter(a=>a.type==='file');
  const urls  =atts.filter(a=>a.type==='url');
  const el=document.getElementById('view-attachments');
  el.innerHTML='';
  if(!atts.length){el.style.display='none';return;}
  el.style.display='block';

  if(images.length){
    el.innerHTML+=`<div class="memo-att-section">
      <div class="memo-att-title">🖼️ Images</div>
      <div class="att-img-grid">${images.map(a=>`
        <div class="att-img-item" onclick="openLightbox('${API}?action=file&id=${a.id}')">
          <img src="${API}?action=file&id=${a.id}" alt="${escHtml(a.original_name||'')}">
          <button class="att-del" onclick="event.stopPropagation();deleteAttachment(${a.id},${noteId})" title="Supprimer">✕</button>
        </div>`).join('')}</div></div>`;
  }
  if(files.length){
    el.innerHTML+=`<div class="memo-att-section">
      <div class="memo-att-title">📎 Fichiers</div>
      <div class="att-file-list">${files.map(a=>`
        <div class="att-file-item">
          <a href="${API}?action=file&id=${a.id}" target="_blank">${escHtml(a.original_name||a.filename)}</a>
          <span class="att-file-size">${fmtSize(a.size)}</span>
          <button class="btn btn-danger btn-sm" onclick="deleteAttachment(${a.id},${noteId})">✕</button>
        </div>`).join('')}</div></div>`;
  }
  if(urls.length){
    el.innerHTML+=`<div class="memo-att-section">
      <div class="memo-att-title">🔗 Liens</div>
      <div class="att-url-list">${urls.map(a=>`
        <div class="att-url-item">
          <a href="${escHtml(a.url)}" target="_blank" rel="noopener">${escHtml(a.label||a.url)}</a>
          <button class="btn btn-danger btn-sm" onclick="deleteAttachment(${a.id},${noteId})">✕</button>
        </div>`).join('')}</div></div>`;
  }
}

async function deleteAttachment(attId,noteId){
  if(!confirm('Supprimer cet attachement ?'))return;
  try{ await api('attachments','DELETE',null,'&id='+attId); toast('Supprimé'); viewNote(noteId); }
  catch(e){toast(e.message,'error');}
}

// Lightbox
function openLightbox(src){
  const lb=document.getElementById('memo-lightbox');
  const img=lb.querySelector('img');
  img.src=src;lb.classList.add('show');
}
function closeLightbox(){ document.getElementById('memo-lightbox')?.classList.remove('show'); }

// ─── Edit / Create ────────────────────────────────────────────────────────────
let editingId=null;
let editTags=[];
let tagAutocomplete=[];

function openCreate(){
  editingId=null;pendingFiles=[];pendingUrls=[];editTags=[];
  document.getElementById('edit-page-title').textContent='Nouvelle note';
  document.getElementById('edit-title').value='';
  document.getElementById('edit-content').value='';
  document.getElementById('md-live-preview').innerHTML='';
  document.getElementById('edit-tags-chips').innerHTML='';
  document.getElementById('edit-tags-input').value='';
  document.getElementById('edit-attach-preview').innerHTML='';
  document.getElementById('edit-url-rows').innerHTML=addUrlRowHtml();
  renderTagChips();
  showPage('edit');
  document.getElementById('edit-title')?.focus();
}

async function openEdit(id){
  editingId=id;pendingFiles=[];pendingUrls=[];editTags=[];
  try{
    const note=await api('notes','GET',null,'&id='+id);
    document.getElementById('edit-page-title').textContent='Modifier la note';
    document.getElementById('edit-title').value=note.title;
    document.getElementById('edit-content').value=note.content||'';
    editTags=(note.tags||'').split(',').filter(t=>t.trim());
    renderTagChips();
    updatePreview();
    // existing attachments shown below form
    renderAttachmentsEdit(note.attachments||[]);
    document.getElementById('edit-url-rows').innerHTML=addUrlRowHtml();
    showPage('edit');
  }catch(e){toast(e.message,'error');}
}

function renderAttachmentsEdit(atts){
  const el=document.getElementById('edit-existing-attachments');
  if(!atts.length){el.innerHTML='';return;}
  el.innerHTML=`<div class="memo-att-title" style="margin-bottom:.5rem">Attachements existants</div>`+
    atts.map(a=>`<div class="att-file-item">
      ${a.type==='url'?
        `<a href="${escHtml(a.url)}" target="_blank">${escHtml(a.label||a.url)}</a>`:
        `<a href="${API}?action=file&id=${a.id}" target="_blank">${escHtml(a.original_name||a.filename)}</a>
         <span class="att-file-size">${fmtSize(a.size)}</span>`}
      <button class="btn btn-danger btn-sm" onclick="deleteAttachment(${a.id},${editingId})">✕</button>
    </div>`).join('');
}

function updatePreview(){
  const content=document.getElementById('edit-content')?.value||'';
  const el=document.getElementById('md-live-preview');
  if(!el)return;
  el.innerHTML=window.marked?marked.parse(content):'<em style="color:var(--text-muted)">Preview chargement…</em>';
}

// Tags
function renderTagChips(){
  const wrap=document.getElementById('edit-tags-chips');
  wrap.innerHTML=editTags.map(t=>
    `<span class="tag-chip">#${escHtml(t)}<button type="button" onclick="removeTag('${escHtml(t)}')" title="Retirer">×</button></span>`
  ).join('');
}
function removeTag(t){ editTags=editTags.filter(x=>x!==t);renderTagChips(); }
function addTagFromInput(){
  const inp=document.getElementById('edit-tags-input');
  const val=inp.value.trim().replace(/^#/,'').toLowerCase();
  if(val&&!editTags.includes(val)){editTags.push(val);renderTagChips();}
  inp.value='';
  document.getElementById('tags-suggestions')?.remove();
}
function onTagKeydown(e){
  if(e.key==='Enter'||e.key===','||e.key===' '){e.preventDefault();addTagFromInput();}
  else if(e.key==='Backspace'&&!e.target.value&&editTags.length){
    editTags.pop();renderTagChips();
  } else showTagSuggestions(e.target.value);
}
function showTagSuggestions(q){
  document.getElementById('tags-suggestions')?.remove();
  if(!q||!allTagsList.length)return;
  const matches=allTagsList.filter(t=>t.tag.includes(q.toLowerCase())).slice(0,6);
  if(!matches.length)return;
  const inp=document.getElementById('edit-tags-input');
  const box=document.createElement('div');
  box.id='tags-suggestions';box.className='tags-suggestions';
  box.style.cssText=`position:absolute;top:${inp.offsetTop+inp.offsetHeight+4}px;left:${inp.offsetLeft}px`;
  matches.forEach(t=>{const d=document.createElement('div');d.textContent='#'+t.tag;d.onclick=()=>{
    if(!editTags.includes(t.tag)){editTags.push(t.tag);renderTagChips();}
    inp.value='';box.remove();
  };box.appendChild(d);});
  inp.closest('.tags-input-wrap').style.position='relative';
  inp.closest('.tags-input-wrap').appendChild(box);
}

// File drop
function initDropZone(){
  const zone=document.getElementById('edit-drop-zone');
  const inp=document.getElementById('edit-file-input');
  if(!zone)return;
  zone.addEventListener('click',()=>inp?.click());
  zone.addEventListener('dragover',e=>{e.preventDefault();zone.classList.add('drag-over');});
  zone.addEventListener('dragleave',()=>zone.classList.remove('drag-over'));
  zone.addEventListener('drop',e=>{e.preventDefault();zone.classList.remove('drag-over');handleFileSelect(e.dataTransfer.files);});
  inp?.addEventListener('change',()=>handleFileSelect(inp.files));
  document.getElementById('edit-content')?.addEventListener('paste',e=>{
    const files=[...e.clipboardData.files].filter(f=>f.type.startsWith('image/'));
    if(files.length){e.preventDefault();handleFileSelect(files);}
  });
}
function handleFileSelect(files){
  const preview=document.getElementById('edit-attach-preview');
  [...files].forEach(f=>{
    pendingFiles.push(f);
    const item=document.createElement('div');item.className='attach-preview-item';
    if(f.type.startsWith('image/')){
      const img=document.createElement('img');
      const reader=new FileReader();
      reader.onload=ev=>{img.src=ev.target.result;};
      reader.readAsDataURL(f);
      item.appendChild(img);
    }else{
      item.innerHTML=`<div style="background:#f1f5f9;padding:.3rem .5rem;border-radius:6px;font-size:.75rem;max-width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${escHtml(f.name)}</div>`;
    }
    const rm=document.createElement('button');rm.className='rm';rm.textContent='✕';
    rm.onclick=()=>{pendingFiles=pendingFiles.filter(x=>x!==f);item.remove();};
    item.appendChild(rm);
    preview.appendChild(item);
  });
}

// URL rows
function addUrlRowHtml(){
  return `<div class="url-row" style="margin-bottom:.4rem">
    <input type="text" class="form-control url-input" placeholder="https://..." style="flex:1.5">
    <input type="text" class="form-control url-label" placeholder="Libellé (optionnel)" style="flex:1">
    <button type="button" class="btn btn-secondary btn-sm" onclick="addUrlRow()">+</button>
  </div>`;
}
function addUrlRow(){
  const wrap=document.getElementById('edit-url-rows');
  wrap.insertAdjacentHTML('beforeend',addUrlRowHtml());
}

// Save
async function saveNote(){
  const title=document.getElementById('edit-title')?.value.trim();
  if(!title){toast('Titre requis','error');return;}
  const content=document.getElementById('edit-content')?.value||'';
  const tags=editTags.join(',');

  // Collect URLs
  const urlRows=document.querySelectorAll('#edit-url-rows .url-row');
  const urls=[];
  urlRows.forEach(row=>{
    const u=row.querySelector('.url-input')?.value.trim();
    const l=row.querySelector('.url-label')?.value.trim()||'';
    if(u)urls.push({url:u,label:l});
  });

  try{
    let noteId;
    if(editingId){
      await api('notes','PUT',{title,content,tags},'&id='+editingId);
      noteId=editingId;toast('Note mise à jour');
    }else{
      const r=await api('notes','POST',{title,content,tags,urls});
      noteId=r.id;toast('Note créée');
    }

    // Upload pending files
    for(const f of pendingFiles){
      const fd=new FormData();fd.append('file',f);fd.append('note_id',noteId);
      try{await api('attachments','POST',fd);}catch(e){toast('Erreur upload: '+f.name,'error');}
    }
    // Add URLs (for edit mode)
    if(editingId){
      for(const u of urls){
        const fd=new FormData();fd.append('note_id',noteId);fd.append('url',u.url);fd.append('label',u.label);
        try{await api('attachments','POST',fd);}catch{}
      }
    }

    loadList(currentQ,currentTag);
    viewNote(noteId);
  }catch(e){toast(e.message,'error');}
}

async function deleteNote(id){
  if(!confirm('Supprimer cette note définitivement ?'))return;
  try{
    await api('notes','DELETE',null,'&id='+id);
    toast('Note supprimée');loadList(currentQ,currentTag);
  }catch(e){toast(e.message,'error');}
}

// ─── Init ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded',()=>{
  loadList();
  loadSidebar();
  initDropZone();
  // Search
  const sinp=document.getElementById('memo-search-input');
  if(sinp){
    let t;sinp.addEventListener('input',()=>{clearTimeout(t);t=setTimeout(()=>doSearch(),300);});
    sinp.addEventListener('keydown',e=>{if(e.key==='Enter')doSearch();});
  }
  // Content live preview
  document.getElementById('edit-content')?.addEventListener('input',updatePreview);
  // Lightbox close
  document.getElementById('memo-lightbox')?.addEventListener('click',closeLightbox);
  // Tags input
  document.getElementById('edit-tags-input')?.addEventListener('keydown',onTagKeydown);
  document.getElementById('edit-tags-input')?.addEventListener('blur',()=>setTimeout(()=>document.getElementById('tags-suggestions')?.remove(),150));
  document.addEventListener('click',e=>{if(!e.target.closest('.tags-input-wrap'))document.getElementById('tags-suggestions')?.remove();});
});
