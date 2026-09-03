(()=>{
  const oldFab=document.querySelector('.chat-fab');
  const oldChat=document.querySelector('.chat');
  oldFab?.remove(); oldChat?.remove();
  if(!document.querySelector('link[href^="ai-chat.css"]')){
    const css=document.createElement('link'); css.rel='stylesheet'; css.href='ai-chat.css?v=20260903-ai4'; document.head.appendChild(css);
  }

  const cfg=window.GT_AI_CONFIG||{};
  const chatEndpoint=cfg.chatEndpoint||cfg.endpoint||'';
  const pollEndpoint=cfg.pollEndpoint||'';
  const readEndpoint=cfg.readEndpoint||'';
  const pollMs=Math.max(5000,Number(cfg.pollIntervalMs)||10000);
  const pollMaxMs=Math.max(60000,Number(cfg.pollMaxMs)||180000);

  const getStable=(key,prefix)=>{
    let value=localStorage.getItem(key);
    if(value) return value;
    const rand=crypto.getRandomValues(new Uint32Array(3)).join('');
    value=`${prefix}_${Date.now()}_${rand}`;
    localStorage.setItem(key,value);
    return value;
  };
  const visitorId=getStable('gt_ai_visitor_id','gtv');
  let conversationId=localStorage.getItem('gt_ai_conversation_id')||getStable('gt_ai_conversation_id','gtc');
  let lastMessageId=localStorage.getItem('gt_ai_last_message_id')||'';
  let history=[];
  let pollTimer=null,pollStartedAt=0,pollBusy=false,pollFailures=0;

  const root=document.createElement('div');
  root.innerHTML=`<button class="gt-ai-launcher" aria-label="KI Assistent öffnen"><span class="gt-ai-orb">AI</span><span class="gt-ai-launcher-copy"><b>Temperli KI-Assistent</b><small><span class="gt-ai-dot"></span> Online · 24/7</small></span></button><section class="gt-ai-panel" aria-live="polite"><header class="gt-ai-head"><span class="gt-ai-orb">AI</span><div class="gt-ai-title"><strong>Temperli KI-Assistent</strong><span><span class="gt-ai-dot"></span> Service, Pneus & Termine</span></div><button class="gt-ai-close" aria-label="Schliessen">×</button></header><div class="gt-ai-body"><div class="gt-ai-day">Heute</div><div class="gt-msg"><span class="gt-msg-avatar">AI</span><div class="gt-msg-bubble">Grüezi 👋 Ich helfe Ihnen bei Service, Reparaturen, Pneus und Terminanfragen. Was möchten Sie wissen?</div></div><div class="gt-ai-quick"><button data-booking="1">📅 Termin anfragen</button><button data-q="Ich brauche neue Pneus">🛞 Pneus</button><button data-q="Was kostet ein Service?">🔧 Service</button><button data-q="Wann habt ihr geöffnet?">🕒 Öffnungszeiten</button></div><section class="gt-booking" aria-label="Terminanfrage"><div class="gt-booking-head"><div><small>Wunschtermin</small><strong>Wann passt es Ihnen?</strong></div><button class="gt-booking-close" type="button">×</button></div><label class="gt-booking-label">Leistung<select class="gt-booking-service"><option>Service & Wartung</option><option>Pneus & Räder</option><option>Reparatur / Diagnose</option><option>Klimaservice</option><option>Andere Anfrage</option></select></label><label class="gt-booking-label">Datum<input class="gt-booking-date" type="date"></label><div class="gt-booking-label">Wunschzeit<div class="gt-booking-slots"><button type="button" data-time="07:30">07:30</button><button type="button" data-time="08:30">08:30</button><button type="button" data-time="09:30">09:30</button><button type="button" data-time="10:30">10:30</button><button type="button" data-time="13:00">13:00</button><button type="button" data-time="14:00">14:00</button><button type="button" data-time="15:00">15:00</button><button type="button" data-time="16:00">16:00</button></div></div><p class="gt-booking-note">Wir prüfen Ihren Wunsch und bestätigen den Termin anschliessend.</p><button class="gt-booking-confirm" type="button">Terminwunsch senden →</button></section><div class="gt-ai-typing"><i></i><i></i><i></i></div><div class="gt-ai-error"></div></div><footer class="gt-ai-foot"><form class="gt-ai-form"><textarea rows="1" maxlength="800" placeholder="Nachricht schreiben…" aria-label="Nachricht"></textarea><button class="gt-ai-send" type="submit">➤</button></form><div class="gt-ai-meta"><span>Ihre Anfrage wird vertraulich behandelt.</span><strong>Powered by AutomationAI</strong></div></footer></section>`;
  document.body.append(...root.childNodes);

  const launcher=document.querySelector('.gt-ai-launcher'),panel=document.querySelector('.gt-ai-panel'),close=document.querySelector('.gt-ai-close'),body=document.querySelector('.gt-ai-body'),form=document.querySelector('.gt-ai-form'),input=form.querySelector('textarea'),typing=document.querySelector('.gt-ai-typing'),err=document.querySelector('.gt-ai-error'),booking=document.querySelector('.gt-booking'),bookingClose=document.querySelector('.gt-booking-close'),bookingDate=document.querySelector('.gt-booking-date'),bookingService=document.querySelector('.gt-booking-service'),bookingConfirm=document.querySelector('.gt-booking-confirm');
  let selectedTime='';

  const localToday=()=>{const d=new Date(),y=d.getFullYear(),m=String(d.getMonth()+1).padStart(2,'0'),day=String(d.getDate()).padStart(2,'0');return `${y}-${m}-${day}`};
  bookingDate.min=localToday();
  const bubble=(text,user=false)=>{if(!text)return;const row=document.createElement('div');row.className='gt-msg'+(user?' user':'');if(!user){const av=document.createElement('span');av.className='gt-msg-avatar';av.textContent='AI';row.appendChild(av)}const b=document.createElement('div');b.className='gt-msg-bubble';b.textContent=String(text);row.appendChild(b);body.insertBefore(row,typing);body.scrollTop=body.scrollHeight};
  const setTyping=v=>{typing.classList.toggle('show',v);body.scrollTop=body.scrollHeight};
  const showError=t=>{err.textContent=t;err.classList.add('show');body.scrollTop=body.scrollHeight};
  const remember=(role,text)=>{history.push({role,content:String(text).slice(0,800)});history=history.slice(-10)};
  const persistIds=data=>{
    const c=data?.conversationId||data?.conversation_id;
    const m=data?.messageId||data?.message_id;
    if(c){conversationId=String(c);localStorage.setItem('gt_ai_conversation_id',conversationId)}
    if(m){lastMessageId=String(m);localStorage.setItem('gt_ai_last_message_id',lastMessageId)}
  };
  const answerFrom=data=>data?.answer||data?.reply||data?.message||data?.output||'';

  async function postJson(url,payload){
    const r=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload),credentials:'same-origin'});
    if(!r.ok) throw new Error(`HTTP ${r.status}`);
    return r.json();
  }

  function stopPolling(){if(pollTimer)clearTimeout(pollTimer);pollTimer=null;pollBusy=false;pollFailures=0}
  function schedulePoll(delay=pollMs){
    if(!pollEndpoint||!panel.classList.contains('open'))return;
    if(!pollStartedAt)pollStartedAt=Date.now();
    if(Date.now()-pollStartedAt>pollMaxMs){stopPolling();return}
    clearTimeout(pollTimer);pollTimer=setTimeout(pollOnce,delay);
  }
  async function acknowledge(messageId){
    if(!readEndpoint||!messageId)return;
    try{await postJson(readEndpoint,{conversationId,visitorId,messageId})}catch(_e){}
  }
  async function pollOnce(){
    if(pollBusy||!pollEndpoint||!panel.classList.contains('open'))return;
    pollBusy=true;
    try{
      const data=await postJson(pollEndpoint,{conversationId,visitorId,messageId:lastMessageId||undefined});
      persistIds(data);
      const items=Array.isArray(data?.messages)?data.messages:[];
      let delivered=false;
      for(const item of items){
        const id=String(item?.messageId||item?.message_id||'');
        const text=item?.answer||item?.reply||item?.message||item?.text||item?.content||'';
        if(!text|| (id&&id===lastMessageId))continue;
        bubble(text);remember('assistant',text);delivered=true;
        if(id){lastMessageId=id;localStorage.setItem('gt_ai_last_message_id',id);await acknowledge(id)}
      }
      if(!items.length){
        const text=answerFrom(data);
        const id=String(data?.messageId||data?.message_id||'');
        if(text && (!id||id!==lastMessageId)){
          bubble(text);remember('assistant',text);delivered=true;
          if(id){lastMessageId=id;localStorage.setItem('gt_ai_last_message_id',id);await acknowledge(id)}
        }
      }
      pollFailures=0;
      schedulePoll(delivered?pollMs:pollMs);
    }catch(_e){
      pollFailures=Math.min(pollFailures+1,4);
      schedulePoll(Math.min(60000,pollMs*Math.pow(2,pollFailures)));
    }finally{pollBusy=false}
  }
  function startPolling(){if(!pollEndpoint)return;pollStartedAt=Date.now();schedulePoll(1200)}

  async function ask(text){
    booking.classList.remove('open');err.classList.remove('show');bubble(text,true);remember('user',text);setTyping(true);
    if(!chatEndpoint){setTimeout(()=>{setTyping(false);bubble('Der KI-Assistent wird gerade verbunden. Für eine direkte Anfrage erreichen Sie uns unter 044 725 43 82.')},350);return}
    try{
      const data=await postJson(chatEndpoint,{message:String(text).slice(0,800),conversationId,visitorId,messageId:lastMessageId||undefined,history});
      persistIds(data);
      const answer=answerFrom(data);
      if(!answer)throw new Error('Keine Antwort');
      setTyping(false);bubble(answer);remember('assistant',answer);startPolling();
    }catch(_e){setTyping(false);showError('Die KI ist gerade nicht erreichbar. Bitte versuchen Sie es erneut oder rufen Sie uns an.')}
  }

  function open(){panel.classList.add('open');setTimeout(()=>input.focus(),120);if(pollEndpoint&&conversationId)startPolling()}
  function shut(){panel.classList.remove('open');booking.classList.remove('open');stopPolling()}
  launcher.onclick=open; close.onclick=shut;
  bookingClose.onclick=()=>booking.classList.remove('open');
  document.querySelector('[data-booking]').onclick=()=>{booking.classList.add('open');if(!bookingDate.value)bookingDate.value=localToday();body.scrollTop=body.scrollHeight};
  document.querySelectorAll('.gt-booking-slots button').forEach(b=>b.onclick=()=>{document.querySelectorAll('.gt-booking-slots button').forEach(x=>x.classList.remove('active'));b.classList.add('active');selectedTime=b.dataset.time});
  bookingConfirm.onclick=()=>{if(!bookingDate.value){showError('Bitte wählen Sie zuerst ein Datum.');return}if(!selectedTime){showError('Bitte wählen Sie eine Wunschzeit.');return}const [y,m,d]=bookingDate.value.split('-');ask(`Ich möchte einen Termin für ${bookingService.value} am ${d}.${m}.${y} um ${selectedTime}. Bitte erfasse meinen Terminwunsch und frage die noch benötigten Angaben ab.`)};
  form.addEventListener('submit',e=>{e.preventDefault();const q=input.value.trim();if(!q)return;input.value='';ask(q)});
  input.addEventListener('keydown',e=>{if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();form.requestSubmit()}});
  document.querySelectorAll('.gt-ai-quick button[data-q]').forEach(b=>b.onclick=()=>ask(b.dataset.q));
  document.addEventListener('visibilitychange',()=>{if(document.hidden)stopPolling();else if(panel.classList.contains('open'))startPolling()});
})();
