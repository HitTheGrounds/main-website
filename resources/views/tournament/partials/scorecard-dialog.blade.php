<dialog id="match-scorecard" aria-labelledby="scorecard-title" class="tc-scorecard">
    <header><h2 id="scorecard-title">Match scorecard</h2>
        <form method="dialog"><button class="btn btn-sm" autofocus>Close</button></form>
    </header>
    <div data-scorecard-body></div>
</dialog>
<script>
    (() => {
        const dialog = document.getElementById('match-scorecard');
        const body = dialog.querySelector('[data-scorecard-body]');
        let request;
        document.addEventListener('click', async event => {
            const opener = event.target.closest('[data-scorecard]');
            if (!opener) return;
            request?.abort();
            const controller = new AbortController();
            request = controller;
            body.innerHTML = '<p role="status">Loading scorecard…</p>';
            dialog.showModal();
            try {
                const response = await fetch(opener.dataset.scorecard, {signal: controller.signal});
                if (!response.ok) throw new Error();
                const html = await response.text();
                if (!controller.signal.aborted) body.innerHTML = html;
            } catch {
                if (!controller.signal.aborted) body.innerHTML = '<p role="alert">The scorecard could not be loaded. Close and reopen it to try again.</p>';
            }
        });
        dialog.addEventListener('close', () => request?.abort());
        dialog.addEventListener('change', event => {
            if (event.target.name !== 'innings') return;
            for (const panel of body.querySelectorAll('[data-innings]')) {
                panel.hidden = panel.dataset.innings !== event.target.value;
            }
        });
    })();
</script>
