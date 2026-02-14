/**
 * DENIS FBI STORE - Core JS
 */

// Register Service Worker for PWA
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        // TEMPORARY: Unregister to fix cache issues
        navigator.serviceWorker.getRegistrations().then(function (registrations) {
            for (let registration of registrations) {
                registration.unregister();
            }
        });
        /*
        navigator.serviceWorker.register('/denis/sw.js')
            .then((registration) => {
                console.log('ServiceWorker registration successful');
            })
            .catch((err) => {
                console.log('ServiceWorker registration failed: ', err);
            });
        */
    });
}

let confirmCallback = null;

document.addEventListener('DOMContentLoaded', () => {
    console.log('DENIS FBI STORE Loaded');

    const sidebar = document.getElementById('sidebar');
    const sidebarCollapse = document.getElementById('sidebarCollapse');
    const wrapper = document.querySelector('.wrapper');

    // Create Overlay for Mobile
    const overlay = document.createElement('div');
    overlay.className = 'overlay';
    if (wrapper) {
        wrapper.appendChild(overlay);
    }

    if (sidebarCollapse) {
        sidebarCollapse.addEventListener('click', () => {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        });
    }

    // Close sidebar when clicking overlay
    overlay.addEventListener('click', () => {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
    });

    // Close sidebar when clicking a link inside it (mobile: smooth transition to new page)
    if (sidebar) {
        sidebar.addEventListener('click', (e) => {
            const link = e.target.closest('a[href]');
            if (link && link.getAttribute('href') && !link.getAttribute('href').startsWith('#')) {
                if (window.innerWidth < 768) {
                    sidebar.classList.remove('active');
                    if (overlay) overlay.classList.remove('active');
                }
            }
        });
    }

    // Inject Confirmation Modal
    const modalHTML = `
    <div class="modal fade" id="confirmationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark text-white border-0 glass-panel">
                <div class="modal-header border-bottom border-secondary">
                    <h5 class="modal-title"><i class="fa-solid fa-triangle-exclamation text-warning me-2"></i> Confirmation</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="confirmationMessage" class="mb-0">Êtes-vous sûr de vouloir effectuer cette action ?</p>
                </div>
                <div class="modal-footer border-top border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Annuler</button>
                    <a href="#" id="confirmActionBtn" class="btn btn-danger">Confirmer</a>
                </div>
            </div>
        </div>
    </div>`;
    document.body.insertAdjacentHTML('beforeend', modalHTML);

    // Event Delegation for Confirm Button
    document.body.addEventListener('click', (e) => {
        if (e.target && e.target.id === 'confirmActionBtn') {
            if (confirmCallback) {
                e.preventDefault();
                confirmCallback();
                const el = document.getElementById('confirmationModal');
                const modal = bootstrap.Modal.getInstance(el);
                modal.hide();
            }
        }
    });
});

// Global Confirm Action
function confirmAction(url, message = "Êtes-vous sûr de vouloir continuer ?") {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Êtes-vous sûr ?',
            text: message,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Oui, continuer',
            cancelButtonText: 'Annuler',
            background: '#1e293b',
            color: '#fff'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = url;
            }
        });
        return false;
    }
    return confirm(message);
}

function showConfirmation(message, callback) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Confirmation',
            text: message,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, confirmer',
            cancelButtonText: 'Annuler',
            background: '#1e293b',
            color: '#fff'
        }).then((result) => {
            if (result.isConfirmed) {
                callback();
            }
        });
    } else {
        if (confirm(message)) callback();
    }
}

function showAlert(title, message, icon = 'info') {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: title,
            text: message,
            icon: icon,
            background: '#1e293b',
            color: '#fff'
        });
    } else {
        alert(message);
    }
}

function openEditUserModal(id, username, role) {
    document.getElementById('edit_id_user').value = id;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_role').value = role;

    var myModal = new bootstrap.Modal(document.getElementById('editUserModal'));
    myModal.show();
}

function togglePassword(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);

    if (input.type === "password") {
        input.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
    } else {
        input.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
    }
}