// app.js - Fonctionnalités de base pour Denis FBI Store

document.addEventListener('DOMContentLoaded', function() {
    // Gestion du Sidebar (Mobile & Desktop)
    const sidebarCollapse = document.getElementById('sidebarCollapse');
    const sidebar = document.getElementById('sidebar');
    
    if (sidebarCollapse && sidebar) {
        sidebarCollapse.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            const content = document.getElementById('content');
            if (content) content.classList.toggle('active');
        });
    }

    // Fermeture automatique des alertes
    const alerts = document.querySelectorAll('.alert:not(.alert-important)');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const closeBtn = alert.querySelector('.btn-close');
            if (closeBtn) closeBtn.click();
        }, 5000);
    });
});

/**
 * Alterne la visibilité du mot de passe
 * @param {string} fieldId - ID de l'input password
 * @param {string} iconId - ID de l'icône à changer
 */
function togglePassword(fieldId, iconId) {
    const passwordField = document.getElementById(fieldId);
    const icon = document.getElementById(iconId);
    if (passwordField && icon) {
        if (passwordField.type === "password") {
            passwordField.type = "text";
            icon.classList.replace("fa-eye", "fa-eye-slash");
        } else {
            passwordField.type = "password";
            icon.classList.replace("fa-eye-slash", "fa-eye");
        }
    }
}

/**
 * Demande confirmation avant une action critique
 * @param {string} url - URL de redirection si confirmé
 * @param {string} message - Message de confirmation
 * @returns {boolean}
 */
function confirmAction(url, message) {
    if (confirm(message || "Êtes-vous sûr de vouloir continuer ?")) {
        if (url && url !== '#') {
            window.location.href = url;
        }
        return true;
    }
    return false;
}