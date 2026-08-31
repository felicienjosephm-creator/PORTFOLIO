CREATE DATABASE IF NOT EXISTS portfolio
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE portfolio;

-- Table des messages reçus
CREATE TABLE IF NOT EXISTS contacts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(120) NOT NULL,
  email VARCHAR(180) NOT NULL,
  sujet VARCHAR(200) NOT NULL,
  message TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  replied TINYINT(1) NOT NULL DEFAULT 0,
  reply_text TEXT NULL,
  replied_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table du profil et textes généraux
CREATE TABLE IF NOT EXISTS profile_info (
  cle VARCHAR(50) PRIMARY KEY,
  valeur TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO profile_info (cle, valeur) VALUES
('hero_title', 'MANAMPISOA Felicien Joseph'),
('hero_subtitle', 'Développeur Web & Étudiant M1'),
('hero_description', 'Étudiant en Master 1 Informatique (Spécialité Développement Web) à l\'ISIME Betongolo, Antananarivo. Je conçois des solutions digitales complètes, modernes et sécurisées.'),
('about_text', 'Passionné d\'informatique et de technologies web, j\'allie méthodologie académique et réalisations pratiques pour développer des applications fluides et scalables.'),
('email', 'felicienjosephm@gmail.com'),
('telephone', '+261 38 45 103 10'),
('localisation', 'Antananarivo, Madagascar')
ON DUPLICATE KEY UPDATE cle=cle;

-- Table des compétences
CREATE TABLE IF NOT EXISTS skills (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(100) NOT NULL,
  niveau INT NOT NULL DEFAULT 80,
  categorie ENUM('web', 'poo', 'other') NOT NULL DEFAULT 'web'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO skills (nom, niveau, categorie) VALUES
('HTML5 / CSS3', 90, 'web'),
('JavaScript', 80, 'web'),
('PHP', 85, 'web'),
('SQL / MySQL', 80, 'web'),
('Python', 75, 'poo'),
('Java / JSP', 80, 'poo'),
('Framework Bootstrap', 90, 'web'),
('Câblage RJ45 / Réseau', 75, 'other'),
('Cybersécurité (Kali Linux)', 65, 'other');

-- Table du parcours / formations / diplômes
CREATE TABLE IF NOT EXISTS timeline (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  annee VARCHAR(50) NOT NULL,
  titre VARCHAR(150) NOT NULL,
  etablissement VARCHAR(150) NOT NULL,
  ordre INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 'ordre' contrôle l'affichage (valeur la plus haute = affichée en premier),
-- indépendamment de l'ordre d'insertion en base.
INSERT INTO timeline (annee, titre, etablissement, ordre) VALUES
('2026 - En cours', 'Master 1 Informatique - Développement Web', 'ISIME Betongolo, Antananarivo', 40),
('Août 2025', 'Formation Cybersécurité (Kali Linux)', 'Orange Madagascar, Antananarivo', 30),
('2025', 'Licence en Informatique (Développement Web)', 'ISIME Betongolo, Antananarivo', 20),
('2024', 'DTS en Informatique (Développement Web)', 'Université Saint Vincent de Paul Akamasoa', 10);

-- Table des projets
CREATE TABLE IF NOT EXISTS projects (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  titre VARCHAR(150) NOT NULL,
  tag VARCHAR(100) NOT NULL,
  description TEXT NOT NULL,
  technologies VARCHAR(255) NOT NULL,
  icone VARCHAR(50) DEFAULT 'fa-code'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO projects (titre, tag, description, technologies, icone) VALUES
('Gestion des Matériels Médicaux', 'Stage Licence • Hôpital CENHOSOA', 'Application desktop robuste assurant la traçabilité des équipements hospitaliers, la gestion des entrées/sorties et le suivi des stocks.', 'Java, Swing/AWT, SQL', 'fa-hospital-user'),
('Gestion du Pointage des Professeurs', 'Stage DTS • École Chateaubriand', 'Plateforme web de suivi de la présence des enseignants, intégration du calendrier scolaire et calcul automatisé des salaires mensuels.', 'PHP, Bootstrap, JavaScript, MySQL', 'fa-school');

-- Table des publications / actualités
CREATE TABLE IF NOT EXISTS posts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  titre VARCHAR(200) NOT NULL,
  contenu TEXT NOT NULL,
  image VARCHAR(255) NULL,
  likes INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des commentaires sur les publications
CREATE TABLE IF NOT EXISTS post_comments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  post_id INT UNSIGNED NOT NULL,
  auteur VARCHAR(100) NOT NULL DEFAULT 'Anonyme',
  commentaire TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;