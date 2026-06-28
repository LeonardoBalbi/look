function locxBars(id, labels, values){
  const el=document.getElementById(id); if(!el) return;
  const nums = values.map(v=>Number(v||0));
  const max=Math.max(...nums,1);
  const isReport = el.classList.contains('report-bars');
  const money = v => Number(v||0).toLocaleString('pt-BR',{style:'currency',currency:'BRL',maximumFractionDigits:0});
  el.innerHTML=labels.map((l,i)=>{
    const val = nums[i] || 0;
    const h = Math.max(5,(val/max)*100);
    return `<div class="bar-item">${isReport ? `<strong class="bar-value">${money(val)}</strong>` : ''}<div class="bar-wrap"><span style="height:${h}%"></span></div><small>${l}</small></div>`;
  }).join('');
}
function locxDonut(id, items){
  const el=document.getElementById(id); if(!el) return; const rawTotal=items.reduce((a,b)=>a+Number(b.value),0); const total=rawTotal||1; let acc=0;
  const colors=['#22c55e','#0ea5e9','#f59e0b','#ef4444','#8b5cf6','#64748b'];
  const stops=rawTotal ? items.map((it,i)=>{const s=acc; acc+=it.value/total*100; return `${colors[i%colors.length]} ${s}% ${acc}%`;}).join(',') : '#e2e8f0 0 100%';
  el.innerHTML=`<div class="donut" style="background:conic-gradient(${stops})"><strong>${rawTotal}</strong><span>Total</span></div><div class="legend">${items.map((it,i)=>`<p><b style="background:${colors[i%colors.length]}"></b>${it.label}<span>${it.value}</span></p>`).join('')}</div>`;
}
(function(){
  function getSidebar(){
    return document.getElementById('sidebarMenu') || document.querySelector('.sidebar');
  }
  function getOverlay(){
    let ov=document.querySelector('.mobile-menu-overlay');
    if(!ov){ ov=document.createElement('div'); ov.className='mobile-menu-overlay'; document.body.prepend(ov); }
    return ov;
  }
  function openMenu(){
    const s=getSidebar(), ov=getOverlay();
    if(!s) return;
    s.id = s.id || 'sidebarMenu';
    s.classList.add('is-open','open');
    ov.classList.add('is-open');
    document.body.classList.add('menu-open');
  }
  function closeMenu(){
    const s=getSidebar(), ov=getOverlay();
    if(s) s.classList.remove('is-open','open');
    if(ov) ov.classList.remove('is-open');
    document.body.classList.remove('menu-open');
  }
  document.addEventListener('DOMContentLoaded', function(){
    const s=getSidebar(); if(s) s.id = s.id || 'sidebarMenu';
    document.querySelectorAll('.mobile-menu-toggle,.hamburger,#menuToggle,[data-menu-toggle]').forEach(btn=>{
      btn.setAttribute('type','button');
      btn.addEventListener('click', function(e){e.preventDefault();e.stopPropagation();openMenu();}, false);
    });
    document.querySelectorAll('.mobile-menu-close,.mobile-menu-overlay,[data-menu-close]').forEach(btn=>{
      btn.addEventListener('click', function(e){e.preventDefault();closeMenu();}, false);
    });
    document.querySelectorAll('.sidebar .menu a').forEach(a=>a.addEventListener('click',()=>{ if(window.innerWidth<=768) closeMenu(); }));
    document.querySelectorAll('.pix-copy-btn').forEach(btn=>{
      btn.addEventListener('click', async function(){
        const pix=this.dataset.pix || '';
        if(!pix) return;
        try{
          if(navigator.clipboard && window.isSecureContext){
            await navigator.clipboard.writeText(pix);
          }else{
            const tmp=document.createElement('textarea');
            tmp.value=pix;
            tmp.style.position='fixed';
            tmp.style.opacity='0';
            document.body.appendChild(tmp);
            tmp.focus();
            tmp.select();
            document.execCommand('copy');
            document.body.removeChild(tmp);
          }
          const original=this.textContent;
          this.textContent='PIX copiado';
          this.classList.add('success');
          setTimeout(()=>{this.textContent=original;this.classList.remove('success');},1800);
        }catch(e){
          this.textContent='Falha ao copiar';
          setTimeout(()=>{this.textContent='Copiar PIX';},1800);
        }
      });
    });
  });
  document.addEventListener('click', function(e){
    if(e.target.closest('.mobile-menu-toggle,.hamburger,#menuToggle,[data-menu-toggle]')){e.preventDefault();openMenu();}
    if(e.target.closest('.mobile-menu-close,.mobile-menu-overlay,[data-menu-close]')){e.preventDefault();closeMenu();}
  }, true);
  document.addEventListener('keydown', e=>{ if(e.key==='Escape') closeMenu(); });
})();

/* =========================================================
   LOCX V6.3 - GRÁFICOS EXECUTIVOS PREMIUM
   Donuts e barras com legendas alinhadas, fontes maiores e layout limpo.
   ========================================================= */
function locxFormatValue(value, prefix){
  const n = Number(value || 0);
  if(prefix === 'R$'){
    return n.toLocaleString('pt-BR', {style:'currency', currency:'BRL', maximumFractionDigits:0});
  }
  return n.toLocaleString('pt-BR');
}
function locxDonutPremium(id, items, prefix){
  const el=document.getElementById(id); if(!el) return;
  const rawTotal=items.reduce((a,b)=>a+Number(b.value||0),0);
  const total=rawTotal||1;
  let acc=0;
  const stops=rawTotal ? items.map(it=>{
    const s=acc;
    acc += (Number(it.value||0)/total)*100;
    return `${it.color || '#2563eb'} ${s}% ${acc}%`;
  }).join(',') : '#e2e8f0 0 100%';
  const main = prefix === 'R$' ? locxFormatValue(rawTotal, 'R$') : locxFormatValue(rawTotal);
  el.innerHTML = `
    <div class="donut-premium-chart" style="background:conic-gradient(${stops})">
      <div class="donut-premium-center"><strong>${main}</strong><span>Total</span></div>
    </div>
    <div class="donut-premium-legend">
      ${items.map(it=>{
        const val = locxFormatValue(it.value, prefix);
        const pct = rawTotal ? Math.round((Number(it.value||0)/rawTotal)*100) : 0;
        return `<div class="legend-line"><span class="legend-name"><i style="background:${it.color || '#2563eb'}"></i>${it.label}</span><b>${val}</b><small>${pct}%</small></div>`;
      }).join('')}
    </div>`;
}
function locxMiniBarsPremium(id, labels, values, prefix){
  const el=document.getElementById(id); if(!el) return;
  const max=Math.max(...values.map(v=>Number(v||0)),1);
  el.innerHTML = `<div class="mini-bars-list">${labels.map((label,i)=>{
    const val=Number(values[i]||0);
    const width=Math.max(5, Math.round((val/max)*100));
    return `<div class="mini-bar-row"><div class="mini-bar-top"><span>${label}</span><b>${locxFormatValue(val,prefix)}</b></div><div class="mini-bar-track"><i style="width:${width}%"></i></div></div>`;
  }).join('')}</div>`;
}
