<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Deep Agents Chat</title>
    <script src="https://cdn.jsdelivr.net/npm/marked@12/marked.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dompurify@3/dist/purify.min.js"></script>
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
            background: radial-gradient(1200px 600px at 50% -10%, #1e293b, #0b1120);
            color: #e2e8f0;
        }
        .card {
            width: 100%;
            max-width: 640px;
            background: #0f172a;
            border: 1px solid #1e293b;
            border-radius: 16px;
            box-shadow: 0 24px 60px rgba(0,0,0,.45);
            display: flex;
            flex-direction: column;
            height: min(80vh, 720px);
            overflow: hidden;
        }
        header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #1e293b;
        }
        header h1 { margin: 0; font-size: 1.05rem; font-weight: 600; }
        header p { margin: .2rem 0 0; font-size: .8rem; color: #94a3b8; }
        header code { color: #7dd3fc; }
        #log {
            flex: 1;
            overflow-y: auto;
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            gap: .75rem;
        }
        .msg { max-width: 80%; padding: .6rem .85rem; border-radius: 14px; line-height: 1.45; white-space: pre-wrap; word-wrap: break-word; }
        .user { align-self: flex-end; background: #2563eb; color: #fff; border-bottom-right-radius: 4px; }
        .bot { align-self: flex-start; background: #1e293b; color: #e2e8f0; border-bottom-left-radius: 4px; }
        .bot.thinking { color: #94a3b8; font-style: italic; }
        .tool {
            align-self: stretch;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: .76rem;
            color: #7dd3fc;
            background: #0b1120;
            border: 1px dashed #334155;
            border-radius: 8px;
            padding: .4rem .6rem;
        }
        .empty { margin: auto; color: #64748b; font-size: .9rem; text-align: center; }
        form { display: flex; gap: .5rem; padding: .85rem; border-top: 1px solid #1e293b; }
        input[type=text] {
            flex: 1;
            background: #1e293b;
            border: 1px solid #334155;
            color: #e2e8f0;
            border-radius: 10px;
            padding: .65rem .85rem;
            font-size: .95rem;
            outline: none;
        }
        input[type=text]:focus { border-color: #2563eb; }
        button {
            background: #2563eb;
            color: #fff;
            border: 0;
            border-radius: 10px;
            padding: 0 1.1rem;
            font-weight: 600;
            cursor: pointer;
        }
        button:disabled { opacity: .5; cursor: not-allowed; }
        .approval {
            align-self: stretch;
            background: #1c1206;
            border: 1px solid #b45309;
            border-radius: 12px;
            padding: .75rem .9rem;
            font-size: .85rem;
        }
        .approval .head { color: #fbbf24; font-weight: 600; margin-bottom: .5rem; }
        .approval .calls {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: .8rem;
            color: #fde68a;
            white-space: pre-wrap;
            margin-bottom: .65rem;
        }
        .approval .actions { display: flex; gap: .5rem; }
        .approval button { padding: .4rem .8rem; font-size: .82rem; border-radius: 8px; }
        .approval .approve { background: #16a34a; }
        .approval .reject { background: #334155; }
        .msg.markdown { white-space: normal; }
        .msg.markdown > :first-child { margin-top: 0; }
        .msg.markdown > :last-child { margin-bottom: 0; }
        .msg.markdown p { margin: .5rem 0; }
        .msg.markdown ul, .msg.markdown ol { margin: .5rem 0; padding-left: 1.25rem; }
        .msg.markdown li { margin: .15rem 0; }
        .msg.markdown h1, .msg.markdown h2, .msg.markdown h3 { margin: .6rem 0 .3rem; font-size: 1.02rem; }
        .msg.markdown a { color: #7dd3fc; }
        .msg.markdown code { background: #0b1120; padding: .1rem .3rem; border-radius: 4px; font-size: .85em; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
        .msg.markdown pre { background: #0b1120; padding: .6rem .8rem; border-radius: 8px; overflow-x: auto; }
        .msg.markdown pre code { background: none; padding: 0; }
        .msg.markdown blockquote { margin: .5rem 0; padding-left: .7rem; border-left: 3px solid #334155; color: #94a3b8; }
        .msg.markdown table { border-collapse: collapse; margin: .5rem 0; }
        .msg.markdown th, .msg.markdown td { border: 1px solid #334155; padding: .3rem .5rem; }
    </style>
</head>
<body>
    <div class="card">
        <header>
            <h1>🧠 Deep Agents Chat</h1>
            <p>powered by <code>twdnhfr/laravel-deepagents</code> → OpenRouter</p>
        </header>

        <div id="log"><div class="empty">Ask me anything…</div></div>

        <form id="chat">
            <input type="text" id="message" name="message" placeholder="Try: weather in Tokyo?  ·  roll a d20  ·  fetch the report on vector databases  ·  delete records older than 30 days" autocomplete="off" autofocus>
            <button type="submit" id="send">Send</button>
        </form>
    </div>

    <script>
        const log = document.getElementById('log');
        const form = document.getElementById('chat');
        const input = document.getElementById('message');
        const send = document.getElementById('send');
        const csrf = document.querySelector('meta[name="csrf-token"]').content;

        const canMarkdown = window.marked && window.DOMPurify;

        function bubble(text, who, markdown = false) {
            const empty = log.querySelector('.empty');
            if (empty) empty.remove();
            const el = document.createElement('div');
            el.className = 'msg ' + who;
            if (markdown && canMarkdown) {
                el.classList.add('markdown');
                el.innerHTML = DOMPurify.sanitize(marked.parse(text)); // sanitize untrusted model output
            } else {
                el.textContent = text;
            }
            log.appendChild(el);
            log.scrollTop = log.scrollHeight;
            return el;
        }

        function fmtArgs(args) {
            return Object.entries(args || {}).map(([k, v]) => `${k}: ${JSON.stringify(v)}`).join(', ');
        }

        // Only one approval can be live per conversation; visually retire the rest.
        function supersedeApprovals() {
            log.querySelectorAll('.approval').forEach((card) => {
                if (card.dataset.resolved) return;
                card.dataset.resolved = '1';
                card.querySelectorAll('button').forEach((b) => (b.disabled = true));
                card.style.opacity = '.5';
                const note = document.createElement('div');
                note.style.cssText = 'margin-top:.45rem;color:#94a3b8;font-size:.72rem;';
                note.textContent = '— superseded';
                card.appendChild(note);
            });
        }

        async function post(url, body) {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(body),
            });
            return res.json();
        }

        // Render a server response by status: done | approval | rejected.
        function respond(data) {
            if (data.status === 'approval') {
                renderApproval(data);
                return;
            }
            if (data.status === 'rejected' || data.status === 'stale') {
                bubble(data.reply, 'bot', true);
                return;
            }
            (data.tools || []).forEach((t) => {
                const line = document.createElement('div');
                line.className = 'tool';
                line.textContent = `🔧 ${t.name}(${fmtArgs(t.arguments)}) → ${t.result}`;
                log.appendChild(line);
            });
            bubble(data.reply ?? '(no reply)', 'bot', true);
            log.scrollTop = log.scrollHeight;
        }

        function renderApproval(data) {
            supersedeApprovals(); // a newer approval retires any older one
            const card = document.createElement('div');
            card.className = 'approval';

            const head = document.createElement('div');
            head.className = 'head';
            head.textContent = '⚠️ The agent needs your approval to run:';

            const calls = document.createElement('div');
            calls.className = 'calls';
            calls.textContent = data.pending.map((c) => `${c.name}(${fmtArgs(c.arguments)})`).join('\n');

            const actions = document.createElement('div');
            actions.className = 'actions';
            const approve = document.createElement('button');
            approve.className = 'approve';
            approve.textContent = 'Approve & run';
            const reject = document.createElement('button');
            reject.className = 'reject';
            reject.textContent = 'Reject';
            approve.onclick = () => decide(card, data.token, true);
            reject.onclick = () => decide(card, data.token, false);
            actions.append(approve, reject);

            card.append(head, calls, actions);
            log.appendChild(card);
            log.scrollTop = log.scrollHeight;
        }

        async function decide(card, token, approve) {
            card.dataset.resolved = '1';
            card.querySelectorAll('button').forEach((b) => (b.disabled = true));
            const thinking = bubble(approve ? 'running…' : 'cancelling…', 'bot thinking');
            try {
                const data = await post('/chat/approve', { token, approve });
                thinking.remove();
                respond(data);
            } catch (err) {
                if (thinking.isConnected) thinking.remove();
                bubble('⚠️ ' + err.message, 'bot');
            }
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const message = input.value.trim();
            if (!message) return;

            bubble(message, 'user');
            supersedeApprovals(); // sending a new message retires any open approval
            input.value = '';
            input.disabled = send.disabled = true;
            const thinking = bubble('thinking…', 'bot thinking');

            try {
                const data = await post('/chat', { message });
                thinking.remove();
                respond(data);
            } catch (err) {
                if (thinking.isConnected) thinking.remove();
                bubble('⚠️ ' + err.message, 'bot');
            } finally {
                input.disabled = send.disabled = false;
                input.focus();
                log.scrollTop = log.scrollHeight;
            }
        });
    </script>
</body>
</html>
