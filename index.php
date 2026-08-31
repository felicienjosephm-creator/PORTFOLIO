<?php
declare(strict_types=1);
require_once __DIR__ . '/configuration.php';
startSecureSession();

// Telechargement CV : redirige vers cv.html, la version à jour et unique du CV.
// (Si un jour un vrai fichier PDF généré est disponible sur le serveur, on le sert directement.)
if (isset($_GET['download']) && $_GET['download'] === 'cv') {
    $cvPdfPath = __DIR__ . '/CV_MANAMPISOA_Felicien.pdf';
    if (file_exists($cvPdfPath)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="CV_MANAMPISOA_Felicien.pdf"');
        header('Content-Length: ' . filesize($cvPdfPath));
        readfile($cvPdfPath);
        exit;
    }
    redirect('cv.html');
}

// Gestion des Likes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'like_post') {
    $postId = (int)($_POST['post_id'] ?? 0);
    if ($postId > 0) {
        $stmt = db()->prepare('UPDATE posts SET likes = likes + 1 WHERE id = :id');
        $stmt->execute([':id' => $postId]);
    }
    redirect('index.php#publications');
}

// Gestion des Commentaires
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_comment') {
    $postId = (int)($_POST['post_id'] ?? 0);
    $nom = trim((string)($_POST['nom'] ?? 'Anonyme'));
    $comment = trim((string)($_POST['commentaire'] ?? ''));

    if ($postId > 0 && $comment !== '') {
        $stmt = db()->prepare('INSERT INTO post_comments (post_id, auteur, commentaire) VALUES (:pid, :a, :c)');
        $stmt->execute([':pid' => $postId, ':a' => $nom ?: 'Anonyme', ':c' => $comment]);
    }
    redirect('index.php#publications');
}

$infoStmt = db()->query('SELECT cle, valeur FROM profile_info');
$info = $infoStmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Triée par "ordre" (le plus récent en premier), champ géré depuis l'espace admin
$timelines = db()->query('SELECT * FROM timeline ORDER BY ordre DESC, id DESC')->fetchAll();
$skills = db()->query('SELECT * FROM skills ORDER BY id ASC')->fetchAll();
$projects = db()->query('SELECT * FROM projects ORDER BY id DESC')->fetchAll();
$posts = db()->query('SELECT * FROM posts ORDER BY created_at DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Portfolio professionnel de <?php echo h($info['hero_title'] ?? ''); ?>">
    <meta name="author" content="<?php echo h($info['hero_title'] ?? ''); ?>">
    <meta name="theme-color" content="#0f172a">
    <title><?php echo h($info['hero_title'] ?? ''); ?> | Portfolio</title>

    <link rel="icon" type="image/png" href="profil.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <nav class="navbar">
        <div class="container">
            <a href="#accueil" class="logo">Felicien<span>.dev</span></a>
            <ul class="nav-menu" id="nav-menu">
                <li><a href="#accueil" class="nav-link active">Accueil</a></li>
                <li><a href="#apropos" class="nav-link">À propos</a></li>
                <li><a href="#competences" class="nav-link">Compétences</a></li>
                <li><a href="#projets" class="nav-link">Projets</a></li>
                <?php if (!empty($posts)): ?>
                    <li><a href="#publications" class="nav-link">Publications</a></li>
                <?php endif; ?>
                <li><a href="#contact" class="nav-link">Contact</a></li>
            </ul>
            <div class="hamburger" id="hamburger"><i class="fas fa-bars"></i></div>
        </div>
    </nav>

    <section id="accueil" class="hero">
        <div class="container">
            <div class="hero-grid">
                <div class="hero-text">
                    <span class="badge"><i class="fas fa-circle-dot"></i> Disponible pour opportunités</span>
                    <h1 class="hero-title"><?php echo nl2br(h($info['hero_title'] ?? '')); ?></h1>
                    <h2 class="hero-subtitle">Je suis <span class="typing-text" id="typing-text"></span></h2>
                    <p class="hero-description"><?php echo h($info['hero_description'] ?? ''); ?></p>
                    <div class="hero-actions">
                        <a href="#projets" class="btn btn-primary"><i class="fas fa-eye"></i> Explorer mes projets</a>
                        <a href="index.php?download=cv" class="btn btn-outline"><i class="fas fa-file-pdf"></i> Télécharger mon CV</a>
                    </div>
                </div>
                <div class="avatar-container">
                    <div class="avatar-box">
                        <div class="avatar-inner">
                            <img src="profil.png" alt="Portrait professionnel">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="apropos">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">À Propos & <span>Parcours</span></h2>
                <p class="section-subtitle">Mon profil professionnel et mes formations académiques</p>
                <div class="title-bar"></div>
            </div>

            <div class="about-wrapper">
                <div class="glass-card">
                    <h3><i class="fas fa-user-gear"></i> Profil & Intérêts</h3>
                    <p style="color: var(--text-muted); margin-bottom: 20px;">
                        <?php echo h($info['about_text'] ?? ''); ?>
                    </p>
                    <h4 style="font-size: 1rem; margin-bottom: 12px;">Centres d'intérêt</h4>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 25px;">
                        <span class="tech-pill"><i class="fas fa-basketball"></i> Basket-ball</span>
                        <span class="tech-pill"><i class="fas fa-book"></i> Lecture technique</span>
                        <span class="tech-pill"><i class="fas fa-music"></i> Chant & Musique</span>
                        <span class="tech-pill"><i class="fas fa-person-dots-from-line"></i> Danse</span>
                    </div>
                    <h4 style="font-size: 1rem; margin-bottom: 12px;">Langues</h4>
                    <p style="font-size: 0.9rem; color: var(--text-muted);">
                        • <strong>Malagasy :</strong> Langue maternelle<br>
                        • <strong>Français :</strong> Courant / Professionnel<br>
                        • <strong>Anglais :</strong> Niveau technique / Basique
                    </p>
                </div>

                <div class="glass-card">
                    <h3><i class="fas fa-graduation-cap"></i> Formations & Diplômes</h3>
                    <div class="timeline">
                        <?php foreach ($timelines as $item): ?>
                            <div class="timeline-item">
                                <span class="timeline-date"><?php echo h($item['annee']); ?></span>
                                <div class="timeline-title"><?php echo h($item['titre']); ?></div>
                                <div class="timeline-sub"><?php echo h($item['etablissement']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="competences">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Compétences <span>Techniques</span></h2>
                <p class="section-subtitle">Langages, frameworks, réseaux et outils maîtrisés</p>
                <div class="title-bar"></div>
            </div>

            <div class="skills-filter">
                <button class="btn-filter active" onclick="filterSkills('all', this)">Tous</button>
                <button class="btn-filter" onclick="filterSkills('web', this)">Développement Web</button>
                <button class="btn-filter" onclick="filterSkills('poo', this)">POO / App</button>
                <button class="btn-filter" onclick="filterSkills('other', this)">Autre</button>
            </div>

            <div class="skills-grid">
                <?php foreach ($skills as $skill): ?>
                    <div class="skill-card" data-category="<?php echo h($skill['categorie'] ?? 'web'); ?>">
                        <div class="skill-info">
                            <span><?php echo h($skill['nom']); ?></span>
                            <span style="color: var(--accent);"><?php echo (int)$skill['niveau']; ?>%</span>
                        </div>
                        <div class="skill-bar-bg">
                            <div class="skill-bar-fill" style="width: <?php echo (int)$skill['niveau']; ?>%;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section id="projets">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Projets <span>Réalisés</span></h2>
                <p class="section-subtitle">Mise en avant de mes travaux de fin d'études et stages</p>
                <div class="title-bar"></div>
            </div>

            <div class="projects-grid">
                <?php foreach ($projects as $proj): ?>
                    <div class="project-card">
                        <div class="project-thumb">
                            <i class="fas <?php echo h($proj['icone'] ?: 'fa-code'); ?>"></i>
                        </div>
                        <div class="project-content">
                            <span class="project-tag"><?php echo h($proj['tag']); ?></span>
                            <h3 class="project-title"><?php echo h($proj['titre']); ?></h3>
                            <p class="project-desc"><?php echo h($proj['description']); ?></p>
                            <div class="tech-stack">
                                <?php foreach (explode(',', $proj['technologies']) as $tech): ?>
                                    <span class="tech-pill"><?php echo h(trim($tech)); ?></span>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="btn btn-outline" style="width:100%; justify-content:center;" onclick="openProjectModal('modal-proj-<?php echo $proj['id']; ?>')">
                                <i class="fas fa-info-circle"></i> En savoir plus
                            </button>
                        </div>
                    </div>

                    <div id="modal-proj-<?php echo $proj['id']; ?>" class="login-modal" style="display: none;">
                        <div class="login-modal-content glass-card">
                            <span class="close-modal" onclick="closeProjectModal('modal-proj-<?php echo $proj['id']; ?>')">&times;</span>
                            <div class="section-header" style="margin-bottom:15px; text-align:left;">
                                <h3 class="section-title" style="font-size:1.3rem;"><?php echo h($proj['titre']); ?></h3>
                                <span class="project-tag"><?php echo h($proj['tag']); ?></span>
                            </div>
                            <p style="color: var(--text-muted); margin-bottom: 20px; text-align:justify;">
                                <?php echo nl2br(h($proj['description'])); ?>
                            </p>
                            <h4 style="font-size:0.9rem; margin-bottom:8px;">Technologies & Outils :</h4>
                            <div class="tech-stack">
                                <?php foreach (explode(',', $proj['technologies']) as $tech): ?>
                                    <span class="tech-pill"><?php echo h(trim($tech)); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- SECTION PUBLICATIONS DÉDIÉE -->
    <?php if (!empty($posts)): ?>
    <section id="publications" style="background: rgba(15, 23, 42, 0.4);">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Dernières <span>Publications</span></h2>
                <p class="section-subtitle">Annonces, partages et actualités récentes</p>
                <div class="title-bar"></div>
            </div>

            <!-- Menu / Filtres des publications -->
            <div class="skills-filter">
                <button class="btn-filter active" onclick="filterPosts('all', this)">Toutes les publications</button>
                <button class="btn-filter" onclick="filterPosts('popular', this)">Les plus adorées ❤️</button>
            </div>

            <div class="projects-grid" id="posts-container">
                <?php foreach ($posts as $post): 
                    $cStmt = db()->prepare('SELECT * FROM post_comments WHERE post_id = :pid ORDER BY created_at ASC');
                    $cStmt->execute([':pid' => $post['id']]);
                    $comments = $cStmt->fetchAll();
                ?>
                    <div class="glass-card post-item" data-likes="<?php echo (int)($post['likes'] ?? 0); ?>" style="display:flex; flex-direction:column; justify-content:space-between;">
                        <div>
                            <?php if (!empty($post['image'])): ?>
                                <img src="<?php echo h($post['image']); ?>" alt="Publication" style="width:100%; height:200px; object-fit:cover; border-radius:8px; margin-bottom:15px;">
                            <?php endif; ?>
                            
                            <span style="font-size: 0.8rem; color: var(--accent);"><i class="fas fa-calendar-alt"></i> Publié le <?php echo h($post['created_at']); ?></span>
                            <h3 style="margin: 10px 0; font-size: 1.2rem; color: var(--text-main);"><?php echo h($post['titre']); ?></h3>
                            <p style="color: var(--text-muted); text-align: justify; margin-bottom:20px;">
                                <?php echo nl2br(h($post['contenu'])); ?>
                            </p>
                        </div>

                        <!-- Menu d'action de la publication -->
                        <div style="display:flex; gap:10px; margin-top:auto; flex-wrap:wrap;">
                            <form method="post" action="index.php" style="margin:0;">
                                <input type="hidden" name="action" value="like_post">
                                <input type="hidden" name="post_id" value="<?php echo (int)$post['id']; ?>">
                                <button type="submit" class="btn btn-outline" style="padding:6px 14px; font-size:0.85rem;">
                                    <i class="fas fa-heart" style="color: #e11d48;"></i> Adorer❤️ (<?php echo (int)($post['likes'] ?? 0); ?>)
                                </button>
                            </form>

                            <button type="button" class="btn btn-primary" style="padding:6px 14px; font-size:0.85rem;" onclick="openProjectModal('modal-post-<?php echo $post['id']; ?>')">
                                <i class="fas fa-comments"></i> Voir & Commenter (<?php echo count($comments); ?>)
                            </button>
                        </div>
                    </div>

                    <!-- MODAL INTERACTIF POUR CHAQUE PUBLICATION -->
                    <div id="modal-post-<?php echo $post['id']; ?>" class="login-modal" style="display: none;">
                        <div class="login-modal-content glass-card" style="max-width: 650px; max-height: 90vh; overflow-y: auto;">
                            <span class="close-modal" onclick="closeProjectModal('modal-post-<?php echo $post['id']; ?>')">&times;</span>
                            
                            <div class="section-header" style="margin-bottom:15px; text-align:left;">
                                <h3 class="section-title" style="font-size:1.4rem;"><?php echo h($post['titre']); ?></h3>
                                <span style="font-size:0.8rem; color:var(--accent);"><i class="fas fa-clock"></i> <?php echo h($post['created_at']); ?></span>
                            </div>

                            <?php if (!empty($post['image'])): ?>
                                <img src="<?php echo h($post['image']); ?>" alt="Publication image" style="width:100%; max-height:300px; object-fit:cover; border-radius:8px; margin-bottom:15px;">
                            <?php endif; ?>

                            <p style="color: var(--text-main); margin-bottom:20px; line-height:1.6; text-align:justify;">
                                <?php echo nl2br(h($post['contenu'])); ?>
                            </p>

                            <hr style="border:0; border-top:1px solid var(--border); margin:20px 0;">

                            <h4 style="font-size:1rem; margin-bottom:12px; color:var(--accent);"><i class="fas fa-comments"></i> Commentaires (<?php echo count($comments); ?>) :</h4>
                            
                            <div style="margin-bottom:20px; max-height:220px; overflow-y:auto; display:flex; flex-direction:column; gap:10px;">
                                <?php if (empty($comments)): ?>
                                    <p style="font-size:0.85rem; color:var(--text-muted);">Soyez le premier à commenter cette publication !</p>
                                <?php else: ?>
                                    <?php foreach ($comments as $com): ?>
                                        <div style="background: rgba(15, 23, 42, 0.6); padding:10px 14px; border-radius:8px; border:1px solid var(--border);">
                                            <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                                                <strong style="font-size:0.85rem; color:var(--accent);"><?php echo h($com['auteur']); ?></strong>
                                                <small style="font-size:0.75rem; color:var(--text-muted);"><?php echo h($com['created_at']); ?></small>
                                            </div>
                                            <p style="font-size:0.9rem; color:var(--text-main); margin:0;"><?php echo nl2br(h($com['commentaire'])); ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <!-- Formulaire de saisie de commentaire -->
                            <form method="post" action="index.php" class="contact-form">
                                <input type="hidden" name="action" value="add_comment">
                                <input type="hidden" name="post_id" value="<?php echo (int)$post['id']; ?>">
                                <input type="text" class="form-control" name="nom" placeholder="Votre nom (optionnel)" maxlength="100">
                                <textarea class="form-control" name="commentaire" rows="2" placeholder="Écrire un commentaire..." required></textarea>
                                <button type="submit" class="btn btn-primary" style="justify-content:center;">
                                    <i class="fas fa-paper-plane"></i> Publier le commentaire
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section id="contact">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Me <span>Contacter</span></h2>
                <p class="section-subtitle">Discutons de vos projets ou d'opportunités de collaboration</p>
                <div class="title-bar"></div>
            </div>

            <p id="form-alert" class="form-alert" hidden></p>

            <div class="contact-grid">
                <div class="contact-info">
                    <div class="contact-card">
                        <i class="fas fa-envelope"></i>
                        <div>
                            <div style="font-size: 0.8rem; color: var(--text-muted);">Email</div>
                            <strong style="font-size: 0.95rem;"><?php echo h($info['email'] ?? ''); ?></strong>
                        </div>
                    </div>
                    <div class="contact-card">
                        <i class="fas fa-phone"></i>
                        <div>
                            <div style="font-size: 0.8rem; color: var(--text-muted);">Téléphone _ WhatsApp</div>
                            <strong style="font-size: 0.95rem;"><?php echo h($info['telephone'] ?? ''); ?></strong>
                        </div>
                    </div>
                    <div class="contact-card">
                        <i class="fas fa-location-dot"></i>
                        <div>
                            <div style="font-size: 0.8rem; color: var(--text-muted);">Localisation</div>
                            <strong style="font-size: 0.95rem;"><?php echo h($info['localisation'] ?? ''); ?></strong>
                        </div>
                    </div>

                    <a href="cv.html" target="_blank" rel="noopener noreferrer" class="btn btn-primary" style="width: 100%; justify-content: center; margin-top: 10px;">
                        <i class="fas fa-file-pdf"></i>Mon CV
                    </a>
                </div>

                <form class="contact-form" id="contactForm" action="contact.php" method="post">
                    <input type="text" name="site_web" id="site_web" class="hp-field" tabindex="-1" autocomplete="off" aria-hidden="true">
                    <input type="text" class="form-control" name="nom" id="nom" placeholder="Votre nom complet" required maxlength="120">
                    <input type="email" class="form-control" name="email" id="email" placeholder="Votre adresse email" required maxlength="180">
                    <input type="text" class="form-control" name="sujet" id="sujet" placeholder="Sujet du message" required maxlength="200">
                    <textarea class="form-control" name="message" id="message" rows="5" placeholder="Votre message..." required></textarea>
                    <button type="submit" class="btn btn-primary" style="justify-content: center;">
                        <i class="fas fa-paper-plane"></i> Envoyer le message
                    </button>
                </form>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <p>&copy; 2026 <?php echo h($info['hero_title'] ?? 'MANAMPISOA Felicien Joseph'); ?>. Tous droits réservés.</p>
            <p class="footer-admin-link">
                <button type="button" onclick="openLoginModal()" class="btn-login-trigger">
                    <i class="fas fa-user-lock"></i> Connexion : MANAMPISOA Felicien Joseph
                </button>
            </p>
        </div>
    </footer>

    <div id="loginModal" class="login-modal" style="display: none;">
        <div class="login-modal-content glass-card">
            <span class="close-modal" onclick="closeLoginModal()">&times;</span>
            <div class="section-header" style="margin-bottom: 20px;">
                <h3 class="section-title" style="font-size: 1.4rem;">Espace <span>Personnel</span></h3>
                <p class="section-subtitle">MANAMPISOA Felicien Joseph</p>
            </div>

            <form action="interface.php" method="post" class="contact-form">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="csrf_token" value="<?php echo h(csrfToken()); ?>">
                
                <label style="font-size: 0.85rem; color: var(--text-muted);">Compte administrateur :</label>
                <input type="text" class="form-control" value="MANAMPISOA Felicien Joseph" readonly style="opacity: 0.8; cursor: not-allowed;">
                <input type="hidden" name="email" value="<?php echo h(ADMIN_EMAIL); ?>">

                <label style="font-size: 0.85rem; color: var(--text-muted); margin-top: 10px;">Code secret / mot de passe :</label>
                <input type="password" class="form-control" name="password" placeholder="Mot de passe..." required autofocus>

                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; margin-top: 15px;">
                    <i class="fas fa-key"></i> Connexion
                </button>
            </form>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>