<?php
declare(strict_types=1);

$appSidebarIdentity = is_array($appSidebarIdentity ?? null) ? $appSidebarIdentity : [];
if ($appSidebarIdentity === [] && defined('AILHOST_ROOT') && is_file(AILHOST_ROOT . '/var/settings.json')) {
    $settings = json_decode((string) file_get_contents(AILHOST_ROOT . '/var/settings.json'), true);
    if (is_array($settings)) {
        $appSidebarIdentity = [
            'name' => (string) ($settings['server_name'] ?? $settings['hostname'] ?? 'AIL-SERVER'),
            'ip' => (string) ($settings['server_ip'] ?? '127.0.0.1'),
        ];
    }
}
$appSidebarName = trim((string) ($appSidebarIdentity['name'] ?? 'AIL-SERVER'));
$appSidebarIp = trim((string) ($appSidebarIdentity['ip'] ?? '127.0.0.1'));
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'ailhost', ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        :root {
            --bg: #f3f3f3;
            --surface: #ffffff;
            --field: #fcfcfc;
            --line: #d9d9d9;
            --line-strong: #bbbbbb;
            --text: #131313;
            --muted: #666666;
            --soft: #f0f0f0;
            --hover: #ebebeb;
            --strong-line: #111111;
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "Segoe UI", Tahoma, sans-serif; background: var(--bg); color: var(--text); }
        .layout-default .wrap { max-width: 1240px; margin: 24px auto; padding: 0 16px; }
        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 10px; padding: 24px; }
        h1 { margin: 0; font-size: 2.75rem; line-height: 1.1; letter-spacing: 0; font-weight: 700; }
        h2 { margin: 0 0 16px; font-size: 1.2rem; letter-spacing: 0; }
        h3 { margin: 0 0 10px; font-size: .98rem; letter-spacing: 0; }
        p { color: var(--muted); line-height: 1.55; margin: 0 0 12px; }
        a { color: var(--text); }
        code { color: var(--text); background: var(--soft); border: 1px solid var(--line); border-radius: 5px; padding: 2px 6px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; border-bottom: 1px solid var(--line); padding: 10px 0; vertical-align: top; }
        td + td, th + th { padding-left: 16px; }
        th { color: var(--text); font-weight: 600; }
        label { display: block; margin-bottom: 7px; color: var(--text); font-weight: 600; }
        input, select { width: 100%; padding: 11px 12px; margin: 0; background: var(--field); border: 1px solid var(--line-strong); color: var(--text); border-radius: 12px; }
        input:focus, select:focus { border-color: var(--strong-line); outline: 2px solid var(--soft); }
        button { padding: 10px 18px; background: var(--text); color: var(--surface); border: 1px solid var(--text); border-radius: 999px; cursor: pointer; font-weight: 600; }
        button:hover { background: var(--muted); border-color: var(--muted); }
        button:disabled { opacity: .45; cursor: not-allowed; }
        .button-secondary { background: var(--surface); color: var(--text); border-color: var(--line); }
        .button-secondary:hover { background: var(--soft); color: var(--text); border-color: var(--line); }
        .toast-host { position: fixed; top: 16px; right:16px; z-index: 1000; width: min(380px, calc(100vw - 24px)); }
        .toast { display: flex; align-items: flex-start; gap: 10px; padding: 12px 12px; border-radius: 8px; border: 1px solid var(--line); background: var(--surface); color: var(--text); box-shadow: 0 8px 16px rgba(0, 0, 0, 0.08); }
        .toast[data-type="error"] { border-color: var(--strong-line); }
        .toast-text { flex: 1; font-size: .94rem; line-height: 1.4; }
        .toast-close { width: auto; padding: 4px 10px; font-size: .85rem; background: var(--surface); color: var(--text); border: 1px solid var(--line); }
        .toast-close:hover { background: var(--soft); border-color: var(--line); }
        .drawer-backdrop { position: fixed; inset: 0; background: rgba(0, 0, 0, 0.24); z-index: 900; display: none; }
        .drawer-backdrop.is-open { display: block; }
        .drawer { position: fixed; top: 0; right: 0; width: min(540px, 96vw); height: 100vh; background: var(--surface); border-left: 1px solid var(--line); z-index: 901; transform: translateX(104%); transition: transform .2s ease; padding: 18px; overflow: auto; }
        .drawer.is-open { transform: translateX(0); }
        .drawer-header { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 12px; }
        .drawer-title { margin: 0; font-size: 1.1rem; }
        .confirm-modal-wrap { position: fixed; inset: 0; display: none; place-items: center; z-index: 950; background: rgba(0, 0, 0, 0.24); }
        .confirm-modal-wrap.is-open { display: grid; }
        .confirm-modal { width: min(460px, calc(100vw - 24px)); background: var(--surface); border: 1px solid var(--line); border-radius: 10px; padding: 18px; }
        .confirm-modal p { margin: 0 0 14px; color: var(--text); }
        .confirm-actions { display: flex; gap: 10px; justify-content: flex-end; }
        .form-block { margin: 0; }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px 16px; }
        .form-field-full { grid-column: 1 / -1; }
        .form-actions { display: flex; justify-content: flex-end; margin-top: 18px; }
        .form-stack-top { margin-top: 16px; }
        .inline-form { display: inline-flex; justify-content: flex-end; gap: 8px; margin: 0; }
        .status-line { color: var(--muted); }
        .field-hint { margin-top: 6px; margin-bottom: 0; font-size: .82rem; color: var(--muted); }
        .field-error { margin-top: 6px; margin-bottom: 0; font-size: .82rem; color: var(--text); }
        .page-header { display: flex; justify-content: space-between; gap: 24px; align-items: flex-start; padding-bottom: 22px; border-bottom: 1px solid var(--line); }
        .page-header .quick-create { display: grid; grid-template-columns: minmax(220px, 1fr) auto; gap: 10px; align-items: end; width: min(520px, 100%); }
        .module-layout { display: grid; grid-template-columns: 280px minmax(0, 1fr); gap: 20px; margin-top: 20px; align-items: start; }
        .module-sidebar, .module-main { min-width: 0; }
        .module-main { display: grid; gap: 16px; }
        .module-card { border: 1px solid var(--line); border-radius: 8px; background: var(--surface); padding: 18px; }
        .module-card + .module-card { margin-top: 14px; }
        .module-main .module-card + .module-card { margin-top: 0; }
        .site-list, .module-nav, .quick-links { display: grid; gap: 8px; }
        .site-list a, .module-nav a, .quick-links a { display: flex; justify-content: space-between; gap: 10px; align-items: center; padding: 10px 12px; border: 1px solid var(--line); border-radius: 999px; text-decoration: none; color: var(--text); }
        .site-list a.is-active, .module-nav a.is-active, .quick-links a:hover, .site-list a:hover, .module-nav a:hover { border-color: var(--text); background: var(--soft); }
        .site-list small { color: var(--muted); }
        .metric-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }
        .metric-grid-small { grid-template-columns: repeat(3, minmax(0, 1fr)); margin-bottom: 12px; }
        .metric-value { margin: 0 0 6px; font-size: 2rem; line-height: 1; color: var(--text); font-weight: 700; }
        .split-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .site-context-header { display: flex; justify-content: space-between; gap: 16px; align-items: end; }
        .context-meta { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin-top: 14px; }
        .context-meta-item { border: 1px solid var(--line); border-radius: 8px; padding: 10px 12px; min-width: 0; }
        .context-meta-item span { display: block; color: var(--muted); font-size: .78rem; margin-bottom: 4px; }
        .context-meta-item strong { display: block; color: var(--text); font-size: .92rem; overflow-wrap: anywhere; }
        .site-switcher { width: min(280px, 100%); }
        .module-tabs { display: flex; gap: 18px; margin-top: 16px; border-bottom: 1px solid var(--line); overflow-x: auto; padding-bottom: 2px; }
        .module-tabs a { text-align: center; border-bottom: 2px solid transparent; padding: 8px 2px 10px; text-decoration: none; color: var(--muted); font-weight: 600; white-space: nowrap; border-radius: 0; }
        .module-tabs a.is-active { color: var(--text); border-bottom-color: var(--text); }
        .module-tabs a:hover { color: var(--text); }
        .site-cards { display: grid; gap: 12px; margin-top: 14px; }
        .site-card { border: 1px solid var(--line); border-radius: 8px; padding: 14px; background: var(--surface); }
        .site-card-top { display: flex; justify-content: space-between; gap: 10px; align-items: center; }
        .site-card-top h3 { margin: 0; font-size: 1.02rem; }
        .site-card-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; }
        .site-card-actions a { border: 1px solid var(--line); border-radius: 999px; padding: 6px 12px; text-decoration: none; color: var(--text); font-weight: 600; }
        .site-card-actions a:hover { border-color: var(--text); background: var(--soft); }
        .code-editor { width: 100%; border: 1px solid var(--line-strong); border-radius: 8px; padding: 12px; background: var(--field); color: var(--text); font-family: ui-monospace, SFMono-Regular, Consolas, monospace; }
        .log-console { margin: 0; border: 1px solid var(--line); border-radius: 8px; padding: 12px; background: var(--field); color: var(--text); max-height: 280px; overflow: auto; white-space: pre-wrap; font: 12px/1.45 ui-monospace, SFMono-Regular, Consolas, monospace; }
        .inline-actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .install-header { display: flex; justify-content: space-between; gap: 24px; align-items: flex-start; padding-bottom: 22px; border-bottom: 1px solid var(--line); }
        .install-status { display: flex; gap: 8px; flex-wrap: wrap; justify-content: flex-end; }
        .status-pill { display: inline-flex; align-items: center; border: 1px solid var(--line-strong); border-radius: 999px; padding: 6px 12px; color: var(--text); background: var(--surface); font-size: .875rem; }
        .install-grid { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 28px; margin-top: 24px; align-items: start; }
        .steps { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; margin-bottom: 22px; }
        .step { border: 1px solid var(--line); border-radius: 999px; padding: 8px 12px; color: var(--muted); font-size: .9rem; text-align: center; white-space: nowrap; }
        .step.is-done { color: var(--text); border-color: var(--text); font-weight: 600; }
        .install-section { padding: 22px 0; border-top: 1px solid var(--line); }
        .install-section:first-of-type { border-top: 0; padding-top: 0; }
        .step-summary { display: flex; justify-content: space-between; gap: 16px; align-items: center; padding: 16px 0; border-top: 1px solid var(--line); }
        .step-summary:first-child { border-top: 0; }
        .step-summary-title { font-weight: 700; color: var(--text); }
        .step-summary-status { color: var(--muted); white-space: nowrap; }
        .step-summary-status a { color: var(--text); text-decoration: none; border-bottom: 1px solid var(--line); }
        .step-summary-status a:hover { border-bottom-color: var(--text); }
        .active-step { border: 1px solid var(--text); border-radius: 8px; padding: 22px; margin: 18px 0; }
        .install-actions-row { display: flex; justify-content: space-between; gap: 12px; align-items: center; }
        .side-section { padding: 18px 0; border-top: 1px solid var(--line); }
        .side-section:first-child { border-top: 0; padding-top: 0; }
        .empty-state { color: var(--muted); border: 1px dashed var(--line); border-radius: 6px; padding: 12px; }
        .guide-panel { border: 1px solid var(--line); border-radius: 8px; background: var(--surface); padding: 18px; }
        .guide-panel h2 { margin-bottom: 6px; }
        .guide-panel > p { margin-bottom: 14px; }
        .guide-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
        .guide-block { border: 1px solid var(--line); border-radius: 8px; padding: 12px; background: var(--field); }
        .guide-block h3 { margin-bottom: 8px; }
        .guide-list { margin: 0; padding-left: 18px; color: var(--text); line-height: 1.55; }
        .guide-list li + li { margin-top: 4px; }
        .impact-box { border: 1px solid var(--line-strong); border-radius: 8px; padding: 12px; background: var(--surface); }
        .impact-box p { margin-bottom: 6px; color: var(--text); }
        .impact-box p:last-child { margin-bottom: 0; }
        .operation-strip { display: flex; justify-content: space-between; gap: 12px; align-items: center; border: 1px solid var(--line); border-radius: 8px; padding: 12px; background: var(--field); }
        .operation-strip strong { color: var(--text); }
        .next-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }
        .next-actions a { border: 1px solid var(--line); border-radius: 999px; padding: 6px 12px; text-decoration: none; color: var(--text); font-weight: 600; }
        .next-actions a:hover { border-color: var(--text); background: var(--soft); }
        .quick-action-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
        .quick-action-card { display: grid; gap: 8px; min-height: 132px; border: 1px solid var(--line); border-radius: 8px; padding: 14px; text-decoration: none; color: var(--text); background: var(--surface); }
        .quick-action-card:hover { border-color: var(--text); background: var(--soft); }
        .quick-action-card strong { font-size: 1rem; }
        .quick-action-card span { color: var(--muted); line-height: 1.45; }
        details { border-top: 1px solid var(--line); padding: 16px 0; }
        summary { cursor: pointer; font-weight: 600; }
        .danger-zone { border-color: var(--strong-line); }
        .danger-zone p { color: var(--text); }
        .compact-table { margin-top: 12px; font-size: .92rem; }
        .truncate-code { display: inline-block; max-width: 140px; overflow: hidden; text-overflow: ellipsis; vertical-align: bottom; white-space: nowrap; }
        .layout-app { background: #ffffff; font-family: Roboto, "Segoe UI", Tahoma, sans-serif; }
        .app-shell { min-height: 100vh; display: grid; grid-template-columns: 300px minmax(0, 1fr); background: #ffffff; overflow: hidden; }
        .app-sidebar { position: sticky; top: 0; height: 100vh; border-right: 10px solid #f7f9fb; background: #ffffff; padding: 20px; display: grid; grid-template-rows: auto minmax(0, 1fr) auto; gap: 20px; overflow: hidden; }
        .app-brand-wrap { display: inline-flex; align-items: flex-end; gap: 10px; padding: 10px 26px; width: 100%; color: #1a1a1a; text-decoration: none; }
        .app-brand-wrap img { width: 42px; height: 42px; border-radius: 12px; display: block; }
        .app-brand-meta { display: grid; gap: 0; justify-items: start; min-width: 0; }
        .app-brand-meta small { font-size: 8px; letter-spacing: 0; color: #545454; text-transform: uppercase; font-weight: 400; line-height: normal; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .app-brand { font-size: 24px; line-height: normal; font-weight: 800; color: #1a1a1a; letter-spacing: 0; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .app-nav-wrap { min-height: 0; overflow-y: auto; padding: 0 10px; scrollbar-width: none; }
        .app-nav-wrap::-webkit-scrollbar { width: 0; display: none; }
        .app-nav { position: relative; display: grid; gap: 0; align-content: start; }
        .app-nav a { position: relative; z-index: 2; display: flex; align-items: center; gap: 10px; min-height: 49px; padding: 15px; text-decoration: none; color: #727272; font-size: 16px; line-height: normal; font-weight: 400; border-radius: 8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .app-nav a.is-active { color: #000000; font-weight: 600; }
        /* .app-nav a.is-active::before { content: ""; width: 8px; height: 8px; border-radius: 999px; background: #000000; flex: 0 0 auto; } */
        .app-nav-active-dot { position: absolute; z-index: 1; left: 0; top: 0; display: block; width: 8px; height: 8px; border-radius: 999px; background: #000000; pointer-events: none; transform: translate3d(0, var(--nav-dot-y, 0px), 0); transition: transform 220ms cubic-bezier(.2, .8, .2, 1), opacity 160ms ease; }
        .app-nav.has-active-dot .app-nav-active-dot { opacity: 1; }
        .app-nav a:hover { color: #000000; background: transparent; }
        .app-sidebar-user { position: relative; display: inline-flex; align-items: center; gap: 10px; max-width: 100%; width: fit-content; border: 0; background: #ededed; border-radius: 30px; padding: 10px 25px; color: #727272; font-size: 16px; line-height: normal; }
        .app-sidebar-user span:not(.app-user-avatar) { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; }
        .app-user-avatar { width: 20px; height: 20px; border-radius: 6px; background: #d9d9d9; flex: 0 0 auto; }
        .app-sidebar-id { display: grid; gap: 2px; min-width: 0; }
        .app-sidebar-id strong { color: #1a1a1a; font-size: 16px; line-height: normal; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .app-sidebar-id small { color: #727272; font-size: 12px; line-height: normal; font-weight: 400; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .app-user-more { border: 0; background: transparent; color: #727272; width: 16px; height: 16px; border-radius: 6px; display: grid; place-items: center; padding: 0; font-size: 16px; line-height: 1; flex: 0 0 auto; }
        .app-user-more:hover { background: #dedede; color: #111111; }
        .app-user-menu { position: absolute; left: 0; bottom: calc(100% + 10px); min-width: 160px; border: 1px solid #d7dce2; border-radius: 14px; background: #fff; box-shadow: 0 12px 24px rgba(17, 22, 30, 0.12); padding: 6px 0; display: none; z-index: 120; }
        .app-user-menu.is-open { display: block; }
        .app-user-menu a, .app-user-menu button { width: 100%; text-align: left; border: 0; background: transparent; color: #1a1e24; font-size: 16px; padding: 10px 14px; display: flex; align-items: center; gap: 10px; border-radius: 0; text-decoration: none; }
        .app-user-menu a:hover, .app-user-menu button:hover { background: #f3f5f8; color: #111; }
        .app-user-menu hr { border: 0; border-top: 1px solid #eceff3; margin: 4px 0; }
        .app-main { min-width: 0; padding: 32px; overflow: auto; }
        .app-content { width: 100%; max-width: 1140px; }
        .app-panel { border: 1px solid #ededed; background: #ffffff; border-radius: 16px; box-shadow: none; overflow: hidden; }
        .app-panel.service-overflow-panel { overflow: visible; display: inherit !important;}
        .admin-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; margin-bottom: 27px; }
        .admin-crumb { margin: 0 0 10px; color: #727272; font-size: 12px; letter-spacing: 2.4px; line-height: normal; text-transform: uppercase; }
        .admin-title { margin: 0; color: #000000; font-size: 40px; line-height: normal; font-weight: 700; letter-spacing: 0; }
        .admin-subtitle { margin: 7px 0 0; color: #727272; font-size: 14px; line-height: normal; }
        .admin-cta { display: inline-flex; align-items: center; justify-content: center; min-width: 204px; height: 46px; border: 0; border-radius: 25px; padding: 0 25px; background: #0088ff; color: #ffffff; font-size: 14px; font-weight: 500; text-decoration: none; white-space: nowrap; }
        .admin-cta:hover { background: #0088ff; color: #ffffff; }
        .admin-grid-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 15px; margin-bottom: 16px; }
        .metric-card { border: 1px solid #ededed; border-radius: 16px; background: #ffffff; padding: 25px; box-shadow: 0 8px 16px rgba(0, 0, 0, .06); }
        .metric-card-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; }
        .metric-icon { width: 42px; height: 42px; border-radius: 8px; background: #f7f9fb; color: #1a1a1a; display: grid; place-items: center; font-size: 18px; }
        .metric-card h3 { margin: 0 0 10px; font-size: 12px; color: #727272; font-weight: 400; letter-spacing: .6px; text-transform: uppercase; }
        .metric-card strong { display: block; font-size: 32px; line-height: normal; color: #000000; font-weight: 700; }
        .metric-bar { margin-top: 10px; height: 6px; border-radius: 3px; background: #ededed; overflow: hidden; }
        .metric-bar span { display: block; height: 100%; border-radius: 3px; background: #000000; }
        .metric-card.metric-state-busy .metric-bar span { background: #eb923a; }
        .metric-card.metric-state-critical .metric-bar span { background: #be3b34; }
        .metric-card[data-live-metric] strong, .metric-card[data-live-metric] p, .metric-card[data-live-metric] .metric-bar span { transition: width .28s ease, color .2s ease, opacity .2s ease; }
        .metric-card.is-refreshing strong, .metric-card.is-refreshing p { opacity: .72; }
        .metric-card p { margin: 10px 0 0; color: #727272; font-size: 12px; line-height: normal; }
        .split-main { display: grid; grid-template-columns: minmax(0, 1fr) 350px; gap: 15px; margin-bottom: 16px; }
        .service-panel-head { display: flex; justify-content: space-between; align-items: center; padding: 20px 25px; border-bottom: 1px solid #ededed; background: #f7f9fb; border-radius: 16px 16px 0 0; overflow: hidden; }
        .service-panel-head h2 { margin: 0; font-size: 20px; line-height: normal; font-weight: 700; color: #000000; }
        .service-panel-head a { color: #0088ff; text-decoration: none; font-size: 16px; }
        .service-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .service-item { border-right: 1px solid #ededed; border-bottom: 1px solid #ededed; padding: 20px 15px; display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        .service-item:nth-child(2n) { border-right: 0; }
        .service-item:nth-last-child(-n+2) { border-bottom: 0; }
        .service-item strong { display: flex; align-items: center; font-size: 16px; color: #000000; font-weight: 500; line-height: normal; }
        .service-item p { margin: 8px 0 0 18px; font-size: 14px; color: #727272; line-height: normal; }
        .service-dot { width: 8px; height: 8px; border-radius: 999px; background: #34c759; margin-right: 10px; display: inline-block; }
        .service-dot.off { background: #c7c7cc; }
        .service-dot.error { background: #be3b34; }
        .service-actions { position: relative; flex: 0 0 auto; }
        .service-menu-button { width: 32px; height: 32px; display: grid; place-items: center; padding: 0; border: 0; border-radius: 999px; background: transparent; color: #727272; cursor: pointer; }
        .service-menu-button:hover, .service-menu-button.is-open { background: #f7f9fb; color: #000000; }
        .service-menu-button svg { width: 18px; height: 18px; }
        .service-popover { position: absolute; right: 0; top: calc(100% + 8px); min-width: 176px; border: 1px solid #d7dce2; border-radius: 10px; background: #ffffff; box-shadow: 0 12px 24px rgba(17, 22, 30, .12); padding: 6px 0; display: none; z-index: 60; }
        .service-popover.is-open { display: block; }
        .service-popover a, .service-popover button { width: 100%; min-height: 34px; display: flex; align-items: center; gap: 9px; padding: 8px 12px; border: 0; border-radius: 0; background: transparent; color: #1a1a1a; text-align: left; text-decoration: none; font-size: 14px; line-height: 1.2; font-weight: 400; box-shadow: none; }
        .service-popover a:hover, .service-popover button:hover { background: #f7f9fb; color: #000000; }
        .service-popover button.is-danger, .service-popover a.is-danger { color: #be3b34; }
        .service-popover button.is-danger:hover, .service-popover a.is-danger:hover { background: rgba(190, 59, 52, .08); color: #be3b34; }
        .service-popover svg { width: 16px; height: 16px; color: currentColor; }
        .service-popover form { margin: 0; }
        .service-popover hr { border: 0; border-top: 1px solid #ededed; margin: 6px 0; }
        .identity-card { min-height: 264px; border-radius: 16px; background: #050608; color: #ffffff; padding: 32px 25px; display: flex; flex-direction: column; justify-content: space-between; gap: 18px; }
        .identity-card small { color: #ededed; font-size: 12px; letter-spacing: 2.4px; text-transform: uppercase; }
        .identity-card h3 { margin: 6px 0 0; font-size: 24px; line-height: normal; color: #ffffff; font-weight: 700; }
        .identity-card p { margin: 0; color: #ededed; font-size: 14px; line-height: normal; }
        .identity-list { display: grid; gap: 12px; }
        .identity-list p { display: flex; justify-content: space-between; gap: 16px; border-top: 1px solid rgba(237, 237, 237, .16); padding-top: 10px; }
        .identity-list span { color: #aeb4bd; }
        .identity-list b { color: #ffffff; font-weight: 500; text-align: right; overflow-wrap: anywhere; }
        .quick-task-panel { margin-bottom: 16px; }
        .quick-task-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; max-height: 36px; overflow: hidden; }
        .quick-task-row a { display: inline-flex; align-items: center; height: 34px; padding: 0 14px; border: 1px solid #ededed; border-radius: 999px; background: #ffffff; color: #1a1a1a; text-decoration: none; font-size: 16px; line-height: 1; font-weight: 400; }
        .quick-task-row a:hover { border-color: #d9d9d9; background: #f7f9fb; color: #000000; }
        .table-panel .service-panel-head { padding-bottom: 14px; }
        .table-panel table { width: 100%; border-collapse: collapse; }
        .table-panel th { text-align: left; font-size: 14px; letter-spacing: 1.4px; color: #727272; font-weight: 500; padding: 10px 25px; border-top: 1px solid #ededed; border-bottom: 1px solid #ededed; }
        .table-panel td { padding: 20px 25px; border-bottom: 1px solid #ededed; color: #727272; font-size: 16px; }
        .table-panel tr:last-child td { border-bottom: 0; }
        .status-badge { display: inline-block; padding: 7px 15px; border-radius: 15px; font-size: 12px; line-height: 1; }
        .status-badge.ok { background: rgba(52, 199, 89, .10); color: #34c759; }
        .status-badge.run { background: rgba(114, 114, 114, .10); color: #727272; }
        .status-badge.err { background: rgba(190, 58, 52, .10); color: #be3a34; }
        .status-badge-link { display: inline-flex; align-items: center; gap: 6px; text-decoration: none; }
        .status-badge-link svg { width: 14px; height: 14px; }
        .layout-auth { background: #f2f4f7; }
        .auth-shell { min-height: 100vh; background: #f2f4f7; border-radius: 34px; overflow: hidden; padding: 24px 48px 20px; display: grid; grid-template-rows: auto 1fr auto; }
        .auth-topbar { display: flex; justify-content: space-between; align-items: center; gap: 24px; }
        .auth-brand { display: inline-flex; align-items: center; gap: 12px; color: #111317; text-decoration: none; font-size: 2.15rem; font-weight: 800; letter-spacing: 0; }
        .auth-brand img { width: 42px; height: 42px; border-radius: 12px; display: block; }
        .auth-links { display: flex; align-items: center; justify-content: flex-end; gap: 16px; }
        .auth-links a { display: inline-flex; align-items: center; gap: 6px; color: #727986; text-decoration: none; font-size: .96rem; font-weight: 500; }
        .auth-links svg { width: 16px; height: 16px; color: #8f96a3; }
        .auth-links a:hover { color: #111111; }
        .auth-main { min-height: 0; display: grid; place-items: center; }
        .auth-inner { width: min(520px, 100%); }
        .auth-title { margin: 0; color: #1b1c20; font-size: 3.4rem; line-height: 1.08; text-align: center; font-weight: 700; letter-spacing: -.01em; }
        .auth-copy { margin: 20px auto 0; text-align: center; color: #1f2227; line-height: 1.35; font-size: 2rem; }
        .auth-form { margin-top: 58px; }
        .auth-form-field { display: grid; gap: 8px; margin-bottom: 22px; }
        .auth-form-label { color: #7a7a7a; font-size: .82rem; letter-spacing: .22em; font-weight: 500; }
        .auth-form-input-wrap { position: relative; }
        .auth-form-input { width: 100%; height: 46px; border: 1px solid #c0c6cf; border-radius: 12px; background: #f2f4f7; color: #505862; font-size: 1.05rem; font-weight: 500; padding: 0 16px; }
        .auth-form-input::placeholder { color: #7d8086; }
        .auth-form-input:focus { outline: 3px solid #e9edf2; border-color: #b6bec9; }
        .auth-eye-toggle { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); width: 34px; height: 34px; display: grid; place-items: center; border: 0; border-radius: 999px; background: transparent; color: #707070; cursor: pointer; }
        .auth-eye-toggle:hover { background: #eef0f3; color: #111111; }
        .auth-eye-toggle svg { width: 20px; height: 20px; }
        .auth-row { display: flex; justify-content: space-between; align-items: center; gap: 14px; margin-top: 14px; }
        .auth-check { display: inline-flex; align-items: center; gap: 10px; color: #6f7783; font-size: 1rem; }
        .auth-check input { width: 24px; height: 24px; margin: 0; border-radius: 8px; }
        .auth-link { color: #6f7783; text-decoration: none; font-size: 1rem; }
        .auth-link:hover { color: #111111; }
        .auth-action { display: flex; justify-content: center; margin-top: 64px; }
        .auth-pill { --pill-icon-left: calc(100% - 40px); position: relative; display: inline-flex; align-items: center; justify-content: center; gap: 12px; height: 46px; min-width: 166px; padding: 0 58px 0 24px; border-radius: 999px; border: 1px solid #2fbf52; color: #ffffff; background: #34c759; box-shadow: 0 14px 26px rgba(52, 199, 89, .24); font-size: 1rem; font-weight: 700; cursor: pointer; overflow: hidden; text-decoration: none; }
        .auth-pill:hover { background: #34c759; border-color: #2fbf52; color: #ffffff; }
        .auth-pill-icon { position: absolute; left: var(--pill-icon-left); top: 50%; transform: translateY(-50%); width: 34px; height: 34px; display: inline-grid; place-items: center; border-radius: 999px; background: #f4f6f7; color: #101113; }
        .auth-pill-icon svg { width: 18px; height: 18px; }
        .auth-footer { text-align: center; color: #9aa1ab; font-size: .84rem; letter-spacing: .24em; font-weight: 700; padding-top: 24px; }
        .auth-login-hero { padding-top: 94px; }
        .auth-login-form { width: min(520px, 100%); margin-top: 56px; }
        .auth-login-fields { display: grid; gap: 18px; width: 100%; }
        .auth-login-form .setup-field + .setup-field { border-top: 0; padding-top: 0; }
        .auth-login-form .setup-field span:first-child { width: 100%; text-align: left; }
        .auth-login-form .setup-field input { border-radius: 12px; background: #ffffff; }
        .auth-login-form .setup-eye-toggle { top: 27px; }
        .auth-login-row { width: 100%; display: flex; justify-content: space-between; align-items: center; gap: 14px; margin-top: 20px;}
        .auth-login-check { display: inline-flex; align-items: center; gap: 10px; color: #6f7783; font-size: .95rem; font-weight: 400;}
        .auth-login-check input { width: 15px; height: 16px; margin: 0; border-radius: 8px; }
        .auth-login-link { color: #6f7783; text-decoration: none; font-size: .95rem; }
        .auth-login-link:hover { color: #111111; }
        .layout-install { background: #f8fafc; color: #0c0d10; font-family: Inter, "Segoe UI", Tahoma, sans-serif; overflow: hidden; }
        .setup-shell { height: 100vh; display: grid; grid-template-rows: auto minmax(0, 1fr) auto; padding: 20px 54px 10px; background: #f8fafc; overflow: hidden; }
        .setup-topbar { display: flex; justify-content: space-between; align-items: center; gap: 28px; }
        .setup-brand { display: inline-flex; align-items: center; gap: 12px; color: #0a0b0d; text-decoration: none; font-size: 1.18rem; font-weight: 800; letter-spacing: -.01em; }
        .setup-brand img { width: 42px; height: 42px; border-radius: 12px; display: block; }
        .setup-links { display: flex; align-items: center; justify-content: flex-end; gap: 26px; }
        .setup-links a { display: inline-flex; align-items: center; gap: 7px; color: #7d838c; text-decoration: none; font-size: .93rem; font-weight: 400; }
        .setup-links svg { width: 18px; height: 18px; color: #9aa0a8; }
        .setup-links a:hover { color: #111111; }
        .setup-content { min-height: 0; width: calc(100% + 108px); margin: 0 -54px; overflow-y: auto; scrollbar-width: thin; overscroll-behavior: contain; }
        .setup-hero { width: min(1010px, calc(100% - 108px)); min-height: 100%; margin: 0 auto; display: flex; flex-direction: column; align-items: center; text-align: center; padding: 94px 0 56px; }
        .setup-hero-welcome { padding-top: 232px; }
        .setup-hero h1 { margin: 0; color: #07080a; font-size: 2.48rem; line-height: 1.1; font-weight: 800; letter-spacing: -.02em; }
        .setup-copy { width: min(690px, 100%); margin: 22px auto 0; color: #1f2227; font-size: 1.02rem; line-height: 1.32; }
        .setup-feature-grid { width: 940px; max-width: 100%; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; margin-top: 62px; }
        .setup-feature-card { min-height: 163px; text-align: center; background: #eeeeee; border: 1px solid #d0d0d0; border-radius: 14px; padding: 25px 28px 20px; box-shadow: 0 18px 42px rgba(13, 17, 23, .05); transition: transform .26s ease, box-shadow .26s ease, border-color .26s ease; will-change: transform; }
        .setup-feature-card:hover { transform: translateY(-6px); border-color: #c2c6cc; box-shadow: 0 24px 56px rgba(13, 17, 23, .13); }
        .setup-feature-icon { width: 36px; height: 36px; color: #202124; margin: 0 auto 20px; }
        .setup-feature-card h2 { margin: 0 0 15px; color: #202124; font-size: 1.02rem; line-height: 1.25; font-weight: 800; }
        .setup-feature-card p { margin: 0 auto; max-width: 230px; color: #777777; font-size: .96rem; line-height: 1.22; }
        .setup-pill { --pill-icon-left: calc(100% - 40px); position: relative; display: inline-flex; align-items: center; justify-content: center; gap: 12px; height: 46px; min-width: 181px; margin-top: 64px; padding: 0 58px 0 24px; border-radius: 999px; text-decoration: none; border: 1px solid #d7dce3; color: #15171a; background: #ffffff; box-shadow: 0 16px 34px rgba(13, 17, 23, .07); font-size: .96rem; font-weight: 800; cursor: pointer; overflow: hidden; transition: background-color .3s ease, border-color .3s ease, color .3s ease, box-shadow .3s ease, padding .3s ease; }
        .setup-pill > span:not(.setup-pill-icon) { position: relative; z-index: 1; }
        .setup-hero-welcome .setup-pill { margin-top: 65px; }
        .setup-pill:hover { background: #ffffff; border-color: #c9ced8; color: #15171a; }
        .setup-pill-green { background: #34c759; border-color: #2fbf52; color: #ffffff; box-shadow: 0 18px 36px rgba(52, 199, 89, .22); }
        .setup-pill-green:hover { background: #34c759; border-color: #2fbf52; color: #ffffff; }
        .setup-pill-danger { background: #e5484d; border-color: #e5484d; color: #ffffff; box-shadow: 0 18px 36px rgba(229, 72, 77, .18); }
        .setup-pill-danger { padding-left: 24px; padding-right: 24px; }
        .setup-pill-danger:disabled { opacity: 1; cursor: not-allowed; }
        .setup-pill-icon { position: absolute; left: var(--pill-icon-left); top: 50%; transform: translateY(-50%); width: 34px; height: 34px; display: inline-grid; place-items: center; border-radius: 999px; background: rgba(255, 255, 255, .82); color: #101113; transition: left .3s ease, background-color .3s ease, color .3s ease; z-index: 1; }
        .setup-pill-icon svg { width: 18px; height: 18px; }
        .setup-pill-light .setup-pill-icon { background: #f2f4f7; }
        .setup-pill-gray { --pill-icon-left: 6px; background: #7b7b7b; border-color: #7b7b7b; color: #ffffff; box-shadow: 0 18px 36px rgba(80, 80, 80, .2); padding-left: 58px; padding-right: 24px; }
        .setup-pill-gray:hover { background: #707070; border-color: #707070; color: #ffffff; }
        .setup-pill-gray .setup-pill-icon { background: #ffffff; color: #111111; }
        .setup-pill-green { --pill-icon-left: calc(100% - 40px); padding-left: 24px; padding-right: 58px; }
        .setup-pill-green .setup-pill-icon { background: rgba(255, 255, 255, .82); color: #101113; }
        .setup-card { width: 940px; max-width: 100%; margin-top: 64px; background: #ffffff; border: 1px solid #d9dde4; border-radius: 14px; box-shadow: 0 18px 44px rgba(13, 17, 23, .06); overflow: hidden; text-align: left; }
        .setup-card.setup-settings-card { overflow: visible; }
        .setup-requirements-card { padding: 0; }
        .setup-table-head { display: grid; grid-template-columns: 1fr 80px; align-items: center; padding: 16px 24px; background: #eeeeee; color: #737373; font-size: .76rem; letter-spacing: .16em; font-weight: 800; }
        .setup-table-head span:last-child { justify-self: center; }
        .setup-requirement-row { display: grid; grid-template-columns: 1fr 80px; align-items: center; gap: 22px; min-height: 62px; padding: 12px 24px; border-top: 1px solid #c9cdd3; }
        .setup-requirements-card.is-animated .setup-requirement-row { opacity: 0; transform: translateX(28px); animation: setupRowIn .42s ease forwards; }
        .setup-requirement-row strong { display: block; color: #6f6f6f; font-size: .95rem; font-weight: 500; }
        .setup-requirement-row span { display: block; color: #7d8592; font-size: .82rem; margin-top: 4px; }
        .setup-requirement-row span.is-installable { color: #f28a20; }
        .setup-status { display: inline-flex; align-items: center; gap: 0; justify-self: center; color: #34c759; font-size: .92rem; font-weight: 700; line-height: 1.35; }
        .setup-status span { margin: 0; color: inherit; font-size: inherit; }
        .setup-status-icon { width: 18px; height: 18px; display: inline-grid; place-items: center; border-radius: 999px; background: transparent; flex: 0 0 auto; }
        .setup-status-icon svg { width: 18px; height: 18px; stroke-width: 2.8; }
        .setup-status-icon.is-loading { position: relative; color: #9aa1ab; }
        .setup-status-icon.is-loading svg { opacity: 0; }
        .setup-status-icon.is-loading::before { content: ""; position: absolute; inset: 0; border: 2px solid #d4d8de; border-top-color: #7b7b7b; border-radius: 999px; animation: setupSpin .7s linear infinite; }
        .setup-status-warn { color: #f28a20; }
        .setup-status-warn .setup-status-icon { background: transparent; }
        .setup-status-error { color: #111111; }
        .setup-status-error .setup-status-icon { background: transparent; }
        .setup-stepper { width: 810px; max-width: 100%; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0; margin: 0 auto; position: relative; padding-bottom: 38px; }
        .setup-step { position: relative; z-index: 1; display: grid; justify-items: center; gap: 14px; color: #777777; font-size: .92rem; font-weight: 700; }
        a.setup-step { text-decoration: none; }
        .setup-step.is-clickable { cursor: pointer; }
        .setup-step.is-clickable:hover { color: #111111; }
        .setup-step.is-locked { cursor: default; }
        .setup-step-circle { width: 24px; height: 24px; display: grid; place-items: center; border: 0; color: #777777; background: transparent; }
        .setup-step-circle svg { width: 23px; height: 23px; }
        .setup-step.is-active { color: #111111; }
        .setup-step.is-active .setup-step-circle { background: transparent; border-color: transparent; color: #111111; }
        .setup-step-track { position: absolute; left: 10%; right: 10%; bottom: 5px; height: 2px; background: #d0d2d6; }
        .setup-step-track-fill { position: absolute; left: 0; top: 0; height: 2px; background: #111111; }
        .setup-track-dot { position: absolute; top: 50%; width: 24px; height: 24px; border-radius: 999px; border: 2px solid #c9ccd2; background: #f8fafc; transform: translate(-50%, -50%); }
        .setup-track-dot:nth-of-type(2) { left: 0; }
        .setup-track-dot:nth-of-type(3) { left: 33.333%; }
        .setup-track-dot:nth-of-type(4) { left: 66.666%; }
        .setup-track-dot:nth-of-type(5) { left: 100%; }
        .setup-track-dot.is-filled { border-color: #111111; background: #111111; }
        .setup-track-dot.is-current { border-color: #111111; background: #f8fafc; }
        .setup-form { padding-top: 88px; }
        .setup-settings-card, .setup-admin-card { padding: 34px; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 28px 26px; }
        .setup-settings-card { grid-template-columns: 1fr; gap: 22px; padding: 26px 25px 24px; overflow: visible; }
        .setup-settings-top { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; padding-bottom: 18px; border-bottom: 1px solid #e6e6e6; place-items: start }
        .setup-field { display: grid; gap: 9px; position: relative; margin: 0; color: #17191d; }
        .setup-field + .setup-field { border-top: 1px solid #eceff3; padding-top: 24px; }
        .setup-settings-card .setup-field + .setup-field { border-top: 0; padding-top: 0; }
        .setup-settings-card > .setup-field-full { padding-bottom: 18px; border-bottom: 1px solid #e6e6e6; }
        .setup-settings-card > .setup-field-full:last-child { padding-bottom: 0; border-bottom: 0; }
        .setup-admin-card .setup-field + .setup-field { border-top: 0; padding-top: 0; }
        .setup-admin-card .setup-field { align-self: start; grid-template-rows: auto 46px minmax(18px, auto); }
        .setup-field .setup-eye-toggle { position: absolute; right: 10px; top: 28px; width: 38px; height: 38px; display: grid; place-items: center; border: 0; border-radius: 999px; background: transparent; color: #707070; padding: 0; box-shadow: none; cursor: pointer; }
        .setup-field .setup-eye-toggle:hover { background: #eef0f3; color: #111111; border: 0; }
        .setup-field .setup-eye-toggle svg { width: 20px; height: 20px; }
        .setup-admin-card .setup-field-icon input { padding-left: 14px; padding-right: 52px; }
        .setup-admin-card .setup-field-icon > svg { left: auto; right: 17px; top: 38px; width: 20px; height: 20px; color: #707070; }
        .setup-admin-card .setup-eye-toggle { position: absolute; right: 10px; top: 28px; width: 38px; height: 38px; display: grid; place-items: center; border: 0; border-radius: 999px; background: transparent; color: #707070; padding: 0; box-shadow: none; cursor: pointer; }
        .setup-admin-card .setup-eye-toggle:hover { background: #eef0f3; color: #111111; border: 0; }
        .setup-admin-card .setup-eye-toggle svg { width: 20px; height: 20px; }
        .setup-field-full { grid-column: 1 / -1; }
        .setup-field span:first-child { color: #101113; font-size: 12px; letter-spacing: 2px; font-weight: 400; color: #969da8 }
        .setup-field input, .setup-field select { width: 100%; height: 46px; margin: 0; border: 1px solid #c6cbd2; border-radius: 10px; background: #f7f8f9; color: #707070; font-size: .96rem; font-weight: 500; padding: 0 14px; box-shadow: none; }
        .setup-field input.is-invalid { border-color: #df695f; color: #b73b34; }
        .setup-field input::placeholder { color: #969da8; font-weight: 400; font-size: 14px}
        .setup-field input:focus, .setup-field select:focus { outline: 3px solid #eef1f5; border-color: #b8c0cc; }
        .setup-input-with-unit { position: relative; display: block; }
        .setup-input-with-unit input { padding-right: 70px; }
        .setup-input-with-unit b { position: absolute; right: 16px; top: 50%; transform: translateY(-50%); color: #707070; font-size: .8rem; letter-spacing: .05em; }
        .setup-field-icon input { padding-right: 52px; }
        .setup-field-icon > svg { position: absolute; left: 15px; top: 38px; width: 21px; height: 21px; color: #707070; pointer-events: none; }
        .setup-field-icon input { padding-left: 50px; }
        .setup-field-icon select { appearance: none; padding-right: 46px; }
        .setup-field-icon select + svg { left: auto; right: 14px; width: 18px; height: 18px; }
        .setup-field small { color: #727272; font-size: 12px; line-height: 1.32; font-weight: 400}
        .setup-dir-suggestions { display: none; }
        .setup-dir-portal { position: fixed; inset: 0; z-index: 80; pointer-events: none; }
        .setup-dir-menu { position: fixed; min-width: 188px; max-width: 280px; border: 1px solid #cfd5dd; border-radius: 10px; background: #ffffff; box-shadow: 0 10px 24px rgba(13, 17, 23, .12); overflow: hidden; pointer-events: auto; }
        .setup-dir-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; gap: 8px; width: 100%; margin: 0; padding: 10px 12px; border-top: 1px solid #edf0f4; background: #ffffff; color: #222; font-size: .85rem; font-weight: 500; cursor: pointer; }
        .setup-dir-row:first-child { border-top: 0; }
        .setup-dir-row:hover { background: #f3f6fa; color: #111; }
        .setup-dir-row-label { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .setup-dir-row-caret { color: #8e97a4; font-size: 15px; line-height: 1; }
        .setup-dir-row.no-children .setup-dir-row-caret { visibility: hidden; }
        .setup-dir-create-action { border-top: 1px solid #edf0f4; }
        .setup-dir-create-action .setup-dir-row-caret { color: #6f7783; font-weight: 700; }
        .setup-dir-create-action:hover .setup-dir-row-caret { color: #111; }
        .setup-dir-menu-state { margin: 0; padding: 10px 12px; color: #6f7783; font-size: .82rem; }
        .setup-field.is-invalid input { border-color: #df695f; }
        .setup-field.is-invalid small { color: #df695f; }
        .setup-bottom { display: grid; justify-items: center; gap: 24px; padding: 14px 0 18px; background: #f8fafc; }
        .setup-footer { color: #9aa1ab; font-size: .77rem; letter-spacing: .22em; font-weight: 800; }
        @keyframes setupRowIn {
            from { opacity: 0; transform: translateX(28px); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes setupSpin {
            to { transform: rotate(360deg); }
        }
        @media (max-width: 900px) {
            .setup-shell { padding: 14px 20px 10px; }
            .setup-content { width: calc(100% + 40px); margin: 0 -20px; }
            .setup-hero { width: min(1010px, calc(100% - 40px)); }
            .setup-topbar { align-items: flex-start; }
            .setup-links { gap: 12px; flex-wrap: wrap; }
            .setup-hero, .setup-hero-welcome, .setup-form { padding-top: 60px; padding-bottom: 42px; }
            .setup-feature-grid { grid-template-columns: 1fr; }
            .setup-table-head, .setup-requirement-row { grid-template-columns: 1fr; gap: 12px; }
            .setup-status { justify-self: stretch; }
            .setup-settings-card, .setup-admin-card { grid-template-columns: 1fr; }
            .setup-dir-menu { min-width: 170px; max-width: 240px; }
            .setup-stepper { grid-template-columns: repeat(2, minmax(0, 1fr)); row-gap: 20px; }
            .setup-stepper::before { display: none; }
            .install-header { display: block; }
            .page-header { display: grid; }
            .page-header .quick-create { grid-template-columns: 1fr; }
            .install-status { justify-content: flex-start; margin-top: 14px; }
            .install-grid { grid-template-columns: 1fr; }
            .module-layout, .split-grid, .metric-grid, .metric-grid-small, .guide-grid, .context-meta, .quick-action-grid { grid-template-columns: 1fr; }
            .module-tabs { gap: 12px; }
            .site-context-header { display: grid; }
            .steps { grid-template-columns: 1fr 1fr; }
            .app-shell { grid-template-columns: 1fr; }
            .app-sidebar { position: static; height: auto; min-height: 0; border-right: 0; border-bottom: 10px solid #f7f9fb; padding-bottom: 14px; }
            .app-nav-wrap { max-height: 320px; }
            .app-main { padding: 16px 16px 24px; }
            .admin-grid-3, .split-main { grid-template-columns: 1fr; }
            .service-grid { grid-template-columns: 1fr; }
            .service-item, .service-item:nth-child(2n) { border-right: 0; }
            .app-nav a { font-size: 16px; }
            .auth-shell { padding: 16px 20px 12px; border-radius: 0; }
            .auth-topbar { align-items: flex-start; }
            .auth-links { gap: 12px; flex-wrap: wrap; }
            .auth-main { padding: 28px 0; }
            .auth-title { font-size: 2.2rem; }
            .auth-copy { font-size: 1rem; }
            .auth-form-label { font-size: .8rem; }
            .auth-check, .auth-link { font-size: .95rem; }
            .auth-footer { font-size: .72rem; }
        }
        @media (max-width: 620px) {
            .setup-topbar { display: grid; }
            .setup-links { justify-content: flex-start; }
            .setup-hero h1 { font-size: 2rem; }
            .setup-card { margin-top: 34px; }
            .setup-requirement-row, .setup-table-head { padding-left: 20px; padding-right: 20px; }
            .setup-settings-card, .setup-admin-card { padding: 24px 20px; }
            .wrap { margin: 16px auto; padding: 0 12px; }
            .panel { padding: 18px; }
            .steps, .form-grid { grid-template-columns: 1fr; }
            .form-actions { justify-content: stretch; }
            button { width: 100%; }
            h1 { font-size: 2rem; }
            .auth-links { justify-content: flex-start; }
            .app-content { padding: 12px; }
            .module-tabs { gap: 10px; padding-bottom: 0; }
            .compact-table thead { display: none; }
            .compact-table,
            .compact-table tbody,
            .compact-table tr,
            .compact-table td { display: block; width: 100%; }
            .compact-table tr { border: 1px solid var(--line); border-radius: 8px; padding: 10px; margin-bottom: 10px; }
            .compact-table td { border-bottom: 0; padding: 4px 0; }
            .compact-table td + td, .compact-table th + th { padding-left: 0; }
        }
    </style>
</head>
<body class="layout-<?= htmlspecialchars((string) ($layoutMode ?? 'default'), ENT_QUOTES, 'UTF-8') ?>">
<?php if (!empty($toastMessage ?? '')): ?>
    <div class="toast-host" id="toastHost">
        <div class="toast" id="globalToast" data-type="<?= htmlspecialchars((string) ($toastType ?? 'info'), ENT_QUOTES, 'UTF-8') ?>" data-persistent="<?= !empty($toastPersistent ?? false) ? 'true' : 'false' ?>">
            <div class="toast-text"><?= htmlspecialchars((string) $toastMessage, ENT_QUOTES, 'UTF-8') ?></div>
            <button class="toast-close" type="button" id="toastClose">Kapat</button>
        </div>
    </div>
<?php endif; ?>
<?php if (($layoutMode ?? 'default') === 'install'): ?>
    <?= $content ?? '' ?>
<?php elseif (($layoutMode ?? 'default') === 'auth'): ?>
    <main class="setup-shell">
        <header class="setup-topbar">
            <a class="setup-brand" href="/login" aria-label="ailpanel">
                <img src="/assets/icon.png" alt="">
                <span>ailpanel</span>
            </a>
            <nav class="setup-links" aria-label="Giriş bağlantıları">
                <a href="#"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3.6 9h16.8M3.6 15h16.8M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/></svg><span>Website</span></a>
                <a href="#"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 19c-4.5 1.5-4.5-2.5-6-3m12 5v-3.9c0-1 .1-1.4-.5-2 2.8-.3 5.5-1.4 5.5-6a4.6 4.6 0 0 0-1.3-3.2 4.2 4.2 0 0 0-.1-3.2s-1.1-.3-3.5 1.3a12.3 12.3 0 0 0-6.2 0C6.5 1.4 5.4 1.7 5.4 1.7a4.2 4.2 0 0 0-.1 3.2A4.6 4.6 0 0 0 4 8.1c0 4.6 2.7 5.7 5.5 6-.6.6-.6 1.2-.5 2V21"/></svg><span>Github</span></a>
                <a href="#"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A15 15 0 0 1 3 6a2 2 0 0 1 2-2"/></svg><span>İletişim</span></a>
                <a href="#"><span>v1.0.0-alpha</span></a>
            </nav>
        </header>
        <section class="setup-content">
            <?= $content ?? '' ?>
        </section>
        <footer class="setup-bottom">
            <div class="setup-footer">AILDEV SOFTWARE</div>
        </footer>
    </main>
<?php elseif (($layoutMode ?? 'default') === 'app'): ?>
    <main class="app-shell">
        <aside class="app-sidebar">
            <div class="app-brand-wrap">
                <img src="/assets/icon.png" alt="">
                <div class="app-brand-meta">
                    <small>AILDEV SOFTWARE</small>
                    <div class="app-brand">ailpanel</div>
                </div>
            </div>
            <div class="app-nav-wrap">
                <nav class="app-nav" data-nav>
                    <span class="app-nav-active-dot" data-nav-dot aria-hidden="true"></span>
                    <a href="/dashboard" class="<?= (($navActive ?? '') === 'dashboard') ? 'is-active' : '' ?>">Genel Bakış</a>
                    <a href="/sites" class="<?= (($navActive ?? '') === 'sites') ? 'is-active' : '' ?>">Websiteler</a>
                    <a href="/sites/dns" class="<?= (($navActive ?? '') === 'dns') ? 'is-active' : '' ?>">DNS</a>
                    <a href="/sites/ssl" class="<?= (($navActive ?? '') === 'ssl') ? 'is-active' : '' ?>">SSL</a>
                    <a href="/sites/files" class="<?= (($navActive ?? '') === 'files') ? 'is-active' : '' ?>">Dosyalar</a>
                    <a href="/sites/deploy" class="<?= (($navActive ?? '') === 'deploy') ? 'is-active' : '' ?>">Dağıtım</a>
                    <a href="/sites/backups" class="<?= (($navActive ?? '') === 'backups') ? 'is-active' : '' ?>">Yedekleme</a>
                    <a href="/sites/mail" class="<?= (($navActive ?? '') === 'mail') ? 'is-active' : '' ?>">E-posta</a>
                    <a href="/sites/overview" class="<?= (($navActive ?? '') === 'wordpress') ? 'is-active' : '' ?>">Wordpress</a>
                    <a href="/services" class="<?= (($navActive ?? '') === 'services') ? 'is-active' : '' ?>">Docker</a>
                    <a href="/users" class="<?= (($navActive ?? '') === 'users') ? 'is-active' : '' ?>">Kullanıcılar</a>
                    <a href="/jobs" class="<?= (($navActive ?? '') === 'jobs') ? 'is-active' : '' ?>">Çalışma Kuyruğu</a>
                    <a href="/logs" class="<?= (($navActive ?? '') === 'logs') ? 'is-active' : '' ?>">Günlükler</a>
                    <a href="/account" class="<?= (($navActive ?? '') === 'account') ? 'is-active' : '' ?>">Ayarlar</a>
                </nav>
            </div>
            <div class="app-sidebar-user">
                <span class="app-user-avatar" aria-hidden="true"></span>
                <span class="app-sidebar-id">
                    <strong><?= htmlspecialchars($appSidebarName !== '' ? $appSidebarName : 'AIL-SERVER', ENT_QUOTES, 'UTF-8') ?></strong>
                    <small><?= htmlspecialchars($appSidebarIp !== '' ? $appSidebarIp : '127.0.0.1', ENT_QUOTES, 'UTF-8') ?></small>
                </span>
                <button type="button" class="app-user-more" data-user-menu-toggle aria-label="Kullanıcı menüsü">⋮</button>
                <div class="app-user-menu" data-user-menu>
                    <a href="/account"><?= htmlspecialchars((string) ($authEmail ?? 'Account'), ENT_QUOTES, 'UTF-8') ?></a>
                    <hr>
                    <a href="/account">Account</a>
                    <a href="/account">Billing</a>
                    <a href="/logs">Notifications</a>
                    <hr>
                    <?php if (!empty($csrfToken ?? '')): ?>
                        <form method="post" action="/logout" class="form-block">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit">Sign Out</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </aside>
        <section class="app-main">
            <section class="app-content">
                <?= $content ?? '' ?>
            </section>
        </section>
    </main>
<?php else: ?>
    <main class="wrap">
        <section class="panel">
            <?= $content ?? '' ?>
        </section>
    </main>
<?php endif; ?>
<div id="drawerBackdrop" class="drawer-backdrop" hidden></div>
<div id="confirmWrap" class="confirm-modal-wrap" hidden>
    <div class="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirmTitle">
        <h3 id="confirmTitle">İşlem Onayı</h3>
        <p id="confirmMessage">Bu işlemi yapmak istediğinize emin misiniz?</p>
        <div class="confirm-actions">
            <button type="button" class="button-secondary" id="confirmCancel">Vazgeç</button>
            <button type="button" id="confirmApprove">Devam Et</button>
        </div>
    </div>
</div>
<?php if (!empty($toastMessage ?? '')): ?>
<script>
(() => {
    const toast = document.getElementById('globalToast');
    const closeBtn = document.getElementById('toastClose');
    if (!toast || !closeBtn) return;

    const removeToast = () => {
        const host = document.getElementById('toastHost');
        if (host) host.remove();
    };

    closeBtn.addEventListener('click', removeToast);

    if (toast.dataset.persistent !== 'true') {
        setTimeout(removeToast, 4200);
    }
})();
</script>
<?php endif; ?>
<script>
(() => {
    const backdrop = document.getElementById('drawerBackdrop');
    const drawers = Array.from(document.querySelectorAll('.drawer'));
    const openDrawer = (id) => {
        const target = document.getElementById(id);
        if (!target || !backdrop) return;
        backdrop.hidden = false;
        backdrop.classList.add('is-open');
        target.classList.add('is-open');
        target.removeAttribute('hidden');
    };
    const closeDrawers = () => {
        if (backdrop) {
            backdrop.classList.remove('is-open');
            backdrop.hidden = true;
        }
        drawers.forEach((drawer) => {
            drawer.classList.remove('is-open');
            drawer.setAttribute('hidden', 'hidden');
        });
    };
    document.querySelectorAll('[data-open-drawer]').forEach((button) => {
        button.addEventListener('click', () => {
            const id = button.getAttribute('data-open-drawer');
            if (id) openDrawer(id);
        });
    });
    document.querySelectorAll('[data-close-drawer]').forEach((button) => {
        button.addEventListener('click', closeDrawers);
    });
    backdrop && backdrop.addEventListener('click', closeDrawers);
})();
</script>
<script>
(() => {
    const wrap = document.getElementById('confirmWrap');
    const msg = document.getElementById('confirmMessage');
    const btnCancel = document.getElementById('confirmCancel');
    const btnApprove = document.getElementById('confirmApprove');
    let pendingForm = null;

    const close = () => {
        if (!wrap) return;
        wrap.classList.remove('is-open');
        wrap.hidden = true;
        pendingForm = null;
    };
    const open = (message, form) => {
        if (!wrap || !msg) return;
        pendingForm = form;
        msg.textContent = message || 'Bu işlemi yapmak istediğinize emin misiniz?';
        wrap.hidden = false;
        wrap.classList.add('is-open');
    };

    document.querySelectorAll('form[data-confirm-message]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.confirmed === 'true') {
                form.dataset.confirmed = 'false';
                return;
            }
            event.preventDefault();
            open(form.getAttribute('data-confirm-message') || '', form);
        });
    });

    btnCancel && btnCancel.addEventListener('click', close);
    wrap && wrap.addEventListener('click', (event) => {
        if (event.target === wrap) close();
    });
    btnApprove && btnApprove.addEventListener('click', () => {
        if (!pendingForm) return close();
        pendingForm.dataset.confirmed = 'true';
        pendingForm.requestSubmit();
        close();
    });
})();
</script>
<script>
(() => {
    const toggle = document.querySelector('[data-user-menu-toggle]');
    const menu = document.querySelector('[data-user-menu]');
    if (!toggle || !menu) return;

    const closeMenu = () => menu.classList.remove('is-open');
    toggle.addEventListener('click', (event) => {
        event.stopPropagation();
        menu.classList.toggle('is-open');
    });
    menu.addEventListener('click', (event) => event.stopPropagation());
    document.addEventListener('click', closeMenu);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeMenu();
    });
})();
</script>

<script>
(() => {
    const nav = document.querySelector('[data-nav]');
    if (!nav) return;

    const dot = nav.querySelector('[data-nav-dot]');
    if (!dot) return;

    const moveDotTo = (link, animate = true) => {
        if (!link) return;

        const navRect = nav.getBoundingClientRect();
        const linkRect = link.getBoundingClientRect();

        const dotY = linkRect.top - navRect.top + (linkRect.height / 2) - 4;

        if (!animate) {
            dot.style.transition = 'none';
        }

        dot.style.setProperty('--nav-dot-y', `${dotY}px`);
        nav.classList.add('has-active-dot');

        if (!animate) {
            requestAnimationFrame(() => {
                dot.style.transition = '';
            });
        }
    };

    const activeLink = nav.querySelector('a.is-active');
    moveDotTo(activeLink, false);

    nav.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', (event) => {
            if (
                event.button !== 0 ||
                event.metaKey ||
                event.ctrlKey ||
                event.shiftKey ||
                event.altKey ||
                link.target === '_blank'
            ) {
                return;
            }

            event.preventDefault();

            nav.querySelectorAll('a.is-active').forEach((item) => {
                item.classList.remove('is-active');
            });

            link.classList.add('is-active');
            moveDotTo(link, true);

            setTimeout(() => {
                window.location.href = link.href;
            }, 180);
        });
    });

    window.addEventListener('resize', () => {
        const currentActive = nav.querySelector('a.is-active');
        moveDotTo(currentActive, false);
    });
})();
</script>

<script>
(() => {
    const toggles = Array.from(document.querySelectorAll('[data-popover-toggle]'));
    if (toggles.length === 0) return;

    const closeAll = () => {
        document.querySelectorAll('[data-popover-menu].is-open').forEach((menu) => menu.classList.remove('is-open'));
        toggles.forEach((button) => button.classList.remove('is-open'));
    };

    toggles.forEach((button) => {
        const menu = button.parentElement ? button.parentElement.querySelector('[data-popover-menu]') : null;
        if (!menu) return;
        button.addEventListener('click', (event) => {
            event.stopPropagation();
            const willOpen = !menu.classList.contains('is-open');
            closeAll();
            if (willOpen) {
                button.classList.add('is-open');
                menu.classList.add('is-open');
            }
        });
        menu.addEventListener('click', (event) => event.stopPropagation());
    });

    document.addEventListener('click', closeAll);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeAll();
    });
})();
</script>
<script>
(() => {
    const forms = Array.from(document.querySelectorAll('form'));
    const fields = Array.from(document.querySelectorAll('input[data-validate], select[data-validate], textarea[data-validate]'));
    if (fields.length === 0) return;

    const messageFor = (field) => {
        const v = field.validity;
        if (v.valueMissing) return field.dataset.msgRequired || 'Bu alan zorunlu.';
        if (v.typeMismatch) return field.dataset.msgType || 'Geçerli bir değer girin.';
        if (v.tooShort) return field.dataset.msgMinlength || 'Değer çok kısa.';
        if (v.tooLong) return field.dataset.msgMaxlength || 'Değer çok uzun.';
        if (v.rangeUnderflow) return field.dataset.msgMin || 'Değer sınırın altında.';
        if (v.rangeOverflow) return field.dataset.msgMax || 'Değer sınırın üstünde.';
        if (v.patternMismatch) return field.dataset.msgPattern || 'Biçim geçersiz.';
        if (v.badInput) return field.dataset.msgBadinput || 'Geçersiz giriş.';
        return '';
    };

    const clearError = (field) => {
        field.setCustomValidity('');
        const targetId = field.dataset.errorTarget || '';
        if (!targetId) return;
        const el = document.getElementById(targetId);
        if (el) el.textContent = '';
    };

    const setError = (field, message) => {
        field.setCustomValidity(message);
        const targetId = field.dataset.errorTarget || '';
        if (!targetId) return;
        const el = document.getElementById(targetId);
        if (el) el.textContent = message;
    };

    fields.forEach((field) => {
        field.addEventListener('input', () => clearError(field));
        field.addEventListener('change', () => clearError(field));
    });

    forms.forEach((form) => {
        form.addEventListener('submit', (event) => {
            let firstInvalid = null;
            const scoped = Array.from(form.querySelectorAll('[data-validate]'));
            scoped.forEach((field) => {
                clearError(field);
                if (!field.checkValidity()) {
                    const message = messageFor(field);
                    if (message !== '') setError(field, message);
                    if (firstInvalid === null) firstInvalid = field;
                }
            });
            if (firstInvalid !== null) {
                event.preventDefault();
                firstInvalid.reportValidity();
            }
        });
    });
})();
</script>
</body>
</html>
