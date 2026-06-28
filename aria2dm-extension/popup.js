const langDict = {
    en: {
        title: "Aria2DM Status",
        dashTitle: "Open Full Dashboard",
        loading: "Loading data...",
        errConn: "Failed to connect to Aria2 Server",
        noTask: "No active downloads.",
        unknownFile: "Unknown File",
        paused: "Paused",
        actResume: "Resume",
        actPause: "Pause",
        actDelete: "Delete",
        modTitle: "<i class='fa-solid fa-triangle-exclamation text-red-500 mr-1'></i> Confirm Deletion",
        modDesc: "Remove this task from the queue?<br><span>(Physical files on the server will not be deleted)</span>",
        btnCancel: "Cancel",
        btnConfirm: "Yes, Delete"
    },
    id: {
        title: "Status Aria2DM",
        dashTitle: "Buka Dashboard Penuh",
        loading: "Memuat data...",
        errConn: "Gagal terhubung ke Server Aria2",
        noTask: "Tidak ada unduhan berjalan.",
        unknownFile: "File Tidak Diketahui",
        paused: "Dihentikan",
        actResume: "Lanjutkan",
        actPause: "Jeda",
        actDelete: "Hapus",
        modTitle: "<i class='fa-solid fa-triangle-exclamation text-red-500 mr-1'></i> Konfirmasi Hapus",
        modDesc: "Hapus tugas ini dari antrean?<br><span>(File fisik di server tidak akan terhapus)</span>",
        btnCancel: "Batal",
        btnConfirm: "Ya, Hapus"
    }
};

let rpcUrl = '';
let rpcSecret = '';
let currentLang = 'id';
let t = langDict['id'];
let refreshInterval;
let pendingDeleteGid = null; 

document.addEventListener('DOMContentLoaded', () => {
    chrome.storage.local.get(['rpc_url', 'rpc_secret', 'lang'], (items) => {
        rpcUrl = items.rpc_url || 'http://127.0.0.1:6800/jsonrpc';
        rpcSecret = items.rpc_secret || '';
        currentLang = items.lang || 'id';
        t = langDict[currentLang] || langDict['en'];

        document.getElementById('popup_title').innerHTML = `<i class="fa-solid fa-cloud-arrow-down"></i> ${t.title}`;
        document.getElementById('btn_open_dash').title = t.dashTitle;
        document.getElementById('empty_state').innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${t.loading}`;
        document.getElementById('modal_title').innerHTML = t.modTitle;
        document.getElementById('modal_desc').innerHTML = t.modDesc;
        document.getElementById('btn_cancel_del').innerText = t.btnCancel;
        document.getElementById('btn_confirm_del').innerText = t.btnConfirm;
        
        fetchDownloads();
        refreshInterval = setInterval(fetchDownloads, 1000); 
    });

    document.getElementById('btn_open_dash').addEventListener('click', () => {
        try {
            const parsedUrl = new URL(rpcUrl);
            chrome.tabs.create({ url: parsedUrl.protocol + "//" + parsedUrl.hostname + "/aria2dm" });
        } catch (e) {
            chrome.tabs.create({ url: 'http://127.0.0.1/aria2dm' });
        }
    });

    document.getElementById('btn_cancel_del').addEventListener('click', () => {
        document.getElementById('confirm_modal').style.display = 'none';
        pendingDeleteGid = null;
    });

    document.getElementById('btn_confirm_del').addEventListener('click', async () => {
        if (pendingDeleteGid) {
            await rpcCall('aria2.forceRemove', [pendingDeleteGid]);
            document.getElementById('confirm_modal').style.display = 'none';
            pendingDeleteGid = null;
            fetchDownloads();
        }
    });
});

function rpcCall(method, params = []) {
    const payload = {
        jsonrpc: "2.0", id: "ext_popup_" + Date.now(),
        method: method, params: ["token:" + rpcSecret, ...params]
    };
    return fetch(rpcUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    }).then(res => res.json()).catch(() => null);
}

async function fetchDownloads() {
    if (document.getElementById('confirm_modal').style.display === 'flex') return;

    const listEl = document.getElementById('downloads_list');
    const activeData = await rpcCall('aria2.tellActive');
    
    if (!activeData || !activeData.result) {
        listEl.innerHTML = `<div id="empty_state" style="color:#ef4444;"><i class="fa-solid fa-link-slash mb-2 text-2xl"></i><br>${t.errConn}</div>`;
        return;
    }

    const waitingData = await rpcCall('aria2.tellWaiting', [0, 10]);
    let allTasks = activeData.result;
    
    if (waitingData && waitingData.result) {
        allTasks = allTasks.concat(waitingData.result);
    }

    if (allTasks.length === 0) {
        listEl.innerHTML = `<div id="empty_state"><i class="fa-solid fa-mug-hot mb-2 text-2xl text-gray-600"></i><br>${t.noTask}</div>`;
        return;
    }

    let html = '';
    allTasks.forEach(task => {
        let name = t.unknownFile;
        if (task.files && task.files[0] && task.files[0].path) {
            name = task.files[0].path.split(/[/\\]/).pop();
        } else if (task.files && task.files[0] && task.files[0].uris && task.files[0].uris[0]) {
            let u = task.files[0].uris[0].uri;
            name = u.substring(u.lastIndexOf('/') + 1) || t.unknownFile;
        }

        let total = parseInt(task.totalLength) || 1;
        let completed = parseInt(task.completedLength) || 0;
        let pct = total > 1 ? ((completed / total) * 100).toFixed(1) : 0;
        
        let speed = parseInt(task.downloadSpeed) || 0;
        let speedStr = speed > 1048576 ? (speed/1048576).toFixed(2) + ' MB/s' : (speed/1024).toFixed(2) + ' KB/s';
        
        let isPaused = task.status === 'paused';
        let statusText = isPaused ? t.paused : speedStr;

        html += `
            <div class="task-card">
                <div class="task-name" title="${name}">${name}</div>
                <div class="task-stats">
                    <span>${pct}%</span>
                    <span>${statusText}</span>
                </div>
                <div class="progress-bg">
                    <div class="progress-bar" style="width: ${pct}%"></div>
                </div>
                <div class="action-btns">
                    ${isPaused ? 
                        `<button class="btn-action btn-resume" data-gid="${task.gid}" data-action="unpause" title="${t.actResume}"><i class="fa-solid fa-play"></i></button>` : 
                        `<button class="btn-action btn-pause" data-gid="${task.gid}" data-action="pause" title="${t.actPause}"><i class="fa-solid fa-pause"></i></button>`
                    }
                    <button class="btn-action btn-delete" data-gid="${task.gid}" data-action="remove" title="${t.actDelete}"><i class="fa-solid fa-trash"></i></button>
                </div>
            </div>
        `;
    });
    
    listEl.innerHTML = html;

    document.querySelectorAll('.btn-action').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            const btnEl = e.currentTarget;
            const gid = btnEl.getAttribute('data-gid');
            const action = btnEl.getAttribute('data-action');
            
            if (action === 'remove') {
                pendingDeleteGid = gid;
                document.getElementById('confirm_modal').style.display = 'flex';
            } else if (action === 'pause') {
                await rpcCall('aria2.forcePause', [gid]);
                fetchDownloads();
            } else if (action === 'unpause') {
                await rpcCall('aria2.unpause', [gid]);
                fetchDownloads();
            }
        });
    });
}