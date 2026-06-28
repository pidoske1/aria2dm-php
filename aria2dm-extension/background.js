const bgDict = {
    en: {
        ctxDownload: "Download with Aria2DM",
        ctxDashboard: "🌐 Open Aria2DM Dashboard",
        errTitle: "Failed",
        errBody: "Aria2 Error: ",
        okTitle: "Success!",
        okBody: "Download successfully sent to Aria2DM queue.",
        connTitle: "Connection Failed",
        connBody: "Failed to contact RPC. Check extension IP settings."
    },
    id: {
        ctxDownload: "Download dengan Aria2DM",
        ctxDashboard: "🌐 Buka Dashboard Aria2DM",
        errTitle: "Gagal",
        errBody: "Error Aria2: ",
        okTitle: "Sukses!",
        okBody: "Unduhan berhasil dikirim ke antrean Aria2DM.",
        connTitle: "Koneksi Gagal",
        connBody: "Gagal menghubungi RPC. Periksa setelan IP ekstensi."
    }
};

function setupContextMenus(lang = 'id') {
    const dict = bgDict[lang] || bgDict['id'];
    
    chrome.contextMenus.removeAll(() => {
        chrome.contextMenus.create({
            id: "download_aria2dm",
            title: dict.ctxDownload,
            contexts: ["link", "image", "video", "audio"]
        });

        chrome.contextMenus.create({
            id: "open_aria2dm_dashboard",
            title: dict.ctxDashboard,
            contexts: ["all"]
        });
    });
}

chrome.runtime.onInstalled.addListener(() => {
    chrome.storage.local.get(['lang'], (items) => {
        setupContextMenus(items?.lang || 'id');
    });
});

chrome.storage.onChanged.addListener((changes, namespace) => {
    if (namespace === 'local' && changes.lang && changes.lang.newValue) {
        setupContextMenus(changes.lang.newValue);
    }
});

chrome.contextMenus.onClicked.addListener((info, tab) => {
    if (info.menuItemId === "open_aria2dm_dashboard") {
        chrome.storage.local.get(['rpc_url'], (items) => {
            const rpcUrl = items?.rpc_url || 'http://127.0.0.1:6800/jsonrpc';
            try {
                const parsedUrl = new URL(rpcUrl);
                chrome.tabs.create({ url: parsedUrl.protocol + "//" + parsedUrl.hostname + "/aria2dm" });
            } catch (e) {
                chrome.tabs.create({ url: 'http://127.0.0.1/aria2dm' });
            }
        });
    } 
    else if (info.menuItemId === "download_aria2dm") {
        const downloadUrl = info.linkUrl || info.srcUrl || info.pageUrl;
        if (downloadUrl) {
            sendToAria2(downloadUrl);
        }
    }
});

function showNotification(title, message) {
    chrome.notifications.create({
        type: 'basic',
        iconUrl: 'icon.png',
        title: title,
        message: message
    });
}

function sendToAria2(url) {
    chrome.storage.local.get(['rpc_url', 'rpc_secret', 'lang'], (items) => {
        const rpcUrl = items?.rpc_url || 'http://127.0.0.1:6800/jsonrpc';
        const rpcSecret = items?.rpc_secret || '';
        const userLang = items?.lang || 'id';
        const dict = bgDict[userLang] || bgDict['id'];

        const payload = {
            jsonrpc: "2.0", 
            id: "aria2dm_ext_" + Date.now(),
            method: "aria2.addUri", 
            params: ["token:" + rpcSecret, [url]]
        };

        fetch(rpcUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                showNotification(dict.errTitle, dict.errBody + data.error.message);
            } else {
                showNotification(dict.okTitle, dict.okBody);
            }
        })
        .catch(err => {
            showNotification(dict.connTitle, dict.connBody);
        });
    });
}