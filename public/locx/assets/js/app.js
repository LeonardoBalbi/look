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
  function startChatIntro(popup){
    if(!popup) return;
    const options=popup.querySelector('[data-chat-options]');
    const thread=popup.querySelector('[data-chat-thread]');
    const typing=popup.querySelector('[data-chat-typing]');
    const isHuman=popup.dataset.chatHuman==='1';
    const showOptions=popup.dataset.chatShowOptions==='1' && !isHuman;
    if(typing) typing.style.display='none';
    if(options){
      if(thread) thread.appendChild(options);
      options.classList.toggle('is-hidden', !showOptions);
      if(thread) thread.scrollTop=thread.scrollHeight;
    }
  }
  function chatEscape(text){
    return String(text || '').replace(/[&<>"']/g, ch=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
  }
  function chatLastMessageId(thread, attr){
    if(!thread) return 0;
    return Math.max(0, ...Array.from(thread.querySelectorAll('['+attr+']')).map(item=>Number(item.getAttribute(attr) || 0)));
  }
  function chatRowByClientToken(thread, clientToken){
    if(!thread || !clientToken) return null;
    return Array.from(thread.querySelectorAll('[data-chat-client-token]'))
      .find(item=>item.getAttribute('data-chat-client-token')===clientToken) || null;
  }
  function chatAppend(thread, remetente, mensagem, hora, id, criadoEm, clientToken, pending, remetenteNome){
    if(!thread) return null;
    const persisted=id ? thread.querySelector('[data-chat-message-id="'+id+'"]') : null;
    if(persisted) return persisted;
    let row=chatRowByClientToken(thread, clientToken);
    if(!row) row=document.createElement('article');
    const nome=remetente==='cliente' ? 'Voce' : (remetente==='humano' ? (remetenteNome || 'Equipe LOCX') : (remetenteNome || 'Lau'));
    row.className='chat-row '+remetente+(pending ? ' is-pending' : '');
    row.setAttribute('role','article');
    row.setAttribute('aria-label', nome+' disse'+(hora ? ' as '+hora : ''));
    if(id) row.setAttribute('data-chat-message-id', id);
    if(clientToken) row.setAttribute('data-chat-client-token', clientToken);
    const avatar=remetente==='cliente' ? '' : '<span class="chat-mini-avatar"><img src="/locx/assets/img/atendente-lauro.png" alt="Lau"></span>';
    const sender='<span class="chat-sender">'+chatEscape(nome)+'</span>';
    row.innerHTML=avatar+'<div class="chat-bubble">'+sender+chatEscape(mensagem)+(hora ? '<time datetime="'+chatEscape(criadoEm || new Date().toISOString())+'">'+chatEscape(hora)+'</time>' : '')+'</div>';
    if(!row.parentNode) thread.appendChild(row);
    thread.scrollTop=thread.scrollHeight;
    return row;
  }
  function chatCreateClientToken(){
    if(window.crypto && typeof window.crypto.randomUUID==='function') return window.crypto.randomUUID();
    return 'chat-'+Date.now()+'-'+Math.random().toString(16).slice(2);
  }
  function chatSetStatus(popup, text){
    const status=popup?.querySelector('[data-chat-status]');
    if(status && text) status.textContent=text;
  }
  async function syncClientChat(popup){
    if(!popup || popup.dataset.syncing==='1') return;
    const form=popup.querySelector('[data-chat-form]');
    const atendimento=form?.querySelector('[data-chat-atendimento]')?.value || '';
    const url=popup.dataset.chatSyncUrl || '';
    const thread=popup.querySelector('[data-chat-thread]');
    if(!atendimento || !url || !thread) return;
    popup.dataset.syncing='1';
    try{
      const after=chatLastMessageId(thread, 'data-chat-message-id');
      const response=await fetch(url+'?atendimento_id='+encodeURIComponent(atendimento)+'&after_id='+encodeURIComponent(after),{
        headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},
        credentials:'same-origin'
      });
      const data=await response.json();
      if(!response.ok || !data.ok) return;
      if(data.humano){
        popup.dataset.chatHuman='1';
        popup.dataset.chatShowOptions='0';
        chatSetStatus(popup, 'Aguardando a loja');
      }
      const novas=data.mensagens || [];
      const temNovaRecebida=novas.some(msg=>crmNormalizarRemetente(msg.remetente)!=='cliente');
      novas.forEach(msg=>chatAppend(thread, msg.remetente, msg.mensagem, msg.hora, msg.id, msg.criado_em, msg.client_token, false, msg.remetente_nome));
      if(popup.dataset.chatSoundReady==='1' && temNovaRecebida) crmPlayNotification();
      popup.dataset.chatSoundReady='1';
      const options=popup.querySelector('[data-chat-options]');
      if(data.mostrar_opcoes && !data.humano){
        popup.dataset.chatShowOptions='1';
        showChatOptions(popup);
      }else if(options && popup.dataset.chatHuman==='1'){
        popup.dataset.chatShowOptions='0';
        options.classList.add('is-hidden');
      }
      if(data.encerrado_por_inatividade || data.encerrado){
        expireClientChat(popup);
        showChatOptions(popup);
      }
    }catch(e){
      // Mantem o chat utilizavel mesmo se a rede oscilar.
    }finally{
      popup.dataset.syncing='0';
    }
  }
  function startClientChatSync(popup){
    if(!popup || popup._chatSyncTimer || !popup.classList.contains('is-open')) return;
    syncClientChat(popup);
    popup._chatSyncTimer=window.setInterval(()=>{
      if(document.visibilityState==='visible' && popup.classList.contains('is-open')) syncClientChat(popup);
    },5000);
  }
  function stopClientChatSync(popup){
    if(!popup || !popup._chatSyncTimer) return;
    window.clearInterval(popup._chatSyncTimer);
    popup._chatSyncTimer=null;
  }
  function expireClientChat(popup){
    if(!popup) return;
    const form=popup.querySelector('[data-chat-form]');
    const atendimento=form?.querySelector('[data-chat-atendimento]');
    const subject=form?.querySelector('#chatSubject');
    const textarea=form?.querySelector('textarea[name="mensagem"]');
    const options=popup.querySelector('[data-chat-options]');
    stopClientChatSync(popup);
    if(atendimento) atendimento.value='';
    if(subject) subject.value='';
    if(textarea){
      textarea.value='';
      textarea.placeholder='Abra uma nova conversa...';
    }
    popup.dataset.chatHuman='0';
    popup.dataset.chatShowOptions='1';
    chatSetStatus(popup, 'Atendimento encerrado');
    popup.querySelectorAll('[data-chat-subject]').forEach(item=>item.classList.remove('is-selected'));
    if(options) options.classList.remove('is-hidden');
  }
  function showChatOptions(popup){
    if(!popup) return;
    popup.dataset.chatShowOptions='1';
    const thread=popup.querySelector('[data-chat-thread]');
    const options=popup.querySelector('[data-chat-options]');
    if(!options) return;
    options.classList.remove('is-hidden');
    if(thread){
      thread.appendChild(options);
      thread.scrollTop=thread.scrollHeight;
    }
  }
  function crmNormalizarRemetente(remetente){
    const valor=String(remetente || '').trim().toLowerCase();
    if(['cliente','client','usuario','user','portal_cliente'].includes(valor)) return 'cliente';
    if(['humano','atendente','admin','administrador','equipe','operador'].includes(valor)) return 'humano';
    return 'bot';
  }
  function crmNomeRemetente(remetente, nome){
    const tipo=crmNormalizarRemetente(remetente);
    if(tipo==='cliente') return 'Cliente';
    if(tipo==='humano') return 'Atendente · '+(nome || 'Equipe LOCX');
    return 'Assistente · '+(nome || 'Lau');
  }
  function crmStatusClass(status){
    return ({novo:'is-new',aguardando_humano:'is-attention',em_atendimento:'is-active',respondido:'is-waiting',fechado:'is-resolved',cancelado:'is-muted'})[status] || 'is-muted';
  }
  const crmSoundState={ctx:null,unlocked:false,lastAt:0};
  function crmAudioContext(){
    const AudioCtx=window.AudioContext || window.webkitAudioContext;
    if(!AudioCtx) return null;
    if(!crmSoundState.ctx) crmSoundState.ctx=new AudioCtx();
    return crmSoundState.ctx;
  }
  function crmUnlockSound(){
    const ctx=crmAudioContext();
    if(!ctx) return;
    crmSoundState.unlocked=true;
    if(ctx.state==='suspended') ctx.resume().catch(()=>{});
  }
  function crmPlayNotification(){
    const ctx=crmAudioContext();
    if(!ctx || !crmSoundState.unlocked) return;
    const nowMs=Date.now();
    if(nowMs-crmSoundState.lastAt<1200) return;
    crmSoundState.lastAt=nowMs;
    if(ctx.state==='suspended'){
      ctx.resume().catch(()=>{});
      return;
    }
    const now=ctx.currentTime;
    [0,0.13].forEach((offset,index)=>{
      const osc=ctx.createOscillator();
      const gain=ctx.createGain();
      osc.type='sine';
      osc.frequency.value=index ? 880 : 660;
      gain.gain.setValueAtTime(0.0001, now+offset);
      gain.gain.exponentialRampToValueAtTime(0.16, now+offset+0.015);
      gain.gain.exponentialRampToValueAtTime(0.0001, now+offset+0.11);
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.start(now+offset);
      osc.stop(now+offset+0.12);
    });
  }
  function crmApplyStatus(card, status, label, attendant){
    if(!card) return;
    card.classList.remove('is-new','is-attention','is-active','is-waiting','is-resolved','is-muted');
    card.classList.add(crmStatusClass(status));
    card.dataset.status=status || '';
    const pill=card.querySelector('[data-crm-status-label]');
    if(pill){
      pill.className='crm-status-pill '+crmStatusClass(status);
      pill.textContent=label || status || 'Atendimento';
    }
    const attendantEl=card.querySelector('[data-crm-attendant]');
    if(attendantEl) attendantEl.textContent=attendant || 'Não atribuído';
  }
  function crmAppendMessage(thread, msg){
    if(!thread || !msg || !msg.id || thread.querySelector('[data-crm-chat-message-id="'+msg.id+'"]')) return;
    const tipo=crmNormalizarRemetente(msg.remetente);
    const nome=crmNomeRemetente(tipo, msg.remetente_nome);
    const row=document.createElement('article');
    row.className='crm-chat-line '+tipo;
    row.setAttribute('role','article');
    row.setAttribute('aria-label', nome+' disse'+(msg.hora ? ' às '+msg.hora : ''));
    row.setAttribute('data-crm-chat-message-id', msg.id);
    row.innerHTML='<div class="crm-chat-bubble"><span>'+chatEscape(nome)+'</span><p>'+chatEscape(msg.mensagem)+'</p>'+(msg.hora ? '<time datetime="'+chatEscape(msg.criado_em || new Date().toISOString())+'">'+chatEscape(msg.hora)+'</time>' : '')+'</div>';
    thread.appendChild(row);
    thread.scrollTop=thread.scrollHeight;
  }
  async function crmMarkRead(card){
    if(!card || !card.dataset.readUrl || card.dataset.reading==='1') return;
    card.dataset.reading='1';
    const desk=card.closest('[data-crm-desk]');
    try{
      await fetch(card.dataset.readUrl,{
        method:'POST',
        headers:{
          'Accept':'application/json',
          'X-Requested-With':'XMLHttpRequest',
          'X-CSRF-TOKEN':desk?.dataset.csrf || ''
        },
        credentials:'same-origin'
      });
      const selectedId=card.dataset.crmChat || '';
      document.querySelectorAll('[data-crm-inbox-item][data-atendimento-id="'+selectedId+'"]').forEach(item=>{
        item.classList.remove('has-unread');
        item.querySelector('.crm-unread-badge')?.remove();
      });
    }catch(e){
      // A leitura sera sincronizada novamente na proxima atualizacao.
    }finally{
      card.dataset.reading='0';
    }
  }
  async function syncCrmChat(card){
    if(!card || card.dataset.syncing==='1') return;
    const url=card.dataset.syncUrl || '';
    const thread=card.querySelector('.crm-chat-thread');
    if(!url || !thread) return;
    card.dataset.syncing='1';
    try{
      const after=chatLastMessageId(thread, 'data-crm-chat-message-id');
      const response=await fetch(url+'?after_id='+encodeURIComponent(after),{
        headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},
        credentials:'same-origin'
      });
      const data=await response.json();
      if(!response.ok || !data.ok) return;
      const novas=data.mensagens || [];
      const temNovaDoCliente=novas.some(msg=>crmNormalizarRemetente(msg.remetente)==='cliente');
      novas.forEach(msg=>crmAppendMessage(thread, msg));
      crmApplyStatus(card, data.status, data.status_label, data.atendente_nome);
      if(card.dataset.crmSoundReady==='1' && temNovaDoCliente) crmPlayNotification();
      card.dataset.crmSoundReady='1';
      if(temNovaDoCliente) crmMarkRead(card);
    }catch(e){
      // Falha silenciosa para nao travar o atendimento.
    }finally{
      card.dataset.syncing='0';
    }
  }
  async function syncCrmChatList(list){
    if(!list || list.dataset.syncing==='1') return;
    const url=list.dataset.syncUrl || '';
    if(!url) return;
    list.dataset.syncing='1';
    try{
      const response=await fetch(url,{
        headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},
        credentials:'same-origin'
      });
      const data=await response.json();
      if(!response.ok || !data.ok) return;
      for(const atendimento of (data.atendimentos || [])){
        const card=list.querySelector('[data-crm-chat="'+atendimento.id+'"]');
        if(!card) continue;
        const thread=card.querySelector('.crm-chat-thread');
        if(thread && Number(atendimento.ultima_mensagem_id || 0) > chatLastMessageId(thread, 'data-crm-chat-message-id')) syncCrmChat(card);
      }
    }catch(e){
      // Mantem a tela atual se houver oscilacao de rede.
    }finally{
      list.dataset.syncing='0';
    }
  }
  function crmInboxItemHtml(item, selectedId, selectedClientId){
    const selected=Number(item.id)===Number(selectedId) || Number(item.cliente_id)===Number(selectedClientId) ? ' is-selected' : '';
    const unread=item.nao_lido ? ' has-unread' : '';
    const high=item.prioridade==='alta' ? '<b>Alta prioridade</b>' : '';
    const unreadCount=Math.max(0, Number(item.nao_lidos || 0));
    const total=Math.max(1, Number(item.total_atendimentos || 1));
    const openCount=Math.max(0, Number(item.atendimentos_abertos || 0));
    const history=total+' '+(total===1 ? 'atendimento' : 'atendimentos')+' no histórico'+(openCount>1 ? ' · '+openCount+' assuntos abertos' : '');
    const badge=unreadCount>0 ? '<span class="crm-unread-badge" aria-label="'+unreadCount+' mensagens não lidas">'+unreadCount+'</span>' : '';
    const search=chatEscape(((item.cliente_nome || '')+' '+(item.assunto_label || '')+' '+(item.assuntos_busca || '')+' '+(item.ultima_mensagem || '')).toLowerCase());
    return '<a href="'+chatEscape(item.url || '#')+'" class="crm-conversation-item'+selected+unread+'" data-crm-inbox-item data-status="'+chatEscape(item.status || '')+'" data-search="'+search+'" data-cliente-id="'+Number(item.cliente_id || 0)+'" data-atendimento-id="'+Number(item.id || 0)+'">'
      +'<span class="crm-contact-avatar">'+chatEscape(item.iniciais || 'CL')+'</span>'
      +'<span class="crm-conversation-content"><span class="crm-conversation-title"><strong>'+chatEscape(item.cliente_nome || 'Cliente')+'</strong><time>'+chatEscape(item.ultima_hora || '')+'</time></span>'
      +'<span class="crm-conversation-preview">'+chatEscape(item.ultima_mensagem || '')+'</span>'
      +'<span class="crm-conversation-meta"><em class="crm-status-dot '+crmStatusClass(item.status)+'">'+chatEscape(item.status_label || item.status || '')+'</em><small>'+chatEscape(item.assunto_label || '')+'</small>'+high+'</span>'
      +'<span class="crm-conversation-history-summary">'+chatEscape(history)+'</span></span>'+badge+'</a>';
  }
  function crmApplyInboxFilters(scope){
    if(!scope) return;
    const input=scope.querySelector('[data-crm-inbox-search]');
    const active=scope.querySelector('[data-crm-inbox-filter].is-active');
    const term=(input?.value || '').trim().toLowerCase();
    const filter=active?.dataset.crmInboxFilter || 'all';
    scope.querySelectorAll('[data-crm-inbox-item]').forEach(item=>{
      const matchesSearch=!term || (item.dataset.search || '').includes(term);
      const status=item.dataset.status || '';
      const matchesFilter=filter==='all'
        || (filter==='attention' && ['novo','aguardando_humano'].includes(status))
        || status===filter;
      item.hidden=!(matchesSearch && matchesFilter);
    });
  }
  function crmRenderInbox(inbox, data){
    if(!inbox || !Array.isArray(data.atendimentos)) return;
    const selectedId=inbox.dataset.selectedId || '';
    const selectedClientId=inbox.dataset.selectedClientId || '';
    inbox.innerHTML=data.atendimentos.length
      ? data.atendimentos.map(item=>crmInboxItemHtml(item, selectedId, selectedClientId)).join('')
      : '<div class="crm-empty-state compact"><strong>Nenhuma conversa</strong><p>Cada cliente aparecerá uma única vez; os chats antigos ficam no histórico.</p></div>';
    inbox.dataset.signature=data.assinatura || '';
    const scope=inbox.closest('.crm-conversation-sidebar');
    const counter=scope?.querySelector('.crm-queue-count');
    if(counter) counter.textContent=String(data.atendimentos.length);
    Object.entries(data.metricas || {}).forEach(([key,value])=>{
      document.querySelectorAll('[data-crm-metric="'+key+'"]').forEach(el=>el.textContent=String(value));
    });
    crmApplyInboxFilters(scope);
  }
  function crmInboxUnreadTotal(data){
    return (data?.atendimentos || []).reduce((total,item)=>total+Math.max(0, Number(item.nao_lidos || 0)),0);
  }
  async function syncCrmPortalInbox(inbox){
    if(!inbox || inbox.dataset.syncing==='1') return;
    const url=inbox.dataset.syncUrl || '';
    if(!url) return;
    inbox.dataset.syncing='1';
    try{
      const response=await fetch(url,{
        headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},
        credentials:'same-origin'
      });
      const data=await response.json();
      if(!response.ok || !data.ok) return;
      const unreadTotal=crmInboxUnreadTotal(data);
      const previousUnread=Number(inbox.dataset.unreadTotal || unreadTotal);
      if((data.assinatura || '') !== (inbox.dataset.signature || '')) crmRenderInbox(inbox, data);
      else Object.entries(data.metricas || {}).forEach(([key,value])=>document.querySelectorAll('[data-crm-metric="'+key+'"]').forEach(el=>el.textContent=String(value)));
      if(inbox.dataset.crmSoundReady==='1' && unreadTotal>previousUnread) crmPlayNotification();
      inbox.dataset.unreadTotal=String(unreadTotal);
      inbox.dataset.crmSoundReady='1';
    }catch(e){
      // Mantem a tela aberta se houver oscilacao.
    }finally{
      inbox.dataset.syncing='0';
    }
  }
  function resetChatConversation(popup){
    if(!popup) return;
    const thread=popup.querySelector('[data-chat-thread]');
    const typing=popup.querySelector('[data-chat-typing]');
    const options=popup.querySelector('[data-chat-options]');
    const form=popup.querySelector('[data-chat-form]');
    const atendimento=form?.querySelector('[data-chat-atendimento]');
    const subject=form?.querySelector('#chatSubject');
    const mode=form?.querySelector('[data-chat-mode]');
    const textarea=form?.querySelector('textarea[name="mensagem"]');
    const greeting=popup.dataset.chatGreeting || 'Ola. Como posso ajudar?';
    if(atendimento) atendimento.value='';
    if(subject) subject.value='';
    if(mode) mode.value='';
    if(textarea) textarea.value='';
    popup.querySelectorAll('[data-chat-subject]').forEach(item=>item.classList.remove('is-selected'));
    if(options) options.classList.remove('is-hidden');
    popup.dataset.chatHuman='0';
    popup.dataset.chatShowOptions='1';
    chatSetStatus(popup, 'Online');
    if(typing) typing.style.display='none';
    if(thread){
      thread.innerHTML='';
      chatAppend(thread, 'bot', greeting, '');
      if(typing) thread.appendChild(typing);
      if(options) thread.appendChild(options);
      thread.scrollTop=0;
    }
  }
  async function submitChatForm(form){
    if(!form || form.dataset.sending==='1') return;
    const popup=form.closest('.client-chat-popup');
    const thread=popup ? popup.querySelector('[data-chat-thread]') : null;
    const typing=popup ? popup.querySelector('[data-chat-typing]') : null;
    const textarea=form.querySelector('textarea[name="mensagem"]');
    const subject=form.querySelector('#chatSubject');
    const submitButton=form.querySelector('button[type="submit"]');
    const message=(textarea?.value || '').trim();
    if(message && subject) subject.value='';
    const assunto=(subject?.value || '').trim();
    const modeValue=(form.querySelector('[data-chat-mode]')?.value || '').trim();
    if(!message && !assunto && modeValue!=='continuar') return;

    const clientToken=form.dataset.retryToken || chatCreateClientToken();
    delete form.dataset.retryToken;
    const localTime=new Date().toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'});
    const previousError=chatRowByClientToken(thread, 'error-'+clientToken);
    if(previousError) previousError.remove();
    const pendingRow=message ? chatAppend(thread, 'cliente', message, localTime, null, new Date().toISOString(), clientToken, true) : null;
    const payload=new FormData(form);
    payload.set('client_token', clientToken);

    form.dataset.sending='1';
    if(submitButton) submitButton.disabled=true;
    if(popup) stopClientChatSync(popup);
    if(textarea){
      textarea.value='';
      textarea.style.height='';
      textarea.placeholder='Digite sua mensagem...';
    }
    if(typing){
      if(thread) thread.appendChild(typing);
      typing.style.display='flex';
      if(thread) thread.scrollTop=thread.scrollHeight;
    }

    try{
      const response=await fetch(form.action,{
        method:'POST',
        headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},
        body:payload,
        credentials:'same-origin'
      });
      let data={};
      try{
        data=await response.json();
      }catch(parseError){
        throw new Error(response.status === 419 ? 'Sua sessao expirou. Recarregue a pagina e entre novamente.' : 'Resposta invalida do servidor.');
      }
      if(!response.ok || !data.ok){
        const validationMessage=data.errors ? Object.values(data.errors).flat().shift() : null;
        throw new Error(data.message || validationMessage || 'Falha ao enviar');
      }

      const previousError=chatRowByClientToken(thread, 'error-'+clientToken);
      if(previousError) previousError.remove();
      const atendimento=form.querySelector('[data-chat-atendimento]');
      if(atendimento) atendimento.value=data.atendimento_id || '';
      if(subject) subject.value=data.assunto || subject.value;
      const mode=form.querySelector('[data-chat-mode]');
      if(mode) mode.value='';
      if(popup){
        popup.dataset.chatHuman=data.humano ? '1' : '0';
        popup.dataset.chatShowOptions=data.mostrar_opcoes && !data.humano ? '1' : '0';
        chatSetStatus(popup, data.humano ? 'Aguardando a loja' : (data.encerrado ? 'Atendimento encerrado' : 'Online'));
      }

      if(typing) typing.style.display='none';
      (data.mensagens || []).forEach(msg=>{
        chatAppend(thread, msg.remetente, msg.mensagem, msg.hora, msg.id, msg.criado_em, msg.client_token, false, msg.remetente_nome);
      });

      const options=popup?.querySelector('[data-chat-options]');
      if(data.encerrado){
        if(popup){
          expireClientChat(popup);
          showChatOptions(popup);
        }
      }else if(data.mostrar_opcoes && !data.humano){
        if(popup) showChatOptions(popup);
      }else{
        if(options) options.classList.add('is-hidden');
        if(popup) popup.dataset.chatShowOptions='0';
      }
    }catch(e){
      if(typing) typing.style.display='none';
      if(pendingRow && pendingRow.parentNode && !pendingRow.hasAttribute('data-chat-message-id')) pendingRow.remove();
      form.dataset.retryToken=clientToken;
      const errorRow=chatAppend(thread, 'bot', e.message || 'Nao consegui enviar agora. Tente novamente em instantes.', '', null, null, 'error-'+clientToken, false);
      if(errorRow) errorRow.classList.add('chat-error');
      if(textarea && message) textarea.value=message;
    }finally{
      form.dataset.sending='0';
      if(submitButton) submitButton.disabled=false;
      const atendimento=form.querySelector('[data-chat-atendimento]')?.value || '';
      if(popup && atendimento && popup.classList.contains('is-open')) startClientChatSync(popup);
      if(textarea) textarea.focus();
    }
  }
  function billingMatchesFilter(row, filter){
    const status=(row.dataset.status || '').toLowerCase();
    const due=row.dataset.due || '';
    const pix=row.dataset.pix || 'no';
    const whatsapp=(row.dataset.whatsapp || '').toLowerCase();
    const telegram=(row.dataset.telegram || '').toLowerCase();
    if(filter==='overdue') return due==='overdue';
    if(filter==='today') return due==='today';
    if(filter==='open') return ['aberta','parcial','pendente','atrasada'].includes(status);
    if(filter==='without-pix') return pix==='no';
    if(filter==='with-pix') return pix==='yes';
    if(filter==='whatsapp-sent') return ['enviado','sucesso','sent','ok'].includes(whatsapp);
    if(filter==='telegram-sent') return ['enviado','sucesso','sent','ok'].includes(telegram);
    return true;
  }
  function applyBillingFilters(scope){
    if(!scope) return;
    const panel=scope.closest('.panel') || document;
    const term=(scope.querySelector('[data-billing-search]')?.value || '').trim().toLowerCase();
    const filter=scope.querySelector('[data-billing-filter].is-active')?.dataset.billingFilter || 'all';
    let visible=0;
    panel.querySelectorAll('[data-billing-row]').forEach(row=>{
      const matchesSearch=!term || (row.dataset.search || '').includes(term);
      const matchesFilter=billingMatchesFilter(row, filter);
      const show=matchesSearch && matchesFilter;
      row.hidden=!show;
      if(show) visible++;
    });
    panel.querySelectorAll('[data-billing-visible-count]').forEach(el=>el.textContent=String(visible));
    panel.querySelectorAll('[data-billing-empty]').forEach(row=>row.hidden=visible!==0);
  }
  document.addEventListener('DOMContentLoaded', function(){
    ['pointerdown','keydown','touchstart'].forEach(eventName=>{
      document.addEventListener(eventName, crmUnlockSound, {once:true,passive:true});
    });
    const s=getSidebar(); if(s) s.id = s.id || 'sidebarMenu';
    document.querySelectorAll('.mobile-menu-toggle,.hamburger,#menuToggle,[data-menu-toggle]').forEach(btn=>{
      btn.setAttribute('type','button');
      btn.addEventListener('click', function(e){e.preventDefault();e.stopPropagation();openMenu();}, false);
    });
    document.querySelectorAll('.mobile-menu-close,.mobile-menu-overlay,[data-menu-close]').forEach(btn=>{
      btn.addEventListener('click', function(e){e.preventDefault();closeMenu();}, false);
    });
    document.querySelectorAll('.sidebar .menu-group').forEach(group=>{
      group.addEventListener('toggle', function(){
        if(!group.open) return;
        document.querySelectorAll('.sidebar .menu-group[open]').forEach(other=>{
          if(other !== group) other.open = false;
        });
      });
    });
    document.querySelectorAll('.sidebar .menu a').forEach(a=>a.addEventListener('click',()=>{ if(window.innerWidth<=768) closeMenu(); }));
    document.querySelectorAll('[data-billing-tools]').forEach(scope=>{
      applyBillingFilters(scope);
      scope.querySelectorAll('[data-billing-search]').forEach(input=>{
        input.addEventListener('input', ()=>applyBillingFilters(scope));
      });
      scope.querySelectorAll('[data-billing-filter]').forEach(button=>{
        button.addEventListener('click', ()=>{
          scope.querySelectorAll('[data-billing-filter]').forEach(item=>item.classList.toggle('is-active', item===button));
          applyBillingFilters(scope);
        });
      });
    });
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
    document.querySelectorAll('[data-chat-subject]').forEach(btn=>{
      btn.addEventListener('click', function(){
        const popup=this.closest('.client-chat-popup');
        const form=popup ? popup.querySelector('[data-chat-form]') : document.querySelector('[data-chat-form]');
        const subject=form ? form.querySelector('input[name="assunto"]') : null;
        if(!form || !subject) return;
        subject.value=this.dataset.chatSubject || '';
        const mode=form.querySelector('[data-chat-mode]');
        if(mode && !form.querySelector('[data-chat-atendimento]')?.value) mode.value=mode.value || 'novo';
        if(popup) popup.querySelectorAll('[data-chat-subject]').forEach(item=>item.classList.remove('is-selected'));
        this.classList.add('is-selected');
        const options=this.closest('[data-chat-options]');
        if(options) options.classList.add('is-hidden');
        if(popup) popup.dataset.chatShowOptions='0';
        const textarea=form ? form.querySelector('textarea[name="mensagem"]') : document.querySelector('.chat-form textarea[name="mensagem"]');
        if(textarea && !textarea.value.trim()) textarea.value='';
        submitChatForm(form);
      });
    });
    document.querySelectorAll('[data-chat-resume-action]').forEach(button=>{
      button.addEventListener('click', function(){
        const popup=this.closest('.client-chat-popup');
        const form=popup?.querySelector('[data-chat-form]');
        if(!popup || !form) return;
        const action=this.dataset.chatResumeAction || '';
        const atendimento=form.querySelector('[data-chat-atendimento]');
        const mode=form.querySelector('[data-chat-mode]');
        const subject=form.querySelector('#chatSubject');
        const resumeCard=popup.querySelector('[data-chat-resume-card]');
        const textarea=form.querySelector('textarea[name="mensagem"]');
        if(action==='continuar'){
          if(atendimento) atendimento.value=this.dataset.atendimentoId || '';
          if(mode) mode.value='continuar';
          if(subject) subject.value='';
          if(textarea) textarea.value='';
          if(resumeCard) resumeCard.classList.add('is-hidden');
          submitChatForm(form);
          return;
        }
        if(action==='novo'){
          if(atendimento) atendimento.value='';
          if(mode) mode.value='novo';
          if(subject) subject.value='';
          if(resumeCard) resumeCard.classList.add('is-hidden');
          popup.dataset.chatShowOptions='1';
          showChatOptions(popup);
          textarea?.focus();
        }
      });
    });
    document.querySelectorAll('[data-chat-form]').forEach(form=>{
      const textarea=form.querySelector('textarea[name="mensagem"]');
      const subject=form.querySelector('#chatSubject');
      if(textarea && subject){
        textarea.addEventListener('input', function(){
          if(this.value.trim()) subject.value='';
          delete form.dataset.retryToken;
          this.style.height='auto';
          this.style.height=Math.min(this.scrollHeight,120)+'px';
        });
        textarea.addEventListener('keydown', function(e){
          if(e.key==='Enter' && !e.shiftKey){
            e.preventDefault();
            submitChatForm(form);
          }
        });
      }
      form.addEventListener('submit', function(e){
        e.preventDefault();
        submitChatForm(form);
      });
    });
    document.querySelectorAll('[data-chat-end]').forEach(btn=>{
      btn.addEventListener('click', async function(){
        const popup=this.closest('.client-chat-popup');
        const form=popup?.querySelector('[data-chat-form]');
        if(!popup || !form) return;
        const atendimento=form.querySelector('[data-chat-atendimento]')?.value || '';
        const url=popup.dataset.chatCloseUrl || '';
        btn.disabled=true;
        try{
          if(url){
            const fd=new FormData();
            const token=form.querySelector('input[name="_token"]')?.value || '';
            if(token) fd.append('_token', token);
            if(atendimento) fd.append('atendimento_id', atendimento);
            await fetch(url,{
              method:'POST',
              headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},
              body:fd,
              credentials:'same-origin'
            });
          }
          stopClientChatSync(popup);
          resetChatConversation(popup);
          popup.classList.remove('is-open');
          document.querySelectorAll('[data-chat-toggle][aria-controls="'+popup.id+'"]').forEach(toggle=>toggle.setAttribute('aria-expanded','false'));
        }catch(e){
          chatAppend(popup.querySelector('[data-chat-thread]'), 'bot', 'Nao consegui encerrar agora. Tente novamente.', '');
        }finally{
          btn.disabled=false;
        }
      });
    });
    document.querySelectorAll('[data-chat-toggle]').forEach(btn=>{
      btn.addEventListener('click', function(){
        const popup=document.getElementById(this.getAttribute('aria-controls') || 'clientChatPopup');
        if(!popup) return;
        const isOpen=popup.classList.toggle('is-open');
        this.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        if(isOpen){
          startChatIntro(popup);
          startClientChatSync(popup);
        }else{
          stopClientChatSync(popup);
        }
      });
    });
    document.querySelectorAll('[data-chat-close]').forEach(btn=>{
      btn.addEventListener('click', function(){
        const popup=this.closest('.client-chat-popup');
        if(!popup) return;
        popup.classList.remove('is-open');
        stopClientChatSync(popup);
        document.querySelectorAll('[data-chat-toggle][aria-controls="'+popup.id+'"]').forEach(toggle=>toggle.setAttribute('aria-expanded','false'));
      });
    });
    document.querySelectorAll('.client-chat-popup').forEach(popup=>{
      const firstBubble=popup.querySelector('.chat-row.bot .chat-bubble');
      if(firstBubble && !popup.dataset.chatGreeting) popup.dataset.chatGreeting=firstBubble.childNodes[0]?.textContent?.trim() || firstBubble.textContent.trim();
      if(popup.classList.contains('is-open')){
        startChatIntro(popup);
        startClientChatSync(popup);
      }
    });
    document.querySelectorAll('[data-crm-chat]').forEach(card=>{
      const thread=card.querySelector('.crm-chat-thread');
      if(thread){
        const scrollToLatest=()=>window.requestAnimationFrame(()=>{
          thread.scrollTop=thread.scrollHeight;
          thread.dataset.crmPinnedBottom='1';
        });
        scrollToLatest();
        window.setTimeout(scrollToLatest,80);
        thread.addEventListener('scroll',()=>{
          const distance=thread.scrollHeight-thread.scrollTop-thread.clientHeight;
          thread.dataset.crmPinnedBottom=distance<90 ? '1' : '0';
        },{passive:true});
        window.addEventListener('resize',()=>{
          if(thread.dataset.crmPinnedBottom==='1') scrollToLatest();
        },{passive:true});
      }
      crmMarkRead(card);
      syncCrmChat(card);
      card._crmSyncTimer=window.setInterval(()=>syncCrmChat(card),3000);
    });
    const focusedCrmChat=document.querySelector('#crmAtendimentoAtual [data-crm-chat]');
    if(focusedCrmChat && (new URLSearchParams(window.location.search).has('atendimento') || window.location.hash==='#crmAtendimentoAtual')){
      window.setTimeout(()=>{
        const target=document.getElementById('crmAtendimentoAtual');
        target?.scrollIntoView({block:'start',behavior:'auto'});
        target?.focus({preventScroll:true});
      },60);
    }
    document.querySelectorAll('[data-crm-chat-list]').forEach(list=>{
      syncCrmChatList(list);
      list._crmListSyncTimer=window.setInterval(()=>syncCrmChatList(list),3000);
    });
    document.querySelectorAll('[data-crm-portal-inbox]').forEach(inbox=>{
      syncCrmPortalInbox(inbox);
      inbox._crmInboxSyncTimer=window.setInterval(()=>syncCrmPortalInbox(inbox),3000);
    });
    document.querySelectorAll('[data-crm-inbox-search]').forEach(input=>{
      input.addEventListener('input', ()=>crmApplyInboxFilters(input.closest('.crm-conversation-sidebar')));
    });
    document.querySelectorAll('[data-crm-inbox-filter]').forEach(button=>{
      button.addEventListener('click', ()=>{
        const scope=button.closest('.crm-conversation-sidebar');
        scope?.querySelectorAll('[data-crm-inbox-filter]').forEach(item=>item.classList.toggle('is-active', item===button));
        crmApplyInboxFilters(scope);
      });
    });
    document.querySelectorAll('[data-crm-quick-reply]').forEach(button=>{
      button.addEventListener('click', ()=>{
        const card=button.closest('[data-crm-chat]');
        const textarea=card?.querySelector('[data-crm-chat-reply] textarea[name="mensagem"]');
        if(!textarea) return;
        const text=button.dataset.crmQuickReply || '';
        textarea.value=textarea.value.trim() ? textarea.value.trim()+' '+text : text;
        textarea.dispatchEvent(new Event('input',{bubbles:true}));
        textarea.focus();
      });
    });
    document.querySelectorAll('[data-crm-action]').forEach(button=>{
      button.addEventListener('click', async ()=>{
        const card=button.closest('[data-crm-chat]');
        if(!card || !card.dataset.actionUrl || button.dataset.loading==='1') return;
        const desk=card.closest('[data-crm-desk]');
        button.dataset.loading='1';
        button.disabled=true;
        const original=button.textContent;
        button.textContent='Atualizando...';
        const body=new FormData();
        body.append('acao',button.dataset.crmAction || '');
        body.append('_token',desk?.dataset.csrf || '');
        try{
          const response=await fetch(card.dataset.actionUrl,{
            method:'POST',
            headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},
            body,
            credentials:'same-origin'
          });
          const data=await response.json();
          if(!response.ok || !data.ok) throw new Error(data.message || 'Falha ao atualizar');
          crmApplyStatus(card,data.status,data.status_label,data.atendente_nome);
          document.querySelectorAll('[data-crm-portal-inbox]').forEach(inbox=>syncCrmPortalInbox(inbox));
          if(['resolver','reabrir'].includes(button.dataset.crmAction || '')) window.location.reload();
          if(button.dataset.crmAction==='assumir') button.remove();
        }catch(e){
          alert(e.message || 'Não consegui atualizar o atendimento agora. Tente novamente.');
        }finally{
          if(button.isConnected){
            button.dataset.loading='0';
            button.disabled=false;
            button.textContent=original;
          }
        }
      });
    });
    document.querySelectorAll('[data-crm-chat-reply]').forEach(form=>{
      const textarea=form.querySelector('textarea[name="mensagem"]');
      const counter=form.querySelector('[data-crm-char-count]') || form.closest('.crm-composer')?.querySelector('[data-crm-char-count]');
      const updateCounter=()=>{ if(counter && textarea) counter.textContent=String(textarea.value.length)+'/2000'; };
      textarea?.addEventListener('input', updateCounter);
      textarea?.addEventListener('keydown', e=>{
        if(e.key==='Enter' && !e.shiftKey){
          e.preventDefault();
          form.requestSubmit();
        }
      });
      updateCounter();
      form.addEventListener('submit', async function(e){
        e.preventDefault();
        if(form.dataset.sending==='1') return;
        const card=form.closest('[data-crm-chat]');
        const thread=card?.querySelector('.crm-chat-thread');
        const message=(textarea?.value || '').trim();
        if(!message) return;
        form.dataset.sending='1';
        const button=form.querySelector('button[type="submit"]');
        const original=button?.innerHTML || '';
        if(button){ button.disabled=true; button.innerHTML='<span>Enviando...</span>'; }
        try{
          const response=await fetch(form.action,{
            method:'POST',
            headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},
            body:new FormData(form),
            credentials:'same-origin'
          });
          const data=await response.json();
          if(!response.ok || !data.ok) throw new Error(data.message || 'Falha ao enviar');
          if(textarea){ textarea.value=''; updateCounter(); textarea.focus(); }
          (data.mensagens || []).forEach(msg=>crmAppendMessage(thread, msg));
          crmApplyStatus(card,data.status,data.status_label,data.atendente_nome);
          document.querySelectorAll('[data-crm-portal-inbox]').forEach(inbox=>syncCrmPortalInbox(inbox));
        }catch(e){
          alert('Não consegui enviar a resposta agora. Tente novamente.');
        }finally{
          form.dataset.sending='0';
          if(button){ button.disabled=false; button.innerHTML=original; }
        }
      });
    });
    document.querySelectorAll('[data-contract-open]').forEach(btn=>{
      btn.addEventListener('click', function(){
        const modal=document.getElementById(this.dataset.contractOpen);
        if(!modal) return;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden','false');
        document.body.classList.add('contract-open');
      });
    });
    document.querySelectorAll('[data-contract-close]').forEach(btn=>{
      btn.addEventListener('click', function(){
        const modal=this.closest('.contract-modal');
        if(!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden','true');
        document.body.classList.remove('contract-open');
      });
    });
    document.querySelectorAll('[data-contract-print]').forEach(btn=>{
      btn.addEventListener('click', function(){
        const doc=document.getElementById(this.dataset.contractPrint);
        if(!doc) return;
        const win=window.open('', '_blank', 'width=900,height=900');
        if(!win) return;
        win.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>Contrato de Locação</title><style>
          body{font-family:Arial,Helvetica,sans-serif;color:#111827;margin:0;padding:32px;line-height:1.5}
          .contract-brand{margin-bottom:18px}.contract-brand img{width:210px;height:auto}
          h1{margin:0 0 8px;font-size:22px;text-align:center;letter-spacing:0}
          h2{margin:24px 0 12px;font-size:16px;color:#111827}
          h3{margin:0 0 24px;text-align:center;font-size:15px}
          p{margin:0 0 10px}.contract-date{margin-top:28px!important}
          .contract-signatures{display:grid;grid-template-columns:1fr 1fr;gap:48px;margin-top:64px;text-align:center}
          .contract-signatures span{display:block;border-top:1px solid #111827;margin-bottom:8px}
          @page{margin:18mm}
        </style></head><body>${doc.innerHTML}</body></html>`);
        win.document.close();
        win.focus();
        setTimeout(()=>win.print(),250);
      });
    });
  });
  document.addEventListener('click', function(e){
    if(e.target.closest('.mobile-menu-toggle,.hamburger,#menuToggle,[data-menu-toggle]')){e.preventDefault();openMenu();}
    if(e.target.closest('.mobile-menu-close,.mobile-menu-overlay,[data-menu-close]')){e.preventDefault();closeMenu();}
  }, true);
  document.addEventListener('keydown', e=>{ if(e.key==='Escape') closeMenu(); });
  document.addEventListener('keydown', e=>{
    if(e.key!=='Escape') return;
    document.querySelectorAll('.contract-modal.is-open').forEach(modal=>{
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden','true');
    });
    document.body.classList.remove('contract-open');
  });
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
