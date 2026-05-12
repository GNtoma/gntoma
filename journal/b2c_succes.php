<?php
declare(strict_types=1);

require_once __DIR__ . '/i18n.php';
gntoma_init_locale_from_request();
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gntoma_html_lang(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(__('b2c.page_title'), ENT_QUOTES, 'UTF-8') ?></title>
    <?php require_once __DIR__ . '/pwa_head.php'; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
</head>
<body class="bg-[#F5F5F7] min-h-screen flex items-center justify-center p-6">
    <div class="max-w-md w-full bg-white rounded-[3rem] p-10 shadow-2xl text-center animate__animated animate__zoomIn relative">
        <div class="flex justify-end mb-4"><?= gntoma_lang_switch_markup() ?></div>
        <div class="w-24 h-24 bg-green-100 text-green-500 rounded-full flex items-center justify-center mx-auto mb-8">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
        </div>
        <h1 class="text-3xl font-black text-[#1D1D1F] mb-4"><?= htmlspecialchars(__('b2c.heading'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="text-gray-500 font-medium mb-10"><?= htmlspecialchars(__('b2c.sub'), ENT_QUOTES, 'UTF-8') ?></p>
        <a href="dashboard_6.php" class="block w-full bg-[#007AFF] text-white font-bold py-4 rounded-2xl shadow-lg hover:bg-blue-600 transition-all"><?= htmlspecialchars(__('b2c.cta_dashboard'), ENT_QUOTES, 'UTF-8') ?></a>
    </div>
</body>
</html>
