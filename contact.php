<?php
declare(strict_types=1);
require_once __DIR__ . '/configuration.php';
startSecureSession();

// Vérification de la méthode d'envoi (POST uniquement)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

// 1. Protection Anti-Spam (Honeypot)
// Le champ "site_web" est caché en CSS pour les humains. S'il est rempli, c'est un bot.
$honeypot = trim((string) ($_POST['site_web'] ?? ''));
if ($honeypot !== '') {
    // Redirection silencieuse pour tromper le bot
    redirect('index.php?sent=1#contact');
}

// 1bis. Limitation de fréquence : un envoi toutes les 30 secondes maximum par session
$now = time();
$lastSent = (int) ($_SESSION['last_contact_sent'] ?? 0);
if ($now - $lastSent < 30) {
    redirect('index.php?error=1#contact');
}

// 2. Récupération et nettoyage des données soumises
$nom     = trim((string) ($_POST['nom'] ?? ''));
$email   = trim((string) ($_POST['email'] ?? ''));
$sujet   = trim((string) ($_POST['sujet'] ?? ''));
$message = trim((string) ($_POST['message'] ?? ''));

// 3. Validation des champs obligatoires et du format d'email
if ($nom === '' || $email === '' || $sujet === '' || $message === '') {
    redirect('index.php?error=1#contact');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect('index.php?error=1#contact');
}

// Limiter la taille des entrées pour éviter les abus de stockage
if (mb_strlen($nom) > 120 || mb_strlen($email) > 180 || mb_strlen($sujet) > 200 || mb_strlen($message) > 5000) {
    redirect('index.php?error=1#contact');
}

try {
    // 4. Insertion du message dans la base de données
    $stmt = db()->prepare(
        'INSERT INTO contacts (nom, email, sujet, message, created_at) 
         VALUES (:nom, :email, :sujet, :message, NOW())'
    );

    $success = $stmt->execute([
        ':nom'     => $nom,
        ':email'   => $email,
        ':sujet'   => $sujet,
        ':message' => $message,
    ]);

    // 5. Optionnel : Envoi d'une notification par email à votre adresse
    if ($success) {
        $mailTo = ADMIN_EMAIL;
        $emailSubject = "Nouveau message Portfolio: " . $sujet;
        $emailBody = "Vous avez reçu un nouveau message depuis votre site web.\n\n" .
                     "Nom : " . $nom . "\n" .
                     "Email : " . $email . "\n" .
                     "Sujet : " . $sujet . "\n\n" .
                     "Message :\n" . $message . "\n";
        
        $headers = [
            'From: ' . MAIL_FROM,
            'Reply-To: ' . $email,
            'Content-Type: text/plain; charset=UTF-8'
        ];

        // Tente d'envoyer l'alerte mail sans bloquer en cas d'échec d'envoi mail hébergeur
        @mail($mailTo, $emailSubject, $emailBody, implode("\r\n", $headers));

        // Redirection avec confirmation d'envoi réussi
        $_SESSION['last_contact_sent'] = $now;
        redirect('index.php?sent=1#contact');
    } else {
        redirect('index.php?error=1#contact');
    }

} catch (Exception $e) {
    // En cas d'erreur avec la base de données
    error_log("Erreur lors de l'enregistrement du message de contact: " . $e->getMessage());
    redirect('index.php?error=1#contact');
}