{{-- Carta Gantt Styles --}}
<style>
/* ===== STATS ===== */
.sst-stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.85rem;margin-bottom:1.5rem}
.sst-stat-card{display:flex;align-items:center;gap:.75rem;background:var(--surface-color);border:1px solid var(--surface-border);border-radius:12px;padding:.85rem 1rem}
.sst-stat-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:#fff;flex-shrink:0}
.sst-stat-label{font-size:.68rem;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted);font-weight:600}
.sst-stat-value{font-size:1.35rem;font-weight:800;line-height:1.2;color:var(--text-main)}
.sst-progress-track{height:6px;background:var(--surface-border);border-radius:3px;margin-top:.3rem;overflow:hidden}
.sst-progress-fill{height:100%;background:linear-gradient(90deg,#6366f1,#818cf8);border-radius:3px;transition:width .6s ease}
.sst-help-tooltip{display:inline-flex;align-items:center;justify-content:center;width:16px;height:16px;margin-left:.2rem;border-radius:999px;border:1px solid var(--surface-border);color:var(--text-muted);font-size:.62rem;line-height:1;cursor:help;text-transform:none;letter-spacing:0;vertical-align:middle}
.sst-help-tooltip:hover{color:var(--accent-color,#6366f1);border-color:rgba(99,102,241,.35);background:rgba(99,102,241,.08)}

/* ===== QUICK ACTIONS ===== */
.sst-quick-actions{background:var(--surface-color);border:1px solid rgba(99,102,241,.2);border-radius:12px;margin:0 0 1.2rem;box-shadow:var(--shadow-sm);overflow:hidden}
.sst-quick-actions-head{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;padding:.85rem 1rem;border-bottom:1px solid var(--surface-border);background:rgba(99,102,241,.045)}
.sst-quick-actions-title{font-size:.9rem;font-weight:800;color:var(--text-main);display:flex;align-items:center;gap:.4rem}
.sst-quick-actions-title i{color:var(--accent-color,#6366f1)}
.sst-quick-actions-subtitle{font-size:.74rem;color:var(--text-muted);margin-top:.15rem;line-height:1.35}
.sst-quick-actions-count{font-size:.68rem;font-weight:800;color:var(--accent-color,#6366f1);background:rgba(99,102,241,.12);border:1px solid rgba(99,102,241,.2);border-radius:999px;padding:.2rem .55rem;white-space:nowrap}
.sst-quick-actions-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:.65rem;padding:.85rem 1rem}
.sst-quick-card{border:1px solid var(--surface-border);background:var(--bg-color);border-radius:10px;padding:.7rem .75rem;display:flex;flex-direction:column;gap:.65rem}
.sst-quick-card-vencida{border-color:rgba(239,68,68,.22);background:rgba(239,68,68,.045)}
.sst-quick-card-parcial{border-color:rgba(245,158,11,.24);background:rgba(245,158,11,.045)}
.sst-quick-card-pendiente{border-color:rgba(99,102,241,.22);background:rgba(99,102,241,.045)}
.sst-quick-card-main{display:flex;gap:.6rem;align-items:flex-start}
.sst-quick-icon{width:32px;height:32px;border-radius:9px;display:inline-flex;align-items:center;justify-content:center;background:var(--surface-color);border:1px solid var(--surface-border);color:var(--accent-color,#6366f1);flex-shrink:0}
.sst-quick-card-vencida .sst-quick-icon{color:#ef4444}.sst-quick-card-parcial .sst-quick-icon{color:#f59e0b}
.sst-quick-name{font-size:.84rem;font-weight:800;color:var(--text-main);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%}
.sst-quick-meta{font-size:.68rem;color:var(--text-muted);margin-top:.05rem}
.sst-quick-summary{font-size:.74rem;color:var(--text-main);font-weight:600;margin-top:.25rem;line-height:1.35}
.sst-quick-buttons{display:flex;gap:.35rem;flex-wrap:wrap}

/* ===== TOOLBAR ===== */
.sst-toolbar{display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:1.2rem;padding:.6rem .8rem;background:var(--surface-color);border:1px solid var(--surface-border);border-radius:10px}
.sst-view-switcher{display:flex;gap:.25rem;background:var(--bg-color);padding:3px;border-radius:8px}
.sst-view-btn{padding:.35rem .65rem;border:none;border-radius:6px;font-size:.78rem;font-weight:600;cursor:pointer;background:transparent;color:var(--text-muted);transition:all .2s;display:flex;align-items:center;gap:.35rem}
.sst-view-btn:hover{color:var(--text-main);background:var(--surface-border)}
.sst-view-btn.active{background:var(--accent-color);color:#fff;box-shadow:0 2px 4px rgba(99,102,241,.25)}
.sst-period-nav{display:flex;align-items:center;gap:.3rem}
.sst-legend{display:flex;gap:.85rem;font-size:.74rem;color:var(--text-muted);align-items:center;margin-left:auto;flex-wrap:wrap}
.sst-legend span{display:flex;align-items:center;gap:.3rem}
.sst-activity-filter{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin:-.45rem 0 1.2rem;padding:.75rem .9rem;background:var(--surface-color);border:1px solid var(--surface-border);border-radius:10px;box-shadow:var(--shadow-sm)}
.sst-activity-filter-title{font-size:.82rem;font-weight:800;color:var(--text-main);display:flex;align-items:center;gap:.35rem}
.sst-activity-filter-title i{color:var(--accent-color,#6366f1)}
.sst-activity-filter-help{font-size:.72rem;color:var(--text-muted);margin-top:.12rem;line-height:1.35}
.sst-activity-filter-buttons{display:flex;gap:.3rem;flex-wrap:wrap;justify-content:flex-end}
.sst-filter-btn{border:1px solid var(--surface-border);background:var(--bg-color);color:var(--text-muted);border-radius:999px;padding:.32rem .62rem;font-size:.74rem;font-weight:800;cursor:pointer;transition:all .16s}
.sst-filter-btn:hover{color:var(--text-main);border-color:rgba(99,102,241,.35);background:rgba(99,102,241,.07)}
.sst-filter-btn.active{background:var(--accent-color,#6366f1);border-color:var(--accent-color,#6366f1);color:#fff;box-shadow:0 2px 6px rgba(99,102,241,.22)}
.sst-filter-btn span{display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;margin-left:.25rem;border-radius:999px;background:rgba(148,163,184,.16);color:inherit;font-size:.66rem;line-height:1;padding:0 .28rem}
.sst-filter-btn.active span{background:rgba(255,255,255,.22);color:#fff}
.sst-filter-empty{align-items:center;gap:.75rem;margin:-.7rem 0 1.2rem;padding:.8rem .95rem;border:1px dashed rgba(99,102,241,.35);border-radius:10px;background:rgba(99,102,241,.06);color:var(--text-main)}
.sst-filter-empty>i{font-size:1.05rem;color:var(--accent-color,#6366f1)}
.sst-filter-empty div{display:flex;flex-direction:column;gap:.12rem;min-width:0;flex:1}
.sst-filter-empty strong{font-size:.84rem}
.sst-filter-empty span{font-size:.74rem;color:var(--text-muted)}

/* ===== CATEGORY CARDS ===== */
.sst-cat-card{background:var(--surface-color);border:1px solid var(--surface-border);border-radius:12px;margin-bottom:1.1rem;overflow:hidden}
.sst-cat-header{display:flex;align-items:center;justify-content:space-between;padding:.75rem 1rem;border-bottom:1px solid var(--surface-border);flex-wrap:wrap;gap:.5rem}
.sst-cat-icon{width:34px;height:34px;border-radius:8px;background:linear-gradient(135deg,var(--accent-color),#818cf8);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.85rem;flex-shrink:0}
.sst-cat-title{font-size:.92rem;font-weight:700;margin:0;color:var(--text-main)}
.sst-cat-progress{width:80px;height:6px;background:var(--surface-border);border-radius:3px;overflow:hidden;flex-shrink:0}
.sst-cat-progress-fill{height:100%;background:linear-gradient(90deg,#10b981,#34d399);border-radius:3px}

/* ===== ADD FORM / ADD CATEGORY ===== */
.sst-add-form{padding:.85rem 1rem;border-bottom:1px solid var(--surface-border);background:var(--bg-color)}
.sst-add-cat-card{background:var(--surface-color);border:1px solid var(--surface-border);border-radius:12px;padding:1rem;margin-bottom:1.1rem}
.sst-label{font-size:.68rem;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted);font-weight:600;display:block;margin-bottom:.2rem}
.sst-mes-check{font-size:.75rem;display:flex;align-items:center;gap:.2rem;cursor:pointer;padding:.15rem .4rem;border-radius:6px;border:1px solid var(--surface-border);background:var(--surface-color);transition:background .2s}
.sst-mes-check:has(input:checked){background:rgba(99,102,241,.12);border-color:rgba(99,102,241,.3)}

/* ===== GANTT TABLE ===== */
.sst-table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}
.sst-gantt{width:100%;border-collapse:separate;border-spacing:0;font-size:.82rem}
.sst-gantt thead th{position:sticky;top:0;background:var(--surface-color);padding:.45rem .4rem;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.03em;color:var(--text-muted);border-bottom:2px solid var(--surface-border);text-align:center;white-space:nowrap;z-index:2}
.sst-gantt .sst-th-sticky{position:sticky;left:0;background:var(--surface-color);z-index:3;text-align:left}
.sst-gantt tbody td{padding:.4rem;border-bottom:1px solid var(--surface-border);vertical-align:middle}
.sst-gantt tbody td.sst-th-sticky{position:sticky;left:0;background:var(--surface-color);z-index:1}
.sst-gantt .sst-actions-sticky{position:sticky;right:0;background:var(--surface-color);z-index:3;box-shadow:-10px 0 14px -14px rgba(15,23,42,.65)}
.sst-gantt tbody td.sst-actions-sticky{z-index:2}
.sst-td-mes{text-align:center;min-width:38px}

/* Current month highlight */
.sst-mes-actual{background:rgba(99,102,241,.06)!important}
body.dark-mode .sst-mes-actual{background:rgba(99,102,241,.1)!important}

/* Gantt cells */
.gantt-cell{min-width:30px;height:30px;border-radius:6px;border:none;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;font-size:.68rem;font-weight:700;transition:all .2s;padding:0 3px}
.gantt-cell:focus-visible,.sst-btn:focus-visible,.sst-icon-btn:focus-visible{outline:2px solid var(--accent-color,#6366f1);outline-offset:2px}
.gantt-plan{background:rgba(99,102,241,.1);color:#6366f1;border:1.5px solid rgba(99,102,241,.25)}
.gantt-plan:hover{background:rgba(99,102,241,.2);transform:scale(1.1)}
.gantt-done{background:rgba(16,185,129,.15);color:#10b981;border:1.5px solid rgba(16,185,129,.3)}
.gantt-done:hover{background:rgba(16,185,129,.25);transform:scale(1.1)}
.gantt-overdue{background:rgba(239,68,68,.12);color:#ef4444;border:1.5px solid rgba(239,68,68,.3);animation:pulse-overdue 2s ease-in-out infinite}
.gantt-overdue:hover{background:rgba(239,68,68,.2);transform:scale(1.1)}
@keyframes pulse-overdue{0%,100%{opacity:1}50%{opacity:.65}}
.gantt-partial{background:rgba(245,158,11,.12);color:#f59e0b;border:1.5px solid rgba(245,158,11,.3);font-size:.55rem}
.gantt-partial:hover{background:rgba(245,158,11,.2);transform:scale(1.1)}

/* Activity row hover */
.sst-act-row:hover td{background-color:rgba(99,102,241,.03)}
.sst-act-row.sst-row-focus td{background-color:rgba(99,102,241,.11)!important;transition:background-color .25s ease}

/* ===== BUTTONS ===== */
.sst-btn{display:inline-flex;align-items:center;gap:.4rem;padding:.45rem .85rem;border-radius:8px;border:none;font-size:.82rem;font-weight:600;cursor:pointer;transition:all .2s;text-decoration:none;line-height:1.3}
.sst-btn-sm{padding:.3rem .6rem;font-size:.75rem;border-radius:6px}
.sst-btn-primary{background:var(--accent-color,#6366f1);color:#fff}.sst-btn-primary:hover{opacity:.85;transform:translateY(-1px)}
.sst-btn-outline{background:transparent;color:var(--text-main);border:1px solid var(--surface-border)}.sst-btn-outline:hover{background:var(--surface-color);border-color:var(--text-muted)}
.sst-btn-danger{background:#fee2e2;color:#dc2626;border:1px solid #fecaca}.sst-btn-danger:hover{background:#fecaca}
.sst-icon-btn{width:32px;height:32px;border-radius:8px;border:1px solid var(--surface-border);background:var(--surface-color);display:inline-flex;align-items:center;justify-content:center;cursor:pointer;color:var(--text-muted);transition:all .15s;font-size:.85rem;position:relative}
.sst-icon-btn:hover{color:var(--text-main);border-color:var(--text-muted);background:var(--bg-color)}
.sst-icon-btn-xs{width:26px;height:26px;font-size:.72rem;border-radius:6px}
.sst-icon-btn-danger:hover{color:#dc2626;border-color:#fecaca;background:#fee2e2}
.sst-link{background:none;border:none;color:var(--accent-color);cursor:pointer;font-size:inherit;text-decoration:underline;padding:0;font-weight:600}

/* ===== MODALS ===== */
.sst-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1050;display:flex;align-items:center;justify-content:center;padding:1rem;backdrop-filter:blur(4px)}
.sst-modal{background:var(--surface-color);border:1px solid var(--surface-border);border-radius:14px;width:100%;max-width:550px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.2)}
.sst-modal-header{display:flex;align-items:center;justify-content:space-between;padding:.85rem 1.1rem;border-bottom:1px solid var(--surface-border)}
.sst-modal-body{padding:1.1rem}

/* ===== PLANS ROW ===== */
.sst-planes-row td{border-bottom:2px solid var(--surface-border)}

/* ===== DETAIL CARDS (for modal) ===== */
.sst-detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:.6rem;margin-bottom:1rem}
.sst-detail-item{background:var(--bg-color);border-radius:8px;padding:.55rem .7rem}
.sst-detail-item .sst-label{margin-bottom:.1rem}
.sst-detail-item .sst-detail-value{font-size:.88rem;font-weight:600;color:var(--text-main)}
.sst-seg-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:.35rem;margin-top:.5rem}
.sst-seg-cell{text-align:center;padding:.3rem;border-radius:6px;font-size:.7rem;font-weight:600}
.sst-seg-none{background:var(--bg-color);color:var(--text-muted)}
.sst-seg-prog{background:rgba(99,102,241,.1);color:#6366f1}
.sst-seg-done{background:rgba(16,185,129,.12);color:#10b981}
.sst-seg-late{background:rgba(239,68,68,.1);color:#ef4444}

/* ===== DETAIL VIEW TABLES (for semestral/mensual/semanal) ===== */
.sst-detail-table{width:100%;border-collapse:separate;border-spacing:0;font-size:.82rem;margin-bottom:1rem}
.sst-detail-table thead th{padding:.45rem .5rem;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.03em;color:var(--text-muted);border-bottom:2px solid var(--surface-border);background:var(--surface-color);text-align:center;white-space:nowrap}
.sst-detail-table tbody td{padding:.4rem .5rem;border-bottom:1px solid var(--surface-border);vertical-align:middle;text-align:center}
.sst-detail-table .td-name{text-align:left;font-weight:600;min-width:200px}
.sst-highlight-col{background:rgba(99,102,241,.06)!important}
body.dark-mode .sst-highlight-col{background:rgba(99,102,241,.1)!important}

/* ===== RESPONSIVE ===== */
@media(max-width:768px){
    .sst-stats-grid{grid-template-columns:repeat(2,1fr)}
    .sst-toolbar{flex-direction:column;align-items:stretch}
    .sst-view-switcher{order:1;width:100%;justify-content:center}
    .sst-period-nav{order:2;justify-content:center}
    .sst-legend{order:3;justify-content:center}
    .sst-activity-filter{flex-direction:column;gap:.65rem}
    .sst-activity-filter-buttons{justify-content:flex-start}
    .sst-filter-empty{align-items:flex-start;flex-wrap:wrap}
    .sst-detail-grid{grid-template-columns:1fr}
    .sst-seg-grid{grid-template-columns:repeat(4,1fr)}
    .sst-gantt{font-size:.75rem}
    .sst-gantt th,.sst-gantt td{padding:4px 6px}
}
@media(max-width:480px){
    .sst-stats-grid{grid-template-columns:1fr}
    .sst-view-btn span{display:none}
    .gantt-cell{min-width:26px;height:26px;font-size:.56rem;padding:0 2px}
    .sst-seg-grid{grid-template-columns:repeat(3,1fr)}
    .sst-import-tips{grid-template-columns:1fr !important}
    .sst-form-grid{grid-template-columns:1fr !important}
    .sst-quick-actions-head{flex-direction:column;gap:.45rem}
    .sst-quick-actions-grid{grid-template-columns:1fr;padding:.7rem}
    .sst-quick-buttons .sst-btn{width:100%;justify-content:center}
    .sst-filter-btn{flex:1;min-width:calc(50% - .2rem);text-align:center}
}

/* ===== DARK MODE PATCHES ===== */
body.dark-mode .sst-btn-danger{background:rgba(220,38,38,.15);color:#f87171;border-color:rgba(220,38,38,.3)}
body.dark-mode .gantt-plan{background:rgba(99,102,241,.15);border-color:rgba(99,102,241,.35)}
body.dark-mode .gantt-done{background:rgba(16,185,129,.2);border-color:rgba(16,185,129,.4)}
body.dark-mode .gantt-overdue{background:rgba(239,68,68,.18);border-color:rgba(239,68,68,.4)}
body.dark-mode .gantt-partial{background:rgba(245,158,11,.18);border-color:rgba(245,158,11,.4)}
body.dark-mode .sst-planes-row td{background:var(--bg-color)}
</style>
