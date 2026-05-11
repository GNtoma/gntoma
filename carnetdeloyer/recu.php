<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$user = cl_require_user();
$paymentId = (int) ($_GET['id'] ?? 0);
$payment = $paymentId > 0 ? cl_fetch_payment_by_id($pdo, $paymentId, (int) $user['id']) : null;

if (!$payment) {
    cl_set_flash('error', 'Reçu introuvable.');
    cl_redirect('paiement_tableau.php');
}

cl_render_shell_start('Reçu de paiement', $user);
?>
<div class="mx-auto max-w-4xl rounded-[2rem] bg-white p-8 shadow-soft">
    <div class="flex flex-col gap-4 border-b border-slate-100 pb-6 md:flex-row md:items-start md:justify-between">
        <div>
            <p class="text-xs font-black uppercase tracking-[0.28em] text-primary">Carnet de loyer</p>
            <h2 class="mt-2 text-3xl font-black">Reçu #<?= (int) $payment['id'] ?></h2>
            <p class="mt-2 text-sm text-slate-600">Émis le <?= cl_escape((string) date('Y-m-d H:i:s')) ?></p>
        </div>
        <button type="button" onclick="window.print()" class="rounded-full bg-slate-900 px-5 py-3 text-sm font-black uppercase tracking-[0.2em] text-white transition hover:bg-slate-800">Imprimer</button>
    </div>

    <div class="mt-8 grid gap-5 md:grid-cols-2">
        <div class="rounded-3xl bg-slate-50 p-5">
            <p class="text-xs font-black uppercase tracking-[0.24em] text-slate-400">Maison</p>
            <p class="mt-2 text-lg font-black text-slate-900"><?= cl_escape((string) $payment['house_title']) ?></p>
        </div>
        <div class="rounded-3xl bg-slate-50 p-5">
            <p class="text-xs font-black uppercase tracking-[0.24em] text-slate-400">Montant</p>
            <p class="mt-2 text-lg font-black text-slate-900"><?= cl_format_currency((float) $payment['amount_due']) ?></p>
        </div>
        <div class="rounded-3xl bg-slate-50 p-5">
            <p class="text-xs font-black uppercase tracking-[0.24em] text-slate-400">Bailleur</p>
            <p class="mt-2 text-lg font-black text-slate-900"><?= cl_escape((string) $payment['landlord_name']) ?></p>
        </div>
        <div class="rounded-3xl bg-slate-50 p-5">
            <p class="text-xs font-black uppercase tracking-[0.24em] text-slate-400">Locataire</p>
            <p class="mt-2 text-lg font-black text-slate-900"><?= cl_escape((string) $payment['tenant_name']) ?></p>
        </div>
    </div>

    <div class="mt-8 rounded-[2rem] border border-slate-200 p-6">
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.24em] text-slate-400">Mois concerné</p>
                <p class="mt-2 text-sm font-bold text-slate-900"><?= cl_escape((string) $payment['due_month']) ?></p>
            </div>
            <div>
                <p class="text-xs font-black uppercase tracking-[0.24em] text-slate-400">Échéance</p>
                <p class="mt-2 text-sm font-bold text-slate-900"><?= cl_escape((string) $payment['due_date']) ?></p>
            </div>
            <div>
                <p class="text-xs font-black uppercase tracking-[0.24em] text-slate-400">Statut</p>
                <p class="mt-2 text-sm font-bold <?= (string) $payment['payment_status'] === 'paid' ? 'text-emerald-600' : 'text-amber-600' ?>"><?= cl_escape((string) $payment['payment_status']) ?></p>
            </div>
            <div>
                <p class="text-xs font-black uppercase tracking-[0.24em] text-slate-400">Payé le</p>
                <p class="mt-2 text-sm font-bold text-slate-900"><?= cl_escape((string) ($payment['paid_at'] ?? 'Non encore payé')) ?></p>
            </div>
        </div>
    </div>
</div>
<?php cl_render_shell_end();
