<?php
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$basePath = preg_replace('#/journal$#', '', rtrim(dirname($scriptName), '/'));
if ($basePath === '.' || $basePath === '/') {
    $basePath = '';
}

$manifestUrl = $basePath . '/manifest.webmanifest';
$iconUrl = $basePath . '/images/logo.png';
$serviceWorkerUrl = $basePath . '/sw.js';
$scopeUrl = ($basePath === '' ? '/' : $basePath . '/');
?>
<link rel="manifest" href="<?= htmlspecialchars($manifestUrl, ENT_QUOTES, 'UTF-8') ?>">
<meta name="theme-color" content="#007AFF">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="GNTOMA">
<link rel="icon" type="image/png" sizes="750x750" href="<?= htmlspecialchars($iconUrl, ENT_QUOTES, 'UTF-8') ?>">
<link rel="apple-touch-icon" href="<?= htmlspecialchars($iconUrl, ENT_QUOTES, 'UTF-8') ?>">

<!-- Lucide Icons (icones modernes) -->
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>

<!-- Animate.css (animations fluides) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">

<style>
    /* === Améliorations design GNTOMA (compléments à ui_head.php) === */
    
    /* Initialisation des icônes Lucide */
    [data-lucide] { stroke-width: 2; }
    
    /* Animations utilitaires supplémentaires */
    @keyframes gntomaShimmer { 0% { background-position: -200% 0; } 100% { background-position: 200% 0; } }
    @keyframes gntomaPulseGlow { 0%, 100% { box-shadow: 0 0 0 0 rgba(0, 122, 255, 0.4); } 50% { box-shadow: 0 0 0 10px rgba(0, 122, 255, 0); } }
    @keyframes gntomaBounce { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-5px); } }
    
    .gntoma-pulse-glow { animation: gntomaPulseGlow 2s infinite; }
    .gntoma-bounce { animation: gntomaBounce 1.5s ease-in-out infinite; }
    
    /* Loading shimmer pour squelettes */
    .gntoma-shimmer {
        background: linear-gradient(90deg, rgba(240,240,240,0.6) 25%, rgba(224,224,224,0.6) 50%, rgba(240,240,240,0.6) 75%);
        background-size: 200% 100%;
        animation: gntomaShimmer 1.5s infinite;
    }
    
    /* Feedback tactile boutons (sans casser les transforms existants) */
    button:active:not(:disabled), 
    a[href]:not(.no-press):active { 
        filter: brightness(0.95);
    }
    
    /* Focus visible amélioré pour accessibilité */
    *:focus-visible:not(input):not(textarea):not(select) { 
        outline: 2px solid #007AFF; 
        outline-offset: 2px; 
        border-radius: 4px; 
    }
    
    /* Effet hover sur les cards journal */
    .journal-card { transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); }
    .journal-card:hover { transform: translateY(-2px); box-shadow: 0 16px 40px rgba(0, 122, 255, 0.12); }
    
    /* Stylisation des icônes Lucide (taille par défaut) */
    .lucide { display: inline-block; vertical-align: middle; }
</style>

<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('<?= htmlspecialchars($serviceWorkerUrl, ENT_QUOTES, 'UTF-8') ?>', {
            scope: '<?= htmlspecialchars($scopeUrl, ENT_QUOTES, 'UTF-8') ?>'
        }).catch(function (error) {
            console.error('PWA registration failed:', error);
        });
    });
}

// Initialiser les icônes Lucide après le chargement (et après les chargements HTMX)
document.addEventListener('DOMContentLoaded', function() {
    if (window.lucide) lucide.createIcons();
});
document.body && document.body.addEventListener('htmx:afterSwap', function() {
    if (window.lucide) lucide.createIcons();
});
</script>
