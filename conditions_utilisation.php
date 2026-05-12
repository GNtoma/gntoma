<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/journal/i18n.php';
gntoma_init_locale_from_request();

$termsDate = '2026-05-12';

/**
 * Découpe un texte de traduction en paragraphes (double saut de ligne).
 *
 * @return array<int, string>
 */
function gntoma_terms_paragraphs(string $raw): array
{
    $parts = preg_split('/\R{2,}/u', trim($raw)) ?: [];

    return array_values(array_filter(array_map('trim', $parts)));
}

?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gntoma_html_lang(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(__('terms.page_title'), ENT_QUOTES, 'UTF-8') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Outfit', 'sans-serif'] },
                    colors: { primary: '#007AFF', dark: '#1D1D1F' }
                }
            }
        }
    </script>
</head>
<body class="min-h-screen bg-[#F5F5F7] text-dark font-sans antialiased">
    <header class="sticky top-0 z-10 bg-white/90 backdrop-blur border-b border-gray-100 px-4 py-4">
        <div class="max-w-3xl mx-auto flex items-center justify-between gap-3">
            <a href="index.php" class="text-primary font-bold text-sm hover:underline"><?= htmlspecialchars(__('terms.back_home'), ENT_QUOTES, 'UTF-8') ?></a>
            <?= gntoma_lang_switch_markup() ?>
        </div>
    </header>

    <main class="max-w-3xl mx-auto px-4 py-10 pb-16">
        <h1 class="text-3xl font-black tracking-tight mb-2"><?= htmlspecialchars(__('terms.h1'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="text-sm text-gray-500 mb-8"><?= htmlspecialchars(__('terms.updated', ['date' => $termsDate]), ENT_QUOTES, 'UTF-8') ?></p>

        <div class="prose prose-gray max-w-none space-y-6 text-[15px] leading-relaxed border border-gray-100 bg-white/80 rounded-[2rem] p-6 md:p-10 shadow-sm mb-10">
            <?php foreach (gntoma_terms_paragraphs(__('terms.intro')) as $para): ?>
                <p class="text-gray-800"><?= nl2br(htmlspecialchars($para, ENT_QUOTES, 'UTF-8')) ?></p>
            <?php endforeach; ?>
            <?php foreach (gntoma_terms_paragraphs(__('terms.notice_legal')) as $para): ?>
                <p class="text-gray-700"><?= nl2br(htmlspecialchars($para, ENT_QUOTES, 'UTF-8')) ?></p>
            <?php endforeach; ?>
        </div>

        <div class="space-y-10 text-gray-700">
            <?php foreach (range(1, 18) as $i): ?>
                <section class="border-b border-gray-200/80 pb-10 last:border-0 last:pb-0">
                    <h2 class="text-[17px] font-black text-dark mb-4 tracking-tight">
                        <?= htmlspecialchars(__('terms.s' . $i . '_title'), ENT_QUOTES, 'UTF-8') ?>
                    </h2>
                    <div class="space-y-4 text-[15px] leading-relaxed">
                        <?php foreach (gntoma_terms_paragraphs(__('terms.s' . $i . '_body')) as $para): ?>
                            <p><?= nl2br(htmlspecialchars($para, ENT_QUOTES, 'UTF-8')) ?></p>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>

        <p class="mt-12 text-xs text-gray-500 leading-relaxed border-t border-gray-200 pt-8">
            <?= nl2br(htmlspecialchars(__('terms.legal_disclaimer'), ENT_QUOTES, 'UTF-8')) ?>
        </p>
    </main>
</body>
</html>
