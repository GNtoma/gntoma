<?php
declare(strict_types=1);

/**
 * PROJET : GNTOMA
 * FICHIER : journal/journal_list_partial_8.php
 * VERSION : 8
 * DESCRIPTION : Rendu HTMX de la liste des journaux basé sur la structure exacte de la DB.
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    // Si la session a expiré, on renvoie un message discret qui s'insérera via HTMX
    echo '<div class="bg-red-50 text-red-500 font-bold p-6 rounded-3xl text-center border border-red-100">Votre session a expiré. Veuillez vous reconnecter.</div>';
    exit;
}

require_once 'config.php';

try {
    // Récupération des journaux liés au user_code de la session
    $stmt = $pdo->prepare("
        SELECT j.*, 
               (SELECT COUNT(*) FROM journals WHERE user_code = j.user_code AND id <= j.id) as journal_num
        FROM journals j 
        WHERE j.user_code = ? 
        ORDER BY j.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $journals = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Erreur chargement journaux GNTOMA : " . $e->getMessage());
    echo '<div class="bg-red-50 text-red-500 font-bold p-6 rounded-3xl text-center border border-red-100">Impossible de charger vos journaux pour le moment.</div>';
    exit;
}

// FORMATAGE DES DATES EN FRANÇAIS (Mois)
$mois_fr = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
?>

<?php if (empty($journals)): ?>

    <div class="col-span-1 md:col-span-2 lg:col-span-3 animate__animated animate__fadeIn">
        <div class="bg-white/60 backdrop-blur-md border-2 border-dashed border-gray-300/50 rounded-[2.5rem] p-12 text-center flex flex-col items-center justify-center hover:bg-white/80 smooth-transition group">
            <div class="w-20 h-20 bg-blue-50/50 rounded-[1.5rem] flex items-center justify-center mb-6 border border-blue-100/50 group-hover:scale-110 smooth-transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-primary opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
            </div>
            <h3 class="text-xl font-black text-dark mb-2">Aucune publication</h3>
            <p class="text-sm text-gray-500 font-medium max-w-sm mb-8">Votre espace est encore vide. Commencez à écrire et monétisez votre premier journal dès aujourd'hui.</p>
            <a href="journal_create_9.php" class="bg-dark text-white font-bold px-8 py-3.5 rounded-full shadow-xl hover:bg-black active:scale-95 smooth-transition flex items-center space-x-2">
                <span>Créer mon premier journal</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" /></svg>
            </a>
        </div>
    </div>

<?php else: ?>

    <?php foreach ($journals as $index => $journal): 
        // Logique pour les badges de statut
        $status_bg = 'bg-gray-100 text-gray-600';
        $status_label = 'Privé';
        
        if ($journal['status'] === 'public') {
            $status_bg = 'bg-green-100 text-green-700';
            $status_label = 'Public';
        } elseif ($journal['status'] === 'paid') {
            $status_bg = 'bg-orange-100 text-orange-700';
            $status_label = 'Payant';
        }

        // Formatage de la date (Ex: 25 Avril 2026)
        $date_obj = new DateTime($journal['created_at']);
        $jour = $date_obj->format('d');
        $mois = $mois_fr[(int)$date_obj->format('m') - 1];
        $annee = $date_obj->format('Y');
        $date_formatee = "$jour $mois $annee";
    ?>
        <?php 
            // Générer le code du journal (ex: A1J1, A1J2)
            $journal_code = $_SESSION['user_id'] . 'J' . $journal['journal_num'];
            $cover_image = !empty($journal['cover_image']) ? '../' . $journal['cover_image'] : null;
        ?>
        <div class="bg-white/80 backdrop-blur-xl border border-white rounded-[2.5rem] overflow-hidden shadow-sm hover:shadow-xl hover:-translate-y-2 smooth-transition flex flex-col justify-between animate__animated animate__fadeInUp" style="animation-delay: <?php echo $index * 0.1; ?>s;">
            
            <?php if ($cover_image): ?>
                <div class="h-32 bg-gray-100 relative">
                    <img src="<?php echo htmlspecialchars($cover_image); ?>" alt="" class="w-full h-full object-cover">
                    <div class="absolute top-3 left-3">
                        <span class="px-2 py-1 text-[9px] font-black uppercase tracking-widest rounded-full bg-white/90 text-gray-700">
                            <?php echo $journal_code; ?>
                        </span>
                    </div>
                    <div class="absolute top-3 right-3">
                        <span class="px-2 py-1 text-[9px] font-black uppercase tracking-widest rounded-full <?php echo $status_bg; ?>">
                            <?php echo $status_label; ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="p-6 flex-grow">
                <div class="flex justify-between items-start mb-4 <?php echo $cover_image ? '' : 'mt-0'; ?>">
                    <?php if (!$cover_image): ?>
                        <span class="px-3 py-1 text-[10px] font-black uppercase tracking-widest rounded-full <?php echo $status_bg; ?>">
                            <?php echo $status_label; ?>
                        </span>
                    <?php endif; ?>
                    <span class="text-xs font-bold text-gray-400 <?php echo $cover_image ? 'ml-auto' : ''; ?>">
                        <?php echo $date_formatee; ?>
                    </span>
                </div>
                <?php if (!$cover_image): ?>
                    <p class="text-xs text-gray-400 font-medium mb-2"><?php echo $journal_code; ?></p>
                <?php endif; ?>

                <h3 class="text-xl font-black text-dark mb-2 leading-tight line-clamp-2">
                    <?php echo htmlspecialchars($journal['title']); ?>
                </h3>
                
                <?php if (!empty($journal['keywords'])): ?>
                    <p class="text-xs text-gray-400 line-clamp-1"><?php echo htmlspecialchars($journal['keywords']); ?></p>
                <?php endif; ?>
            </div>
            
            <div class="px-6 pb-6 pt-2 border-t border-gray-100/50 flex space-x-3">
                <a href="journal_edit_13.php?id=<?php echo $journal['id']; ?>" class="flex-1 bg-surface text-dark font-bold text-sm py-3 rounded-2xl hover:bg-gray-200 smooth-transition text-center border border-gray-100">
                    Gérer
                </a>
                <button class="w-12 h-12 flex items-center justify-center bg-blue-50 text-primary rounded-2xl hover:bg-blue-100 smooth-transition border border-blue-100" title="Copier le lien d'accès">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                    </svg>
                </button>
            </div>
        </div>
    <?php endforeach; ?>

<?php endif; ?>