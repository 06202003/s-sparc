<?php
require_once __DIR__ . '/config.php';
if (empty($_SESSION['chat_user_id'])) {
  $_SESSION['chat_user_id'] = bin2hex(random_bytes(6));
}
$loggedIn = !empty($_SESSION['flask_cookie']);
$username = $_SESSION['username'] ?? 'Guest';
$currentCourse = $_SESSION['current_course'] ?? null;
$currentAssessment = $_SESSION['current_assessment'] ?? null;
$assessmentId = $_SESSION['assessment_id'] ?? '';

// Wajib login dan memilih mata kuliah + assessment terlebih dahulu
if (!$loggedIn) {
  header('Location: login.php');
  exit;
}
if (!$assessmentId) {
  header('Location: courses.php');
  exit;
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Chatbot - BotMan + Flask</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    :root { color-scheme: light; }
    body { font-family: 'Manrope', system-ui, -apple-system, sans-serif; }
    .glass { backdrop-filter: blur(10px); background: rgba(255,255,255,0.7); }
    .typing-dot { width: 8px; height: 8px; border-radius: 999px; background: #475569; animation: blink 1.2s infinite; }
    .typing-dot:nth-child(2) { animation-delay: 0.2s; }
    .typing-dot:nth-child(3) { animation-delay: 0.4s; }
    @keyframes blink { 0%, 80%, 100% { opacity: 0.2; } 40% { opacity: 1; } }
    .code-block { position: relative; }
    .copy-btn { position: absolute; top: 8px; right: 8px; font-size: 12px; padding: 4px 8px; border-radius: 12px; border: 1px solid #cbd5e1; background: #ffffff; color: #0f172a; cursor: pointer; }
    .copy-btn:hover { background: #e2e8f0; }
  </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-50 via-slate-100 to-slate-200 text-slate-900">
  <div class="min-h-screen flex flex-col">
    <header class="sticky top-0 z-10 border-b border-slate-200/70 bg-white/80 backdrop-blur">
      <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <div class="h-10 w-10 rounded-xl bg-slate-900 text-white grid place-items-center font-semibold">AI</div>
          <div>
            <div class="text-lg font-semibold">Chat Assistant</div>
            <div class="text-xs text-slate-500">courses: <strong><?= htmlspecialchars($currentCourse ?? '-') ?></strong> &mdash; Assessment: <strong><?= htmlspecialchars($currentAssessment ?? '-') ?></strong></div>
          </div>
        </div>
        <nav class="flex items-center gap-3 text-sm font-medium">
          <a class="text-slate-400 hover:text-slate-700" href="dashboard.php">Dashboard</a>
          <a class="text-slate-400 hover:text-slate-700" href="courses.php">Change courses</a>
          <button id="new-chat" type="button" class="inline-flex items-center gap-2 rounded-full bg-slate-900 text-white px-3 py-1 hover:bg-slate-800">New chat</button>
          <button id="clear-chat" type="button" class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-3 py-1 text-slate-700 hover:border-slate-400">Clear history</button>
          <?php if ($loggedIn): ?>
            <a href="logout.php" class="ml-auto inline-flex items-center gap-2 rounded-full bg-red-500 text-white px-3 py-1 hover:bg-red-600 shadow-sm">Logout</a>
          <?php else: ?>
            <a href="login.php" class="ml-auto inline-flex items-center gap-2 rounded-full border border-slate-300 px-3 py-1 text-slate-700 hover:border-slate-500 hover:text-slate-900">Login</a>
          <?php endif; ?>
        </nav>
      </div>
    </header>

    <?php if (!$loggedIn): ?>
      <div class="max-w-6xl mx-auto w-full px-4 pt-4">
        <div class="rounded-lg border border-amber-200 bg-amber-50 text-amber-900 px-4 py-3 text-sm">
          You are not logged in. Log in on the login page so that the Flask session cookie is saved. If you get a job_id, check the status anytime by typing: <span class="font-mono">status &lt;job_id&gt;</span>.
        </div>
      </div>
    <?php endif; ?>

    <main class="flex-1">
      <div class="max-w-6xl mx-auto px-4 py-6 grid gap-4 lg:grid-cols-[1fr_320px] h-full">
        <section class="glass rounded-2xl border border-white/60 shadow-lg p-4 sm:p-6 flex flex-col min-h-[60vh] lg:max-h-[calc(100vh-120px)]">
          <div id="chat-window" class="flex-1 overflow-y-auto space-y-4 pr-1" aria-live="polite"></div>
          <div id="typing" class="hidden mt-2 flex items-center gap-2 text-sm text-slate-600">
            <span class="inline-flex items-center gap-1">
              <span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span>
            </span>
            <span>Assistant is typing…</span>
          </div>
          <form id="chat-form" class="mt-4 flex items-stretch gap-3" onsubmit="sendMessage(event)">
            <div class="flex-1 flex flex-col gap-2">
              <div class="flex gap-2 items-center">
                <label for="language-select" class="text-xs text-slate-600">Language</label>
                <select id="language-select" class="text-sm rounded-md border border-slate-200 px-2 py-1">
                  <option value="">Auto-detect</option>
                  <option value="Python">Python</option>
                  <option value="JavaScript">JavaScript</option>
                  <option value="Java">Java</option>
                  <option value="C">C</option>
                  <option value="C++">C++</option>
                  <option value="Go">Go</option>
                  <option value="PHP">PHP</option>
                </select>
                <label for="response-mode" class="text-xs text-slate-600 ml-3">Mode</label>
                <select id="response-mode" class="text-sm rounded-md border border-slate-200 px-2 py-1">
                  <option value="code">Code (only)</option>
                  <option value="summary">Summary (short)</option>
                  <option value="summary_code_explanation">Summary + Code + Explanation</option>
                </select>
              </div>
              <div class="flex items-stretch gap-3">
                <label for="chat-input" class="sr-only">Write a message</label>
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm focus-within:border-slate-400 flex-1 flex">
                  <textarea id="chat-input" rows="2" class="w-full min-h-[3rem] resize-none overflow-y-auto bg-transparent px-4 py-2 outline-none" placeholder="Write your code question here…" required></textarea>
                </div>
                <button id="send-btn" type="submit" class="h-full flex items-center justify-center rounded-xl bg-slate-900 text-white px-4 font-semibold hover:bg-slate-800 focus:ring focus:ring-slate-200">Send</button>
              </div>
            </div>
          </form>
          <div id="rate-limit-notice" class="hidden mt-2 text-sm text-amber-700"></div>
          <div class="mt-3 flex flex-wrap gap-2 text-sm" id="suggestions">
            <button type="button" class="rounded-full border border-slate-200 bg-white px-3 py-1 text-slate-700 hover:border-slate-400" data-suggest="Create a Python function to calculate factorial">Factorial Python</button>
            <button type="button" class="rounded-full border border-slate-200 bg-white px-3 py-1 text-slate-700 hover:border-slate-400" data-suggest="Write an SQL query to select top 10 from users table ordered by created_at desc">SQL Query top 10</button>
            <button type="button" class="rounded-full border border-slate-200 bg-white px-3 py-1 text-slate-700 hover:border-slate-400" data-suggest="Create a Flask POST /predict endpoint with JSON body validation">Flask POST Endpoint</button>
            <button type="button" class="rounded-full border border-slate-200 bg-white px-3 py-1 text-slate-700 hover:border-slate-400" data-suggest="Example pytest unit test for addition function">Pytest Unit Test</button>
          </div>
        </section>

        <aside class="hidden lg:block space-y-3">
          <div class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm">
            <div class="text-sm font-semibold text-slate-800 mb-2">Quick Tips</div>
            <ul class="space-y-2 text-sm text-slate-600">
              <li>Use a clear format, for example: “Create a Python function to calculate factorials.”</li>
              <li>To check the queue: type <span class="font-mono">status &lt;job_id&gt;</span>.</li>
              <li>Response retrieval is marked with similarity; GPT will queue if necessary.</li>
            </ul>
          </div>
          <!-- Kartu status sesi disembunyikan sesuai permintaan -->
          <div class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm">
            <div class="text-sm font-semibold text-slate-800 mb-2">Token usage this week</div>
            <div class="text-sm text-slate-700 flex flex-col gap-1">
              <span>Total quota: <strong><span id="token-total">-</span></strong> tokens</span>
              <span>Used: <strong><span id="token-used">-</span></strong> tokens</span>
              <span>Remaining: <strong><span id="token-remaining">-</span></strong> tokens</span>
              <span>Active points: <strong><span id="token-points">-</span></strong> points</span>
            </div>
            <p class="mt-2 text-xs text-slate-500">Quota is calculated weekly based on the total tokens used across all sessions.</p>
          </div>
        </aside>
      </div>
    </main>
  </div>

  <script>
    const chatWindow = document.getElementById('chat-window');
    const chatInput = document.getElementById('chat-input');
    const typing = document.getElementById('typing');
    const languageSelect = document.getElementById('language-select');
    const responseModeSelect = document.getElementById('response-mode');
    const newChatBtn = document.getElementById('new-chat');
    const clearChatBtn = document.getElementById('clear-chat');
    const suggestions = document.getElementById('suggestions');
    const tokenTotalEl = document.getElementById('token-total');
    const tokenUsedEl = document.getElementById('token-used');
    const tokenRemainingEl = document.getElementById('token-remaining');
    const tokenPointsEl = document.getElementById('token-points');
    let scrollBtn = null;
    const userId = '<?= htmlspecialchars($_SESSION['chat_user_id']) ?>';
    const assessmentId = '<?= htmlspecialchars($assessmentId, ENT_QUOTES, 'UTF-8') ?>';
    const sendBtn = document.getElementById('send-btn');

    const STORAGE_KEY = 'chat_messages_v1_' + (assessmentId || 'default');
    const state = { messages: [] };
    const tokenState = { total: null, remaining: null, points: null };

    function loadMessages() {
      try {
        const saved = localStorage.getItem(STORAGE_KEY);
        if (!saved) return;
        const parsed = JSON.parse(saved);
        if (Array.isArray(parsed)) {
          state.messages = parsed.slice(-200); // keep last 200
          renderMessages();
        }
      } catch (e) {
        console.warn('Failed to load messages', e);
      }
    }

    function persistMessages() {
      try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(state.messages.slice(-200)));
      } catch (e) {
        console.warn('Failed to persist messages', e);
      }
    }

    function renderMessages() {
      chatWindow.innerHTML = '';
      state.messages.forEach(msg => {
        const row = document.createElement('div');
        row.className = msg.sender === 'user' ? 'flex justify-end items-start gap-2' : 'flex justify-start items-start gap-2';

        const avatar = document.createElement('div');
        avatar.className = 'h-9 w-9 rounded-full flex-shrink-0 grid place-items-center text-xs font-semibold shadow-sm ' + (msg.sender === 'user' ? 'bg-slate-800 text-white' : 'bg-white text-slate-700 border border-slate-200');
        avatar.textContent = msg.sender === 'user' ? 'You' : 'AI';

        const bubble = document.createElement('div');
        bubble.className = msg.sender === 'user'
          ? 'max-w-3xl rounded-2xl bg-slate-900 text-white px-4 py-3 shadow'
          : 'max-w-3xl rounded-2xl bg-white text-slate-900 px-4 py-3 shadow border border-slate-100';

        if (msg.sender === 'bot' && msg.meta) {
          const meta = document.createElement('div');
          meta.className = 'text-xs text-slate-500 mb-1 flex items-center gap-2';
          meta.textContent = msg.meta;
          bubble.appendChild(meta);
        }

        let isCode = msg.sender === 'bot' && (
          msg.text.includes('\n') ||
          msg.text.includes(';') ||
          msg.text.includes('{') ||
          msg.text.includes('def ') ||
          msg.text.includes('class ') ||
          msg.text.includes('function ') ||
          msg.text.includes('import ') ||
          msg.text.includes('#include')
        );

        // Special case: guardrail text like "Here is the code result... Sorry, I can only help..."
        // should be shown as normal chat text, not as a code block.
        if (
          msg.sender === 'bot' &&
          msg.text.startsWith('Here is the code result:') &&
          msg.text.includes('Sorry, I can only help with programming/code questions.')
        ) {
          isCode = false;
        }

        if (isCode) {
          // If message contains fenced code blocks, split into text/code/text
          if (msg.text.includes('```')) {
            // Regex to capture parts: text before, each fenced block, and after
            const parts = [];
            const fenceRe = /```([a-zA-Z0-9+\-]*)\n([\s\S]*?)\n```/g;
            let lastIndex = 0;
            let m;
            while ((m = fenceRe.exec(msg.text)) !== null) {
              const start = m.index;
              const lang = m[1] || '';
              const codeContent = m[2] || '';
              if (start > lastIndex) {
                parts.push({ type: 'text', content: msg.text.slice(lastIndex, start) });
              }
              parts.push({ type: 'code', content: codeContent, lang });
              lastIndex = fenceRe.lastIndex;
            }
            if (lastIndex < msg.text.length) {
              parts.push({ type: 'text', content: msg.text.slice(lastIndex) });
            }
            parts.forEach(p => {
              if (p.type === 'text') {
                const textNode = document.createElement('div');
                textNode.className = 'mb-2';
                textNode.textContent = p.content.trim();
                bubble.appendChild(textNode);
              } else if (p.type === 'code') {
                const wrapper = document.createElement('div');
                wrapper.className = 'code-block rounded-xl border border-slate-200 bg-slate-50 text-slate-900 relative overflow-x-auto my-2';
                const code = document.createElement('pre');
                code.className = 'text-sm leading-relaxed p-3 whitespace-pre-wrap break-words';
                code.textContent = p.content.trim();
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'copy-btn';
                btn.textContent = 'Copy';
                btn.dataset.copy = p.content.trim();
                wrapper.appendChild(btn);
                wrapper.appendChild(code);
                bubble.appendChild(wrapper);
              }
            });
          } else {
            const wrapper = document.createElement('div');
            wrapper.className = 'code-block rounded-xl border border-slate-200 bg-slate-50 text-slate-900 relative overflow-x-auto';
            const code = document.createElement('pre');
            code.className = 'text-sm leading-relaxed p-3 whitespace-pre-wrap break-words';
            code.textContent = msg.text;
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'copy-btn';
            btn.textContent = 'Copy';
            btn.dataset.copy = msg.text;
            wrapper.appendChild(btn);
            wrapper.appendChild(code);
            bubble.appendChild(wrapper);
          }
        } else {
          const text = document.createElement('div');
          text.textContent = msg.text;
          bubble.appendChild(text);
        }

        if (msg.sender === 'bot' && msg.source === 'db' && msg.originalPrompt) {
          const footer = document.createElement('div');
          footer.className = 'mt-2 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500';
          const info = document.createElement('span');
          info.textContent = "The code above is taken from a database (free). If it's not suitable, you can request a new version from ChatGPT.";
          const gptBtn = document.createElement('button');
          gptBtn.type = 'button';
          gptBtn.className = 'gpt-generate inline-flex items-center gap-1 rounded-full bg-slate-900 text-white px-3 py-1 text-xs hover:bg-slate-800';
          gptBtn.dataset.prompt = msg.originalPrompt;
          gptBtn.textContent = 'Generate with ChatGPT';
          footer.appendChild(info);
          footer.appendChild(gptBtn);
          bubble.appendChild(footer);
        }

        if (msg.sender === 'user') {
          row.appendChild(bubble);
          row.appendChild(avatar);
        } else {
          row.appendChild(avatar);
          row.appendChild(bubble);
        }
        chatWindow.appendChild(row);
      });
      chatWindow.scrollTop = chatWindow.scrollHeight;
    }

    function addMessage(sender, text, meta = '', extra = {}) {
      state.messages.push({ sender, text, meta, ...extra });
      renderMessages();
      persistMessages();
    }

    function showTyping(show) {
      typing.classList.toggle('hidden', !show);
    }

    async function refreshGamification() {
      try {
        // If an assessment is selected, prefer per-assessment totals
        if (assessmentId) {
          const res = await fetch('token_usage_breakdown.php', { method: 'GET' });
          if (res && res.ok) {
            const data = await res.json();
            if (data && Array.isArray(data.by_assessment)) {
              const as = data.by_assessment.find(a => String(a.assessment_id) === String(assessmentId));
              if (as) {
                const total = 2000; // per-assessment quota
                const used = Number(as.total_used || 0) || 0;
                const remaining = Math.max(0, total - used);
                const points = remaining;
                tokenState.total = total;
                tokenState.remaining = remaining;
                tokenState.points = points;
                if (tokenTotalEl) tokenTotalEl.textContent = String(total);
                if (tokenRemainingEl) tokenRemainingEl.textContent = String(remaining);
                if (tokenUsedEl) tokenUsedEl.textContent = String(used);
                if (tokenPointsEl) tokenPointsEl.textContent = String(points);
                return;
              }
            }
          }
        }

        // Fallback to global gamification endpoint
        const res2 = await fetch('gamification.php', { method: 'GET' });
        if (!res2.ok) return;
        const data2 = await res2.json();
        if (!data2 || !data2.gamification) return;
        const g = data2.gamification;
        const total = Number(g.total_tokens ?? 0) || 0;
        const remaining = Number(g.remaining_tokens ?? 0) || 0;
        const points = Number(g.points ?? 0) || 0;
        const used = g.used_tokens != null ? (Number(g.used_tokens) || 0) : (total - remaining);
        tokenState.total = total;
        tokenState.remaining = remaining;
        tokenState.points = points;
        if (tokenTotalEl) tokenTotalEl.textContent = total.toString();
        if (tokenRemainingEl) tokenRemainingEl.textContent = remaining.toString();
        if (tokenUsedEl) tokenUsedEl.textContent = used >= 0 ? used.toString() : '0';
        if (tokenPointsEl) tokenPointsEl.textContent = points.toString();
      } catch (e) {
        console.warn('Failed to refresh gamification info', e);
      }
    }

    async function sendMessage(e) {
      e.preventDefault();
      const text = chatInput.value.trim();
      if (!text) return;
      // final client-side guard: require >=100 chars and no emoji
      function _containsEmoji(s){
        try{
          return /[\u{1F300}-\u{1F5FF}\u{1F600}-\u{1F64F}\u{1F680}-\u{1F6FF}\u{1F1E0}-\u{1F1FF}]/u.test(s);
        }catch(e){
          return false;
        }
      }
      if (text.length < 100){
        alert('Please provide at least 100 characters in your prompt.');
        return;
      }
      if (_containsEmoji(text)){
        alert('Please remove emoji or unsupported characters from the prompt.');
        return;
      }
      chatInput.value = '';
      autoResizeTextarea();
      // Jalankan pengiriman di background; tidak perlu menunggu untuk
      // mengosongkan input sehingga UX terasa lebih responsif.
      sendMessageCore(text, text);
    }

    async function sendMessageCore(messageText, displayText) {
      if (!messageText) return;
      addMessage('user', displayText);

      const params = new URLSearchParams();
      params.append('driver', 'web');
      params.append('userId', userId);
      params.append('message', messageText);
      // send optional hints to backend
      if (languageSelect && languageSelect.value) params.append('language', languageSelect.value);
      if (responseModeSelect && responseModeSelect.value) params.append('response_mode', responseModeSelect.value);

      showTyping(true);
      try {
        const res = await fetch('botman.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: params.toString(),
        });
        const raw = await res.text();
        let data = null;
        try {
          data = raw ? JSON.parse(raw) : null;
        } catch (parseErr) {
          // Jika backend mengirim HTML/PHP error, tampilkan cuplikan agar lebih jelas
          const snippet = raw && raw.length > 300 ? raw.slice(0, 300) + '…' : raw;
          addMessage('bot', 'Failed to process server response. Response snippet: ' + (snippet || '[empty]'));
          return;
        }
        // Detect backend rate-limit messages (from BotMan) and temporarily disable send button
        try {
          if (data && Array.isArray(data.messages)) {
            const rl = data.messages.some(m => (m.text || '').toLowerCase().includes('rate limit'));
            const isRetrieval = data.messages.some(m => {
              const t = (m.text || '').toLowerCase();
              return t.startsWith('answers are retrieved from the database') || t.startsWith('found similar code in the database') || t.startsWith('found similar code in the database') || t.startsWith('answers taken from database') || t.includes('similarity');
            });
            // Only start rate-limit countdown when the response is NOT a DB retrieval/suggestion
            if (rl && !isRetrieval && sendBtn) {
              startRateLimitCountdown(61);
            }
          }
        } catch (e) { /* ignore */ }

        if (data && Array.isArray(data.messages) && data.messages.length > 0) {
          const msgs = data.messages;
          // Khusus pola antrian GPT: BotMan mengirim dua pesan sekaligus:
          // 1) "Permintaan Anda sedang diproses (antrian)... job_id: ..."
          // 2) "Berikut hasil kodenya:\n<code>"
          // Untuk UX yang lebih rapi, kita gabungkan menjadi satu bubble saja
          if (
            msgs.length >= 2 &&
            typeof msgs[0].text === 'string' &&
            typeof msgs[1].text === 'string' &&
            // Pesan pertama adalah notifikasi antrian dari BotMan (tidak perlu ditampilkan ke user)
            msgs[0].text.startsWith('Your request is being processed (queued).') &&
            msgs[1].text.startsWith('Here is the code result:')
          ) {
            const queueText = msgs[0].text;
            const finalText = msgs[1].text;
            let jobId = '';
            const match = queueText.match(/job_id:\s*([a-f0-9\-]+)/i);
            if (match) jobId = match[1];

            // Take only the code part from the second message (after the first line)
            const lines = finalText.split('\n');
            const codeOnly = lines.slice(1).join('\n') || finalText;
            const meta = jobId
              ? `Result from ChatGPT (queued earlier, job_id: ${jobId}).`
              : 'Result from ChatGPT (queued earlier).';

            // Hanya tampilkan hasil akhirnya, bukan pesan antrian mentah
            addMessage('bot', codeOnly, meta);
          } else {
            // Default: render semua pesan apa adanya
            msgs.forEach(msg => {
              const body = msg.text || '[empty message]';
              // Answers from the database (retrieval/suggestion): baris pertama berisi keterangan sumber
              const isDbRetrieval =
                body.startsWith('Answers taken from database') ||
                body.startsWith('Answers are retrieved from the database') ||
                body.startsWith('Found similar code in database') ||
                body.startsWith('Found similar code in the database');

              if (isDbRetrieval) {
                const lines = body.split('\n');
                const metaLine = lines[0];
                const codeOnly = lines.slice(1).join('\n');
                addMessage('bot', codeOnly || metaLine, metaLine, {
                  source: 'db',
                  originalPrompt: displayText,
                });
              } else {
                const meta = body.startsWith('Result') ? 'Retrieval / similarity info' : '';
                addMessage('bot', body, meta);
              }
            });
          }
        } else {
          addMessage('bot', 'Unknown response from BotMan.');
        }
      } catch (err) {
        addMessage('bot', 'Failed to send message: ' + err);
      } finally {
        showTyping(false);
        // Update token card after each response
        refreshGamification();
      }
    }

    function clearChat() {
      state.messages = [];
      persistMessages();
      renderMessages();
      chatInput.focus();
    }

    function newChat() {
      clearChat();
      addMessage('bot', 'New chat started. Ask me anything about coding.');
    }

    const MAX_TEXTAREA_HEIGHT = 64; // 4rem assuming 16px base font size

    function autoResizeTextarea() {
      if (!chatInput) return;
      chatInput.style.height = 'auto';
      const newHeight = Math.min(chatInput.scrollHeight, MAX_TEXTAREA_HEIGHT);
      chatInput.style.height = newHeight + 'px';
    }

    chatInput.addEventListener('input', autoResizeTextarea);

    // Disable send button if prompt too short or contains emoji
    function validateInputState(){
      if(!sendBtn || !chatInput) return;
      const v = chatInput.value || '';
      let ok = v.trim().length >= 100;
      try{
        ok = ok && !(/[\u{1F300}-\u{1F5FF}\u{1F600}-\u{1F64F}\u{1F680}-\u{1F6FF}\u{1F1E0}-\u{1F1FF}]/u.test(v));
      }catch(e){ }
      sendBtn.disabled = !ok;
      sendBtn.classList.toggle('opacity-50', !ok);
    }
    chatInput.addEventListener('input', validateInputState);
    // initial validation
    validateInputState();

    // Rate-limit countdown UI
    let _rateLimitTimer = null;
    const rateLimitEl = document.getElementById('rate-limit-notice');
    function startRateLimitCountdown(seconds){
      if (!sendBtn) return;
      // clear any existing timer
      if (_rateLimitTimer) {
        clearInterval(_rateLimitTimer);
        _rateLimitTimer = null;
      }
      let remaining = Math.max(1, Math.floor(Number(seconds) || 60));
      sendBtn.disabled = true;
      sendBtn.classList.add('opacity-50');
      if (rateLimitEl) {
        rateLimitEl.classList.remove('hidden');
        rateLimitEl.textContent = `Rate limit reached. Try again in ${remaining}s.`;
      }
      _rateLimitTimer = setInterval(() => {
        remaining -= 1;
        if (rateLimitEl) rateLimitEl.textContent = `Rate limit reached. Try again in ${remaining}s.`;
        if (remaining <= 0) {
          clearInterval(_rateLimitTimer);
          _rateLimitTimer = null;
          if (rateLimitEl) {
            rateLimitEl.classList.add('hidden');
            rateLimitEl.textContent = '';
          }
          // re-validate input (this may re-enable sendBtn if input valid)
          validateInputState();
        }
      }, 1000);
    }

    chatInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage(e);
      }
    });

    newChatBtn.addEventListener('click', newChat);
    clearChatBtn.addEventListener('click', clearChat);

    // Delegate copy buttons
    chatWindow.addEventListener('click', (e) => {
      const copyBtn = e.target.closest('.copy-btn');
      if (copyBtn) {
        const text = copyBtn.dataset.copy || '';
        navigator.clipboard.writeText(text).then(() => {
          copyBtn.textContent = 'Copied';
          setTimeout(() => { copyBtn.textContent = 'Copy'; }, 1500);
        });
        return;
      }

      const gptBtn = e.target.closest('.gpt-generate');
      if (gptBtn) {
        const original = gptBtn.dataset.prompt || '';
        if (original) {
          const FORCE_PREFIX = '__force_gpt__ ';
          // Tampilkan ke user teks aslinya dengan label kecil, kirim ke backend dengan prefix khusus
          sendMessageCore(FORCE_PREFIX + original, original + ' (generate dengan ChatGPT)');
        }
      }
    });

    // Suggestions chip click
    suggestions.addEventListener('click', (e) => {
      const target = e.target.closest('button[data-suggest]');
      if (!target) return;
      chatInput.value = target.dataset.suggest;
      autoResizeTextarea();
      chatInput.focus();
    });

    // Scroll-to-bottom helper
    function ensureScrollBtn() {
      if (scrollBtn) return scrollBtn;
      scrollBtn = document.createElement('button');
      scrollBtn.type = 'button';
      scrollBtn.textContent = '↓ Ke bawah';
      scrollBtn.className = 'hidden fixed right-6 bottom-6 rounded-full bg-slate-900 text-white px-4 py-2 shadow-lg hover:bg-slate-800';
      scrollBtn.addEventListener('click', () => {
        chatWindow.scrollTop = chatWindow.scrollHeight;
        scrollBtn.classList.add('hidden');
      });
      document.body.appendChild(scrollBtn);
      return scrollBtn;
    }

    chatWindow.addEventListener('scroll', () => {
      const btn = ensureScrollBtn();
      const nearBottom = chatWindow.scrollHeight - chatWindow.scrollTop - chatWindow.clientHeight < 120;
      if (nearBottom) {
        btn.classList.add('hidden');
      } else {
        btn.classList.remove('hidden');
      }
    });

    // Hydrate from localStorage on load and seed welcome message if empty
    loadMessages();
    // Ambil informasi token awal untuk mengisi kartu
    refreshGamification();
    if (state.messages.length === 0) {
      addMessage('bot', 'Hello! I\'m ready to help with any questions about programming. Just ask me anything related to coding.');
    }
    chatInput.focus();
    autoResizeTextarea();
  </script>
</body>
</html>
