const langDict = {
    en: { title: "⚙️ Aria2DM Extension", rpc: "RPC URL:", secret: "Secret Token:", langLabel: "Language:", saveBtn: "Save Configuration", loading: "⏳ Saving...", success: "✅ Saved! Connected to Aria2 v", errToken: "⚠️ Saved, but Secret Token is incorrect!", errConn: "⚠️ Saved, but failed to connect to RPC URL." },
    id: { title: "⚙️ Ekstensi Aria2DM", rpc: "URL RPC:", secret: "Token Rahasia:", langLabel: "Bahasa:", saveBtn: "Simpan Konfigurasi", loading: "⏳ Menyimpan...", success: "✅ Tersimpan! Terhubung ke Aria2 v", errToken: "⚠️ Tersimpan, tapi Token Rahasia salah!", errConn: "⚠️ Tersimpan, tapi gagal terhubung ke URL RPC." }
};

document.addEventListener('DOMContentLoaded', () => {
    const rpcInput = document.getElementById('rpc_url');
    const secretInput = document.getElementById('rpc_secret');
    const langSelect = document.getElementById('lang_select');
    const saveBtn = document.getElementById('save_btn');
    const statusBox = document.getElementById('status');

    chrome.storage.local.get(['rpc_url', 'rpc_secret', 'lang'], (items) => {
        if (rpcInput) rpcInput.value = items?.rpc_url || 'http://127.0.0.1:6800/jsonrpc';
        if (secretInput) secretInput.value = items?.rpc_secret || '';
        if (langSelect) {
            langSelect.value = items?.lang || 'id';
            applyLanguage(langSelect.value);
        }
    });

    function applyLanguage(langCode) {
        const d = langDict[langCode] || langDict.id;
        document.getElementById('t_title').innerText = d.title;
        document.getElementById('t_rpc').innerText = d.rpc;
        document.getElementById('t_secret').innerText = d.secret;
        document.getElementById('t_lang').innerText = d.langLabel;
        saveBtn.innerText = d.saveBtn;
    }

    langSelect.addEventListener('change', () => applyLanguage(langSelect.value));

    saveBtn.addEventListener('click', () => {
        const url = rpcInput?.value || '';
        const secret = secretInput?.value || '';
        const lang = langSelect?.value || 'id';
        const dict = langDict[lang] || langDict.id;

        statusBox.style.display = 'block';
        statusBox.className = 'mb-4 p-3 rounded text-sm font-semibold text-center bg-yellow-950/50 text-yellow-400 border border-yellow-800';
        statusBox.innerText = dict.loading;

        chrome.storage.local.set({ rpc_url: url, rpc_secret: secret, lang: lang }, () => {
            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    jsonrpc: '2.0', id: 'ext_test', method: 'aria2.getVersion', params: ['token:' + secret]
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data?.result?.version) {
                    statusBox.className = 'mb-4 p-3 rounded text-sm font-semibold text-center bg-green-950/50 text-green-400 border border-green-800';
                    statusBox.innerText = dict.success + data.result.version;
                } else if (data?.error?.code === 1) {
                    statusBox.className = 'mb-4 p-3 rounded text-sm font-semibold text-center bg-red-950/50 text-red-400 border border-red-800';
                    statusBox.innerText = dict.errToken;
                } else {
                    statusBox.className = 'mb-4 p-3 rounded text-sm font-semibold text-center bg-red-950/50 text-red-400 border border-red-800';
                    statusBox.innerText = dict.errConn;
                }
            })
            .catch(() => {
                statusBox.className = 'mb-4 p-3 rounded text-sm font-semibold text-center bg-red-950/50 text-red-400 border border-red-800';
                statusBox.innerText = dict.errConn;
            });
        });
    });
});