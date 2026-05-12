<?php
/**
 * PROJET : GNTOMA
 * FICHIER : journal/journal_create_9.php
 * VERSION : 9
 * DESCRIPTION : Interface de création de journal (Design iOS, Formulaire adapté à la DB).
 */

session_start();
require_once __DIR__ . '/i18n.php';
gntoma_init_locale_from_request();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$error_message = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gntoma_html_lang(), ENT_QUOTES, 'UTF-8') ?>" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars(__('journal_create.page_title'), ENT_QUOTES, 'UTF-8') ?></title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Outfit', 'sans-serif'] },
                    colors: { primary: '#007AFF', dark: '#1D1D1F', surface: '#F5F5F7' }
                }
            }
        }
    </script>
    <style>
        body { color: #1D1D1F; -webkit-font-smoothing: antialiased; overflow-x: hidden; background-color: #f4f7fb; }
        
        .snow-wrapper { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: -2; overflow: hidden; background: linear-gradient(135deg, #e6eff9 0%, #f4f7fb 100%); }
        .snow-layer { position: absolute; top: -100vh; left: 0; width: 100vw; height: 200vh; background-image: radial-gradient(4px 4px at 100px 50px, rgba(255,255,255,0.8), transparent), radial-gradient(6px 6px at 200px 150px, rgba(255,255,255,0.9), transparent); background-size: 600px 600px; animation: fall 25s linear infinite; }
        @keyframes fall { 0% { transform: translateY(0); } 100% { transform: translateY(100vh); } }

        .glass-panel { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(25px); -webkit-backdrop-filter: blur(25px); border: 1px solid rgba(255, 255, 255, 1); box-shadow: 0 30px 60px rgba(0, 0, 0, 0.05); }
        .input-lucide { background: rgba(255, 255, 255, 0.9); border: 1px solid rgba(0, 0, 0, 0.05); transition: all 0.3s ease; }
        .input-lucide:focus { background: #fff; border-color: #007AFF; box-shadow: 0 0 0 4px rgba(0, 122, 255, 0.1); outline: none; }
        
        /* Cacher les boutons radio standards pour le style iOS */
        input[type="radio"]:checked + div { border-color: #007AFF; background-color: #eff6ff; }
        input[type="radio"]:checked + div .radio-icon { color: #007AFF; }
        
        .smooth-transition { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    </style>
    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('image-preview');
                    const container = document.getElementById('preview-container');
                    preview.src = e.target.result;
                    preview.classList.remove('hidden');
                    container.classList.add('hidden');
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        function togglePriceField(show) {
            const priceField = document.getElementById('price-field');
            if (show) {
                priceField.classList.remove('hidden');
            } else {
                priceField.classList.add('hidden');
            }
        }
    </script>
</head>
<body class="min-h-screen py-8 md:py-12 px-4">

    <div class="snow-wrapper"><div class="snow-layer"></div></div>

    <div class="max-w-2xl mx-auto animate__animated animate__fadeInUp">
        
        <div class="flex items-center justify-between mb-8 md:mb-10 gap-2">
            <a href="dashboard_6.php" class="w-12 h-12 bg-white/80 backdrop-blur-md rounded-2xl flex items-center justify-center shadow-sm border border-white hover:scale-105 smooth-transition text-dark">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h1 class="text-2xl md:text-3xl font-black tracking-tight text-center flex-1"><?= htmlspecialchars(__('journal_create.heading'), ENT_QUOTES, 'UTF-8') ?></h1>
            <div class="flex-shrink-0"><?= gntoma_lang_switch_markup() ?></div>
        </div>

        <?php if ($error_message): ?>
            <div class="bg-red-50 text-red-600 font-bold p-4 rounded-2xl mb-6 text-center border border-red-100 animate__animated animate__headShake">
                <?php 
                    if ($error_message === 'empty_fields') {
                        echo htmlspecialchars(__('journal_create.err_title_required'), ENT_QUOTES, 'UTF-8');
                    } elseif ($error_message === 'system_error') {
                        echo htmlspecialchars(__('journal_create.err_system'), ENT_QUOTES, 'UTF-8');
                    } else {
                        echo htmlspecialchars(__('journal_create.err_generic'), ENT_QUOTES, 'UTF-8');
                    }
                ?>
            </div>
        <?php endif; ?>

        <form action="journal_create_traitement_10.php" method="POST" enctype="multipart/form-data" class="space-y-6 md:space-y-8">
            
            <div class="glass-panel rounded-[2.5rem] p-6 md:p-10 space-y-8">
                
                <div class="space-y-3">
                    <label class="text-xs font-black uppercase tracking-widest text-gray-400 ml-2"><?= htmlspecialchars(__('journal_create.label_title'), ENT_QUOTES, 'UTF-8') ?></label>
                    <input type="text" name="title" required placeholder="<?= htmlspecialchars(__('journal_create.placeholder_title'), ENT_QUOTES, 'UTF-8') ?>" class="w-full input-lucide rounded-2xl py-4 md:py-5 px-6 font-bold text-lg md:text-xl text-dark placeholder-gray-300">
                </div>

                <!-- Image de couverture -->
                <div class="space-y-3">
                    <label class="text-xs font-black uppercase tracking-widest text-gray-400 ml-2"><?= htmlspecialchars(__('journal_create.label_cover'), ENT_QUOTES, 'UTF-8') ?></label>
                    <div class="relative">
                        <input type="file" name="cover_image" id="cover_image" accept="image/*" class="hidden" onchange="previewImage(this)">
                        <label for="cover_image" class="flex items-center justify-center w-full h-40 bg-white border-2 border-dashed border-gray-300 rounded-2xl cursor-pointer hover:border-primary hover:bg-blue-50 transition-all group">
                            <div id="preview-container" class="text-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-gray-400 group-hover:text-primary mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <span class="text-sm text-gray-500 font-medium"><?= htmlspecialchars(__('journal_create.cover_cta'), ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <img id="image-preview" class="absolute inset-0 w-full h-full object-cover rounded-2xl hidden" />
                        </label>
                    </div>
                    <p class="text-xs text-gray-400 ml-2"><?= htmlspecialchars(__('journal_create.cover_hint'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>

                <!-- Mots-clés -->
                <div class="space-y-3">
                    <label class="text-xs font-black uppercase tracking-widest text-gray-400 ml-2"><?= htmlspecialchars(__('journal_create.label_keywords'), ENT_QUOTES, 'UTF-8') ?></label>
                    <input type="text" name="keywords" placeholder="<?= htmlspecialchars(__('journal_create.placeholder_keywords'), ENT_QUOTES, 'UTF-8') ?>" class="w-full input-lucide rounded-2xl py-4 px-6 font-medium text-dark placeholder-gray-300">
                    <p class="text-xs text-gray-400 ml-2"><?= htmlspecialchars(__('journal_create.keywords_help'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>

                <div class="space-y-3">
                    <label class="text-xs font-black uppercase tracking-widest text-gray-400 ml-2"><?= htmlspecialchars(__('journal_create.label_visibility'), ENT_QUOTES, 'UTF-8') ?></label>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        
                        <label class="relative cursor-pointer group" onclick="togglePriceField(false)">
                            <input type="radio" name="status" value="private" class="peer sr-only" checked onchange="togglePriceField(false)">
                            <div class="h-full bg-white border-2 border-gray-100 rounded-2xl p-5 hover:border-blue-200 smooth-transition flex flex-col items-center text-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-gray-400 radio-icon mb-3 smooth-transition" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                <span class="font-bold text-dark block mb-1"><?= htmlspecialchars(__('journal_create.status_private'), ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="text-[10px] text-gray-500 font-medium leading-tight"><?= htmlspecialchars(__('journal_create.status_private_desc'), ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        </label>

                        <label class="relative cursor-pointer group" onclick="togglePriceField(false)">
                            <input type="radio" name="status" value="public" class="peer sr-only" onchange="togglePriceField(false)">
                            <div class="h-full bg-white border-2 border-gray-100 rounded-2xl p-5 hover:border-blue-200 smooth-transition flex flex-col items-center text-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-gray-400 radio-icon mb-3 smooth-transition" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <span class="font-bold text-dark block mb-1"><?= htmlspecialchars(__('journal_create.status_public'), ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="text-[10px] text-gray-500 font-medium leading-tight"><?= htmlspecialchars(__('journal_create.status_public_desc'), ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        </label>

                        <label class="relative cursor-pointer group" onclick="togglePriceField(true)">
                            <input type="radio" name="status" value="paid" class="peer sr-only" onchange="togglePriceField(true)">
                            <div class="h-full bg-white border-2 border-gray-100 rounded-2xl p-5 hover:border-blue-200 smooth-transition flex flex-col items-center text-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-gray-400 radio-icon mb-3 smooth-transition" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <span class="font-bold text-dark block mb-1"><?= htmlspecialchars(__('journal_create.status_paid'), ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="text-[10px] text-gray-500 font-medium leading-tight"><?= htmlspecialchars(__('journal_create.status_paid_desc'), ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        </label>

                    </div>
                </div>

                <div id="price-field" class="space-y-3 hidden">
                    <label class="text-xs font-black uppercase tracking-widest text-gray-400 ml-2"><?= htmlspecialchars(__('journal_create.label_price'), ENT_QUOTES, 'UTF-8') ?></label>
                    <input type="number" name="price" step="0.01" placeholder="<?= htmlspecialchars(__('journal_create.placeholder_price'), ENT_QUOTES, 'UTF-8') ?>" class="w-full input-lucide rounded-2xl py-4 px-6 font-bold text-lg md:text-xl text-dark placeholder-gray-300">
                    <p class="text-xs text-gray-400 ml-2"><?= htmlspecialchars(__('journal_create.price_hint'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>

            </div>

            <button type="submit" class="w-full bg-dark text-white font-bold py-5 rounded-[2rem] shadow-2xl hover:bg-black active:scale-95 smooth-transition flex items-center justify-center space-x-3 group">
                <span class="text-lg"><?= htmlspecialchars(__('journal_create.submit'), ENT_QUOTES, 'UTF-8') ?></span>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 group-hover:translate-x-1 smooth-transition" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" /></svg>
            </button>

        </form>
    </div>

</body>
</html>