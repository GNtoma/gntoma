<?php
declare(strict_types=1);

/**
 * PROJET : GNTOMA
 * FICHIER : journal/journal_search_partial.php
 * DESCRIPTION : Recherche HTMX des journaux avec filtres et tri.
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    // Si la session a expiré, on renvoie un message discret qui s'insérera via HTMX
    echo '<div class="bg-red-50 text-red-500 font-bold p-6 rounded-3xl text-center border border-red-100">Votre session a expiré. Veuillez vous reconnecter.</div>';
    exit;
}

require_once 'config.php';

$search = trim((string)($_POST['search'] ?? ''));
$user_code = $_SESSION['user_id'];

try {
    // Construction de la requête avec recherche
    $sql = "SELECT id, title, status, created_at FROM journals WHERE user_code = ?";
    $params = [$user_code];
    
    if (!empty($search)) {
        $sql .= " AND (title LIKE ? OR id LIKE ?)";
        $search_param = '%' . $search . '%';
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $journals = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Erreur recherche journaux GNTOMA : " . $e->getMessage());
    echo '<div class="bg-red-50 text-red-500 font-bold p-6 rounded-3xl text-center border border-red-100">Erreur lors de la recherche. Veuillez réessayer.</div>';
    exit;
}

// FORMATAGE DES DATES EN FRANÇAIS
$mois_fr = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
?>

<?php if (empty($journals)): ?>

    <?php if (!empty($search)): ?>
        <div class="col-span-1 md:col-span-2 lg:col-span-3 animate__animated animate__fadeIn">
            <div class="bg-yellow-50 border-2 border-dashed border-yellow-200 rounded-[2.5rem] p-12 text-center flex flex-col items-center justify-center">
                <div class="w-16 h-16 bg-yellow-100 rounded-[1.5rem] flex items-center justify-center mb-4 border border-yellow-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <h3 class="text-lg font-black text-dark mb-2">Aucun résultat trouvé</h3>
                <p class="text-sm text-gray-600 font-medium">Aucun journal ne correspond à "<strong><?php echo htmlspecialchars($search); ?></strong>"</p>
                <button onclick="document.querySelector('[name=search]').value=''; document.querySelector('[name=search]').dispatchEvent(new Event('keyup'))" class="mt-4 text-sm font-bold text-primary hover:underline">
                    Voir tous les journaux
                </button>
            </div>
        </div>
    <?php else: ?>
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
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                    </svg>
                </a>
            </div>
        </div>
    <?php endif; ?>

<?php else: ?>

    <?php foreach ($journals as $index => $journal): 
        // Logique pour les badges de statut
        $status_bg = 'bg-gray-100 text-gray-600';
        $status_label = 'Privé';
        $status_icon = 'lock';
        
        if ($journal['status'] === 'public') {
            $status_bg = 'bg-green-100 text-green-700';
            $status_label = 'Public';
            $status_icon = 'globe';
        } elseif ($journal['status'] === 'paid') {
            $status_bg = 'bg-orange-100 text-orange-700';
            $status_label = 'Payant';
            $status_icon = 'currency-dollar';
        }

        // Formatage de la date
        $date_obj = new DateTime($journal['created_at']);
        $jour = $date_obj->format('d');
        $mois = $mois_fr[(int)$date_obj->format('m') - 1];
        $annee = $date_obj->format('Y');
        $date_formatee = "$jour $mois $annee";
        
        // Surlignage du terme de recherche
        $title_display = htmlspecialchars($journal['title']);
        if (!empty($search)) {
            $escaped_search = preg_quote($search, '/');
            $title_display = preg_replace('/(' . $escaped_search . ')/i', '<mark class="bg-yellow-200 px-1 rounded">$1</mark>', $title_display);
        }
    ?>
        <div class="bg-white/80 backdrop-blur-xl border border-white rounded-[2.5rem] p-6 shadow-sm hover:shadow-xl hover:-translate-y-2 smooth-transition flex flex-col justify-between animate__animated animate__fadeInUp" style="animation-delay: <?php echo $index * 0.1; ?>s;">
            
            <div>
                <div class="flex justify-between items-start mb-6">
                    <span class="px-3 py-1 text-[10px] font-black uppercase tracking-widest rounded-full <?php echo $status_bg; ?> flex items-center space-x-1">
                        <?php if ($status_icon === 'lock'): ?>
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        <?php elseif ($status_icon === 'globe'): ?>
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        <?php else: ?>
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        <?php endif; ?>
                        <span><?php echo $status_label; ?></span>
                    </span>
                    <span class="text-xs font-bold text-gray-400">
                        <?php echo $date_formatee; ?>
                    </span>
                </div>

                <h3 class="text-xl font-black text-dark mb-4 leading-tight line-clamp-2">
                    <?php echo $title_display; ?>
                </h3>
            </div>
            
            <div class="mt-6 pt-6 border-t border-gray-100/50 flex space-x-3">
                <a href="journal_view.php?id=<?php echo $journal['id']; ?>" class="flex-1 bg-surface text-dark font-bold text-sm py-3 rounded-2xl hover:bg-gray-200 smooth-transition text-center border border-gray-100">
                    Lire
                </a>
                <a href="journal_edit.php?id=<?php echo $journal['id']; ?>" class="flex-1 bg-primary text-white font-bold text-sm py-3 rounded-2xl hover:bg-blue-600 smooth-transition text-center border border-primary">
                    Gérer
                </a>
                <button onclick="navigator.clipboard.writeText('https://gntoma.com/journal_view.php?id=<?php echo $journal['id']; ?>')" class="w-12 h-12 flex items-center justify-center bg-blue-50 text-primary rounded-2xl hover:bg-blue-100 smooth-transition border border-blue-100" title="Copier le lien d'accès">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                    </svg>
                </button>
            </div>
        </div>
    <?php endforeach; ?>

<?php endif; ?>
