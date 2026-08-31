<?php
declare(strict_types=1);
require_once __DIR__ . '/configuration.php';
startSecureSession();

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    redirect('interface.php');
}

$error = '';
$notice = '';

$lockedUntil = (int) ($_SESSION['login_locked_until'] ?? 0);
$isLocked = $lockedUntil > time();

if (!$isLocked && ($_POST['action'] ?? '') === 'login') {
    if (!csrfCheck($_POST['csrf_token'] ?? null)) {
        $error = 'Erreur : Session expirée, merci de réessayer.';
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $emailOk = hash_equals(strtolower(ADMIN_EMAIL), strtolower($email));
        $passOk = password_verify($password, ADMIN_PASSWORD_HASH);

        if ($emailOk && $passOk) {
            unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            redirect('interface.php');
        } else {
            $_SESSION['login_attempts'] = (int) ($_SESSION['login_attempts'] ?? 0) + 1;
            if ($_SESSION['login_attempts'] >= LOGIN_MAX_ATTEMPTS) {
                $_SESSION['login_locked_until'] = time() + LOGIN_LOCKOUT_SECONDS;
                $_SESSION['login_attempts'] = 0;
                $isLocked = true;
            }
            $error = 'Erreur : Code ou mot de passe incorrect.';
        }
    }
} elseif ($isLocked) {
    $error = 'Erreur : Trop de tentatives. Réessayez dans quelques minutes.';
}

$isAdmin = !empty($_SESSION['admin']);

if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!csrfCheck($_POST['csrf_token'] ?? null)) {
        $error = 'Session expirée, merci de réessayer.';
    } else {
        if ($action === 'update_profile') {
            foreach ($_POST['info'] as $cle => $valeur) {
                $stmt = db()->prepare('UPDATE profile_info SET valeur = :valeur WHERE cle = :cle');
                $stmt->execute([':valeur' => trim($valeur), ':cle' => $cle]);
            }
            $notice = 'Profil mis à jour avec succès.';
        }
        elseif ($action === 'delete_item') {
            $table = $_POST['table'] ?? '';
            $id = (int)($_POST['id'] ?? 0);
            $allowedTables = ['skills', 'timeline', 'projects', 'contacts', 'posts', 'post_comments'];
            
            if (in_array($table, $allowedTables, true) && $id > 0) {
                $stmt = db()->prepare("DELETE FROM {$table} WHERE id = :id");
                $stmt->execute([':id' => $id]);
                $notice = 'Élément supprimé avec succès.';
            }
        }
        elseif ($action === 'add_project') {
            $titre = trim($_POST['titre'] ?? '');
            $tag = trim($_POST['tag'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            $tech = trim($_POST['technologies'] ?? '');
            $icone = trim($_POST['icone'] ?? 'fa-code');

            if ($titre !== '') {
                $stmt = db()->prepare('INSERT INTO projects (titre, tag, description, technologies, icone) VALUES (:t, :tag, :d, :tech, :ico)');
                $stmt->execute([':t' => $titre, ':tag' => $tag, ':d' => $desc, ':tech' => $tech, ':ico' => $icone]);
                $notice = 'Projet ajouté avec succès.';
            }
        }
        elseif ($action === 'add_timeline') {
            $annee = trim($_POST['annee'] ?? '');
            $titre = trim($_POST['titre'] ?? '');
            $etab = trim($_POST['etablissement'] ?? '');

            if ($titre !== '') {
                // Place automatiquement le nouvel élément en tête d'affichage (ordre = max + 10)
                $maxOrdre = (int) db()->query('SELECT COALESCE(MAX(ordre), 0) FROM timeline')->fetchColumn();
                $stmt = db()->prepare('INSERT INTO timeline (annee, titre, etablissement, ordre) VALUES (:a, :t, :e, :o)');
                $stmt->execute([':a' => $annee, ':t' => $titre, ':e' => $etab, ':o' => $maxOrdre + 10]);
                $notice = 'Diplôme / Formation ajouté(e) avec succès.';
            }
        }
        elseif ($action === 'add_skill') {
            $nom = trim($_POST['nom'] ?? '');
            $niveau = (int) ($_POST['niveau'] ?? 80);
            $categorie = $_POST['categorie'] ?? 'web';
            $allowedCategories = ['web', 'poo', 'other'];

            $niveau = max(0, min(100, $niveau));
            if (!in_array($categorie, $allowedCategories, true)) {
                $categorie = 'web';
            }

            if ($nom !== '') {
                $stmt = db()->prepare('INSERT INTO skills (nom, niveau, categorie) VALUES (:n, :niv, :cat)');
                $stmt->execute([':n' => $nom, ':niv' => $niveau, ':cat' => $categorie]);
                $notice = 'Compétence ajoutée avec succès.';
            }
        }
        elseif ($action === 'add_post') {
            $titre = trim($_POST['titre'] ?? '');
            $contenu = trim($_POST['contenu'] ?? '');
            $commentaireAdmin = trim($_POST['commentaire_admin'] ?? '');
            $imagePath = null;

            // Importation du fichier d'image dans le dossier uploads/
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/uploads/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                // Vérifie que le fichier est réellement une image (pas seulement l'extension du nom)
                $imageInfo = @getimagesize($_FILES['photo']['tmp_name']);
                if ($imageInfo !== false && in_array($ext, $allowed, true) && $_FILES['photo']['size'] <= 5 * 1024 * 1024) {
                    $fileName = 'post_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $fileName)) {
                        $imagePath = 'uploads/' . $fileName;
                    }
                }
            }

            if ($titre !== '' && $contenu !== '') {
                $stmt = db()->prepare('INSERT INTO posts (titre, contenu, image) VALUES (:t, :c, :img)');
                $stmt->execute([':t' => $titre, ':c' => $contenu, ':img' => $imagePath]);
                $postId = (int)db()->lastInsertId();

                if ($postId > 0 && $commentaireAdmin !== '') {
                    $cStmt = db()->prepare('INSERT INTO post_comments (post_id, auteur, commentaire) VALUES (:pid, :a, :c)');
                    $cStmt->execute([
                        ':pid' => $postId,
                        ':a' => 'MANAMPISOA Felicien Joseph (Auteur)',
                        ':c' => $commentaireAdmin
                    ]);
                }

                $notice = 'Publication enregistrée avec succès.';
            }
        }
        elseif ($action === 'reply_message') {
            $id = (int) ($_POST['id'] ?? 0);
            $reply = trim((string) ($_POST['reply_text'] ?? ''));

            if ($id > 0 && $reply !== '') {
                $stmt = db()->prepare('SELECT * FROM contacts WHERE id = :id');
                $stmt->execute([':id' => $id]);
                $contact = $stmt->fetch();

                if ($contact) {
                    $subject = 'Re: ' . $contact['sujet'];
                    $body = "Bonjour " . $contact['nom'] . ",\n\n" . $reply . "\n\n--\n" . SITE_NAME;
                    $headers = ['From: ' . MAIL_FROM, 'Reply-To: ' . MAIL_FROM, 'Content-Type: text/plain; charset=UTF-8'];
                    @mail($contact['email'], $subject, $body, implode("\r\n", $headers));

                    $upd = db()->prepare('UPDATE contacts SET replied = 1, reply_text = :r, replied_at = NOW() WHERE id = :id');
                    $upd->execute([':r' => $reply, ':id' => $id]);
                    $notice = 'Réponse enregistrée.';
                }
            }
        }
    }
}

$contacts = []; $info = []; $projects = []; $timelines = []; $skills = []; $posts = [];
if ($isAdmin) {
    $contacts = db()->query('SELECT * FROM contacts ORDER BY created_at DESC')->fetchAll();
    $info = db()->query('SELECT cle, valeur FROM profile_info')->fetchAll(PDO::FETCH_KEY_PAIR);
    $projects = db()->query('SELECT * FROM projects ORDER BY id DESC')->fetchAll();
    $timelines = db()->query('SELECT * FROM timeline ORDER BY ordre DESC, id DESC')->fetchAll();
    $skills = db()->query('SELECT * FROM skills ORDER BY id ASC')->fetchAll();
    $posts = db()->query('SELECT * FROM posts ORDER BY created_at DESC')->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration | <?php echo h(SITE_NAME); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<section>
    <div class="container">
        <?php if (!$isAdmin): ?>
            <div class="login-box">
                <div class="section-header">
                    <h2 class="section-title"><span>Connexion</span></h2>
                    <p class="section-subtitle">MANAMPISOA Felicien Joseph</p>
                    <div class="title-bar"></div>
                </div>
                <?php if ($error): ?><p class="form-alert error"><?php echo h($error); ?></p><?php endif; ?>
                <form method="post" class="contact-form">
                    <input type="hidden" name="action" value="login">
                    <input type="hidden" name="csrf_token" value="<?php echo h(csrfToken()); ?>">
                    <input type="email" class="form-control" name="email" value="<?php echo h(ADMIN_EMAIL); ?>" required readonly style="opacity:0.8;">
                    <input type="password" class="form-control" name="password" placeholder="Mot de passe..." required autofocus>
                    <button type="submit" class="btn btn-primary" style="justify-content: center;">
                        <i class="fas fa-right-to-bracket"></i> Connexion
                    </button>
                </form>
                <p style="margin-top:16px;text-align:center;"><a href="index.php">Retour au site</a></p>
            </div>
        <?php else: ?>
            <div class="admin-header">
                <div>
                    <h2 class="section-title">Espace <span>Personnel</span></h2>
                    <p class="section-subtitle">Bienvenue MANAMPISOA Felicien Joseph</p>
                </div>
                <div style="display:flex;gap:10px;">
                    <a class="btn btn-outline" href="index.php" target="_blank" rel="noopener noreferrer">Voir le site</a>
                    <a class="btn btn-primary" href="interface.php?logout=1">Déconnexion</a>
                </div>
            </div>

            <?php if ($error): ?><p class="form-alert error"><?php echo h($error); ?></p><?php endif; ?>
            <?php if ($notice): ?><p class="form-alert success"><?php echo h($notice); ?></p><?php endif; ?>

            <!-- Formulaire de Publication avec upload d'image et commentaire d'auteur -->
            <div class="glass-card" style="margin-bottom:30px;">
                <h3><i class="fas fa-bullhorn"></i> Ajouter une Publication sur index.php</h3>
                <form method="post" enctype="multipart/form-data" class="contact-form" style="margin-bottom:20px;">
                    <input type="hidden" name="action" value="add_post">
                    <input type="hidden" name="csrf_token" value="<?php echo h(csrfToken()); ?>">
                    <input type="text" class="form-control" name="titre" placeholder="Titre de la publication" required>
                    
                    <label style="font-size:0.85rem; color:var(--text-muted);">Importer une image locale :</label>
                    <input type="file" class="form-control" name="photo" accept="image/*">
                    
                    <textarea class="form-control" name="contenu" rows="3" placeholder="Contenu de la publication..." required></textarea>
                    
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Publier</button>
                </form>

                <h4>Publications enregistrées :</h4>
                <ul style="margin-top:10px;">
                    <?php foreach ($posts as $po): ?>
                        <li style="display:flex; justify-content:space-between; align-items:center; padding:10px; border-bottom:1px solid var(--border);">
                            <span>
                                <strong><?php echo h($po['titre']); ?></strong> (<?php echo h($po['created_at']); ?>) - ❤️ <?php echo (int)($po['likes'] ?? 0); ?>
                                <?php if(!empty($po['image'])): ?>
                                    <small style="color:var(--accent);">[Image: <?php echo h($po['image']); ?>]</small>
                                <?php endif; ?>
                            </span>
                            <form method="post" onsubmit="return confirm('Supprimer cette publication ?');">
                                <input type="hidden" name="action" value="delete_item">
                                <input type="hidden" name="table" value="posts">
                                <input type="hidden" name="id" value="<?php echo (int)$po['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo h(csrfToken()); ?>">
                                <button type="submit" class="btn btn-outline" style="color:#f87171; border-color:#f87171; padding:4px 8px;"><i class="fas fa-trash">Suprimer</i></button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Gérer les formations -->
            <div class="glass-card" style="margin-bottom:30px;">
                <h3><i class="fas fa-graduation-cap"></i> Gérer les Diplômes & Formations</h3>
                <form method="post" class="contact-form" style="margin-bottom:20px;">
                    <input type="hidden" name="action" value="add_timeline">
                    <input type="hidden" name="csrf_token" value="<?php echo h(csrfToken()); ?>">
                    <div style="display:grid; grid-template-columns: 1fr 2fr 2fr; gap:10px;">
                        <input type="text" class="form-control" name="annee" placeholder="Année (ex: 2025)" required>
                        <input type="text" class="form-control" name="titre" placeholder="Intitulé du Diplôme" required>
                        <input type="text" class="form-control" name="etablissement" placeholder="Établissement" required>
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-top:10px;"><i class="fas fa-plus"></i> Ajouter ce diplôme</button>
                </form>

                <h4>Diplômes et parcours enregistrés :</h4>
                <ul style="margin-top:10px;">
                    <?php foreach ($timelines as $t): ?>
                        <li style="display:flex; justify-content:space-between; align-items:center; padding:10px; border-bottom:1px solid var(--border);">
                            <span><strong>[<?php echo h($t['annee']); ?>]</strong> <?php echo h($t['titre']); ?> - <em><?php echo h($t['etablissement']); ?></em></span>
                            <form method="post" onsubmit="return confirm('Supprimer ce diplôme ?');">
                                <input type="hidden" name="action" value="delete_item">
                                <input type="hidden" name="table" value="timeline">
                                <input type="hidden" name="id" value="<?php echo (int)$t['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo h(csrfToken()); ?>">
                                <button type="submit" class="btn btn-outline" style="color:#f87171; border-color:#f87171; padding:4px 8px;"><i class="fas fa-trash">Suprimer</i></button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Gérer les projets -->
            <div class="glass-card" style="margin-bottom:30px;">
                <h3><i class="fas fa-folder-plus"></i> Gestion des Projets</h3>
                <form method="post" class="contact-form" style="margin-bottom:20px;">
                    <input type="hidden" name="action" value="add_project">
                    <input type="hidden" name="csrf_token" value="<?php echo h(csrfToken()); ?>">
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                        <input type="text" class="form-control" name="titre" placeholder="Titre du projet" required>
                        <input type="text" class="form-control" name="tag" placeholder="Tag (ex: Stage Licence)" required>
                    </div>
                    <textarea class="form-control" name="description" placeholder="Description du projet" required></textarea>
                    <div style="display:grid; grid-template-columns: 2fr 1fr; gap:10px;">
                        <input type="text" class="form-control" name="technologies" placeholder="Technologies (ex: Java, PHP, SQL)" required>
                        <input type="text" class="form-control" name="icone" placeholder="Icône FontAwesome (ex: fa-school)">
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Ajouter ce projet</button>
                </form>

                <h4>Projets enregistrés :</h4>
                <ul style="margin-top:10px;">
                    <?php foreach ($projects as $p): ?>
                        <li style="display:flex; justify-content:space-between; align-items:center; padding:10px; border-bottom:1px solid var(--border);">
                            <span><strong><?php echo h($p['titre']); ?></strong> (<?php echo h($p['tag']); ?>)</span>
                            <form method="post" onsubmit="return confirm('Supprimer ce projet ?');">
                                <input type="hidden" name="action" value="delete_item">
                                <input type="hidden" name="table" value="projects">
                                <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo h(csrfToken()); ?>">
                                <button type="submit" class="btn btn-outline" style="color:#f87171; border-color:#f87171; padding:4px 8px;"><i class="fas fa-trash">Suprimer</i></button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Gérer les compétences -->
            <div class="glass-card" style="margin-bottom:30px;">
                <h3><i class="fas fa-star"></i> Gestion des Compétences</h3>
                <form method="post" class="contact-form" style="margin-bottom:20px;">
                    <input type="hidden" name="action" value="add_skill">
                    <input type="hidden" name="csrf_token" value="<?php echo h(csrfToken()); ?>">
                    <div style="display:grid; grid-template-columns: 2fr 1fr 1fr; gap:10px;">
                        <input type="text" class="form-control" name="nom" placeholder="Nom (ex: JavaScript)" required>
                        <input type="number" class="form-control" name="niveau" placeholder="Niveau (0-100)" min="0" max="100" value="80" required>
                        <select class="form-control" name="categorie" required>
                            <option value="web">Développement Web</option>
                            <option value="poo">POO / App</option>
                            <option value="other">Autre</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-top:10px;"><i class="fas fa-plus"></i> Ajouter cette compétence</button>
                </form>

                <h4>Compétences enregistrées :</h4>
                <ul style="margin-top:10px;">
                    <?php foreach ($skills as $sk): ?>
                        <li style="display:flex; justify-content:space-between; align-items:center; padding:10px; border-bottom:1px solid var(--border);">
                            <span><strong><?php echo h($sk['nom']); ?></strong> — <?php echo (int)$sk['niveau']; ?>% (<?php echo h($sk['categorie']); ?>)</span>
                            <form method="post" onsubmit="return confirm('Supprimer cette compétence ?');">
                                <input type="hidden" name="action" value="delete_item">
                                <input type="hidden" name="table" value="skills">
                                <input type="hidden" name="id" value="<?php echo (int)$sk['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo h(csrfToken()); ?>">
                                <button type="submit" class="btn btn-outline" style="color:#f87171; border-color:#f87171; padding:4px 8px;"><i class="fas fa-trash">Suprimer</i></button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Informations du profil -->
            <div class="glass-card" style="margin-bottom:30px;">
                <h3><i class="fas fa-user-edit"></i> Informations du Profil</h3>
                <form method="post" class="contact-form">
                    <input type="hidden" name="action" value="update_profile">
                    <input type="hidden" name="csrf_token" value="<?php echo h(csrfToken()); ?>">
                    
                    <label>Nom complet :</label>
                    <input type="text" class="form-control" name="info[hero_title]" value="<?php echo h($info['hero_title'] ?? ''); ?>" required>
                    
                    <label>Description Accueil :</label>
                    <textarea class="form-control" name="info[hero_description]" rows="2" required><?php echo h($info['hero_description'] ?? ''); ?></textarea>
                    
                    <label>Texte À propos :</label>
                    <textarea class="form-control" name="info[about_text]" rows="3" required><?php echo h($info['about_text'] ?? ''); ?></textarea>
                    
                    <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:10px;">
                        <div>
                            <label>Email :</label>
                            <input type="email" class="form-control" name="info[email]" value="<?php echo h($info['email'] ?? ''); ?>" required>
                        </div>
                        <div>
                            <label>Téléphone :</label>
                            <input type="text" class="form-control" name="info[telephone]" value="<?php echo h($info['telephone'] ?? ''); ?>" required>
                        </div>
                        <div>
                            <label>Localisation :</label>
                            <input type="text" class="form-control" name="info[localisation]" value="<?php echo h($info['localisation'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="margin-top:10px;"><i class="fas fa-save"></i> Enregistrer les modifications</button>
                </form>
            </div>

            <!-- Messages des visiteurs -->
            <div class="glass-card">
                <h3><i class="fas fa-envelope"></i> Messages Visiteurs</h3>
                <?php if (!$contacts): ?>
                    <p style="margin-top:10px;">Aucun message reçu.</p>
                <?php else: ?>
                    <div class="admin-table-wrap" style="margin-top:15px;">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Expéditeur</th>
                                    <th>Message</th>
                                    <th>Réponse</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($contacts as $c): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo h($c['nom']); ?></strong><br>
                                        <a href="mailto:<?php echo h($c['email']); ?>"><?php echo h($c['email']); ?></a><br>
                                        <small style="color:var(--text-muted);"><?php echo h($c['created_at']); ?></small>
                                    </td>
                                    <td>
                                        <strong>Sujet : <?php echo h($c['sujet']); ?></strong><br>
                                        <?php echo nl2br(h($c['message'])); ?>
                                    </td>
                                    <td>
                                        <?php if ((int)$c['replied'] === 1): ?>
                                            <span class="badge-ok">Répondu</span>
                                            <div style="font-size:0.85rem; color:var(--text-muted);"><?php echo nl2br(h($c['reply_text'])); ?></div>
                                        <?php else: ?>
                                            <form method="post" class="contact-form">
                                                <input type="hidden" name="action" value="reply_message">
                                                <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo h(csrfToken()); ?>">
                                                <textarea class="form-control" name="reply_text" rows="2" placeholder="Répondre..." required></textarea>
                                                <button type="submit" class="btn btn-primary" style="padding:4px 8px; font-size:0.8rem;">Répondre</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="post" onsubmit="return confirm('Supprimer ce message ?');">
                                            <input type="hidden" name="action" value="delete_item">
                                            <input type="hidden" name="table" value="contacts">
                                            <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo h(csrfToken()); ?>">
                                            <button type="submit" class="btn btn-outline" style="color:#f87171; border-color:#f87171; padding:4px 8px;"><i class="fas fa-trash">Suprimer</i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </div>
</section>
</body>
</html>