const words = ["Développeur Web", "Étudiant en Master 1", "Concepteur Java & PHP"];
let count = 0;
let index = 0;

(function type() {
    const el = document.getElementById("typing-text");
    if (!el) return;

    if (count === words.length) count = 0;
    const currentText = words[count];
    const letter = currentText.slice(0, ++index);
    el.textContent = letter;

    if (letter.length === currentText.length) {
        count++;
        index = 0;
        setTimeout(type, 2000);
    } else {
        setTimeout(type, 100);
    }
})();

const hamburger = document.getElementById("hamburger");
const navMenu = document.getElementById("nav-menu");
if (hamburger && navMenu) {
    hamburger.addEventListener("click", () => navMenu.classList.toggle("active"));
    document.querySelectorAll(".nav-link").forEach((n) => {
        n.addEventListener("click", () => navMenu.classList.remove("active"));
    });
}

const params = new URLSearchParams(window.location.search);
const alertBox = document.getElementById("form-alert");
if (alertBox && params.get("sent") === "1") {
    alertBox.hidden = false;
    alertBox.className = "form-alert success";
    alertBox.textContent = "Merci pour votre message. Je vous répondrai dans les plus brefs délais.";
    document.getElementById("contact")?.scrollIntoView({ behavior: "smooth" });
}
if (alertBox && params.get("error") === "1") {
    alertBox.hidden = false;
    alertBox.className = "form-alert error";
    alertBox.textContent = "Envoi impossible. Vérifiez les champs ou la connexion à la base de données.";
    document.getElementById("contact")?.scrollIntoView({ behavior: "smooth" });
}

// FILTRAGE DES COMPÉTENCES PAR CATÉGORIE
function filterSkills(category, element) {
    const cards = document.querySelectorAll('.skill-card');
    const buttons = document.querySelectorAll('.btn-filter');

    buttons.forEach(btn => btn.classList.remove('active'));
    if (element) {
        element.classList.add('active');
    }

    cards.forEach(card => {
        const cardCategory = card.getAttribute('data-category');
        if (category === 'all' || cardCategory === category) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

// FILTRAGE ET TRI DES PUBLICATIONS (INDEX.PHP)
function filterPosts(filter, element) {
    const postsContainer = document.getElementById('posts-container');
    if (!postsContainer) return;

    const posts = Array.from(postsContainer.getElementsByClassName('post-item'));

    // Mémorise l'ordre d'origine (le plus récent d'abord, tel que rendu par PHP)
    // une seule fois, pour pouvoir vraiment y revenir avec le filtre "Toutes".
    posts.forEach((post, i) => {
        if (post.dataset.originalOrder === undefined) {
            post.dataset.originalOrder = i;
        }
    });

    const buttons = element.parentElement.querySelectorAll('.btn-filter');
    buttons.forEach(btn => btn.classList.remove('active'));
    element.classList.add('active');

    if (filter === 'popular') {
        posts.sort((a, b) => parseInt(b.dataset.likes) - parseInt(a.dataset.likes));
    } else {
        posts.sort((a, b) => parseInt(a.dataset.originalOrder) - parseInt(b.dataset.originalOrder));
    }

    posts.forEach(post => postsContainer.appendChild(post));
}

// GESTION DU MODAL DE CONNEXION
function openLoginModal() {
    const modal = document.getElementById('loginModal');
    if (modal) modal.style.display = 'flex';
}

function closeLoginModal() {
    const modal = document.getElementById('loginModal');
    if (modal) modal.style.display = 'none';
}

// GESTION DES MODALS PROJETS ET PUBLICATIONS
function openProjectModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.style.display = 'flex';
}

function closeProjectModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.style.display = 'none';
}

window.addEventListener('click', function(e) {
    const loginModal = document.getElementById('loginModal');
    if (e.target === loginModal) {
        closeLoginModal();
    }
    if (e.target.classList.contains('login-modal') && e.target.id !== 'loginModal') {
        e.target.style.display = 'none';
    }
});