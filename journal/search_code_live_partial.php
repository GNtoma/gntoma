<?php
declare(strict_types=1);
session_start();
require_once 'config.php';
require_once __DIR__ . '/i18n.php';
gntoma_init_locale_from_request();

if (!isset($_SESSION['user_id'])) {
    exit;
}

$code = trim(strtoupper((string)($_GET['code'] ?? '')));
if (strlen($code) < 2) {
    echo '';
    exit;
}

$is_journal_search = false;
$target_user_code = '';
$target_journal_num = 0;

if (preg_match('/^([A-Z]\d+)J(\d+)$/i', $code, $matches)) {
    $is_journal_search = true;
    $target_user_code = $matches[1];
    $target_journal_num = (int)$matches[2];
} elseif (preg_match('/^([A-Z]\d+)$/i', $code, $matches)) {
    $target_user_code = $matches[1];
} else {
    // Si on a commencé à taper autre chose que le format, on ne montre rien de spécial ou une petite erreur
    if (preg_match('/^[A-Z]/i', $code)) {
        echo '<div class="bg-white/90 backdrop-blur-md rounded-[1.5rem] p-3 shadow-lg border border-gray-100 text-center text-[11px] font-bold text-gray-400 mb-5">' . htmlspecialchars(__('search_code_live_partial.format_hint'), ENT_QUOTES, 'UTF-8') . '</div>';
    }
    exit;
}

try {
    $profilePicSelect = gntoma_users_profile_pic_expr($pdo, 'users', 'profile_pic');
    $author_stmt = $pdo->prepare("
        SELECT user_code, name, first_name, last_name, {$profilePicSelect}, city, country
        FROM users 
        WHERE UPPER(user_code) = UPPER(?)
    ");
    $author_stmt->execute([$target_user_code]);
    $author = $author_stmt->fetch();

    if (!$author) {
        echo '<div class="bg-white/90 backdrop-blur-md rounded-[1.5rem] p-3 shadow-lg border border-gray-100 text-center text-[11px] font-bold text-gray-500 mb-5 animate__animated animate__fadeIn">' . htmlspecialchars(__('search_code_live_partial.author_not_found', ['code' => $target_user_code]), ENT_QUOTES, 'UTF-8') . '</div>';
        exit;
    }

    echo '<div class="bg-white/90 backdrop-blur-md rounded-[1.5rem] shadow-lg border border-gray-100 overflow-hidden mb-5 animate__animated animate__fadeIn">';
    
    // Entête de l'auteur
    $profile_pic = !empty($author['profile_pic']) ? '../' . $author['profile_pic'] : '../images/user_default.png';
    $name = htmlspecialchars(trim(($author['first_name'] ?? '') . ' ' . ($author['last_name'] ?? '')) ?: $author['name']);
    
    echo '<a href="search_code.php?code=' . urlencode($target_user_code) . '" class="flex items-center p-3 border-b border-gray-100 hover:bg-gray-50 transition-all">';
    echo '<img src="' . htmlspecialchars($profile_pic) . '" alt="" class="w-10 h-10 rounded-xl object-cover border border-gray-200">';
    echo '<div class="ml-3 flex-1">';
    echo '<p class="font-black text-sm text-dark leading-tight">' . $name . '</p>';
    echo '<p class="text-[10px] text-primary font-bold uppercase tracking-widest">' . $author['user_code'] . '</p>';
    echo '</div>';
    echo '<svg class="h-5 w-5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" /></svg>';
    echo '</a>';

    if ($is_journal_search) {
        $journal_stmt = $pdo->prepare("
            SELECT j.*, 
                   (SELECT COUNT(*) FROM journals 
                    WHERE user_code = j.user_code AND id <= j.id) as journal_num
            FROM journals j
            WHERE j.user_code = ? 
            AND j.status IN ('public', 'paid')
            HAVING journal_num = ?
            LIMIT 1
        ");
        $journal_stmt->execute([$author['user_code'], $target_journal_num]);
        $specific_journal = $journal_stmt->fetch();

        if (!$specific_journal) {
            echo '<div class="p-4 text-center text-xs font-bold text-gray-500 bg-gray-50/50">' . htmlspecialchars(__('search_code_live_partial.journal_not_found', ['code' => $code]), ENT_QUOTES, 'UTF-8') . '</div>';
        } else {
            render_journal_item($specific_journal, $author['user_code'], $code);
        }
    } else {
        $journals_stmt = $pdo->prepare("
            SELECT j.*, 
                   (SELECT COUNT(*) FROM journals 
                    WHERE user_code = j.user_code AND id <= j.id) as journal_num
            FROM journals j
            WHERE j.user_code = ? 
            AND j.status IN ('public', 'paid')
            ORDER BY j.created_at DESC
            LIMIT 5
        ");
        $journals_stmt->execute([$author['user_code']]);
        $journals = $journals_stmt->fetchAll();

        if (empty($journals)) {
            echo '<div class="p-4 text-center text-xs font-bold text-gray-500 bg-gray-50/50">' . htmlspecialchars(__('search_code_live_partial.no_public'), ENT_QUOTES, 'UTF-8') . '</div>';
        } else {
            foreach ($journals as $journal) {
                render_journal_item($journal, $author['user_code']);
            }
            if (count($journals) >= 5) {
                echo '<a href="search_code.php?code=' . urlencode($target_user_code) . '" class="block p-2.5 text-center text-[10px] font-black uppercase tracking-widest text-primary hover:bg-gray-50 transition-all bg-gray-50/30 border-t border-gray-100">' . htmlspecialchars(__('search_code_live_partial.see_all'), ENT_QUOTES, 'UTF-8') . '</a>';
            }
        }
    }
    
    echo '</div>';

} catch (Throwable $e) {
    error_log("Erreur live search : " . $e->getMessage());
    echo '<div class="bg-white/90 backdrop-blur-md rounded-[1.5rem] p-3 shadow-lg border border-gray-100 text-center text-xs font-bold text-red-500 mb-5">' . htmlspecialchars(__('search_code_live_partial.search_error'), ENT_QUOTES, 'UTF-8') . '</div>';
}

function render_journal_item($journal, $author_code, $searched_code = null) {
    $journal_code = $author_code . 'J' . $journal['journal_num'];
    $date_obj = new DateTime($journal['created_at']);
    $monthKey = (string) (int) $date_obj->format('m');
    $date_formatee = $date_obj->format('d') . ' ' . __('months.' . $monthKey) . ' ' . $date_obj->format('Y');

    $is_paid = ($journal['status'] === 'paid');
    $price_label = '';
    if ($is_paid && !empty($journal['price'])) {
        $price_label = '<span class="px-1.5 py-0.5 text-[8px] font-black uppercase tracking-wider rounded bg-orange-100 text-orange-600">' . number_format((float)$journal['price'], 0) . ' ' . htmlspecialchars($journal['price_currency'] ?? 'CDF') . '</span>';
    }

    // Si c'est le journal spécifiquement cherché, on met un fond léger
    $bg_class = ($searched_code && strtoupper($searched_code) === strtoupper($journal_code)) ? 'bg-primary/5' : '';

    // Pour les journaux PUBLICS : toute la zone est cliquable et redirige vers le contenu
    if (!$is_paid) {
        echo '<a href="journal_view.php?id=' . $journal['id'] . '" class="flex items-center p-3 border-b border-gray-50 last:border-0 hover:bg-blue-50/50 transition-all ' . $bg_class . '">';
        
        if (!empty($journal['cover_image'])) {
            echo '<img src="../' . htmlspecialchars($journal['cover_image']) . '" alt="" class="w-12 h-12 rounded-lg object-cover border border-gray-100 flex-shrink-0">';
        } else {
            echo '<div class="w-12 h-12 rounded-lg bg-primary/10 flex items-center justify-center border border-primary/20 flex-shrink-0">';
            echo '<svg class="h-6 w-6 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>';
            echo '</div>';
        }
        
        echo '<div class="ml-3 flex-1 min-w-0">';
        echo '<div class="flex items-center flex-wrap gap-1 mb-0.5">';
        echo '<span class="px-1.5 py-0.5 text-[9px] font-black uppercase tracking-wider rounded bg-gray-100 text-gray-500">' . $journal_code . '</span>';
        echo '<span class="px-1.5 py-0.5 text-[8px] font-black uppercase tracking-wider rounded bg-green-100 text-green-700">' . htmlspecialchars(__('search_code_live_partial.public'), ENT_QUOTES, 'UTF-8') . '</span>';
        echo '</div>';
        echo '<p class="font-bold text-xs text-dark truncate">' . htmlspecialchars($journal['title']) . '</p>';
        echo '</div>';
        echo '<svg class="h-4 w-4 text-primary ml-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" /></svg>';
        echo '</a>';
        return;
    }

    // Pour les journaux PAYANTS : afficher avec experience immersive + modal de demande
    static $modalScriptPrinted = false;
    if (!$modalScriptPrinted) {
        echo '<script>
        function gntomaOpenAccessModal(id){var m=document.getElementById("access-modal-"+id);if(!m){return;}m.classList.remove("hidden");document.body.classList.add("overflow-hidden");}
        function gntomaCloseAccessModal(id){var m=document.getElementById("access-modal-"+id);if(!m){return;}m.classList.add("hidden");document.body.classList.remove("overflow-hidden");}
        </script>';
        $modalScriptPrinted = true;
    }

    echo '<div class="p-3 border-b border-gray-50 last:border-0 ' . $bg_class . '">';
    echo '<div class="flex items-start gap-3 mb-2">';

    if (!empty($journal['cover_image'])) {
        echo '<img src="../' . htmlspecialchars($journal['cover_image']) . '" alt="" class="w-12 h-12 rounded-lg object-cover border border-gray-100 flex-shrink-0">';
    } else {
        echo '<div class="w-12 h-12 rounded-lg bg-primary/10 flex items-center justify-center border border-primary/20 flex-shrink-0">';
        echo '<svg class="h-6 w-6 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>';
        echo '</div>';
    }

    echo '<div class="flex-1 min-w-0">';
    echo '<div class="flex items-center flex-wrap gap-1 mb-0.5">';
    echo '<span class="px-1.5 py-0.5 text-[9px] font-black uppercase tracking-wider rounded bg-gray-100 text-gray-500">' . $journal_code . '</span>';
    echo '<span class="px-1.5 py-0.5 text-[8px] font-black uppercase tracking-wider rounded bg-orange-100 text-orange-600">' . htmlspecialchars(__('search_code_live_partial.paid'), ENT_QUOTES, 'UTF-8') . '</span>';
    echo $price_label;
    echo '</div>';
    echo '<p class="font-bold text-xs text-dark truncate">' . htmlspecialchars($journal['title']) . '</p>';
    echo '</div>';
    echo '</div>';

    // Bouton premium d'ouverture modale
    echo '<div class="flex gap-1.5">';
    echo '<button type="button" onclick="gntomaOpenAccessModal(' . (int)$journal['id'] . ')" class="flex-1 bg-gradient-to-r from-orange-500 to-amber-500 text-white text-[10px] font-bold py-2 px-3 rounded-lg text-center hover:from-orange-600 hover:to-amber-600 transition-all flex items-center justify-center space-x-1 shadow-sm shadow-orange-500/30">';
    echo '<svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>';
    echo '<span>' . htmlspecialchars(__('search_code_live_partial.request_access'), ENT_QUOTES, 'UTF-8') . '</span>';
    echo '</button>';
    echo '</div>';

    echo '<div id="access-modal-' . (int)$journal['id'] . '" class="hidden fixed inset-0 z-[9999]">';
    echo '<div class="absolute inset-0 bg-slate-900/70 backdrop-blur-md" onclick="gntomaCloseAccessModal(' . (int)$journal['id'] . ')"></div>';
    echo '<div class="relative min-h-full flex items-center justify-center p-4">';
    echo '<div class="w-full max-w-md rounded-[1.8rem] border border-white/20 bg-white/10 backdrop-blur-2xl shadow-2xl shadow-indigo-900/40 overflow-hidden animate__animated animate__fadeInUp">';
    echo '<div class="p-5 bg-gradient-to-br from-indigo-900/70 via-slate-900/70 to-cyan-900/60 text-white">';
    echo '<p class="text-[10px] uppercase tracking-[0.18em] font-black text-cyan-200/80">' . $journal_code . '</p>';
    echo '<h3 class="text-lg font-black leading-tight mt-1">' . htmlspecialchars($journal['title']) . '</h3>';
    if (!empty($journal['price'])) {
        echo '<p class="text-sm font-bold mt-1 text-amber-200">' . number_format((float)$journal['price'], 0) . ' ' . htmlspecialchars($journal['price_currency'] ?? 'CDF') . '</p>';
    }
    echo '</div>';
    echo '<div class="p-5 bg-white/90">';
    echo '<p class="text-xs text-slate-600 mb-4">' . htmlspecialchars(__('search_code_live_partial.modal_body'), ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<div class="space-y-2">';
    echo '<a href="journal_access_request.php?journal_id=' . (int)$journal['id'] . '" class="w-full inline-flex justify-center items-center gap-2 bg-orange-500 text-white text-xs font-black py-3 rounded-xl hover:bg-orange-600 transition-all">' . htmlspecialchars(__('search_code_live_partial.continue_request'), ENT_QUOTES, 'UTF-8') . '</a>';
    echo '<a href="message_send.php?to=' . urlencode($author_code) . '&context=access_request&journal_id=' . (int)$journal['id'] . '" class="w-full inline-flex justify-center items-center gap-2 bg-blue-50 text-primary text-xs font-black py-3 rounded-xl border border-blue-100 hover:bg-blue-100 transition-all">' . htmlspecialchars(__('search_code_live_partial.chat_author'), ENT_QUOTES, 'UTF-8') . '</a>';
    echo '<button type="button" onclick="gntomaCloseAccessModal(' . (int)$journal['id'] . ')" class="w-full text-xs font-bold text-slate-500 py-2">' . htmlspecialchars(__('search_code_live_partial.close'), ENT_QUOTES, 'UTF-8') . '</button>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    echo '</div>';
}
?>
