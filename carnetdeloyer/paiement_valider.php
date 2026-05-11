<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$user = cl_require_user();
$isHtmx = cl_is_htmx_request();
$paymentId = cl_post_int('payment_id');
$houseId = cl_post_int('house_id');
$redirectUrl = 'paiement_tableau.php' . ($houseId > 0 ? '?house_id=' . $houseId : '');

if ($paymentId <= 0) {
    if ($isHtmx) {
        $payments = cl_fetch_payment_rows($pdo, (int) $user['id'], $houseId);
        echo '<div id="flash-zone" hx-swap-oob="true"><div class="animate__animated animate__fadeIn mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">Paiement invalide.</div></div>';
        cl_render_payment_table_component($payments, $houseId);
        exit;
    }
    cl_set_flash('error', 'Paiement invalide.');
    cl_redirect($redirectUrl);
}

try {
    $payment = cl_fetch_payment_by_id($pdo, $paymentId, (int) $user['id']);
    if (!$payment) {
        throw new RuntimeException('Paiement introuvable.');
    }
    if ((string) $payment['payment_status'] !== 'paid') {
        cl_mark_payment_paid($pdo, $paymentId, (float) $payment['amount_due']);
    }
    if (!$isHtmx) {
        cl_set_flash('success', 'Le paiement a été validé.');
    }
} catch (Throwable $e) {
    if ($isHtmx) {
        $payments = cl_fetch_payment_rows($pdo, (int) $user['id'], $houseId);
        echo '<div id="flash-zone" hx-swap-oob="true"><div class="animate__animated animate__fadeIn mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">Impossible de valider ce paiement.</div></div>';
        cl_render_payment_table_component($payments, $houseId);
        exit;
    }
    cl_set_flash('error', 'Impossible de valider ce paiement.');
    cl_redirect($redirectUrl);
}

if ($isHtmx) {
    $payments = cl_fetch_payment_rows($pdo, (int) $user['id'], $houseId);
    echo '<div id="flash-zone" hx-swap-oob="true"><div class="animate__animated animate__fadeIn mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">Le paiement a été validé.</div></div>';
    cl_render_payment_table_component($payments, $houseId);
    exit;
} else {
    cl_redirect($redirectUrl);
}
