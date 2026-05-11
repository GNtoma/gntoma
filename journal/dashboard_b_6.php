<?php
/**
 * PROJET : GNTOMA
 * FICHIER : journal/dashboard_b_6.php
 * DESCRIPTION : Interface complète et optimisée mobile (Recherche -> Liste -> Profil -> Paiement).
 */

// 1. GESTION DES ALERTES VISUELLES
$alert_html = "";
if (isset($_GET['error'])) {
    $msg = "Erreur de session ou de paiement.";
    if ($_GET['error'] === 'session_invalide') $msg = "Session invalide ou expirée.";
    if ($_GET['error'] === 'payment_failed') $msg = "Le paiement n'a pas pu être validé.";
    if ($_GET['error'] === 'payment_init_failed') $msg = "Échec lors de l'initialisation du paiement.";
    if ($_GET['error'] === 'payment_processing_failed') $msg = "Erreur lors du traitement du paiement.";
    if ($_GET['error'] === 'missing_params') $msg = "Paramètres de paiement manquants.";
    if ($_GET['error'] === 'invalid_target') $msg = "Code auteur destinataire invalide.";
    if ($_GET['error'] === 'curl_error') $msg = "Erreur de connexion au service de paiement.";
    if ($_GET['error'] === 'http_error') $msg = "Service de paiement temporairement indisponible.";
    if ($_GET['error'] === 'flexpay_error') $msg = "Erreur du service de paiement. Veuillez réessayer.";
    if ($_GET['error'] === 'subscription_expired') $msg = "Votre abonnement a expiré. Veuillez le prolonger.";
    
    $alert_html = '
    <div class="bg-red-50 border border-red-100 p-4 rounded-3xl flex items-center space-x-3 mb-5 animate__animated animate__headShake">
        <div class="bg-red-500 text-white p-2 rounded-full shadow-lg">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
        </div>
        <span class="text-xs font-black text-red-900">' . $msg . '</span>
    </div>';
} elseif (isset($_GET['success'])) {
    $msg = "Opération réussie avec succès !";
    if ($_GET['success'] === 'payment_success') $msg = "Paiement validé ! Votre abonnement a été prolongé.";
    if ($_GET['success'] === 'gift_success') $msg = "Cadeau envoyé avec succès ! L'abonnement a été prolongé.";
    if ($_GET['success'] === 'journal_created') $msg = "Journal créé avec succès ! Commencez à écrire.";
    if ($_GET['success'] === 'journal_deleted') $msg = "Journal supprimé avec succès.";
    
    $alert_html = '
    <div class="bg-green-50 border border-green-100 p-4 rounded-3xl flex items-center space-x-3 mb-5 animate__animated animate__bounceIn">
        <div class="bg-green-500 text-white p-2 rounded-full shadow-lg">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
        </div>
        <span class="text-xs font-black text-green-900">' . $msg . '</span>
    </div>';
}
?>

<div class="space-y-6 animate__animated animate__fadeIn">

    <?php echo $alert_html; ?>

    <!-- Recherche par code A3 ou A3J2 -->
    <form action="search_code.php" method="GET" class="flex gap-2 w-full min-w-0 mb-3 relative z-20">
        <div class="relative flex-1 min-w-0">
            <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none text-gray-400">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
            </div>
            <input type="text" 
                   name="code" 
                   placeholder="A3, A3J2..." 
                   class="w-full bg-white border-0 py-3 pl-10 pr-3 rounded-[1.5rem] shadow-sm font-bold text-sm outline-none focus:ring-2 focus:ring-primary transition-all uppercase"
                   inputmode="text"
                   autocomplete="off"
                   pattern="[A-Za-z]\d+([Jj]\d+)?"
                   maxlength="10"
                   title="Format: A3 (auteur) ou A3J2 (journal)"
                   hx-get="search_code_live_partial.php"
                   hx-trigger="keyup changed delay:300ms, search"
                   hx-target="#search-results">
        </div>
        <button type="submit" class="bg-primary text-white px-4 py-3 rounded-[1.5rem] shadow-lg flex items-center justify-center active:scale-95 transition-all flex-shrink-0">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
        </button>
        <a href="journal_create_9.php" class="bg-dark text-white px-4 py-3 rounded-[1.5rem] shadow-lg flex items-center justify-center active:scale-95 transition-all flex-shrink-0">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
        </a>
    </form>

    <div id="search-results" class="empty:hidden relative z-10 -mt-1 mb-4"></div>

    <!-- Accès rapide -->
    <div class="flex gap-2">
        <a href="journal_go_to.php" class="flex-1 bg-white/70 backdrop-blur-sm border border-white py-3 px-2 sm:px-4 rounded-[1.5rem] shadow-sm flex items-center justify-center space-x-1 sm:space-x-2 hover:bg-white transition-all min-w-0">
            <svg class="h-4 w-4 sm:h-5 sm:w-5 text-primary flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
            <span class="text-xs sm:text-sm font-bold text-dark truncate">Journal N°</span>
        </a>
        <a href="journal_requests_list.php" class="flex-1 bg-orange-100/70 backdrop-blur-sm border border-orange-200 py-3 px-2 sm:px-4 rounded-[1.5rem] shadow-sm flex items-center justify-center space-x-1 sm:space-x-2 hover:bg-orange-100 transition-all min-w-0 relative">
            <span class="text-base sm:text-lg font-black text-orange-600">D</span>
            <span class="text-xs sm:text-sm font-bold text-dark truncate">Demandes</span>
            <div id="request-badge-anchor" hx-get="api_get_request_count.php" hx-trigger="load, every 10s" class="absolute -top-1 -right-1"></div>
        </a>
    </div>

    <div>
        <div class="flex items-center space-x-2 ml-2 mb-3">
            <div class="bg-blue-100 p-1.5 rounded-lg text-primary">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
            </div>
            <h3 class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Vos Journaux</h3>
        </div>
        
        <div id="journal-list" class="grid grid-cols-1 gap-4" hx-get="journal_list_partial_8.php" hx-trigger="load">
            <div class="bg-white/50 border border-white p-6 rounded-[2rem] shadow-sm animate-pulse flex flex-col justify-between h-32">
                <div class="flex justify-between items-start">
                    <div class="w-16 h-4 bg-gray-200 rounded-full"></div>
                    <div class="w-20 h-3 bg-gray-200 rounded-full"></div>
                </div>
                <div class="w-3/4 h-6 bg-gray-200 rounded-full mt-4"></div>
            </div>
        </div>
    </div>

    <hr class="border-gray-200/60 mx-4 my-2">

    <div class="grid grid-cols-2 gap-3">
        <a href="profile_edit.php" class="bg-white/80 rounded-[2rem] p-3 flex items-center space-x-3 border border-white shadow-sm hover:bg-white transition-all">
            <?php 
            // Gestion complète de l'image de profil
            $profile_pic = !empty($user['profile_pic']) ? '../' . $user['profile_pic'] : '../images/user_default.png'; 
            ?>
            <img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="Profil" class="w-10 h-10 rounded-[1rem] border border-gray-100 object-cover">
            <div class="overflow-hidden">
                <p class="font-black text-[11px] text-dark truncate"><?php echo htmlspecialchars($user['name']); ?></p>
                <p class="text-[9px] text-primary font-bold uppercase"><?php echo htmlspecialchars($user['user_code']); ?></p>
            </div>
        </a>
        <div class="bg-dark rounded-[2rem] p-3 text-white flex flex-col justify-center px-5 shadow-lg relative overflow-hidden">
            <svg class="absolute -right-2 -bottom-2 w-12 h-12 text-white opacity-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <p class="text-[7px] font-black uppercase opacity-50 relative z-10">Accès restant</p>
            <p class="text-sm font-black tracking-tighter relative z-10"><?php echo htmlspecialchars($time_remaining); ?></p>
        </div>
    </div>

    <!-- Messagerie accès rapide -->
    <?php
    $msg_remaining = 0;
    try {
        $msg_c = $pdo->prepare("SELECT remaining_credits FROM message_credits WHERE user_code = ?");
        $msg_c->execute([$_SESSION['user_id']]);
        $msg_r = $msg_c->fetch();
        $msg_remaining = $msg_r['remaining_credits'] ?? 0;
    } catch (Throwable $e) {
        error_log('Erreur messages dashboard GNTOMA : ' . $e->getMessage());
    }
    
    // Compter le nombre d'auteurs suivis
    $following_count = 0;
    try {
        $follow_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM author_follows WHERE follower_user_code = ?");
        $follow_stmt->execute([$_SESSION['user_id']]);
        $following_count = $follow_stmt->fetch()['count'] ?? 0;
    } catch (Throwable $e) {
        error_log('Erreur following count dashboard GNTOMA : ' . $e->getMessage());
    }
    
    ?>
    <div class="grid grid-cols-4 gap-2">
        <a href="message_send.php" class="relative bg-white/80 backdrop-blur-sm border border-white py-3 px-2 rounded-[1.5rem] shadow-sm flex flex-col items-center justify-center space-y-1 hover:bg-white transition-all">
            <svg class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
            </svg>
            <span class="text-[10px] font-bold text-dark">Messages</span>
            <div id="message-badge-anchor" hx-get="api_get_message_count.php" hx-trigger="every 5s, load" class="absolute -top-1 -right-1"></div>
        </a>
        <a href="following_feed.php" class="bg-purple-100/80 backdrop-blur-sm border border-purple-200 py-3 px-2 rounded-[1.5rem] shadow-sm flex flex-col items-center justify-center space-y-1 hover:bg-purple-100 transition-all relative">
            <svg class="h-5 w-5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
            <span class="text-[10px] font-bold text-dark">Suivis</span>
            <?php if ($following_count > 0): ?>
            <span class="absolute -top-1 -right-1 bg-purple-600 text-white text-[8px] font-bold w-4 h-4 rounded-full flex items-center justify-center"><?= $following_count ?></span>
            <?php endif; ?>
        </a>
        <a href="profile_edit.php" class="bg-white/80 backdrop-blur-sm border border-white py-3 px-2 rounded-[1.5rem] shadow-sm flex flex-col items-center justify-center space-y-1 hover:bg-white transition-all">
            <svg class="h-5 w-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            <span class="text-[10px] font-bold text-dark">Profil</span>
        </a>
        <a href="messages_buy.php" class="bg-white/80 backdrop-blur-sm border border-white py-3 px-2 rounded-[1.5rem] shadow-sm flex flex-col items-center justify-center space-y-1 hover:bg-white transition-all">
            <svg class="h-5 w-5 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a1 1 0 11-2 0 1 1 0 012 0z" />
            </svg>
            <span class="text-[10px] font-bold text-dark"><?php echo number_format($msg_remaining); ?> cr.</span>
        </a>
    </div>

    <div class="mt-3 flex items-center justify-end">
        <button id="notif-sound-toggle" type="button" class="inline-flex items-center gap-2 bg-white/80 border border-white rounded-xl px-3 py-2 text-[11px] font-bold text-gray-600 hover:bg-white transition-all">
            <span id="notif-sound-dot" class="w-2.5 h-2.5 rounded-full bg-green-500"></span>
            <span id="notif-sound-label">Son notifications: ON</span>
        </button>
    </div>

    <div class="bg-white/80 border border-white rounded-[2.5rem] p-5 shadow-sm">
        <div class="flex items-center space-x-2 mb-4 ml-1">
            <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Rallonger ou Offrir</p>
        </div>
        
        <!-- Historique des 5 dernières prolongations -->
        <div class="mb-4">
            <div id="payment-history" hx-get="payment_history_partial.php" hx-trigger="load">
                <p class="text-gray-400 text-xs">Chargement...</p>
            </div>
        </div>
        
        <form action="payment_init_11.php" method="POST" class="space-y-3">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(gntoma_payment_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                
                <div class="relative">
                    <select name="forfait" class="w-full bg-white border border-gray-100 rounded-[1.2rem] py-3.5 px-4 font-bold text-xs text-dark shadow-sm outline-none appearance-none cursor-pointer focus:ring-2 focus:ring-primary">
                        <option value="2">2 USD — 60 Jours</option>
                        <option value="3">3 USD — 90 Jours</option>
                    </select>
                    <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-gray-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                    </div>
                </div>

                <div class="relative">
                    <input type="text" 
                          name="target_user" 
                          placeholder="Code Auteur (Cadeau)" 
                          class="w-full bg-white border border-gray-100 rounded-[1.2rem] py-3.5 px-4 font-bold text-xs text-dark placeholder-gray-300 shadow-sm outline-none uppercase focus:ring-2 focus:ring-primary"
                          hx-post="user_lookup_partial.php"
                          hx-trigger="keyup changed delay:300ms"
                          hx-target="#user-lookup-result">
                    <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-gray-300">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                    </div>
                </div>
                
            </div>

            <!-- Affichage du nom du destinataire -->
            <div id="user-lookup-result" class="min-h-[48px] empty:hidden transition-all"></div>

            <button type="submit" class="w-full bg-primary text-white font-black py-4 rounded-[1.2rem] shadow-lg shadow-blue-500/20 active:scale-95 transition-all text-xs flex items-center justify-center space-x-2">
                <span>Confirmer le paiement</span>
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
            </button>
        </form>
    </div>

</div>

<script>
(() => {
    if (window.__gntomaRequestBadgeInit) return;
    window.__gntomaRequestBadgeInit = true;

    let lastRequestCount = null;
    let lastMessageCount = null;
    const storageKey = 'gntomaNotifSoundEnabled';
    let notifSoundEnabled = localStorage.getItem(storageKey) !== '0';

    function updateSoundToggleUi() {
        const label = document.getElementById('notif-sound-label');
        const dot = document.getElementById('notif-sound-dot');
        if (!label || !dot) return;
        label.textContent = notifSoundEnabled ? 'Son notifications: ON' : 'Son notifications: OFF';
        dot.className = 'w-2.5 h-2.5 rounded-full ' + (notifSoundEnabled ? 'bg-green-500' : 'bg-gray-400');
    }

    function playNotificationSound() {
        if (!notifSoundEnabled) return;
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            const ctx = new AudioCtx();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.value = 880;
            gain.gain.setValueAtTime(0.0001, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.06, ctx.currentTime + 0.01);
            gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.14);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.15);
        } catch (e) {
            // no-op
        }
    }

    updateSoundToggleUi();

    const soundToggle = document.getElementById('notif-sound-toggle');
    if (soundToggle) {
        soundToggle.addEventListener('click', () => {
            notifSoundEnabled = !notifSoundEnabled;
            localStorage.setItem(storageKey, notifSoundEnabled ? '1' : '0');
            updateSoundToggleUi();
        });
    }

    document.body.addEventListener('htmx:afterSwap', (evt) => {
        const target = evt.target;
        if (!target) return;

        const isRequest = target.id === 'request-badge-anchor';
        const isMessage = target.id === 'message-badge-anchor';
        if (!isRequest && !isMessage) return;

        const dataKey = isRequest ? 'data-request-count' : 'data-message-count';
        const badge = target.querySelector('[' + dataKey + ']');

        if (!badge) {
            if (isRequest) lastRequestCount = 0;
            if (isMessage) lastMessageCount = 0;
            return;
        }

        const currentCount = parseInt(badge.getAttribute(dataKey) || '0', 10);
        if (!Number.isFinite(currentCount)) return;

        const previous = isRequest ? lastRequestCount : lastMessageCount;
        if (previous !== null && currentCount > previous) {
            badge.classList.add('animate-bounce');
            setTimeout(() => badge.classList.remove('animate-bounce'), 650);
            if (isMessage) {
                playNotificationSound();
            }
        }

        if (isRequest) lastRequestCount = currentCount;
        if (isMessage) lastMessageCount = currentCount;
    });
})();
</script>