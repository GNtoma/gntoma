<?php
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<script src="https://unpkg.com/htmx.org@1.9.10"></script>
<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
<script>
document.documentElement.classList.add('gntoma-ui-ready');
document.addEventListener('DOMContentLoaded', function () {
    document.body.classList.add('gntoma-body-ready');
});
</script>
<style>
:root {
    --gntoma-primary: #007AFF;
    --gntoma-secondary: #5AC8FA;
    --gntoma-accent: #7C3AED;
    --gntoma-dark: #1D1D1F;
    --gntoma-surface: rgba(255, 255, 255, 0.78);
    --gntoma-surface-strong: rgba(255, 255, 255, 0.92);
    --gntoma-border: rgba(255, 255, 255, 0.72);
    --gntoma-shadow: 0 20px 60px rgba(15, 23, 42, 0.10);
    --gntoma-shadow-strong: 0 26px 90px rgba(0, 122, 255, 0.15);
}
html {
    scroll-behavior: smooth;
}
body {
    font-family: 'Outfit', sans-serif;
    background:
        radial-gradient(circle at top left, rgba(90, 200, 250, 0.20), transparent 28%),
        radial-gradient(circle at top right, rgba(124, 58, 237, 0.13), transparent 24%),
        radial-gradient(circle at bottom left, rgba(0, 122, 255, 0.12), transparent 24%),
        linear-gradient(145deg, #edf6ff 0%, #f8fbff 46%, #f5f7fb 100%);
    color: var(--gntoma-dark);
    min-height: 100vh;
    overflow-x: hidden;
}
body.gntoma-body-ready {
    animation: gntomaFadeBody .55s ease-out;
}
::selection {
    background: rgba(0, 122, 255, 0.18);
    color: var(--gntoma-dark);
}
::-webkit-scrollbar {
    width: 0;
    height: 0;
}
html.gntoma-ui-ready body {
    background: transparent !important;
}
.gntoma-ui-background {
    position: fixed;
    inset: 0;
    overflow: hidden;
    pointer-events: none;
    z-index: -1;
}
.gntoma-ui-background::before {
    content: '';
    position: absolute;
    inset: 0;
    background:
        radial-gradient(circle at top left, rgba(90, 200, 250, 0.20), transparent 28%),
        radial-gradient(circle at top right, rgba(124, 58, 237, 0.13), transparent 24%),
        radial-gradient(circle at bottom left, rgba(0, 122, 255, 0.12), transparent 24%),
        linear-gradient(145deg, #edf6ff 0%, #f8fbff 46%, #f5f7fb 100%);
}
.gntoma-confetti {
    position: absolute;
    inset: 0;
    overflow: hidden;
}
.gntoma-confetti-piece {
    position: absolute;
    top: -12vh;
    left: calc(var(--left) * 1%);
    width: var(--w);
    height: var(--h);
    background: var(--c);
    border-radius: var(--r);
    opacity: 0.88;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.12);
    animation: gntomaConfettiFall var(--dur) linear var(--delay) infinite;
    will-change: transform;
}
.gntoma-confetti-layer-0 {
    opacity: 0.55;
    filter: blur(0.3px);
}
.gntoma-confetti-layer-1 {
    opacity: 0.78;
}
.gntoma-confetti-layer-2 {
    opacity: 0.95;
}
.gntoma-ui-grid,
.gntoma-ui-noise,
.gntoma-ui-lines,
.gntoma-ui-glow {
    position: absolute;
    inset: 0;
}
.gntoma-ui-grid {
    background-image:
        linear-gradient(rgba(255,255,255,0.38) 1px, transparent 1px),
        linear-gradient(90deg, rgba(0,122,255,0.05) 1px, transparent 1px);
    background-size: 34px 34px;
    mask-image: radial-gradient(circle at center, rgba(0,0,0,0.9), transparent 78%);
    opacity: 0.7;
}
.gntoma-ui-lines {
    background-image: linear-gradient(120deg, rgba(0,122,255,0.08), transparent 35%, rgba(124,58,237,0.05) 65%, transparent 100%);
    filter: blur(40px);
    opacity: 0.8;
}
.gntoma-ui-noise {
    background-image: radial-gradient(rgba(255,255,255,0.55) 1px, transparent 1px);
    background-size: 18px 18px;
    opacity: 0.22;
}
.gntoma-ui-glow {
    background:
        radial-gradient(circle at 20% 20%, rgba(0,122,255,0.12), transparent 22%),
        radial-gradient(circle at 82% 14%, rgba(90,200,250,0.14), transparent 18%),
        radial-gradient(circle at 70% 78%, rgba(124,58,237,0.11), transparent 18%);
    filter: blur(10px);
}
.gntoma-orb {
    position: absolute;
    border-radius: 9999px;
    filter: blur(18px);
    opacity: 0.7;
    animation: gntomaFloat 18s ease-in-out infinite;
}
.gntoma-orb-a {
    width: 240px;
    height: 240px;
    background: radial-gradient(circle at 30% 30%, rgba(90, 200, 250, 0.8), rgba(0, 122, 255, 0.1));
    top: -40px;
    left: -40px;
}
.gntoma-orb-b {
    width: 200px;
    height: 200px;
    background: radial-gradient(circle at 30% 30%, rgba(124, 58, 237, 0.45), rgba(124, 58, 237, 0.06));
    right: -30px;
    top: 16%;
    animation-duration: 24s;
}
.gntoma-orb-c {
    width: 280px;
    height: 280px;
    background: radial-gradient(circle at 30% 30%, rgba(255,255,255,0.88), rgba(90, 200, 250, 0.08));
    bottom: -80px;
    left: 18%;
    animation-duration: 26s;
}
.gntoma-page-enter {
    animation: fadeInUp 0.65s ease both;
}
.gntoma-card-lift,
.glass-panel,
.glass-panel-light {
    background: linear-gradient(145deg, rgba(255,255,255,0.90), rgba(255,255,255,0.72));
    border: 1px solid var(--gntoma-border);
    box-shadow: var(--gntoma-shadow);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
}
.gntoma-card-lift:hover,
.glass-panel:hover,
.glass-panel-light:hover {
    transform: translateY(-2px);
    box-shadow: var(--gntoma-shadow-strong);
}
.glass-input,
.glass-input-light {
    background: rgba(255,255,255,0.88);
    border: 1px solid rgba(148, 163, 184, 0.16);
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.7);
}
.glass-input:focus,
.glass-input-light:focus {
    border-color: rgba(0, 122, 255, 0.34);
    box-shadow: 0 0 0 5px rgba(0, 122, 255, 0.12), inset 0 1px 0 rgba(255,255,255,0.9);
}
.ios-blur {
    background: rgba(255,255,255,0.72);
    border-bottom: 1px solid rgba(255,255,255,0.7);
    box-shadow: 0 10px 40px rgba(15, 23, 42, 0.06);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
}
.gntoma-topbar {
    background: rgba(255,255,255,0.74);
    border: 1px solid rgba(255,255,255,0.65);
    box-shadow: 0 10px 35px rgba(15, 23, 42, 0.06);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
}
.gntoma-chip {
    background: rgba(255,255,255,0.72);
    border: 1px solid rgba(255,255,255,0.68);
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.05);
}
.gntoma-primary-button {
    background-image: linear-gradient(135deg, #007AFF 0%, #5AC8FA 100%);
    box-shadow: 0 16px 30px rgba(0, 122, 255, 0.25);
}
.gntoma-primary-button:hover {
    filter: brightness(1.03);
    transform: translateY(-1px);
}
.gntoma-dark-button {
    background-image: linear-gradient(135deg, #1D1D1F 0%, #2f2f33 100%);
    box-shadow: 0 16px 30px rgba(29, 29, 31, 0.18);
}
.gntoma-badge {
    background: linear-gradient(135deg, rgba(255,255,255,0.92), rgba(255,255,255,0.76));
    border: 1px solid rgba(255,255,255,0.7);
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
}
.gntoma-title-glow {
    text-shadow: 0 12px 38px rgba(0,122,255,0.16);
}
.gntoma-section-shell {
    position: relative;
    isolation: isolate;
}
.gntoma-section-shell::before {
    content: '';
    position: absolute;
    inset: -1px;
    border-radius: inherit;
    background: linear-gradient(135deg, rgba(255,255,255,0.72), rgba(255,255,255,0.12));
    z-index: -1;
}
@keyframes gntomaFloat {
    0%, 100% { transform: translate3d(0, 0, 0) scale(1); }
    50% { transform: translate3d(0, 24px, 0) scale(1.06); }
}
@keyframes gntomaConfettiFall {
    0% {
        transform: translate3d(0, -10vh, 0) rotate(0deg);
    }
    100% {
        transform: translate3d(var(--sway), 115vh, 0) rotate(720deg);
    }
}
@media (prefers-reduced-motion: reduce) {
    .gntoma-confetti-piece {
        animation: none;
        display: none;
    }
    .gntoma-orb {
        animation: none;
    }
}
@keyframes gntomaFadeBody {
    from { opacity: 0; transform: translateY(4px); }
    to { opacity: 1; transform: translateY(0); }
}
@media (max-width: 640px) {
    .gntoma-orb-a {
        width: 180px;
        height: 180px;
        top: -28px;
        left: -36px;
    }
    .gntoma-orb-b {
        width: 150px;
        height: 150px;
    }
    .gntoma-orb-c {
        width: 190px;
        height: 190px;
        left: auto;
        right: -30px;
        bottom: 10%;
    }
}
</style>
