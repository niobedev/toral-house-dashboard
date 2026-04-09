import { initRecentVisitors } from './charts/recent-visitors.js';
import { initLiveVisitors }   from './charts/live-visitors.js';
import { renderLeaderboard }  from './charts/leaderboard.js';
import { renderHeatmap }      from './charts/heatmap.js';
import { renderHourly }       from './charts/hourly.js';
import { renderWeekday }      from './charts/weekday.js';
import { renderDaily }        from './charts/daily.js';
import { renderDurationDist } from './charts/duration.js';
import { renderNewReturning } from './charts/newreturning.js';
import { renderScatter }      from './charts/scatter.js';

// Charts that auto-refresh: [url, renderer, elementId]
const CHARTS = [
    ['/api/leaderboard',          renderLeaderboard,  'chart-leaderboard'],
    ['/api/heatmap',              renderHeatmap,      'chart-heatmap'],
    ['/api/hourly',               renderHourly,       'chart-hourly'],
    ['/api/weekday',              renderWeekday,      'chart-weekday'],
    ['/api/daily',                renderDaily,        'chart-daily'],
    ['/api/duration-distribution',renderDurationDist, 'chart-duration'],
    ['/api/new-vs-returning',     renderNewReturning, 'chart-newreturning'],
    ['/api/frequency-vs-duration',renderScatter,      'chart-scatter'],
];

function loadAllCharts() {
    CHARTS.forEach(([url, renderer, elementId]) => {
        fetch(url)
            .then(r => r.json())
            .then(data => renderer(elementId, data))
            .catch(err => console.error(`Failed to load ${url}:`, err));
    });
}

let lastSyncedAt = null;

function updateSyncStatus(onNewSync) {
    fetch('/api/sync-status')
        .then(r => r.json())
        .then(data => {
            const el = document.getElementById('sync-status');
            if (!el || !data.synced_at) return;
            const d = new Date(data.synced_at * 1000);
            el.textContent = `Last synced: ${d.toLocaleString()} · ${Number(data.rows_synced).toLocaleString()} total events`;

            if (lastSyncedAt !== null && data.synced_at !== lastSyncedAt) {
                onNewSync();
            }
            lastSyncedAt = data.synced_at;
        })
        .catch(() => {});
}

// Init widgets
initRecentVisitors('recent-visitors');
const liveVisitors = initLiveVisitors('live-visitors');

// Initial chart load
loadAllCharts();
updateSyncStatus(() => {});

// Poll sync status every 10s; reload everything when a new sync is detected
setInterval(() => {
    updateSyncStatus(() => {
        loadAllCharts();
        liveVisitors.refresh();
    });
}, 10_000);

// Full chart refresh every 60s regardless
setInterval(loadAllCharts, 60_000);
