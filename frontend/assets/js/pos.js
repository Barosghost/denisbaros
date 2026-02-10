/**
 * POS Logic for DENIS FBI STORE
 */

let cart = [];

// Add to Cart
function addToCart(id, name, price, maxStock) {
    if (maxStock <= 0) {
        showAlert('Stock Épuisé', "Ce produit n'est plus en stock !", 'error');
        return;
    }

    const existingItem = cart.find(item => item.id === id);

    if (existingItem) {
        if (existingItem.qty < maxStock) {
            existingItem.qty++;
        } else {
            showAlert('Stock Insuffisant', "Vous ne pouvez pas ajouter plus que le stock disponible.", 'warning');
            return;
        }
    } else {
        cart.push({ id, name, price, qty: 1, maxStock });
    }

    renderCart();
    if (cart.length === 1 && typeof openMobileCart === 'function') {
        openMobileCart();
    }
}

// Render Cart UI
function renderCart() {
    const cartContainer = document.getElementById('cartTableBody');
    cartContainer.innerHTML = '';

    const posContent = document.querySelector('.pos-content');
    const topCartBar = document.getElementById('topCartBar');

    if (cart.length === 0) {
        if (posContent) posContent.classList.remove('cart-active');

        // Hide Top Bar Trigger
        if (topCartBar) {
            topCartBar.classList.remove('d-flex');
            topCartBar.classList.add('d-none');
        }

        cartContainer.innerHTML = `
            <div class="d-flex flex-column align-items-center justify-content-center h-100 opacity-20">
                <i class="fa-solid fa-cart-shopping fa-3x mb-3"></i>
                <div class="fw-medium">Votre panier est vide</div>
                <div class="small">Sélectionnez des articles à gauche</div>
            </div>`;
        document.getElementById('totalItems').innerText = 0;
        document.getElementById('totalAmount').innerText = '0 FCFA';
        return;
    }

    // Show Top Cart Trigger
    if (topCartBar) {
        topCartBar.classList.remove('d-none');
        topCartBar.classList.add('d-flex');

        // Pulse animation on the button itself
        const triggerBtn = topCartBar.querySelector('.top-cart-trigger');
        if (triggerBtn) {
            triggerBtn.classList.remove('animate-pulse');
            void triggerBtn.offsetWidth; // Trigger reflow
            triggerBtn.classList.add('animate-pulse');
        }
    }

    let totalItems = 0;
    let totalAmount = 0;

    cart.forEach((item, index) => {
        let subtotal = item.price * item.qty;
        totalItems += item.qty;
        totalAmount += subtotal;

        const itemDiv = document.createElement('div');
        itemDiv.className = 'cart-item-row fade-in';
        itemDiv.innerHTML = `
            <div class="cart-item-info">
                <div class="text-white fw-bold text-truncate" title="${item.name}">${item.name}</div>
                <div class="extra-small text-muted">${new Intl.NumberFormat('fr-FR').format(item.price)} FCFA</div>
            </div>
            <div class="cart-item-qty">
                <div class="input-group input-group-sm">
                    <button class="btn btn-outline-secondary border-0 px-2" onclick="updateQty(${index}, ${item.qty - 1})">-</button>
                    <input type="number" class="form-control bg-transparent text-white border-0 text-center p-0" 
                           value="${item.qty}" readonly>
                    <button class="btn btn-outline-secondary border-0 px-2" onclick="updateQty(${index}, ${item.qty + 1})">+</button>
                </div>
            </div>
            <div class="text-end" style="min-width: 80px;">
                <div class="text-white fw-bold small">${new Intl.NumberFormat('fr-FR').format(subtotal)}</div>
                <button class="btn btn-link text-danger p-0 extra-small text-decoration-none" onclick="removeItem(${index})">
                    <i class="fa-solid fa-trash-can me-1"></i>Oter
                </button>
            </div>
        `;
        cartContainer.appendChild(itemDiv);
    });

    document.getElementById('totalItems').innerText = totalItems;
    document.getElementById('totalAmount').innerText = new Intl.NumberFormat('fr-FR').format(totalAmount) + ' FCFA';

    // Highlight Checkout Button
    const checkoutBtn = document.querySelector('button[onclick="processSale()"]');
    if (checkoutBtn) {
        if (totalItems > 0) {
            checkoutBtn.classList.add('btn-checkout-active');
        } else {
            checkoutBtn.classList.remove('btn-checkout-active');
        }
    }

    // Update Top Cart Badge
    const topBadge = document.getElementById('topCartCount');
    if (topBadge) {
        topBadge.innerText = totalItems;
    }

    // Update Mobile Checkout Bar
    const mobileBar = document.getElementById('mobileCheckoutBar');
    if (mobileBar) {
        if (totalItems > 0) {
            mobileBar.style.display = 'flex';
            document.getElementById('mobileCartTotal').innerText = document.getElementById('totalAmount').innerText;
            document.getElementById('mobileCartCount').innerText = totalItems;
        } else {
            mobileBar.style.display = 'none';
        }
    }
}

// Update Quantity
function updateQty(index, newQty) {
    newQty = parseInt(newQty);
    if (newQty <= 0) {
        removeItem(index);
        return;
    }
    if (newQty > cart[index].maxStock) {
        showAlert('Limite Atteinte', "Quantité limitée au stock disponible.", 'warning');
        cart[index].qty = cart[index].maxStock;
    } else {
        cart[index].qty = newQty;
    }
    renderCart();
}

// Remove Item
function removeItem(index) {
    cart.splice(index, 1);
    renderCart();
}

// Search Filter
document.getElementById('searchProduct').addEventListener('input', function (e) {
    const term = e.target.value.toLowerCase();
    const items = document.querySelectorAll('.product-item');

    items.forEach(item => {
        const name = item.getAttribute('data-name');
        if (name.includes(term)) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });
});

// Process Sale
function processSale() {
    if (cart.length === 0) {
        showAlert('Panier Vide', "Veuillez ajouter des produits avant de valider.", 'info');
        return;
    }

    const clientId = document.getElementById('clientSelect').value;

    // Prepare Data
    const data = {
        client_id: clientId,
        items: cart,
        csrf_token: csrfToken // Send token in body as robust fallback
    };

    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

    if (!csrfToken) {
        // Self-healing: Token missing likely due to stale cache. Force reload.
        Swal.fire({
            title: 'Mise à jour requise',
            text: 'Une mise à jour de sécurité est nécessaire. La page va se recharger.',
            icon: 'info',
            timer: 2000,
            showConfirmButton: false
        }).then(() => {
            window.location.reload(true);
        });
        return;
    }

    // Use Custom Confirmation
    showConfirmation("Confirmer la vente de " + document.getElementById('totalAmount').innerText + " ?", () => {
        // Send to Backend
        fetch('../../backend/actions/process_sale.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(data),
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Vente Validée !',
                        text: 'La transaction a été enregistrée avec succès.',
                        icon: 'success',
                        background: '#1e293b',
                        color: '#fff',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        cart = [];
                        renderCart();
                        window.location.href = `invoice.php?id=${data.sale_id}`;
                    });
                } else {
                    showAlert('Erreur', "La vente n'a pas pu aboutir : " + data.message, 'error');
                }
            })
            .catch((error) => {
                console.error('Error:', error);
                showAlert('Erreur Système', "Impossible de communiquer avec le serveur.", 'error');
            });
    });
}
