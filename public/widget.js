(function() {
    const currentScript = document.currentScript;
    const apiKey = currentScript.getAttribute('data-api-key');
    const apiBaseUrl = currentScript.src.replace('/widget.js', '/api/v1/store');

    if (!apiKey) {
        console.error('Widget Error: Se requiere data-api-key en el script.');
        return;
    }

    // 1. Renderizar Catálogo de Productos con Carrito y Checkout
    async function renderShop(targetSelector) {
        const container = document.querySelector(targetSelector);
        if (!container) return;

        let cart = {};
        let categories = [];

        function allProducts() {
            return categories.flatMap(c => c.products);
        }

        function effectivePrice(prod) {
            return prod.offer_price != null ? parseFloat(prod.offer_price) : parseFloat(prod.price);
        }

        function cartTotal() {
            return Object.keys(cart).reduce((total, id) => {
                const p = allProducts().find(x => x.id === Number(id));
                if (!p) return total;
                return total + effectivePrice(p) * cart[id];
            }, 0).toFixed(2);
        }

        function renderCart() {
            const el = document.getElementById('reserva-cart');
            if (!el) return;

            const ids = Object.keys(cart);
            if (!ids.length) {
                el.innerHTML = '<div style="border:1px solid #cbd5e0; padding:16px; border-radius:8px; margin-top:20px; font-size:0.9em; color:#718096;">El carrito está vacío.</div>';
                return;
            }

            let html = `<div style="border:1px solid #cbd5e0; padding:16px; border-radius:8px; margin-top:20px;"><h3 style="margin:0 0 12px;">Tu carrito</h3>`;
            ids.forEach(id => {
                const p = allProducts().find(x => x.id === Number(id));
                if (!p) return;
                html += `<div style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid #f1f5f9;">
                    <span>${p.name} × ${cart[id]}</span>
                    <span>$${(effectivePrice(p) * cart[id]).toFixed(2)}
                        <button type="button" data-remove="${p.id}" style="margin-left:8px; background:none; border:none; color:#e53e3e; cursor:pointer; font-size:1.1em;" title="Quitar">✕</button>
                    </span>
                </div>`;
            });
            html += `<div style="display:flex; justify-content:space-between; padding:10px 0; font-weight:bold;">Total: $${cartTotal()}</div></div>`;
            el.innerHTML = html;

            el.querySelectorAll('[data-remove]').forEach(btn => {
                btn.addEventListener('click', () => {
                    delete cart[Number(btn.dataset.remove)];
                    renderCart();
                    renderCheckout();
                });
            });
        }

        function renderCheckout() {
            const el = document.getElementById('reserva-checkout');
            if (!el) return;

            if (!Object.keys(cart).length) {
                el.innerHTML = '';
                return;
            }

            el.innerHTML = `
                <div style="border:1px solid #cbd5e0; padding:20px; border-radius:8px; margin-top:20px; max-width:420px;">
                    <h3 style="margin-top:0;">Finalizar pedido</h3>
                    <form id="reserva-order-form">
                        <div style="margin-bottom:10px;">
                            <label style="display:block; font-size:0.85em; font-weight:bold;">Nombre Completo:</label>
                            <input type="text" id="reserva_order_name" required style="width:100%; padding:8px; box-sizing:border-box; border:1px solid #ccc; border-radius:4px;">
                        </div>
                        <div style="margin-bottom:10px;">
                            <label style="display:block; font-size:0.85em; font-weight:bold;">Correo Electrónico:</label>
                            <input type="email" id="reserva_order_email" required style="width:100%; padding:8px; box-sizing:border-box; border:1px solid #ccc; border-radius:4px;">
                        </div>
                        <div style="margin-bottom:10px;">
                            <label style="display:block; font-size:0.85em; font-weight:bold;">Teléfono / WhatsApp:</label>
                            <input type="text" id="reserva_order_phone" required style="width:100%; padding:8px; box-sizing:border-box; border:1px solid #ccc; border-radius:4px;">
                        </div>
                        <div style="margin-bottom:10px;">
                            <label style="display:block; font-size:0.85em; font-weight:bold; margin-bottom:4px;">Método de entrega:</label>
                            <label style="font-size:0.9em;"><input type="radio" name="reserva_shipping" value="pickup" checked> Retiro en tienda</label><br>
                            <label style="font-size:0.9em;"><input type="radio" name="reserva_shipping" value="delivery"> Envío a domicilio</label>
                        </div>
                        <div id="reserva_address_wrap" style="display:none; margin-bottom:10px;">
                            <label style="display:block; font-size:0.85em; font-weight:bold;">Dirección de envío:</label>
                            <input type="text" id="reserva_order_address" style="width:100%; padding:8px; box-sizing:border-box; border:1px solid #ccc; border-radius:4px;">
                        </div>
                        <div style="margin-bottom:12px;">
                            <label style="display:block; font-size:0.85em; font-weight:bold;">Notas (opcional):</label>
                            <textarea id="reserva_order_notes" rows="2" style="width:100%; padding:8px; box-sizing:border-box; border:1px solid #ccc; border-radius:4px;"></textarea>
                        </div>
                        <button type="submit" style="width:100%; background:#38a169; color:white; padding:10px; border:none; border-radius:4px; font-weight:bold; cursor:pointer;">Realizar pedido</button>
                    </form>
                    <div id="reserva-order-status" style="margin-top:10px; text-align:center; font-size:0.9em;"></div>
                </div>
            `;

            const addressWrap = el.querySelector('#reserva_address_wrap');
            const addressInput = el.querySelector('#reserva_order_address');
            const statusEl = el.querySelector('#reserva-order-status');

            el.querySelectorAll('input[name="reserva_shipping"]').forEach(radio => {
                radio.addEventListener('change', () => {
                    addressWrap.style.display = radio.value === 'delivery' ? 'block' : 'none';
                    if (radio.value !== 'delivery') addressInput.value = '';
                });
            });

            el.querySelector('#reserva-order-form').addEventListener('submit', async (e) => {
                e.preventDefault();

                const shippingMethod = el.querySelector('input[name="reserva_shipping"]:checked').value;

                if (shippingMethod === 'delivery' && !addressInput.value.trim()) {
                    statusEl.style.color = '#e53e3e';
                    statusEl.innerHTML = 'Indica la dirección de envío.';
                    return;
                }

                statusEl.style.color = '#3182ce';
                statusEl.innerHTML = 'Procesando pedido...';

                const payload = {
                    customer_name: el.querySelector('#reserva_order_name').value,
                    customer_email: el.querySelector('#reserva_order_email').value,
                    customer_phone: el.querySelector('#reserva_order_phone').value,
                    shipping_method: shippingMethod,
                    shipping_address: shippingMethod === 'delivery' ? addressInput.value : null,
                    shipping_notes: el.querySelector('#reserva_order_notes').value || null,
                    items: Object.keys(cart).map(id => ({ product_id: Number(id), quantity: cart[id] })),
                };

                try {
                    const response = await fetch(`${apiBaseUrl}/orders`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Store-Api-Key': apiKey
                        },
                        body: JSON.stringify(payload)
                    });

                    const result = await response.json();

                    if (response.ok) {
                        if (result.checkout_url) {
                            window.location.href = result.checkout_url;
                            return;
                        }

                        statusEl.style.color = '#38a169';
                        statusEl.innerHTML = '¡Pedido realizado con éxito!';
                        cart = {};
                        renderCart();
                        renderCheckout();
                    } else {
                        statusEl.style.color = '#e53e3e';
                        statusEl.innerHTML = result.error || 'Error al procesar el pedido.';
                    }
                } catch (err) {
                    console.error('Error de conexión:', err);
                    statusEl.style.color = '#e53e3e';
                    statusEl.innerHTML = 'Error de conexión con el servidor.';
                }
            });
        }

        try {
            const response = await fetch(`${apiBaseUrl}/catalog`, {
                headers: { 'X-Store-Api-Key': apiKey }
            });

            if (!response.ok) throw new Error('Error al consultar el catálogo');

            const data = await response.json();
            categories = data.categories;

            container.innerHTML = `
                <div class="reserva-plugin-shop">
                    <h2>${data.store_name}</h2>
                    <div id="reserva-shop-body"></div>
                    <div id="reserva-cart"></div>
                    <div id="reserva-checkout"></div>
                </div>
            `;

            let html = '';
            categories.forEach(category => {
                if (category.products.length === 0) return;

                html += `<div class="reserva-categoria"><h3>${category.name}</h3><div class="reserva-grid" style="display:flex; flex-wrap:wrap; gap:15px;">`;

                category.products.forEach(prod => {
                    const hasOffer = prod.offer_price !== null;
                    html += `
                        <div class="reserva-card" style="border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; width: 220px; text-align:center; display:flex; flex-direction:column; gap:8px;">
                            <img src="${prod.image_url}" alt="${prod.name}" style="width: 100%; height: 140px; object-fit: cover; border-radius: 6px;">
                            <h4 style="margin:0;">${prod.name}</h4>
                            <p style="margin:0;">
                                ${hasOffer ? `<span style="text-decoration: line-through; color: #a0aec0; font-size: 0.9em;">$${prod.price}</span> <strong style="color: #e53e3e;">$${prod.offer_price}</strong>` : `<strong>$${prod.price}</strong>`}
                            </p>
                            <div style="display:flex; align-items:center; gap:8px; justify-content:center;">
                                <input type="number" id="reserva-qty-${prod.id}" min="1" max="99" value="1" style="width:60px; padding:6px; border:1px solid #ccc; border-radius:4px; text-align:center;">
                                <button type="button" data-add="${prod.id}" style="background:#3182ce; color:white; padding:8px 12px; border:none; border-radius:4px; cursor:pointer; font-weight:bold;">Agregar</button>
                            </div>
                        </div>
                    `;
                });

                html += `</div></div>`;
            });

            document.getElementById('reserva-shop-body').innerHTML = html;

            container.querySelectorAll('[data-add]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = Number(btn.dataset.add);
                    const input = document.getElementById(`reserva-qty-${id}`);
                    const qty = Math.max(1, Math.min(99, parseInt(input.value || '1', 10)));
                    cart[id] = qty;
                    renderCart();
                    renderCheckout();
                });
            });

        } catch (error) {
            console.error('Error renderizando tienda:', error);
            container.innerHTML = '<p>No se pudo cargar el catálogo.</p>';
        }
    }

    // 2. Renderizar Formulario de Citas con Horarios Dinámicos y Servicios
    function renderBookingForm(targetSelector) {
        const container = document.querySelector(targetSelector);
        if (!container) return;

        let selectedSlot = null;
        let hasServices = false;

        container.innerHTML = `
            <div class="reserva-form-container" style="border:1px solid #cbd5e0; padding:20px; border-radius:8px; max-width:420px; font-family:sans-serif;">
                <h3 style="margin-top:0;">Agendar Cita</h3>
                <form id="reserva-booking-form">
                    <div style="margin-bottom:10px;">
                        <label style="display:block; font-size:0.85em; font-weight:bold;">Nombre Completo:</label>
                        <input type="text" id="reserva_name" required style="width:100%; padding:8px; box-sizing:border-box; border:1px solid #ccc; border-radius:4px;">
                    </div>
                    <div style="margin-bottom:10px;">
                        <label style="display:block; font-size:0.85em; font-weight:bold;">Correo Electrónico:</label>
                        <input type="email" id="reserva_email" required style="width:100%; padding:8px; box-sizing:border-box; border:1px solid #ccc; border-radius:4px;">
                    </div>
                    <div style="margin-bottom:10px;">
                        <label style="display:block; font-size:0.85em; font-weight:bold;">Teléfono / WhatsApp:</label>
                        <input type="text" id="reserva_phone" required style="width:100%; padding:8px; box-sizing:border-box; border:1px solid #ccc; border-radius:4px;">
                    </div>

                    <div id="reserva_service_wrapper" style="margin-bottom:10px;">
                        <label style="display:block; font-size:0.85em; font-weight:bold;">Servicio:</label>
                        <select id="reserva_service" style="width:100%; padding:8px; box-sizing:border-box; border:1px solid #ccc; border-radius:4px;">
                            <option value="">Selecciona un servicio</option>
                        </select>
                    </div>

                    <div style="margin-bottom:10px;">
                        <label style="display:block; font-size:0.85em; font-weight:bold;">Fecha de la Cita:</label>
                        <input type="date" id="reserva_date" required style="width:100%; padding:8px; box-sizing:border-box; border:1px solid #ccc; border-radius:4px;">
                    </div>

                    <div style="margin-bottom:15px;">
                        <label style="display:block; font-size:0.85em; font-weight:bold; margin-bottom:5px;">Horarios Disponibles:</label>
                        <div id="reserva_slots_container" style="display:flex; flex-wrap:wrap; gap:8px; min-height:40px;">
                            <span style="font-size:0.85em; color:#718096;">Selecciona una fecha para ver los horarios.</span>
                        </div>
                    </div>

                    <button type="submit" id="reserva_btn_submit" disabled style="width:100%; background:#a0aec0; color:white; padding:10px; border:none; border-radius:4px; font-weight:bold; cursor:not-allowed;">Confirmar Reserva</button>
                </form>
                <div id="reserva-status-message" style="margin-top:10px; text-align:center; font-size:0.9em;"></div>
            </div>
        `;

        const dateInput = document.getElementById('reserva_date');
        const serviceSelect = document.getElementById('reserva_service');
        const serviceWrapper = document.getElementById('reserva_service_wrapper');
        const slotsContainer = document.getElementById('reserva_slots_container');
        const submitBtn = document.getElementById('reserva_btn_submit');
        const form = document.getElementById('reserva-booking-form');

        function canSubmit() {
            return !!selectedSlot && (!hasServices || !!serviceSelect.value);
        }

        function updateSubmit() {
            if (canSubmit()) {
                submitBtn.disabled = false;
                submitBtn.style.background = '#38a169';
                submitBtn.style.cursor = 'pointer';
            } else {
                submitBtn.disabled = true;
                submitBtn.style.background = '#a0aec0';
                submitBtn.style.cursor = 'not-allowed';
            }
        }

        async function loadSlots() {
            const chosenDate = dateInput.value;
            selectedSlot = null;
            updateSubmit();

            if (!chosenDate) {
                slotsContainer.innerHTML = '<span style="font-size:0.85em; color:#718096;">Selecciona una fecha para ver los horarios.</span>';
                return;
            }

            slotsContainer.innerHTML = '<span style="font-size:0.85em; color:#3182ce;">Consultando disponibilidad...</span>';

            let url = `${apiBaseUrl}/available-slots?date=${chosenDate}`;
            if (serviceSelect.value) {
                url += `&service_id=${serviceSelect.value}`;
            }

            try {
                const response = await fetch(url, {
                    headers: { 'X-Store-Api-Key': apiKey }
                });

                const data = await response.json();

                if (!data.slots || data.slots.length === 0) {
                    slotsContainer.innerHTML = '<span style="font-size:0.85em; color:#e53e3e;">No hay horarios disponibles para este día.</span>';
                    return;
                }

                slotsContainer.innerHTML = '';
                data.slots.forEach(slot => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.innerText = slot.time_label;
                    btn.style.cssText = 'padding:6px 12px; border:1px solid #3182ce; background:white; color:#3182ce; border-radius:4px; cursor:pointer; font-weight:bold; font-size:0.85em;';

                    btn.addEventListener('click', () => {
                        Array.from(slotsContainer.children).forEach(child => {
                            child.style.background = 'white';
                            child.style.color = '#3182ce';
                        });

                        btn.style.background = '#3182ce';
                        btn.style.color = 'white';
                        selectedSlot = slot;
                        updateSubmit();
                    });

                    slotsContainer.appendChild(btn);
                });

            } catch (err) {
                console.error('Error al consultar slots:', err);
                slotsContainer.innerHTML = '<span style="font-size:0.85em; color:#e53e3e;">Error al cargar disponibilidad.</span>';
            }
        }

        // Cargar servicios disponibles (si los hay)
        (async () => {
            try {
                const response = await fetch(`${apiBaseUrl}/services`, {
                    headers: { 'X-Store-Api-Key': apiKey }
                });

                if (!response.ok) return;

                const data = await response.json();
                const services = data.services || [];
                hasServices = services.length > 0;

                if (hasServices) {
                    services.forEach(s => {
                        const opt = document.createElement('option');
                        opt.value = s.id;
                        opt.innerText = s.duration_minutes ? `${s.name} (${s.duration_minutes} min)` : s.name;
                        serviceSelect.appendChild(opt);
                    });
                } else {
                    serviceWrapper.style.display = 'none';
                }
            } catch (err) {
                serviceWrapper.style.display = 'none';
                hasServices = false;
            }
        })();

        dateInput.addEventListener('change', loadSlots);
        serviceSelect.addEventListener('change', loadSlots);

        // Enviar formulario
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!canSubmit()) return;

            const statusDiv = document.getElementById('reserva-status-message');
            statusDiv.style.color = '#3182ce';
            statusDiv.innerHTML = 'Procesando reserva...';

            const payload = {
                customer_name: document.getElementById('reserva_name').value,
                customer_email: document.getElementById('reserva_email').value,
                customer_phone: document.getElementById('reserva_phone').value,
                start_time: selectedSlot.start,
                end_time: selectedSlot.end,
            };

            if (serviceSelect.value) {
                payload.service_id = serviceSelect.value;
            }

            try {
                const response = await fetch(`${apiBaseUrl}/appointments`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Store-Api-Key': apiKey
                    },
                    body: JSON.stringify(payload)
                });

                const result = await response.json();

                if (response.ok) {
                    if (result.checkout_url) {
                        window.location.href = result.checkout_url;
                        return;
                    }

                    statusDiv.style.color = '#38a169';
                    statusDiv.innerHTML = '¡Reserva confirmada con éxito!';
                    form.reset();
                    slotsContainer.innerHTML = '<span style="font-size:0.85em; color:#718096;">Selecciona una fecha para ver los horarios.</span>';
                    selectedSlot = null;
                    updateSubmit();
                } else {
                    statusDiv.style.color = '#e53e3e';
                    statusDiv.innerHTML = result.error || 'Error al procesar la reserva.';
                }
            } catch (err) {
                console.error('Error de conexión:', err);
                statusDiv.style.color = '#e53e3e';
                statusDiv.innerHTML = 'Error de conexión con el servidor.';
            }
        });
    }

    // Exportar métodos globales
    window.ReservaPlugin = {
        initShop: function(selector) {
            this._run(() => renderShop(selector));
        },
        initBooking: function(selector) {
            this._run(() => renderBookingForm(selector));
        },
        _run: function(fn) {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', fn);
            } else {
                fn();
            }
        }
    };
})();
