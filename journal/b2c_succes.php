<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement Réussi</title>
    <?php require_once __DIR__ . '/pwa_head.php'; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
</head>
<body class="bg-[#F5F5F7] min-h-screen flex items-center justify-center p-6">
    <div class="max-w-md w-full bg-white rounded-[3rem] p-10 shadow-2xl text-center animate__animated animate__zoomIn">
        <div class="w-24 h-24 bg-green-100 text-green-500 rounded-full flex items-center justify-center mx-auto mb-8">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
        </div>
        <h1 class="text-3xl font-black text-[#1D1D1F] mb-4">Paiement Validé</h1>
        <p class="text-gray-500 font-medium mb-10">Votre temps d'utilisation a été prolongé avec succès. Merci de votre confiance.</p>
        <a href="dashboard_6.php" class="block w-full bg-[#007AFF] text-white font-bold py-4 rounded-2xl shadow-lg hover:bg-blue-600 transition-all">Retour au Dashboard</a>
    </div>
</body>
</html>