(function(){
  'use strict';
  if (window.JpEsimSupportAgentWidget) return;
  window.JpEsimSupportAgentWidget = true;

  const root = document.createElement('div');
  root.className = 'sa-widget';
  root.innerHTML = '<button class="sa-toggle" type="button" aria-label="Mở hỗ trợ">?</button><section class="sa-panel" aria-label="Trợ lý hỗ trợ jp-esim"><div class="sa-head"><div><div class="sa-title">Hỗ trợ eSIM Nhật</div><div class="sa-sub">Trả lời tự động, không hỏi thông tin nhạy cảm</div></div><button class="sa-close" type="button" aria-label="Đóng">×</button></div><div class="sa-log" role="log" aria-live="polite"></div><form class="sa-form"><input class="sa-input" name="message" maxlength="1600" autocomplete="off" placeholder="Nhập câu hỏi về eSIM..."><button class="sa-send" type="submit">Gửi</button></form></section>';
  document.addEventListener('DOMContentLoaded', function(){ document.body.appendChild(root); init(); });

  function init(){
    const toggle = root.querySelector('.sa-toggle');
    const close = root.querySelector('.sa-close');
    const form = root.querySelector('.sa-form');
    const input = root.querySelector('.sa-input');
    const send = root.querySelector('.sa-send');
    const log = root.querySelector('.sa-log');
    const storageKey = 'jp_support_agent_conversation';
    let conversationId = localStorage.getItem(storageKey) || '';
    let busy = false;

    toggle.addEventListener('click', function(){ root.classList.toggle('open'); if (root.classList.contains('open')) input.focus(); });
    close.addEventListener('click', function(){ root.classList.remove('open'); });
    form.addEventListener('submit', async function(ev){
      ev.preventDefault();
      const text = input.value.trim();
      if (!text || busy) return;
      input.value = '';
      add('user', text);
      busy = true;
      send.disabled = true;
      try {
        const payload = {message:text, conversation_id:conversationId, locale:'vi', page_context:{path:location.pathname, title:document.title}};
        const res = await fetch('/api/support-agent.php', {method:'POST', headers:{'Content-Type':'application/json'}, credentials:'same-origin', body:JSON.stringify(payload)});
        const data = await res.json().catch(function(){ return {}; });
        if (!res.ok) throw new Error(data.message || 'Dạ hệ thống đang bận, vui lòng thử lại sau.');
        if (data.conversation_id) {
          conversationId = data.conversation_id;
          localStorage.setItem(storageKey, conversationId);
        }
        add('bot', data.answer || 'Dạ em chưa có câu trả lời phù hợp. Anh/chị vui lòng liên hệ Messenger để được hỗ trợ ngay ạ.');
      } catch (err) {
        add('bot', err && err.message ? err.message : 'Dạ hệ thống đang bận, vui lòng thử lại sau.');
      } finally {
        busy = false;
        send.disabled = false;
        input.focus();
      }
    });

    add('bot', 'Dạ em hỗ trợ cài eSIM, quét QR, tra cứu đơn và nạp data. Anh/chị cần hỗ trợ gì ạ?');
    function add(role, text){
      const msg = document.createElement('div');
      msg.className = 'sa-msg ' + role;
      msg.textContent = text;
      log.appendChild(msg);
      log.scrollTop = log.scrollHeight;
    }
  }
})();
