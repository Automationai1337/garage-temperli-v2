(()=>{
  const oldFab=document.querySelector('.chat-fab');
  const oldChat=document.querySelector('.chat');
  oldFab?.remove();
  oldChat?.remove();

  if(!document.querySelector('link[href^="ai-chat.css"]')){
    const css=document.createElement('link');
    css.rel='stylesheet';
    css.href='ai-chat.css?v=20260901-ai3';
    document.head.appendChild(css);
  }

  const cfg=window.GT_AI_CONFIG||{};
  const endpoint=cfg.endpoint||'';
  const pollEndpoint=cfg.pollEndpoint||'';
  const readEndpoint=cfg.readEndpoint||'';

  const root=document.createElement('div');
  root.innerHTML=`<button class="gt-ai-launcher" aria-label="KI Assistent öffnen"><span class="gt-ai-orb">AI</span><span class="gt-ai-launcher-copy"><b>Temperli KI-Assistent</b><small><span class="gt-ai-dot"></span> Online · 24/7</small></span></button><section class="gt-ai-panel" aria-live="polite"><header class="gt-ai-head"><span class="gt-ai-orb">AI</span><div class="gt-ai-title"><strong>Temperli KI-Assistent</strong><span><span class="gt-ai-dot"></span> Service, Pneus & Termine</span></div><button class="gt-ai-close" aria-label="Schliessen">×</button></header><div class="gt-ai-body"><div class="gt-ai-day">Heute</div><div class="gt-msg"><span class="gt-msg-avatar">AI</span><div class="gt-msg-bubble">Grüezi 👋 Ich helfe Ihnen bei Service, Reparaturen, Pneus und Terminanfragen. Was möchten Sie wissen?</div></div><div class="gt-ai-quick"><button data-booking="1">📅 Termin buchen</button><button data-q="Ich brauche neue Pneus">🛞 Pneus</button><button data-q="Was kostet ein Service?">🔧 Service</button><button data-q="Wann habt ihr geöffnet?">🕒 Öffnungszeiten</button></div><section class="gt-booking" aria-label="Terminauswahl"><div class="gt-booking-head"><div><small>Termin auswählen</small><strong>Wann dürfen wir Sie einplanen?</strong></div><button class="gt-booking-close" type="button">×</button></div><label class="gt-booking-label">Leistung<select class="gt-booking-service"><option>Service & Wartung</option><option>Pneus & Räder</option><option>Reparatur / Diagnose</option><option>Klimaservice</option><option>Andere Anfrage</option></select></label><label class="gt-booking-label">Datum<input class="gt-booking-date" type="date"></label><div class="gt-booking-label">Wunschzeit<div class="gt-booking-slots"><button type="button" data-time="07:30">07:30</button><button type="button" data-time="08:30">08:30</button><button type="button" data-time="09:30">09:30</button><button type="button" data-time="10:30">10:30</button><button type="button" data-time="13:00">13:00</button><button type="button" data-time="14:00">14:00</button><button type="button" data-time="15:00">15:00</button><button type="button" data-time="16:00">16:00</button></div></div><p class="gt-booking-note">Die tatsächliche Verfügbarkeit wird nach Ihrer Auswahl geprüft.</p><button class="gt-booking-confirm" type="button">Terminwunsch prüfen →</button></section><div class="gt-ai-typing"><i></i><i></i><i></i></div><div class="gt-ai-error"></div></div><footer class="gt-ai-foot"><form class="gt-ai-form"><textarea rows="1" placeholder="Nachricht schreiben…" aria-label="Nachricht"></textarea><button class="gt-ai-send" type="submit">➤</button></form><div class="gt-ai-meta"><span>Ihre Anfrage wird vertraulich behandelt.</span><strong>Powered by AutomationAI</strong></div></footer></section>`;
  document.body.append(...root.childNodes);

  const launcher=document.querySelector('.gt-ai-launcher');
  const panel=document.querySelector('.gt-ai-panel');
  const close=document.querySelector('.gt-ai-close');
  const body=document.querySelector('.gt-ai-body');
  const form=document.querySelector('.gt-ai-form');
  const input=form.querySelector('textarea');
  const typing=document.querySelector('.gt-ai-typing');
  const err=document.querySelector('.gt-ai-error');
  const booking=document.querySelector('.gt-booking');
  const bookingClose=document.querySelector('.gt-booking-close');
  const bookingDate=document.querySelector('.gt-booking-date');
  const bookingService=document.querySelector('.gt-booking-service');
  const bookingConfirm=document.querySelector('.gt-booking-confirm');

  let selectedTime='';
  let pollTimer=null;
  let pollAttemptsLeft=0;
  let pollAttempt=0;
  let handoffActive=localStorage.getItem('gt_ai_handoff')==='1';

  const sid=localStorage.getItem('gt_ai_sid')||(()=>{
    const values=new Uint32Array(2);
    crypto.getRandomValues(values);
    const s='gt_'+Array.from(values).join('');
    localStorage.setItem('gt_ai_sid',s);
    return s;
  })();

  const localToday=()=>{
    const d=new Date();
    const y=d.getFullYear();
    const m=String(d.getMonth()+1).padStart(2,'0');
    const day=String(d.getDate()).padStart(2,'0');
    return `${y}-${m}-${day}`;
  };
  bookingDate.min=localToday();

  function bubble(text,user=false,label='AI'){
    const row=document.createElement('div');
    row.className='gt-msg'+(user?' user':'');
    if(!user){
      const av=document.createElement('span');
      av.className='gt-msg-avatar';
      av.textContent=label==='Team'?'GT':'AI';
      row.appendChild(av);
    }
    const b=document.createElement('div');
    b.className='gt-msg-bubble';
    if(!user&&label==='Team'){
      const tag=document.createElement('strong');
      tag.textContent='Garage Temperli Team';
      tag.style.display='block';
      tag.style.marginBottom='4px';
      b.appendChild(tag);
    }
    b.appendChild(document.createTextNode(text));
    row.appendChild(b);
    body.insertBefore(row,typing);
    body.scrollTop=body.scrollHeight;
  }

  function setTyping(v){
    typing.classList.toggle('show',v);
    body.scrollTop=body.scrollHeight;
  }

  function showError(t){
    err.textContent=t;
    err.classList.add('show');
    body.scrollTop=body.scrollHeight;
  }

  function stopPoll(){
    if(pollTimer){
      clearTimeout(pollTimer);
      pollTimer=null;
    }
    pollAttemptsLeft=0;
    pollAttempt=0;
  }

  async function markRead(ids){
    if(!readEndpoint||!Array.isArray(ids)||!ids.length)return;
    if(!panel.classList.contains('open')||document.visibilityState!=='visible')return;
    try{
      await fetch(readEndpoint,{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({sessionId:sid,ids})
      });
    }catch(_e){}
  }

  function schedulePoll(){
    if(!pollEndpoint||pollAttemptsLeft<=0||!panel.classList.contains('open'))return;
    const delays=[5000,8000,12000,20000,30000];
    const delay=delays[Math.min(pollAttempt,delays.length-1)];
    pollTimer=setTimeout(pollOnce,delay);
  }

  async function pollOnce(){
    pollTimer=null;
    if(!pollEndpoint||pollAttemptsLeft<=0||!panel.classList.contains('open'))return;
    if(document.visibilityState!=='visible'){
      schedulePoll();
      return;
    }
    pollAttemptsLeft--;
    pollAttempt++;
    try{
      const r=await fetch(pollEndpoint,{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({sessionId:sid})
      });
      if(r.ok){
        const data=await r.json();
        const replies=Array.isArray(data.replies)?data.replies:[];
        const ids=Array.isArray(data.replyIds)?data.replyIds:[];
        if(replies.length){
          replies.forEach(text=>bubble(String(text),false,'Team'));
          pollAttempt=0;
          if(handoffActive)pollAttemptsLeft=Math.max(pollAttemptsLeft,12);
          await markRead(ids);
        }
      }
    }catch(_e){}
    schedulePoll();
  }

  function startPoll(longMode=false){
    if(!pollEndpoint)return;
    if(longMode){
      handoffActive=true;
      localStorage.setItem('gt_ai_handoff','1');
      pollAttemptsLeft=Math.max(pollAttemptsLeft,24);
    }else{
      pollAttemptsLeft=Math.max(pollAttemptsLeft,6);
    }
    if(!pollTimer){
      pollAttempt=0;
      pollTimer=setTimeout(pollOnce,2500);
    }
  }

  function open(){
    panel.classList.add('open');
    setTimeout(()=>input.focus(),120);
    if(handoffActive)startPoll(true);
  }

  function shut(){
    panel.classList.remove('open');
    booking.classList.remove('open');
    stopPoll();
  }

  launcher.onclick=open;
  close.onclick=shut;

  async function ask(text){
    booking.classList.remove('open');
    err.classList.remove('show');
    bubble(text,true);
    setTyping(true);
    if(!endpoint){
      setTyping(false);
      showError('Die KI-Verbindung ist noch nicht freigeschaltet. Bitte rufen Sie uns unter 044 725 43 82 an.');
      return;
    }
    try{
      const r=await fetch(endpoint,{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({message:text,sessionId:sid,tenant:'garage-temperli',page:location.href,origin:location.origin})
      });
      let data={};
      try{data=await r.json();}catch(_e){}
      if(!r.ok)throw new Error(data.message||('HTTP '+r.status));
      const answer=data.answer||data.reply||data.message||data.output;
      if(!answer)throw new Error('Keine Antwort');
      if(data.conversationId)localStorage.setItem('gt_ai_cid',String(data.conversationId));
      setTyping(false);
      bubble(String(answer));
      startPoll(data.escalate===true);
    }catch(e){
      setTyping(false);
      showError(e&&e.message&&e.message!=='Failed to fetch'?e.message:'Die KI ist gerade nicht erreichbar. Bitte versuchen Sie es erneut oder rufen Sie uns an.');
    }
  }

  function openBooking(){
    booking.classList.add('open');
    if(!bookingDate.value)bookingDate.value=localToday();
    body.scrollTop=body.scrollHeight;
  }

  bookingClose.onclick=()=>booking.classList.remove('open');
  document.querySelector('[data-booking]').onclick=openBooking;
  document.querySelectorAll('.gt-booking-slots button').forEach(b=>b.onclick=()=>{
    document.querySelectorAll('.gt-booking-slots button').forEach(x=>x.classList.remove('active'));
    b.classList.add('active');
    selectedTime=b.dataset.time;
  });
  bookingConfirm.onclick=()=>{
    if(!bookingDate.value){showError('Bitte wählen Sie zuerst ein Datum.');return;}
    if(!selectedTime){showError('Bitte wählen Sie eine Wunschzeit.');return;}
    const [y,m,d]=bookingDate.value.split('-');
    const nice=`${d}.${m}.${y}`;
    ask(`Ich möchte einen Termin für ${bookingService.value} am ${nice} um ${selectedTime}. Bitte prüfe die Verfügbarkeit und führe mich durch die Buchung.`);
  };

  form.addEventListener('submit',e=>{
    e.preventDefault();
    const q=input.value.trim();
    if(!q)return;
    input.value='';
    ask(q);
  });
  input.addEventListener('keydown',e=>{
    if(e.key==='Enter'&&!e.shiftKey){
      e.preventDefault();
      form.requestSubmit();
    }
  });
  document.querySelectorAll('.gt-ai-quick button[data-q]').forEach(b=>b.onclick=()=>ask(b.dataset.q));
  document.addEventListener('visibilitychange',()=>{
    if(document.visibilityState==='visible'&&panel.classList.contains('open')&&handoffActive)startPoll(true);
  });
})();