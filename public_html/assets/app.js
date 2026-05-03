const $ = s => document.querySelector(s); const $$ = s => [...document.querySelectorAll(s)];


const ICONS={
moon:'<svg class="ui-ico fill" viewBox="0 0 24 24"><path d="M21.4 14.4a.9.9 0 0 0-1-.2 7.5 7.5 0 0 1-9.8-9.8.9.9 0 0 0-1.1-1.2A9.8 9.8 0 1 0 21.6 15a.9.9 0 0 0-.2-.6z"/></svg>',
sun:'<svg class="ui-ico fill" viewBox="0 0 24 24"><path d="M12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10z"/><path d="M12 1.8a1.2 1.2 0 0 1 1.2 1.2v1a1.2 1.2 0 1 1-2.4 0V3A1.2 1.2 0 0 1 12 1.8zM12 18.8A1.2 1.2 0 0 1 13.2 20v1a1.2 1.2 0 1 1-2.4 0v-1a1.2 1.2 0 0 1 1.2-1.2zM1.8 12A1.2 1.2 0 0 1 3 10.8h1a1.2 1.2 0 1 1 0 2.4H3A1.2 1.2 0 0 1 1.8 12zM18.8 12a1.2 1.2 0 0 1 1.2-1.2h1a1.2 1.2 0 1 1 0 2.4h-1a1.2 1.2 0 0 1-1.2-1.2z"/></svg>',
apple:'<svg class="ui-ico fill" viewBox="0 0 24 24"><path d="M16.8 12.8c0-2 1.6-3 1.7-3.1-1-.1-2.2-.7-3.3-.7-1.4 0-2.5.8-3.1.8-.7 0-1.7-.8-2.9-.8-1.5 0-3 .9-3.8 2.3-1.6 2.8-.4 6.9 1.2 9.1.8 1.1 1.7 2.3 2.9 2.3 1.2-.1 1.6-.8 3-.8 1.4 0 1.8.8 3 .8 1.2 0 2.1-1.1 2.9-2.3.9-1.3 1.2-2.6 1.2-2.7-.1 0-2.8-1.1-2.8-4.9z"/><path d="M14.8 7.4c.7-.8 1.1-1.9 1-3-1 0-2.1.7-2.8 1.5-.6.8-1.2 1.9-1 3 1.1.1 2.1-.6 2.8-1.5z"/></svg>',
android:'<svg class="ui-ico fill" viewBox="0 0 24 24"><path d="M8.3 7.1 6.8 4.6a1 1 0 0 1 1.7-1l1.5 2.6a8 8 0 0 1 4 0l1.5-2.6a1 1 0 1 1 1.7 1l-1.5 2.5A6.8 6.8 0 0 1 19 12v6.2a2.8 2.8 0 0 1-2.8 2.8H7.8A2.8 2.8 0 0 1 5 18.2V12c0-2 1.1-3.8 3.3-4.9zM9 12.2a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm6 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"/></svg>',
sim:'<svg class="ui-ico fill" viewBox="0 0 24 24"><path d="M7 2h7.6c.5 0 1 .2 1.4.6L20.4 7c.4.4.6.9.6 1.4V20a2 2 0 0 1-2 2H7a3 3 0 0 1-3-3V5a3 3 0 0 1 3-3zm7 2.5V8h3.5L14 4.5zM8 13a1 1 0 0 0 0 2h8a1 1 0 1 0 0-2H8zm0 4a1 1 0 1 0 0 2h5a1 1 0 1 0 0-2H8z"/></svg>',
wifi:'<svg class="ui-ico fill" viewBox="0 0 24 24"><path d="M12 19.8a1.8 1.8 0 1 0 0-3.6 1.8 1.8 0 0 0 0 3.6z"/><path d="M7.7 14.7a1.4 1.4 0 0 0 2 2 3.3 3.3 0 0 1 4.6 0 1.4 1.4 0 1 0 2-2 6.1 6.1 0 0 0-8.6 0z"/><path d="M4.2 11.2a1.4 1.4 0 0 0 2 2 8.2 8.2 0 0 1 11.6 0 1.4 1.4 0 1 0 2-2 11 11 0 0 0-15.6 0z"/></svg>',
chat:'<svg class="ui-ico fill" viewBox="0 0 24 24"><path d="M12 3C6.5 3 2 6.9 2 11.8c0 2.8 1.5 5.3 3.9 6.9l-.9 3a.8.8 0 0 0 1.2.9l3.6-2.1c.7.1 1.5.2 2.2.2 5.5 0 10-3.9 10-8.8S17.5 3 12 3zm-4 10.2a1.4 1.4 0 1 1 0-2.8 1.4 1.4 0 0 1 0 2.8zm4 0a1.4 1.4 0 1 1 0-2.8 1.4 1.4 0 0 1 0 2.8zm4 0a1.4 1.4 0 1 1 0-2.8 1.4 1.4 0 0 1 0 2.8z"/></svg>',
clock:'<svg class="mini-ico fill" viewBox="0 0 24 24"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm1.1 10.2 3 1.8a1.1 1.1 0 0 1-1.1 1.9l-3.5-2.1a1.1 1.1 0 0 1-.5-.9V7a1.1 1.1 0 1 1 2.2 0v5.2z"/></svg>',
copy:'<svg class="mini-ico fill" viewBox="0 0 24 24"><path d="M8 7a3 3 0 0 1 3-3h7a3 3 0 0 1 3 3v10a3 3 0 0 1-3 3h-7a3 3 0 0 1-3-3V7z"/><path d="M6 7.5A1.5 1.5 0 0 0 4.5 9v9A1.5 1.5 0 0 0 6 19.5a1 1 0 1 1 0 2A3.5 3.5 0 0 1 2.5 18V9A3.5 3.5 0 0 1 6 5.5a1 1 0 1 1 0 2z"/></svg>',
checkShield:'<svg class="ui-ico fill" viewBox="0 0 24 24"><path d="M12 2.2 20 5.5a1.5 1.5 0 0 1 .9 1.4v5.2c0 5-3.3 8.4-8.4 9.8a2 2 0 0 1-1 0C6.3 20.5 3 17.1 3 12.1V6.9c0-.6.4-1.2.9-1.4L12 2.2zm4.1 7.1a1.1 1.1 0 0 0-1.6 0l-3.8 3.8-1.4-1.4a1.1 1.1 0 1 0-1.6 1.6l2.2 2.2c.4.4 1.1.4 1.6 0l4.6-4.6c.4-.5.4-1.2 0-1.6z"/></svg>',
scan:'<svg class="ui-ico fill" viewBox="0 0 24 24"><path d="M4 3h5a1 1 0 1 1 0 2H5v4a1 1 0 1 1-2 0V4a1 1 0 0 1 1-1zm11 0h5a1 1 0 0 1 1 1v5a1 1 0 1 1-2 0V5h-4a1 1 0 1 1 0-2zM4 14a1 1 0 0 1 1 1v4h4a1 1 0 1 1 0 2H4a1 1 0 0 1-1-1v-5a1 1 0 0 1 1-1zm16 0a1 1 0 0 1 1 1v5a1 1 0 0 1-1 1h-5a1 1 0 1 1 0-2h4v-4a1 1 0 0 1 1-1z"/><path d="M8 8h3v3H8V8zm5 0h3v3h-3V8zm-5 5h3v3H8v-3zm5 0h3v3h-3v-3z"/></svg>',
phoneBolt:'<svg class="ui-ico fill" viewBox="0 0 24 24"><path d="M8 2h8a3 3 0 0 1 3 3v14a3 3 0 0 1-3 3H8a3 3 0 0 1-3-3V5a3 3 0 0 1 3-3zm1 3a1 1 0 1 0 0 2h6a1 1 0 1 0 0-2H9zm4.7 4.1a1 1 0 0 0-1.8-.7l-3 5a1 1 0 0 0 .9 1.5h1.5l-1 3a1 1 0 0 0 1.8.8l3-5a1 1 0 0 0-.9-1.5h-1.5l1-3.1z"/></svg>',
wifiFill:'<svg class="ui-ico fill" viewBox="0 0 24 24"><path d="M12 19.5a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"/><path d="M6.9 14.4a1.4 1.4 0 0 0 2 2 4.4 4.4 0 0 1 6.2 0 1.4 1.4 0 1 0 2-2 7.2 7.2 0 0 0-10.2 0z"/><path d="M2.8 10.3a1.4 1.4 0 0 0 2 2 10.2 10.2 0 0 1 14.4 0 1.4 1.4 0 1 0 2-2 13 13 0 0 0-18.4 0z"/></svg>',
spark:'<svg class="mini-ico fill" viewBox="0 0 24 24"><path d="M12 1.8a1 1 0 0 1 1 .8l1.5 5.3 5.3 1.5a1 1 0 0 1 0 2L14.5 13 13 18.2a1 1 0 0 1-2 0L9.5 13l-5.3-1.5a1 1 0 0 1 0-2l5.3-1.5L11 2.6a1 1 0 0 1 1-.8z"/></svg>'
};
function icon(name){return ICONS[name]||''}
function renderStaticIcons(){ $$('[data-i]').forEach(el=>{el.innerHTML=icon(el.dataset.i)}); }

const state = {plans:[], telecoms:[], activeTelecom:null, selectedPlan:null, topupPlans:[], selectedTopupPlan:null, topupIccid:null, supportUser:localStorage.getItem('jp_support_user') || ('web_'+Math.random().toString(36).slice(2))};
localStorage.setItem('jp_support_user', state.supportUser);
function esc(s){return String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]))}

// ---- production safe helpers ----
function safePaymentDescription(d,type){
  if (typeof paymentDescription === 'function') return safePaymentDescription(d,type);
  const title = d?.detailTitle || d?.planName || '';
  if (title) return title;
  if (type === 'topup') return `Đơn nạp data ${d?.gb?d.gb+'GB ':''}${d?.iccid?'cho ICCID '+d.iccid:''}`.trim();
  return 'Đơn hàng eSIM Nhật Bản';
}
function safeParseServerDate(v){
  if(!v)return null;
  const s=String(v).replace(' ','T');
  const d=new Date(s.includes('Z')||/[+-]\d\d:?\d\d$/.test(s)?s:s+'+07:00');
  return Number.isNaN(d.getTime())?null:d;
}
function safeFmtTime(v){
  if(!v)return '–';
  try{const d=safeParseServerDate(v)||new Date(v); return d.toLocaleString('vi-VN',{hour12:false});}catch(_){return String(v)}
}
function safeGbText(v){
  if(v===null||v===undefined||v==='')return '–';
  const n=Number(v);
  return Number.isFinite(n)?`${n.toLocaleString('vi-VN',{maximumFractionDigits:2})} GB`:String(v);
}
function safeTopupStatusText(s){
  const x=String(s||'').toUpperCase();
  return ({RELEASED:'Chưa sử dụng',ENABLED:'Đang sử dụng',DELETED:'Đã hết hạn',GOT_RESOURCE:'Đã cấp phát'})[x]||s||'–';
}
function safeTopupInfoHtml(d){
  if (typeof topupInfoHtml === 'function') return safeTopupInfoHtml(d);
  const c=d.current||{};
  const rows=[
    ['ICCID',d.iccid],
    ['Kích hoạt',c.activatedAt?safeFmtTime(c.activatedAt):'Chưa kích hoạt'],
    ['Hạn dùng',safeFmtTime(c.expiredAt)],
    ['Tổng gói',safeGbText(c.totalGB)],
    ['Đã dùng',safeGbText(c.usedGB)],
    ['Còn lại',safeGbText(c.remainingGB)],
    ['Cập nhật',safeFmtTime(c.lastUpdateAt)],
    ['Trạng thái',safeTopupStatusText(c.status)]
  ];
  return `<div class="info-card topup-status-card">${rows.map(r=>`<div class="topup-row"><span>${esc(r[0])}</span><b>${esc(r[1]??'–')}</b></div>`).join('')}</div>`;
}
function safePaymentExpiresAt(createdAt){
  const d=safeParseServerDate(createdAt);
  return d ? d.getTime()+15*60*1000 : Date.now()+15*60*1000;
}
function safeStartPayCountdown(createdAt){
  if (createdAt && typeof createdAt === 'object') return startServerCountdown(createdAt);
  if (typeof startPayCountdown === 'function') return startPayCountdown(createdAt);
  const el=$('#countdownText'); if(!el)return;
  const end=safePaymentExpiresAt(createdAt);
  if(window.__payCountdown) clearInterval(window.__payCountdown);
  const tick=()=>{const left=end-Date.now(); if(left<=0){el.textContent='Đã quá 15 phút. Nếu chưa thanh toán, vui lòng tạo đơn mới.'; el.classList.add('expired'); clearInterval(window.__payCountdown); return;} const m=Math.floor(left/60000), s=Math.floor((left%60000)/1000); el.textContent=`Còn ${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')} để thanh toán`;};
  tick(); window.__payCountdown=setInterval(tick,1000);
}

function toast(m){const t=$('#toast'); if(!t)return; t.textContent=m; t.classList.add('show'); setTimeout(()=>t.classList.remove('show'),2600)}
async function api(url, opts={}){const r=await fetch(url,{cache:'no-store',headers:{'Content-Type':'application/json',...(opts.headers||{})},...opts}); const j=await r.json().catch(()=>null); if(!r.ok||!j?.ok) throw new Error(j?.code==='CAPTCHA_FAILED'?'Xác minh không thành công, vui lòng thử lại':(j?.message||'Lỗi kết nối')); return j.data}
let recaptchaWidgetId = null;
async function waitForRecaptcha(){
  const start = Date.now();
  while(!window.grecaptcha && Date.now() - start < 10000){
    await new Promise(resolve => setTimeout(resolve, 150));
  }
  if(!window.grecaptcha) throw new Error('Khong tai duoc reCAPTCHA. Vui long tat adblock/VPN hoac thu lai.');
}
async function captcha(action){
  if(!window.RECAPTCHA_SITE) return '';
  await waitForRecaptcha();
  return new Promise((resolve, reject) => grecaptcha.ready(() => {
    try{
      if(recaptchaWidgetId === null){
        let box = document.getElementById('recaptchaInvisible');
        if(!box){ box = document.createElement('div'); box.id = 'recaptchaInvisible'; document.body.appendChild(box); }
        recaptchaWidgetId = grecaptcha.render(box, {sitekey: window.RECAPTCHA_SITE, size: 'invisible', badge: 'bottomright', callback: token => window.__recaptchaResolve(token), 'error-callback': () => window.__recaptchaReject(), 'expired-callback': () => window.__recaptchaReject()});
      }
      grecaptcha.reset(recaptchaWidgetId);
      window.__recaptchaResolve = token => resolve(token || '');
      window.__recaptchaReject = () => reject(new Error('Xac minh khong thanh cong, vui long thu lai'));
      grecaptcha.execute(recaptchaWidgetId);
    }catch(e){ reject(new Error('Xac minh khong thanh cong, vui long thu lai')); }
  }));
}
window.__recaptchaResolve = token => token;
window.__recaptchaReject = () => {};
function openSheet(html){$('#sheetContent').innerHTML=html; $('#sheet').classList.add('open'); $('#sheet').setAttribute('aria-hidden','false')}
function closeSheet(){ $('#sheet').classList.remove('open'); $('#sheet').setAttribute('aria-hidden','true') }
document.addEventListener('click',e=>{ if(e.target.classList.contains('sheet-backdrop')) closeSheet(); if(e.target.matches('[data-copy]')){navigator.clipboard?.writeText(e.target.dataset.copy); toast('Đã copy')} if(e.target.matches('[data-close]')) closeSheet(); if(e.target.matches('[data-resume]')) resumeLast(); const rb=e.target.closest('[data-resume-id]'); if(rb){const flow={id:rb.dataset.resumeId,type:rb.dataset.resumeType,t:Date.now()}; localStorage.setItem('jp_last_flow',JSON.stringify(flow)); resumeLast(flow);} const ro=e.target.closest('[data-remove-order]'); if(ro){clearHistoryId(ro.dataset.removeOrder);} });
function switchView(v){$$('.view').forEach(x=>x.classList.toggle('active',x.id==='view-'+v)); $$('.tabbar .tab').forEach(x=>x.classList.toggle('active',x.dataset.view===v)); location.hash=v==='buy'?'':'#'+v}
function saveLast(type,id){saveHistory(type,id)}
function orderHistory(){try{return JSON.parse(localStorage.getItem('jp_order_history')||'[]')}catch(_){return []}}
function saveHistory(type,id){if(!id)return; let arr=orderHistory().filter(x=>x.id!==id); arr.unshift({type,id,t:Date.now()}); arr=arr.slice(0,5); localStorage.setItem('jp_order_history',JSON.stringify(arr)); localStorage.setItem('jp_last_flow',JSON.stringify(arr[0])); renderResumeCard()}
function getLast(){try{const cur=JSON.parse(localStorage.getItem('jp_last_flow')||'null'); if(cur?.id) return cur;}catch(_){} const arr=orderHistory(); if(arr[0]) return arr[0]; return null}
function clearHistoryId(id){let arr=orderHistory().filter(x=>x.id!==id); localStorage.setItem('jp_order_history',JSON.stringify(arr)); if(arr[0]) localStorage.setItem('jp_last_flow',JSON.stringify(arr[0])); else localStorage.removeItem('jp_last_flow'); renderResumeCard()}
function clearLast(){localStorage.removeItem('jp_order_history');localStorage.removeItem('jp_last_flow'); renderResumeCard()}
function renderResumeCard(){
  $$('.resume-card').forEach(x=>x.remove());
  const arr=orderHistory();
  if(!arr.length)return;
  const rows=arr.map(x=>`<div class="recent-order-row"><button class="recent-main" data-resume-id="${esc(x.id)}" data-resume-type="${esc(x.type)}"><b>${esc(x.id)}</b><span>${x.type==='topup'?'Nạp data':'JP eSIM'}</span></button><button class="recent-del" data-remove-order="${esc(x.id)}">${icon('copy')}</button></div>`).join('');
  const html=`<div class="resume-card info-card"><div class="recent-head"><b>Đơn gần đây</b><span class="muted">Tối đa 5 đơn</span></div>${rows}</div>`;
  const target=$('#view-buy .hero-card');
  target?.insertAdjacentHTML('afterend',html);
}
async function resumeLast(flow=null){const last=flow||getLast(); if(!last?.id)return toast('Không có đơn gần đây'); try{const p=await api(`/api/payment.php?id=${encodeURIComponent(last.id)}&type=${last.type}`); if(p.paid){ if(last.type==='order') pollEsim(last.id); else openSheet(`<span class="status-pill ok">Đã thanh toán</span><h2>Đơn nạp data</h2><p>Đơn ${esc(last.id)} đã nhận thanh toán. Trạng thái: ${esc(p.topupStatus||'processing')}.</p><button class="primary" data-close>Đóng</button>`)} else {showPayment(p,last.type); pollPayment(last.id,last.type)} }catch(e){toast(e.message)}}
async function loadPlans(){ const box=$('#plans'); if(!box)return; box.innerHTML='<div class="skeleton"></div><div class="skeleton"></div>'; try{const d=await api('/api/plans.php?type=esim'); state.plans=d.plans; state.telecoms=d.telecoms; state.activeTelecom=d.telecoms[0]||null; renderTelecoms(); renderPlans();}catch(e){box.innerHTML='';toast(e.message)}}
function renderTelecoms(){const tabs=$('#telecomTabs'); if(!tabs)return; tabs.innerHTML=state.telecoms.map(t=>`<button class="${t===state.activeTelecom?'active':''}" data-tel="${esc(t)}">${t==='Docomo'?'Docomo 4G':'au 5G'}</button>`).join(''); tabs.onclick=e=>{const b=e.target.closest('button'); if(!b)return; state.activeTelecom=b.dataset.tel; state.selectedPlan=null; renderTelecoms(); renderPlans();}}
function renderPlans(){const box=$('#plans'); const arr=state.plans.filter(p=>p.telecom===state.activeTelecom); if(!state.selectedPlan && arr[0]) state.selectedPlan=arr[0]; box.innerHTML=arr.map(p=>`<div class="plan-card ${state.selectedPlan?.id===p.id?'selected':''}" data-id="${p.id}"><div><h3>${esc(p.name)}</h3><div class="plan-meta"><span class="meta-pill strong">${esc(p.network||p.telecom||'4G')}</span><span class="meta-pill">${icon("clock")} ${p.day} ngày</span><span class="meta-pill">Phát WiFi</span></div></div><div class="price">${esc(p.priceText)}</div></div>`).join(''); box.onclick=e=>{const c=e.target.closest('.plan-card'); if(!c)return; state.selectedPlan=state.plans.find(p=>p.id==c.dataset.id); renderPlans();}}
async function createOrder(){ const email=$('#orderEmail').value.trim(); if(!/^\S+@\S+\.\S+$/.test(email)) return toast('Email không hợp lệ'); if(!state.selectedPlan) return toast('Chọn gói trước'); openSheet(`<h2>Xác nhận đơn</h2><p class="muted">${esc(state.selectedPlan.telecom)} ${esc(state.selectedPlan.name)} • ${esc(state.selectedPlan.priceText)}</p><div class="copy-row"><span>Email</span><b>${esc(email)}</b></div><button class="primary" id="confirmOrderBtn">Xác nhận & tạo QR</button><button class="primary" data-close style="background:#eef2f7;color:#111;box-shadow:none">Huỷ</button>`); $('#confirmOrderBtn').onclick=async()=>{try{const token=await captcha('order'); const d=await api('/api/orders.php',{method:'POST',body:JSON.stringify({email,planId:state.selectedPlan.id,captcha:token})}); const id=d.orderId||d.id; saveLast('order',id); showPayment(d,'order'); pollPayment(id,'order')}catch(e){toast(e.message)}} }

function countdownEndMs(d){
  if(d && Number(d.expiresAtMs)>0) return Number(d.expiresAtMs);
  if(d && d.expiresAt){ const t=Date.parse(d.expiresAt); if(Number.isFinite(t)) return t; }
  if(d && Number(d.expiresIn)>=0) return Date.now()+Number(d.expiresIn)*1000;
  return Date.now()+900000;
}
function startServerCountdown(payload){
  const el=$('#countdownText'); if(!el)return;
  const end=countdownEndMs(payload||{});
  if(window.__payCountdown) clearInterval(window.__payCountdown);
  const tick=()=>{const left=end-Date.now(); if(left<=0){el.textContent='Đã quá 15 phút. Nếu chưa thanh toán, vui lòng tạo đơn mới.'; el.classList.add('expired'); clearInterval(window.__payCountdown); return;} el.classList.remove('expired'); const m=Math.floor(left/60000), s=Math.floor((left%60000)/1000); el.textContent=`Còn ${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')} để thanh toán`;};
  tick(); window.__payCountdown=setInterval(tick,1000);
}

function showPayment(d,type){
const id=d.id||d.orderId||d.tid;
openSheet(`<span class="status-pill">Đang chờ thanh toán</span><h2>Thanh toán ${esc(d.amountText)}</h2><p><b>${esc(safePaymentDescription(d,type))}</b></p><p class="muted">Nội dung chuyển khoản: <b>${esc(id)}</b></p><p class="pay-countdown" id="countdownText"></p><img class="pay-qr" src="${esc(d.qrUrl)}" alt="QR"><div class="copy-row"><span>STK ${esc(d.bank.code)}</span><b>${esc(d.bank.account)}</b><button data-copy="${esc(d.bank.account)}">${icon("copy")}<span>Copy</span></button></div><div class="copy-row"><span>Nội dung</span><b>${esc(id)}</b><button data-copy="${esc(id)}">${icon("copy")}<span>Copy</span></button></div><p class="muted" id="payStatus">Hệ thống tự kiểm tra mỗi 2 giây...</p>`);
startServerCountdown(d);
}
async function pollPayment(id,type){let n=0; const timer=setInterval(async()=>{try{const p=await api(`/api/payment.php?id=${encodeURIComponent(id)}&type=${type}`); if($('#countdownText') && !$('#countdownText').dataset.synced){$('#countdownText').dataset.synced='1'; startServerCountdown(p);} const el=$('#payStatus'); if(el) el.textContent=p.paid?'Đã nhận thanh toán, đang xử lý...':'Đang chờ thanh toán...'; if(p.paid){clearInterval(timer); if(type==='order') pollEsim(id); else openSheet(`<span class="status-pill ok">Đã thanh toán</span><h2>Đơn nạp data đang xử lý</h2><p>Đơn ${esc(id)} đã nhận thanh toán. Hệ thống đang nạp data cho eSIM.</p><button class="primary" data-close>Đóng</button>`)} if(++n>450)clearInterval(timer)}catch(e){}},2000)}
function esimInfoRows(e){const rows=[]; if(e.iccid) rows.push(['ICCID',e.iccid,true]); if(e.packageName) rows.push(['Gói',e.packageName,false]); if(e.packageCode) rows.push(['Mã gói',e.packageCode,false]); if(e.totalVolumeGB) rows.push(['Dung lượng',`${e.totalVolumeGB}GB`,false]); if(e.totalDuration) rows.push(['Thời hạn',`${e.totalDuration} ${e.durationUnit||'DAY'}`,false]); if(e.expiredTime) rows.push(['Hết hạn',e.expiredTime,false]); if(e.apn) rows.push(['APN',e.apn,true]); if(e.smdpStatus) rows.push(['SMDP',e.smdpStatus,false]); if(e.esimStatus) rows.push(['Trạng thái',e.esimStatus,false]); return rows.map(([k,v,c])=>`<div class="copy-row"><span>${esc(k)}</span><b>${esc(v)}</b>${c?`<button data-copy="${esc(v)}">${icon("copy")}<span>Copy</span></button>`:''}</div>`).join('')}
function showEsimReady(d){
const e=d.esims[0]||{};
const ios=e.install?.ios||'';
const android=e.install?.android||'';
const title=d.title||`eSIM Nhật Bản ${d.carrier||'Docomo'} ${d.network||''} ${d.plan||''} ${d.day?d.day+' ngày':''}`.replace(/\s+/g,' ').trim();
if(d.visible===false){
  openSheet(`<span class="status-pill ok">${icon('spark')} eSIM đã sẵn sàng</span><h2>${esc(title)}</h2><p class="muted order-note">Mã đơn: <b>${esc(d.orderId||'')}</b></p><div class="copy-row"><span>ICCID</span><b>${esc(e.iccid||'')}</b><button data-copy="${esc(e.iccid||'')}">${icon('copy')}<span>Copy</span></button></div><p class="muted">Thông tin QR/link kích hoạt chỉ hiển thị 24 giờ theo thời gian máy chủ. Vui lòng kiểm tra email để xem lại eSIM.</p><button class="primary" data-close>Đóng</button>`);
  return;
}
openSheet(`<span class="status-pill ok">${icon('spark')} eSIM đã sẵn sàng</span><h2>${esc(title)}</h2><p class="muted order-note">Mã đơn: <b>${esc(d.orderId||'')}</b></p>${e.qrCodeUrl?`<img class="pay-qr esim-qr small" src="${esc(e.qrCodeUrl)}" alt="QR eSIM">`:''}<div class="install-actions compact">${ios?`<a class="primary" href="${esc(ios)}">${icon('apple')}<span>iOS</span></a>`:''}${android?`<a class="primary secondary" href="${esc(android)}">${icon('android')}<span>Android</span></a>`:''}</div><div class="copy-row"><span>ICCID</span><b>${esc(e.iccid||'')}</b><button data-copy="${esc(e.iccid||'')}">${icon('copy')}<span>Copy</span></button></div><p class="muted">Thông tin eSIM này chỉ hiển thị trong 24 giờ kể từ lúc mua. Nếu cần xem lại, vui lòng kiểm tra email.</p><button class="primary" data-close>Hoàn tất</button>`)}
async function pollEsim(orderId){openSheet(`<span class="status-pill ok">Đã thanh toán</span><h2>Đang tạo eSIM</h2><p class="muted" id="esimStatus">Vui lòng chờ 15-30 giây...</p>`); let n=0; const timer=setInterval(async()=>{try{const d=await api(`/api/esim.php?orderId=${orderId}`); if(d.status==='ready'){clearInterval(timer); saveLast('order',orderId); showEsimReady(d)} else {const el=$('#esimStatus'); if(el) el.textContent=d.message||'Đang xử lý...'} if(++n>180)clearInterval(timer)}catch(e){}},2000)}
async function lookupTopup(){
  const input=$('#topupLookup');
  const id=input ? input.value.trim() : '';
  if(!id)return toast('Nhập ICCID hoặc mã đơn');
  const info=$('#topupInfo');
  if(info) info.innerHTML='<div class="info-card"><b>Đang kiểm tra eSIM...</b><br><span class="muted">Hệ thống đang gọi trực tiếp nhà cung cấp bằng ICCID.</span></div>';
  try{
    const d=await api('/api/topup.php?id='+encodeURIComponent(id));
    state.topupIccid=d.iccid;
    state.topupPlans=d.plans||[];
    state.selectedTopupPlan=state.topupPlans[0]||null;
    if(info) info.innerHTML=safeTopupInfoHtml(d);
    renderTopupPlans();
    $('#topupForm')?.classList.remove('hidden');
  }catch(e){
    if(info) info.innerHTML='';
    toast(e.message);
  }
}
function renderTopupPlans(){const box=$('#topupPlans'); box.innerHTML=state.topupPlans.map(p=>`<div class="plan-card ${state.selectedTopupPlan?.id===p.id?'selected':''}" data-id="${p.id}"><div><h3>${esc(p.name)}</h3><div class="plan-meta"><span class="meta-pill strong">${esc(p.network||'4G')}</span><span class="meta-pill">${icon("clock")} ${p.day} ngày</span><span class="meta-pill">Phát WiFi</span></div></div><div class="price">${esc(p.priceText)}</div></div>`).join(''); box.onclick=e=>{const c=e.target.closest('.plan-card'); if(!c)return; state.selectedTopupPlan=state.topupPlans.find(p=>p.id==c.dataset.id); renderTopupPlans();}}
async function createTopup(){const email=$('#topupEmail').value.trim(); if(!/^\S+@\S+\.\S+$/.test(email))return toast('Email không hợp lệ'); if(!state.selectedTopupPlan||!state.topupIccid)return toast('Chưa chọn gói'); try{const token=await captcha('topup'); const d=await api('/api/topup.php',{method:'POST',body:JSON.stringify({iccid:state.topupIccid,planId:state.selectedTopupPlan.id,email,captcha:token})}); const id=d.tid||d.id; saveLast('topup',id); showPayment(d,'topup'); pollPayment(id,'topup')}catch(e){toast(e.message)}}
function addMsg(role,text){const box=$('#chatBox'); if(!box)return; box.insertAdjacentHTML('beforeend',`<div class="msg ${role}">${esc(text)}</div>`); box.scrollTop=box.scrollHeight}
async function sendSupport(){const inp=$('#supportInput'); const text=inp.value.trim(); if(!text)return; inp.value=''; addMsg('user',text); try{const d=await api('/api/support.php',{method:'POST',body:JSON.stringify({channel:'web',userId:state.supportUser,message:text})}); addMsg('bot',d.reply||''); if(d.actions) d.actions.filter(a=>a.type==='image').forEach(a=>addMsg('bot','QR thanh toán: '+a.url))}catch(e){addMsg('bot',e.message||'Lỗi hệ thống')}}
function migrateOldHistory(){const old=(()=>{try{return JSON.parse(localStorage.getItem('jp_last_flow')||'null')}catch(_){return null}})(); if(old?.id && !orderHistory().length) saveHistory(old.type||'order',old.id)}
function initTheme(){const saved=localStorage.getItem('jp_theme')||'dark';document.body.classList.toggle('light',saved==='light');const btn=$('#themeToggle');if(btn){btn.innerHTML=saved==='light'?icon('sun'):icon('moon');btn.onclick=()=>{const isLight=!document.body.classList.contains('light');document.body.classList.toggle('light',isLight);localStorage.setItem('jp_theme',isLight?'light':'dark');btn.innerHTML=isLight?icon('sun'):icon('moon');};}}

function getAutoTopupId(){
  const p = new URLSearchParams(location.search);
  let id = p.get('topup_id') || p.get('iccid') || '';
  if(!id && location.hash){
    const h = location.hash.replace(/^#/, '');
    const q = h.includes('?') ? h.split('?').slice(1).join('?') : '';
    if(q){
      const hp = new URLSearchParams(q);
      id = hp.get('topup_id') || hp.get('iccid') || hp.get('id') || '';
    }
  }
  if(!id){
    try { id = sessionStorage.getItem('jp_pending_topup_id') || localStorage.getItem('jp_pending_topup_id') || ''; } catch(e) {}
  }
  return (id || '').trim();
}
function clearAutoTopupId(){
  try { sessionStorage.removeItem('jp_pending_topup_id'); localStorage.removeItem('jp_pending_topup_id'); } catch(e) {}
}
async function autoLookupTopupFromUrl(){
  const id = getAutoTopupId();
  if(!id) return;
  switchView('topup');
  const input = $('#topupLookup');
  if(input){
    input.value = id;
    input.dispatchEvent(new Event('input', {bubbles:true}));
  }
  clearAutoTopupId();
  await new Promise(r=>setTimeout(r,250));
  lookupTopup();
}

document.addEventListener('DOMContentLoaded',()=>{ migrateOldHistory(); renderStaticIcons(); initTheme(); initAds(); $$('.tabbar .tab').forEach(b=>b.onclick=()=>switchView(b.dataset.view)); if(location.hash==='#topup') switchView('topup'); if(location.hash==='#support') switchView('support'); loadPlans(); renderResumeCard(); const params=new URLSearchParams(location.search); autoLookupTopupFromUrl(); const qOrder=params.get('order')||params.get('id');
  if(qOrder){localStorage.setItem('jp_last_flow',JSON.stringify({type:qOrder.toUpperCase().startsWith('T')?'topup':'order',id:qOrder.toUpperCase(),t:Date.now()})); setTimeout(resumeLast,500)} $('#buyBtn')?.addEventListener('click',createOrder); $('#lookupTopupBtn')?.addEventListener('click',lookupTopup); $('#createTopupBtn')?.addEventListener('click',createTopup); $('#sendSupportBtn')?.addEventListener('click',sendSupport); $('#supportInput')?.addEventListener('keydown',e=>{if(e.key==='Enter')sendSupport()}); addMsg('bot','Dạ em có thể tư vấn gói eSIM Nhật, tạo đơn, kiểm tra thanh toán hoặc hỗ trợ nạp data ạ.');});

function initAds(){
  const slides=$$('.ad-slide');
  const dots=$('#adDots');
  if(!slides.length||!dots)return;
  let idx=0;
  dots.innerHTML=slides.map((_,i)=>'<button class="'+(i===0?'active':'')+'" data-ad="'+i+'" aria-label="Quảng cáo '+(i+1)+'"></button>').join('');
  function show(i){
    idx=(i+slides.length)%slides.length;
    slides.forEach((s,n)=>s.classList.toggle('active',n===idx));
    $$('#adDots button').forEach((d,n)=>d.classList.toggle('active',n===idx));
  }
  dots.onclick=e=>{const b=e.target.closest('button'); if(!b)return; show(Number(b.dataset.ad||0));};
  setInterval(()=>show(idx+1),3600);
}
