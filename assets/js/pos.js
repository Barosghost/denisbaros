/**
 * POS Logic for DENIS FBI STORE
 */

let cart = [];

// Add to Cart
function addToCart(id, name, price, maxStock) {
    if (maxStock <= 0) {
        alert("Stock épuisé pour ce produit !");
        return;
    }

    const existingItem = cart.find(item => item.id === id);

    if (existingItem) {
        if (existingItem.qty < maxStock) {
            existingItem.qty++;
        } else {
            alert("Stock insuffisant !");
            return;
        }
    } else {
        cart.push({ id, name, price, qty: 1, maxStock });
    }

    renderCart();
}

// Render Cart HTML
function renderCart() {
    const tbody = document.getElementById('cartTableBody');
    tbody.innerHTML = '';

    if (cart.length === 0) {
        tbody.innerHTML = `
            <tr class="text-center">
                <td colspan="4" class="py-5">
                    <div class="d-flex flex-column align-items-center justify-content-center text-muted">
                        <i class="fa-solid fa-cart-arrow-down fa-3x mb-3 opacity-25"></i>
                        <p class="mb-0 fw-light">Votre panier est vide</p>
                    </div>
                </td>
            </tr>`;
        document.getElementById('totalItems').innerText = 0;
        document.getElementById('totalAmount').innerText = '0 FCFA';
        return;
    }

    let totalItems = 0;
    let totalAmount = 0;

    cart.forEach((item, index) => {
        let subtotal = item.price * item.qty;
        totalItems += item.qty;
        totalAmount += subtotal;

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <div class="fw-bold text-truncate" style="max-width: 120px;">${item.name}</div>
                <div class="small text-muted">${item.price} FCFA</div>
            </td>
            <td class="text-center">
                <input type="number" min="1" max="${item.maxStock}" class="form-control form-control-sm bg-dark text-white border-secondary text-center p-1" 
                       value="${item.qty}" onchange="updateQty(${index}, this.value)">
            </td>
            <td class="text-end fw-bold">${subtotal}</td>
            <td class="text-end">
                <button class="btn btn-sm text-danger p-0" onclick="removeItem(${index})"><i class="fa-solid fa-times"></i></button>
            </td>
        `;
        tbody.appendChild(tr);
    });

    document.getElementById('totalItems').innerText = totalItems;
    document.getElementById('totalAmount').innerText = new Intl.NumberFormat('fr-FR').format(totalAmount) + ' FCFA';
}

// Update Quantity
function updateQty(index, newQty) {
    newQty = parseInt(newQty);
    if (newQty <= 0) {
        removeItem(index);
        return;
    }
    if (newQty > cart[index].maxStock) {
        alert("Quantité supérieure au stock disponible !");
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
        alert("Le panier est vide !");
        return;
    }

    const clientId = document.getElementById('clientSelect').value;

    // Prepare Data
    const data = {
        client_id: clientId,
        items: cart
    };

    // Use Custom Confirmation
    showConfirmation("Confirmer la vente de " + document.getElementById('totalAmount').innerText + " ?", () => {
        // Send to Backend
        fetch('../actions/process_sale.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data),
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Success Message could be a notification too, but alert is fine for now or we can use a custom modal
                    alert("Vente enregistrée avec succès !");
                    cart = [];
                    renderCart();
                    // Redirect to Invoice
                    window.location.href = `invoice.php?id=${data.sale_id}`;
                } else {
                    alert("Erreur lors de la vente : " + data.message);
                }
            })
            .catch((error) => {
                console.error('Error:', error);
                alert("Erreur de communication avec le serveur.");
            });
    });
}
