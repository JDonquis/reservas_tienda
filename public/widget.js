(function() {
    const currentScript = document.currentScript;
    const apiKey = currentScript.getAttribute('data-api-key');
    const apiBaseUrl = currentScript.src.replace('/widget.js', '/api/v1/store');

    if (!apiKey) {
        console.error('Widget Error: Se requiere data-api-key en el script.');
        return;
    }

    // 1. Renderizar Catálogo de Productos
    async function renderShop(targetSelector) {
        const container = document.querySelector(targetSelector);
        if (!container) return;

        try {
            const response = await fetch(`${apiBaseUrl}/catalog`, {
                headers: { 'X-Store-Api-Key': apiKey }
            });

            if (!response.ok) throw new Error('Error al consultar el catálogo');

            const data = await response.json();
            let html = `<div class="reserva-plugin-shop"><h2>${data.store_name}</h2>`;

            data.categories.forEach(category => {
                if (category.products.length === 0) return;

                html += `<div class="reserva-categoria"><h3>${category.name}</h3><div class="reserva-grid" style="display:flex; flex-wrap:wrap; gap:15px;">`;

                category.products.forEach(prod => {
                    const hasOffer = prod.offer_price !== null;
                    html += `
                        <div class="reserva-card" style="border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; width: 220px; text-align:center;">
                            <img src="${prod.image_url}" alt="${prod.name}" style="width: 100%; height: 140px; object-fit: cover; border-radius: 6px;">
                            <h4 style="margin: 10px 0 5px;">${prod.name}</h4>
                            <p style="margin: 0;">
                                ${hasOffer ? `<span style="text-decoration: line-through; color: #a0aec0; font-size: 0.9em;">$${prod.price}</span> <strong style="color: #e53e3e;">$${prod.offer_price}</strong>` : `<strong>$${prod.price}</strong>`}
                            </p>
                        </div>
                    `;
                });

                html += `</div></div>`;
            });

            html += `</div>`;
            container.innerHTML = html;

        } catch (error) {
            console.error('Error renderizando tienda:', error);
            container.innerHTML = '<p>No se pudo cargar el catálogo.</p>';
        }
    }

    // 2. Renderizar Formulario de Citas con Horarios Dinámicos
    function renderBookingForm(targetSelector) {
        const container = document.querySelector(targetSelector);
        if (!container) return;

        let selectedSlot = null;

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
        const slotsContainer = document.getElementById('reserva_slots_container');
        const submitBtn = document.getElementById('reserva_btn_submit');
        const form = document.getElementById('reserva-booking-form');

        // Escuchar cambio de fecha para consultar horarios disponibles
        dateInput.addEventListener('change', async (e) => {
            const chosenDate = e.target.value;
            selectedSlot = null;
            submitBtn.disabled = true;
            submitBtn.style.background = '#a0aec0';
            submitBtn.style.cursor = 'not-allowed';

            if (!chosenDate) return;

            slotsContainer.innerHTML = '<span style="font-size:0.85em; color:#3182ce;">Consultando disponibilidad...</span>';

            try {
                const response = await fetch(`${apiBaseUrl}/available-slots?date=${chosenDate}`, {
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
                        // Desmarcar otros botones
                        Array.from(slotsContainer.children).forEach(child => {
                            child.style.background = 'white';
                            child.style.color = '#3182ce';
                        });

                        // Marcar botón activo
                        btn.style.background = '#3182ce';
                        btn.style.color = 'white';
                        selectedSlot = slot;

                        // Habilitar botón de confirmación
                        submitBtn.disabled = false;
                        submitBtn.style.background = '#38a169';
                        submitBtn.style.cursor = 'pointer';
                    });

                    slotsContainer.appendChild(btn);
                });

            } catch (err) {
                console.error('Error al consultar slots:', err);
                slotsContainer.innerHTML = '<span style="font-size:0.85em; color:#e53e3e;">Error al cargar disponibilidad.</span>';
            }
        });

        // Enviar formulario
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!selectedSlot) return;

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
                    statusDiv.style.color = '#38a169';
                    statusDiv.innerHTML = '¡Reserva confirmada con éxito!';
                    form.reset();
                    slotsContainer.innerHTML = '<span style="font-size:0.85em; color:#718096;">Selecciona una fecha para ver los horarios.</span>';
                    submitBtn.disabled = true;
                    submitBtn.style.background = '#a0aec0';
                    submitBtn.style.cursor = 'not-allowed';
                    selectedSlot = null;
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
