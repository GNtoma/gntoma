<?php
declare(strict_types=1);

$gntomaConfettiColors = ['#007AFF', '#5AC8FA', '#7C3AED', '#FF9500', '#FF3B82', '#FFD60A', '#34C759', '#FF2D55', '#BF5AF2'];
$gntomaConfettiPieces = [];
for ($i = 0; $i < 56; $i++) {
    $gntomaConfettiPieces[] = [
        'left' => mt_rand(0, 10000) / 100,
        'delay' => mt_rand(0, 160) / 10,
        'duration' => mt_rand(100, 240) / 10,
        'w' => mt_rand(5, 11),
        'h' => mt_rand(8, 16),
        'color' => $gntomaConfettiColors[array_rand($gntomaConfettiColors)],
        'round' => mt_rand(0, 1) === 1,
        'sway' => mt_rand(-80, 80),
        'layer' => $i % 3,
    ];
}
?>
<div class="gntoma-ui-background" aria-hidden="true">
    <div class="gntoma-ui-glow"></div>
    <div class="gntoma-ui-lines"></div>
    <div class="gntoma-ui-grid"></div>
    <div class="gntoma-ui-noise"></div>
    <div class="gntoma-orb gntoma-orb-a"></div>
    <div class="gntoma-orb gntoma-orb-b"></div>
    <div class="gntoma-orb gntoma-orb-c"></div>
    <div class="gntoma-confetti">
        <?php foreach ($gntomaConfettiPieces as $piece): ?>
        <span
            class="gntoma-confetti-piece gntoma-confetti-layer-<?= (int) $piece['layer'] ?>"
            style="--left: <?= htmlspecialchars((string) $piece['left'], ENT_QUOTES, 'UTF-8') ?>;
                   --delay: <?= htmlspecialchars((string) $piece['delay'], ENT_QUOTES, 'UTF-8') ?>s;
                   --dur: <?= htmlspecialchars((string) $piece['duration'], ENT_QUOTES, 'UTF-8') ?>s;
                   --w: <?= (int) $piece['w'] ?>px;
                   --h: <?= (int) $piece['h'] ?>px;
                   --c: <?= htmlspecialchars($piece['color'], ENT_QUOTES, 'UTF-8') ?>;
                   --r: <?= $piece['round'] ? '9999px' : '2px' ?>;
                   --sway: <?= (int) $piece['sway'] ?>px;"
        ></span>
        <?php endforeach; ?>
    </div>
</div>
