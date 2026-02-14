/**
 * POS Logic for DENIS FBI STORE
 */

let cart = [];

// Add to Cart
function addToCart(id, name, price, maxStock, image = null) {
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
        cart.push({ id, name, price, qty: 1, maxStock, image, warrantyExtension: false });
    }

    renderCart();
    // Auto-scroll to bottom of cart
    const cartContainer = document.getElementById('cartTableBody');
    if (cartContainer) {
        cartContainer.scrollTop = cartContainer.scrollHeight;
    }
}

// Render Cart UI
function renderCart() {
    console.log("Rendering cart...", cart);
    const cartContainer = document.getElementById('cartTableBody');
    if (!cartContainer) return;

    cartContainer.innerHTML = '';

    const topCartBar = document.getElementById('topCartBar');

    if (cart.length === 0) {
        cartContainer.innerHTML = `
            <div class="d-flex flex-column align-items-center justify-content-center py-5 opacity-20">
                <i class="fa-solid fa-cart-shopping fa-3x mb-3"></i>
                <div class="fw-medium text-white">Votre panier est vide</div>
                <div class="small text-muted">Recherchez des articles ci-dessus</div>
            </div>`;

        if (document.getElementById('subtotalDisplay')) document.getElementById('subtotalDisplay').innerText = '0 FCFA';
        if (document.getElementById('totalAmount')) document.getElementById('totalAmount').innerText = '0 FCFA';
        if (document.getElementById('warrantyCostDisplay')) document.getElementById('warrantyCostDisplay').innerText = '0 FCFA';
        return;
    }

    let subtotalValue = 0;
    let totalItems = 0;
    let warrantyExtraTotal = 0;

    cart.forEach((item, index) => {
        const itemSubtotal = item.price * item.qty;
        subtotalValue += itemSubtotal;
        totalItems += item.qty;

        // Warranty logic (default 12 mois free for demo if not specified)
        const warrantyCost = (item.warrantyExtension) ? 5000 * item.qty : 0; // Example: 5000 FCFA for extension
        warrantyExtraTotal += warrantyCost;

        const itemCard = document.createElement('div');
        itemCard.className = 'cart-card animate__animated animate__fadeInUp';
        itemCard.style.animationDelay = `${index * 0.05}s`;

        itemCard.innerHTML = `
            <div class="cart-card-main">
                <div class="cart-card-img">
                    ${item.image ? `<img src="../${item.image}">` : `<i class="fa-solid fa-cube fa-2x"></i>`}
                </div>
                <div class="cart-card-info">
                    <div class="cart-card-title">${item.name}</div>
                    <div class="cart-card-prices">
                        <span class="price-old">${new Intl.NumberFormat('fr-FR').format(item.price * 1.2)} FCFA</span>
                        <span class="price-new">${new Intl.NumberFormat('fr-FR').format(item.price)} FCFA</span>
                    </div>
                    <div class="cart-card-subtotal mt-1 text-uppercase extra-small fw-bold">
                        S-Total: ${new Intl.NumberFormat('fr-FR').format(itemSubtotal)} FCFA
                    </div>
                </div>
                <div class="cart-card-actions">
                    <div class="btn-remove-item mb-2" onclick="removeItem(${index})">
                        <i class="fa-solid fa-circle-xmark fa-lg"></i>
                    </div>
                    <div class="input-group input-group-sm bg-black bg-opacity-30 rounded-pill overflow-hidden border border-white border-opacity-10" style="width: 90px;">
                        <button class="btn btn-sm text-white px-2 border-0" onclick="updateQty(${index}, ${item.qty - 1})">-</button>
                        <input type="text" class="form-control form-control-sm bg-transparent border-0 text-white text-center p-0 fw-bold" value="${item.qty}" readonly>
                        <button class="btn btn-sm text-white px-2 border-0" onclick="updateQty(${index}, ${item.qty + 1})">+</button>
                    </div>
                </div>
            </div>
            
            <div class="cart-card-options">
                <div class="cart-option-item">
                    <div class="form-check p-0 m-0">
                        <input class="form-check-input ms-0 me-2" type="checkbox" id="warFree_${index}" checked disabled>
                        <label class="form-check-label text-muted extra-small" for="warFree_${index}">Garantie 3 mois constructeur</label>
                    </div>
                    <span class="text-success extra-small fw-bold">OFFERT</span>
                </div>
                <div class="cart-option-item">
                    <div class="form-check p-0 m-0">
                        <input class="form-check-input ms-0 me-2" type="checkbox" id="warPaid_${index}" ${item.warrantyExtension ? 'checked' : ''} onchange="toggleItemWarranty(${index}, this.checked)">
                        <label class="form-check-label text-white extra-small" for="warPaid_${index}">Protection d'écran & Extension 12 mois</label>
                    </div>
                    <span class="text-info extra-small fw-bold">+ 5 000 FCFA</span>
                </div>
            </div>
        `;
        cartContainer.appendChild(itemCard);
    });

    // Handle sums
    const subtotalDisplay = document.getElementById('subtotalDisplay');
    const totalAmountDisplay = document.getElementById('totalAmount');
    const warrantyCostDisplay = document.getElementById('warrantyCostDisplay');
    const resellerRow = document.getElementById('resellerDiscountRow');
    const resellerDiscountDisplay = document.getElementById('resellerDiscountDisplay');

    const isResellerMode = document.getElementById('resellerModeToggle') ? document.getElementById('resellerModeToggle').checked : false;

    if (subtotalDisplay) subtotalDisplay.innerText = new Intl.NumberFormat('fr-FR').format(subtotalValue) + ' FCFA';
    if (warrantyCostDisplay) warrantyCostDisplay.innerText = new Intl.NumberFormat('fr-FR').format(warrantyExtraTotal) + ' FCFA';

    let finalTotal = subtotalValue + warrantyExtraTotal;

    if (isResellerMode) {
        // In reseller mode, if a final price is set, use it. Otherwise, use shop total.
        const resellerFinalInput = document.getElementById('resellerFinalPrice');
        const resellerFinal = parseFloat(resellerFinalInput?.value) || finalTotal;

        const discount = finalTotal - resellerFinal;
        if (resellerRow) resellerRow.style.setProperty('display', 'flex', 'important');
        if (resellerDiscountDisplay) resellerDiscountDisplay.innerText = '-' + new Intl.NumberFormat('fr-FR').format(discount) + ' FCFA';

        finalTotal = resellerFinal;
    } else {
        if (resellerRow) resellerRow.style.setProperty('display', 'none', 'important');
    }

    if (totalAmountDisplay) totalAmountDisplay.innerText = new Intl.NumberFormat('fr-FR').format(finalTotal) + ' FCFA';

    // Update simple count
    if (document.getElementById('totalItems')) document.getElementById('totalItems').innerText = totalItems;
}

// Custom Warranty Toggle
function toggleItemWarranty(index, isChecked) {
    if (cart[index]) {
        cart[index].warrantyExtension = isChecked;
        renderCart();
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

// Reseller Mode Logic (Margin Model)
function toggleResellerMode() {
    const isChecked = document.getElementById('resellerModeToggle').checked;
    const container = document.getElementById('resellerSelectContainer');
    console.log("Reseller Mode Toggled:", isChecked, "Container found:", !!container);
    if (!container) {
        console.error("Reseller Container NOT FOUND in DOM!");
    }
    container.style.display = isChecked ? 'block' : 'none';

    if (isChecked) {
        updateResellerValues();
    } else {
        document.getElementById('resellerSelect').value = "";
        const finalPriceInput = document.getElementById('resellerFinalPrice');
        if (finalPriceInput) finalPriceInput.value = "";
    }
}

function updateResellerValues() {
    let shopTotal = 0;
    cart.forEach(item => shopTotal += (item.price * item.qty));

    // Update Shop Price Display
    const shopPriceEl = document.getElementById('shopPriceDisplay');
    if (shopPriceEl) {
        shopPriceEl.innerText = new Intl.NumberFormat('fr-FR').format(shopTotal) + ' FCFA';
    }

    calculateResellerMargin();
}

function calculateResellerMargin() {
    let shopTotal = 0;
    cart.forEach(item => shopTotal += (item.price * item.qty));

    const finalPriceInput = document.getElementById('resellerFinalPrice');
    const marginDisplay = document.getElementById('resellerMarginDisplay');

    if (!finalPriceInput || !marginDisplay) return;

    let finalPrice = parseFloat(finalPriceInput.value);

    // If input is empty or 0, default to 0 margin (Final = Shop)
    if (isNaN(finalPrice) || finalPrice === 0) {
        marginDisplay.innerText = "0 FCFA";
        marginDisplay.className = "fw-bold text-muted";
        return;
    }

    let margin = finalPrice - shopTotal;

    marginDisplay.innerText = new Intl.NumberFormat('fr-FR').format(margin) + ' FCFA';

    if (margin < 0) {
        marginDisplay.className = "fw-bold text-danger"; // Selling below cost?
        marginDisplay.innerText += " (Perte)";
    } else {
        marginDisplay.className = "fw-bold text-success";
    }
}

// Hook into renderCart to update values when cart changes
const originalRenderCart = renderCart;
renderCart = function () {
    originalRenderCart();
    if (document.getElementById('resellerModeToggle') && document.getElementById('resellerModeToggle').checked) {
        updateResellerValues();
    }
}

// ==== Packs Integration ====
function addSelectedPackToCart() {
    if (!window.packsData || !Array.isArray(packsData) || packsData.length === 0) {
        showAlert('Aucun Pack', "Aucun pack n'est configuré pour le moment.", 'info');
        return;
    }
    const select = document.getElementById('packQuickSelect');
    if (!select || !select.value) {
        showAlert('Pack manquant', "Veuillez sélectionner un pack.", 'warning');
        return;
    }
    const packId = parseInt(select.value, 10);
    const pack = packsData.find(p => parseInt(p.id_pack, 10) === packId);
    if (!pack || !Array.isArray(pack.components) || pack.components.length === 0) {
        showAlert('Pack invalide', "Ce pack ne contient aucun produit.", 'error');
        return;
    }

    // Vérifier le stock disponible pour tous les composants (1 pack)
    for (const comp of pack.components) {
        const needed = comp.quantite || 1;
        const existingQty = cart
            .filter(it => it.id === comp.id_produit)
            .reduce((sum, it) => sum + it.qty, 0);
        const stock = comp.stock_actuel || 0;
        if (existingQty + needed > stock) {
            showAlert('Stock insuffisant', `Stock insuffisant pour ${comp.designation} pour ajouter ce pack.`, 'warning');
            return;
        }
    }

    // Calculer la répartition du prix pack sur les composants
    let fullPrice = 0;
    pack.components.forEach(comp => {
        fullPrice += (comp.prix_unitaire || 0) * (comp.quantite || 1);
    });
    const packPrice = pack.prix_pack || fullPrice;
    const ratio = fullPrice > 0 ? (packPrice / fullPrice) : 1;

    // Ajouter les composants dans le panier (1 unité de pack)
    pack.components.forEach((comp, idx) => {
        const basePrice = comp.prix_unitaire || 0;
        let unitPrice = basePrice * ratio;

        // Arrondir au FCFA, ajuster le dernier pour compenser l'arrondi global
        unitPrice = Math.round(unitPrice);

        const qty = comp.quantite || 1;
        const maxStock = comp.stock_actuel || 0;

        // Fusionner avec un item existant du même produit si prix identique
        const existing = cart.find(it => it.id === comp.id_produit && it.price === unitPrice);
        if (existing) {
            existing.qty += qty;
        } else {
            cart.push({
                id: comp.id_produit,
                name: comp.designation + ' (Pack ' + pack.nom_pack + ')',
                price: unitPrice,
                qty: qty,
                maxStock: maxStock
            });
        }
    });

    renderCart();
    showAlert('Pack ajouté', `Le pack "${pack.nom_pack}" a été ajouté au panier.`, 'success');
}

// ==== POS FINALIZATION FLOW [v2.2] ====

function openCheckoutSummary() {
    if (cart.length === 0) {
        showAlert('Panier Vide', "Veuillez ajouter des produits avant de valider.", 'info');
        return;
    }

    console.log("Opening Checkout Summary...");
    const modalEl = document.getElementById('checkoutSummaryModal');
    if (!modalEl) {
        console.error("CRITICAL: Modal element 'checkoutSummaryModal' not found in DOM!");
        showAlert('Erreur UI', "Le modal de résumé est introuvable.", 'error');
        return;
    }

    // Populate Items List
    const list = document.getElementById('summaryItemsList');
    if (list) {
        list.innerHTML = '';
        let total = 0;

        cart.forEach(item => {
            const subtotal = item.price * item.qty;
            total += subtotal;

            const div = document.createElement('div');
            div.className = 'd-flex justify-content-between align-items-center mb-2 p-2 rounded';
            div.style.background = 'rgba(255,255,255,0.03)';
            div.innerHTML = `
                <div class="small">
                    <div class="fw-bold">${item.name}</div>
                    <div class="text-muted extra-small">${item.qty} x ${new Intl.NumberFormat('fr-FR').format(item.price)} FCFA</div>
                </div>
                <div class="fw-bold small">${new Intl.NumberFormat('fr-FR').format(subtotal)}</div>
            `;
            list.appendChild(div);
        });

        const isResellerMode = document.getElementById('resellerModeToggle') ? document.getElementById('resellerModeToggle').checked : false;
        if (isResellerMode) {
            const finalPriceInput = document.getElementById('resellerFinalPrice');
            total = parseFloat(finalPriceInput.value) || total;
        }

        const summaryTotalEl = document.getElementById('summaryTotal');
        if (summaryTotalEl) {
            summaryTotalEl.innerText = new Intl.NumberFormat('fr-FR').format(total) + ' FCFA';
        }
    } else {
        console.warn("Element 'summaryItemsList' not found.");
    }

    // Reset inputs
    if (document.getElementById('amtReceived')) document.getElementById('amtReceived').value = '';
    if (document.getElementById('changeAmount')) document.getElementById('changeAmount').innerText = '0 FCFA';
    if (document.getElementById('paymentMethod')) document.getElementById('paymentMethod').value = 'cash';

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}

// Change Calculation Logic
document.getElementById('amtReceived')?.addEventListener('input', function () {
    let total = 0;
    const isResellerMode = document.getElementById('resellerModeToggle') ? document.getElementById('resellerModeToggle').checked : false;

    if (isResellerMode) {
        total = parseFloat(document.getElementById('resellerFinalPrice').value) || 0;
    } else {
        cart.forEach(item => total += (item.price * item.qty));
    }

    const received = parseFloat(this.value) || 0;
    const change = received - total;

    const changeEl = document.getElementById('changeAmount');
    if (changeEl) {
        changeEl.innerText = new Intl.NumberFormat('fr-FR').format(Math.max(0, change)) + ' FCFA';
        changeEl.className = change >= 0 ? 'h4 fw-bold text-success mt-2 mb-0' : 'h4 fw-bold text-danger mt-2 mb-0';
    }
});

function finalConfirmSale() {
    try {
        console.log("finalConfirmSale() started");

        // 1. Logic for Total
        const isResellerMode = document.getElementById('resellerModeToggle') ? document.getElementById('resellerModeToggle').checked : false;
        let total = 0;

        if (isResellerMode) {
            total = parseFloat(document.getElementById('resellerFinalPrice')?.value) || 0;
        } else {
            if (!cart || cart.length === 0) {
                console.error("Cart is empty");
                alert("Erreur: Le panier est vide.");
                return;
            }
            cart.forEach(item => total += (item.price * item.qty));
        }

        // 2. Logic for Payment Received
        const receivedInput = document.getElementById('amtReceived');
        const receivedValueString = receivedInput ? receivedInput.value.trim() : '';
        const received = parseFloat(receivedValueString) || 0;
        const paymentMethodEl = document.getElementById('paymentMethod');
        const paymentMethod = paymentMethodEl ? paymentMethodEl.value : 'cash';

        console.log("Validation details:", { paymentMethod, received, total, receivedValueString });

        if (paymentMethod === 'cash') {
            if (receivedValueString === '' || received === 0) {
                const msg = "Veuillez saisir le montant reçu du client pour finaliser l'encaissement.";
                if (typeof Swal !== 'undefined') {
                    showAlert('Action Requise', msg, 'warning');
                } else {
                    alert(msg);
                }
                return;
            }
            if (received < total) {
                const msg = `Le montant reçu (${new Intl.NumberFormat('fr-FR').format(received)} FCFA) est insuffisant pour couvrir le total (${new Intl.NumberFormat('fr-FR').format(total)} FCFA).`;
                if (typeof Swal !== 'undefined') {
                    showAlert('Montant Insuffisant', msg, 'warning');
                } else {
                    alert(msg);
                }
                return;
            }
        }

        // 3. Logic for Data Preparation
        console.log("Preparing data for submission...");
        const clientSelect = document.getElementById('clientSelect');
        const clientId = clientSelect ? clientSelect.value : null;
        const resellerSelect = document.getElementById('resellerSelect');
        const resellerId = isResellerMode ? resellerSelect?.value : null;
        const warrantyEl = document.getElementById('saleWarranty');
        const warranty = warrantyEl ? warrantyEl.value : 'Sans garantie';
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        const data = {
            client_id: clientId,
            reseller_id: resellerId,
            final_price: isResellerMode ? total : null,
            garantie: warranty,
            type_paiement: paymentMethod,
            items: cart,
            csrf_token: csrfToken
        };

        // 4. Modal Transition
        console.log("Transitioning UI...");
        const modalEl = document.getElementById('checkoutSummaryModal');
        if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            try {
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.hide();
            } catch (modalErr) {
                console.warn("Bootstrap Modal hide failed, continuing...", modalErr);
            }
        }

        // 5. Submit to Backend
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Traitement...',
                text: 'Enregistrement de la vente en cours',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); },
                background: '#1e293b',
                color: '#fff'
            });
        } else {
            console.log("Swal missing, showing alert fallback");
        }

        console.log("Submitting fetch post...");
        fetch('../../backend/actions/process_sale.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data),
        })
            .then(response => response.json())
            .then(res => {
                console.log("Backend response:", res);
                if (typeof Swal !== 'undefined') Swal.close();

                if (res.success) {
                    // Show Success Modal
                    const successModalEl = document.getElementById('saleSuccessModal');
                    if (successModalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        const successModal = new bootstrap.Modal(successModalEl);

                        const printBtn = document.getElementById('btnPrintInvoice');
                        if (printBtn) printBtn.onclick = () => {
                            window.open(`invoice.php?id=${res.sale_id}`, '_blank');
                        };

                        const shareBtn = document.getElementById('btnShareWhatsApp');
                        if (shareBtn) shareBtn.onclick = () => {
                            const msg = `Vente Denix FBI Store #${res.sale_id}. Total: ${total} FCFA. MERCI !`;
                            window.open(`https://wa.me/?text=${encodeURIComponent(msg)}`, '_blank');
                        };

                        successModal.show();
                        cart = [];
                        renderCart();
                    } else {
                        alert("Vente enregistrée avec succès! ID: " + res.sale_id);
                        window.location.reload();
                    }
                } else {
                    const errMsg = res.message || "Une erreur est survenue lors de l'enregistrement.";
                    if (typeof Swal !== 'undefined') {
                        showAlert('Erreur', errMsg, 'error');
                    } else {
                        alert("Erreur: " + errMsg);
                    }
                }
            })
            .catch(err => {
                console.error("Fetch error:", err);
                if (typeof Swal !== 'undefined') Swal.close();
                alert("Erreur Système: " + err.message);
            });

    } catch (globalErr) {
        console.error("CRITICAL ERROR in finalConfirmSale:", globalErr);
        alert("Une erreur JavaScript critique est survenue: " + globalErr.message);
    }
}

// Initial Log to verify script is loaded
console.log("pos.js loaded properly [v2.6]");
