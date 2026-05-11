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

// Normaliser la recherche en majuscules pour traitement insensible à la casse
$search_upper = strtoupper($search);

// Initialiser les variables pour éviter les erreurs undefined
$journals = [];
$is_author_search = false;
$target_author_code = null;

try {
    
    // Vérifier si la recherche est un code auteur (ex: A3, A12, B5, a3, b12)
    // Pattern: lettre suivie de chiffres
    if (!empty($search) && preg_match('/^([A-Z]\d+)$/', $search_upper, $matches)) {
        $is_author_search = true;
        $target_author_code = $matches[1]; // Déjà en majuscules
    }
    
    if ($is_author_search) {
        // Récupérer le profil de l'auteur
        $profilePicSelect = gntoma_users_profile_pic_expr($pdo, 'users', 'profile_pic');
        $author_stmt = $pdo->prepare("
            SELECT user_code, name, first_name, last_name, {$profilePicSelect}, bio, city, country,
                   (SELECT COUNT(*) FROM journals WHERE user_code = users.user_code AND status IN ('public','paid')) as journal_count
            FROM users WHERE user_code = ? LIMIT 1
        ");
        $author_stmt->execute([$target_author_code]);
        $author = $author_stmt->fetch(PDO::FETCH_ASSOC);

        // Recherche par code auteur : montrer tous les journaux publics/payants de cet auteur
        $sql = "
            SELECT j.*, u.first_name, u.last_name,
                   (SELECT COUNT(*) FROM journals WHERE user_code = j.user_code AND id <= j.id) as journal_num
            FROM journals j 
            JOIN users u ON j.user_code = u.user_code
            WHERE j.user_code = ? 
            AND j.status IN ('public', 'paid')
            ORDER BY j.created_at DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$target_author_code]);
        $journals = $stmt->fetchAll();
    } else {
        // Recherche normale dans les journaux de l'utilisateur connecté
        $sql = "
            SELECT j.*, 
                   (SELECT COUNT(*) FROM journals WHERE user_code = j.user_code AND id <= j.id) as journal_num
            FROM journals j 
            WHERE j.user_code = ?
        ";
        $params = [$user_code];
        
        if (!empty($search)) {
            // Recherche par titre, ID ou code journal (ex: A3J1)
            $sql .= " AND (j.title LIKE ? OR j.id LIKE ? OR j.keywords LIKE ?)";
            $search_param = '%' . $search . '%';
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        $sql .= " ORDER BY j.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $all_journals = $stmt->fetchAll();
        
        // Filtrer par code journal exact si nécessaire (ex: A3J5, a3j5)
        foreach ($all_journals as $journal) {
            $journal_code = $user_code . 'J' . $journal['journal_num'];
            
            if (!empty($search) && preg_match('/^([A-Z]\d+)J(\d+)$/', $search_upper)) {
                if (strtoupper($journal_code) === $search_upper) {
                    $journals[] = $journal;
                }
            } else {
                $journals[] = $journal;
            }
        }
    }
    
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
                <?php if ($is_author_search): ?>
                    <?php if ($author): ?>
                        <p class="text-sm text-gray-600 font-medium"><strong><?php echo htmlspecialchars(($author['first_name'] ?? '') . ' ' . ($author['last_name'] ?? $author['name'])); ?></strong> n'a pas de journaux publics.</p>
                        <div class="flex justify-center gap-3 mt-4">
                            <a href="profile_view.php?user=<?php echo urlencode($author['user_code']); ?>" class="text-sm font-bold text-primary hover:underline">Voir son profil</a>
                            <a href="message_send.php?to=<?php echo urlencode($author['user_code']); ?>" class="text-sm font-bold text-primary hover:underline">Lui écrire</a>
                        </div>
                    <?php else: ?>
                        <p class="text-sm text-gray-600 font-medium">Aucun auteur trouvé avec le code <strong><?php echo htmlspecialchars($target_author_code); ?></strong>.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-sm text-gray-600 font-medium">Aucun journal ne correspond à "<strong><?php echo htmlspecialchars($search); ?></strong>"</p>
                <?php endif; ?>
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

    <?php if ($is_author_search && $author): ?>
        <!-- PROFIL AUTEUR -->
        <div class="col-span-1 md:col-span-2 lg:col-span-3 mb-6 animate__animated animate__fadeIn">
            <div class="bg-white/80 backdrop-blur-xl border border-white rounded-[2.5rem] overflow-hidden shadow-sm p-6 md:p-8 flex flex-col md:flex-row items-center md:items-start gap-6">
                <!-- Photo -->
                <div class="shrink-0">
                    <?php if (!empty($author['profile_pic'])): ?>
                        <img src="<?php echo htmlspecialchars($author['profile_pic']); ?>" alt="" class="w-24 h-24 rounded-full object-cover border-2 border-primary/20">
                    <?php else: ?>
                        <div class="w-24 h-24 rounded-full bg-primary/10 flex items-center justify-center border-2 border-primary/20">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                    <?php endif; ?>
                </div>
                <!-- Infos -->
                <div class="flex-grow text-center md:text-left">
                    <h2 class="text-2xl font-black text-dark mb-1">
                        <?php echo htmlspecialchars(($author['first_name'] ?? '') . ' ' . ($author['last_name'] ?? $author['name'])); ?>
                    </h2>
                    <p class="text-sm font-bold text-primary mb-2"><?php echo htmlspecialchars($author['user_code']); ?></p>
                    <?php if (!empty($author['bio'])): ?>
                        <p class="text-sm text-gray-600 mb-3 line-clamp-2"><?php echo htmlspecialchars($author['bio']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($author['city']) || !empty($author['country'])): ?>
                        <p class="text-xs text-gray-400 font-medium mb-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 inline mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <?php echo htmlspecialchars(implode(', ', array_filter([$author['city'], $author['country']])); ?>
                        </p>
                    <?php endif; ?>
                    <div class="flex flex-wrap justify-center md:justify-start gap-3">
                        <a href="profile_view.php?user=<?php echo urlencode($author['user_code']); ?>" class="inline-flex items-center space-x-2 bg-primary text-white font-bold text-sm px-5 py-2.5 rounded-2xl hover:bg-blue-600 smooth-transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <span>Voir le profil</span>
                        </a>
                        <a href="message_send.php?to=<?php echo urlencode($author['user_code']); ?>" class="inline-flex items-center space-x-2 bg-surface text-dark font-bold text-sm px-5 py-2.5 rounded-2xl hover:bg-gray-200 smooth-transition border border-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                            </svg>
                            <span>Message</span>
                        </a>
                    </div>
                </div>
                <!-- Stats -->
                <div class="shrink-0 flex md:flex-col gap-4 text-center">
                    <div class="bg-blue-50 rounded-2xl px-4 py-3 min-w-[80px]">
                        <p class="text-xl font-black text-primary"><?php echo (int)$author['journal_count']; ?></p>
                        <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Journaux</p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

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
        
        // Générer le code du journal (utiliser le user_code du journal trouvé)
        $journal_owner_code = $is_author_search ? $target_author_code : $user_code;
        $journal_code = $journal_owner_code . 'J' . $journal['journal_num'];
        $cover_image = !empty($journal['cover_image']) ? '../' . $journal['cover_image'] : null;
        
        // Surlignage du terme de recherche
        $title_display = htmlspecialchars($journal['title']);
        if (!empty($search)) {
            $title_display = preg_replace('/(' . preg_quote($search, '/') . ')/i', '<mark class="bg-yellow-200 px-1 rounded">$1</mark>', $title_display);
        }
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
                    <?php endif; ?>
                    <span class="text-xs font-bold text-gray-400 <?php echo $cover_image ? 'ml-auto' : ''; ?>">
                        <?php echo $date_formatee; ?>
                    </span>
                </div>
                <?php if (!$cover_image): ?>
                    <p class="text-xs text-gray-400 font-medium mb-2"><?php echo $journal_code; ?></p>
                <?php endif; ?>

                <h3 class="text-xl font-black text-dark mb-2 leading-tight line-clamp-2">
                    <?php echo $title_display; ?>
                </h3>
                
                <?php if ($is_author_search && !empty($journal['first_name'])): ?>
                    <p class="text-xs text-primary font-bold mb-1">
                        par <?php echo htmlspecialchars($journal['first_name'] . ' ' . $journal['last_name']); ?>
                    </p>
                <?php endif; ?>
                
                <?php if (!empty($journal['keywords'])): ?>
                    <p class="text-xs text-gray-400 line-clamp-1"><?php echo htmlspecialchars($journal['keywords']); ?></p>
                <?php endif; ?>
            </div>
            
            <div class="px-6 pb-6 pt-2 border-t border-gray-100/50 flex space-x-3">
                <?php if ($is_author_search): ?>
                    <a href="journal_view.php?id=<?php echo $journal['id']; ?>" class="flex-1 bg-primary text-white font-bold text-sm py-3 rounded-2xl hover:bg-blue-600 smooth-transition text-center">
                        Voir le journal
                    </a>
                <?php else: ?>
                    <a href="journal_edit_13.php?id=<?php echo $journal['id']; ?>" class="flex-1 bg-surface text-dark font-bold text-sm py-3 rounded-2xl hover:bg-gray-200 smooth-transition text-center border border-gray-100">
                        Gérer
                    </a>
                <?php endif; ?>
                <button onclick="navigator.clipboard.writeText('<?php echo "https://gntoma.com/journal/journal_view.php?id=" . $journal['id']; ?>')" class="w-12 h-12 flex items-center justify-center bg-blue-50 text-primary rounded-2xl hover:bg-blue-100 smooth-transition border border-blue-100" title="Copier le lien d'accès">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                    </svg>
                </button>
            </div>
        </div>
    <?php endforeach; ?>

<?php endif; ?>
