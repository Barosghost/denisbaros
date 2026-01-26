/**
 * DENIS FBI STORE - Core JS
 */

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
    const modalElement = document.getElementById('confirmationModal');
    const msgElement = document.getElementById('confirmationMessage');
    const btnElement = document.getElementById('confirmActionBtn');

    if (modalElement) {
        msgElement.textContent = message;
        btnElement.href = url;
        const bsModal = new bootstrap.Modal(modalElement);
        bsModal.show();
        return false; // Prevent default link behavior
    }
    return confirm(message); // Fallback
}

function showConfirmation(message, callback) {
    const modalElement = document.getElementById('confirmationModal');
    const msgElement = document.getElementById('confirmationMessage');
    const btnElement = document.getElementById('confirmActionBtn');

    if (modalElement) {
        msgElement.textContent = message;
        btnElement.href = "#"; // Disable link navigation
        confirmCallback = callback;
        const bsModal = new bootstrap.Modal(modalElement);
        bsModal.show();
    } else {
        if (confirm(message)) callback();
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

function openEditProductModal(id, name, catId, price, desc) {
    document.getElementById('edit_id_product').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_category').value = catId;
    document.getElementById('edit_price').value = price;
    document.getElementById('edit_description').value = desc;

    var myModal = new bootstrap.Modal(document.getElementById('editProductModal'));
    myModal.show();
}

function openEditClientModal(id, fullname, phone, email) {
    document.getElementById('edit_id_client').value = id;
    document.getElementById('edit_fullname').value = fullname;
    document.getElementById('edit_phone').value = phone;
    document.getElementById('edit_email').value = email;

    var myModal = new bootstrap.Modal(document.getElementById('editClientModal'));
    myModal.show();
}
