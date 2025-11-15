// script.js - JOAQUÍN REPUESTOS - 100% FUNCIONAL
let repuestos = [];
let indiceVender = null;
let modalVender = null;
let modalCliente = null;
let clienteSeleccionado = null;

document.addEventListener('DOMContentLoaded', function () {
    // === ELEMENTOS DOM ===
    const form = document.getElementById('form-repuesto');
    const tablaBody = document.getElementById('tabla-repuestos');
    const buscarInput = document.getElementById('buscar');
    const exportarBtn = document.getElementById('exportar');
    const verVentasBtn = document.getElementById('btn-ver-ventas');
    const sonido = document.getElementById('sonido-venta');

    // === VERIFICAR BOOTSTRAP ===
    if (typeof bootstrap === 'undefined') {
        console.error('Bootstrap no está cargado');
        alert('Error: Bootstrap no está cargado. Revisa el script en <head>');
        return;
    }

    // === INICIALIZAR MODALES ===
    const modalVenderEl = document.getElementById('modalVender');
    const modalClienteEl = document.getElementById('modalCliente');

    if (modalVenderEl) {
        modalVender = new bootstrap.Modal(modalVenderEl);
    } else {
        console.error('Modal Vender no encontrado (ID: modalVender)');
    }

    if (modalClienteEl) {
        modalCliente = new bootstrap.Modal(modalClienteEl);
    } else {
        console.error('Modal Cliente no encontrado (ID: modalCliente)');
    }

    // === CARGAR REPUESTOS ===
    async function cargarRepuestos() {
        try {
            const res = await fetch('api.php?action=listar_repuestos');
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            repuestos = await res.json();
            renderizar();
        } catch (err) {
            console.error('Error al cargar:', err);
            tablaBody.innerHTML = '<tr><td colspan="5" class="text-danger text-center">Error al cargar repuestos</td></tr>';
        }
    }

    // === RENDERIZAR TABLA ===
    function renderizar() {
        const term = (buscarInput?.value || '').toLowerCase();
        const filtrados = repuestos.filter(r =>
            (r.nombre || '').toLowerCase().includes(term) ||
            (r.descripcion || '').toLowerCase().includes(term)
        );

        tablaBody.innerHTML = '';
        let valorInventario = 0;

        if (filtrados.length === 0) {
            tablaBody.innerHTML = '<tr><td colspan="5" class="text-muted text-center">No hay repuestos</td></tr>';
            return;
        }

        filtrados.forEach(r => {
            const precio = parseFloat(r.precio) || 0;
            const stock = parseInt(r.stock) || 0;
            const subtotal = precio * stock;
            valorInventario += subtotal;

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><strong>${r.nombre || ''}</strong></td>
                <td>${r.descripcion || ''}</td>
                <td>$${precio.toLocaleString('es-CO')}</td>
                <td><span class="badge ${stock > 0 ? 'bg-success' : 'bg-danger'}">${stock}</span></td>
                <td>
                    <button class="btn btn-sm btn-warning me-1 btn-editar" data-id="${r.id}">Editar</button>
                    <button class="btn btn-sm btn-danger me-1 btn-eliminar" data-id="${r.id}">Eliminar</button>
                    <button class="btn btn-sm btn-success btn-vender"
                            data-id="${r.id}"
                            data-nombre="${r.nombre || ''}"
                            data-precio="${precio}"
                            data-stock="${stock}"
                            data-descripcion="${r.descripcion || ''}"
                            ${stock <= 0 ? 'disabled' : ''}>Vender</button>
                </td>
            `;
            tablaBody.appendChild(tr);
        });

        document.getElementById('total-inventario').textContent = `$${valorInventario.toLocaleString('es-CO')}`;
    }

    // === AGREGAR REPUESTO ===
    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const data = {
            nombre: document.getElementById('nombre').value.trim(),
            descripcion: document.getElementById('descripcion').value.trim(),
            precio: parseFloat(document.getElementById('precio').value),
            stock: parseInt(document.getElementById('stock').value)
        };

        if (!data.nombre || !data.descripcion || data.precio <= 0 || data.stock < 0) {
            alert('Completa todos los campos correctamente.');
            return;
        }

        try {
            const res = await fetch('api.php?action=agregar_repuesto', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await res.json();
            if (result.success) {
                form.reset();
                cargarRepuestos();
                tocarSonido();
            } else {
                alert('Error: ' + (result.error || 'Desconocido'));
            }
        } catch {
            alert('Error de conexión');
        }
    });

    // === EVENTOS EN TABLA ===
    tablaBody.addEventListener('click', async (e) => {
        const btn = e.target;

        // EDITAR
        if (btn.classList.contains('btn-editar')) {
            const id = btn.dataset.id;
            const rep = repuestos.find(r => r.id == id);
            if (!rep) return;

            document.getElementById('edit-nombre').value = rep.nombre;
            document.getElementById('edit-descripcion').value = rep.descripcion || '';
            document.getElementById('edit-precio').value = rep.precio;
            document.getElementById('edit-stock').value = rep.stock;

            const modal = new bootstrap.Modal(document.getElementById('modalEditar'));
            modal.show();

            document.getElementById('guardar-edicion').onclick = async () => {
                const data = {
                    id: id,
                    nombre: document.getElementById('edit-nombre').value.trim(),
                    descripcion: document.getElementById('edit-descripcion').value.trim(),
                    precio: parseFloat(document.getElementById('edit-precio').value),
                    stock: parseInt(document.getElementById('edit-stock').value)
                };
                await fetch('api.php?action=editar_repuesto', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                modal.hide();
                cargarRepuestos();
            };
        }

        // ELIMINAR
        if (btn.classList.contains('btn-eliminar')) {
            if (confirm('¿Eliminar este repuesto?')) {
                await fetch(`api.php?action=eliminar_repuesto&id=${btn.dataset.id}`, { method: 'POST' });
                cargarRepuestos();
            }
        }

        // === VENDER → ABRIR MODAL CLIENTE ===
        if (btn.classList.contains('btn-vender')) {
            console.log('Vender clickeado'); // DEBUG

            const rep = {
                id: btn.dataset.id,
                nombre: btn.dataset.nombre || '',
                precio: parseFloat(btn.dataset.precio) || 0,
                stock: parseInt(btn.dataset.stock) || 0,
                descripcion: btn.dataset.descripcion || ''
            };

            if (!rep.id || rep.stock <= 0) {
                alert('Repuesto no disponible');
                return;
            }

            indiceVender = rep;
            clienteSeleccionado = null;

            // Actualizar modal venta
            document.getElementById('modalVenderNombre').textContent = rep.nombre;
            document.getElementById('modalVenderStock').textContent = rep.stock;
            const inputCant = document.getElementById('cantidadVender');
            inputCant.value = 1;
            inputCant.max = rep.stock;

            // Reiniciar cliente
            document.getElementById('infoClienteVenta').textContent = 'Sin cliente';

            // ABRIR MODAL CLIENTE
            if (modalCliente) {
                modalCliente.show();
            } else {
                alert('Error: Modal de cliente no disponible');
            }
        }
    });

    // === CLIENTES ===
    document.getElementById('buscarCliente')?.addEventListener('input', async (e) => {
        const q = e.target.value.trim();
        const lista = document.getElementById('listaClientes');
        if (!lista) return;
        lista.style.display = 'none';
        if (q.length < 2) return;

        try {
            const res = await fetch(`api.php?action=buscar_clientes&q=${encodeURIComponent(q)}`);
            const clientes = await res.json();
            lista.innerHTML = '';
            clientes.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = `${c.nombre} ${c.telefono ? '- ' + c.telefono : ''}`;
                lista.appendChild(opt);
            });
            if (clientes.length > 0) lista.style.display = 'block';
        } catch (err) {
            console.error('Error buscando clientes:', err);
        }
    });

    document.getElementById('listaClientes')?.addEventListener('change', (e) => {
        const id = e.target.value;
        const texto = e.target.selectedOptions[0].textContent;
        document.getElementById('infoCliente').textContent = `Seleccionado: ${texto}`;
        clienteSeleccionado = { id: parseInt(id) };
    });

    document.getElementById('nuevoClienteBtn')?.addEventListener('click', async () => {
        const nombre = prompt('Nombre del cliente:');
        if (!nombre?.trim()) return;
        const telefono = prompt('Teléfono (opcional):') || '';
        const email = prompt('Email (opcional):') || '';

        try {
            const res = await fetch('api.php?action=agregar_cliente', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ nombre: nombre.trim(), telefono, email })
            });
            const result = await res.json();
            if (result.success) {
                clienteSeleccionado = { id: result.id };
                document.getElementById('infoCliente').textContent = `Nuevo: ${nombre}`;
                alert('Cliente agregado');
            }
        } catch {
            alert('Error al agregar cliente');
        }
    });

    // CONFIRMAR CLIENTE
    document.getElementById('confirmarCliente')?.addEventListener('click', () => {
        if (modalCliente) modalCliente.hide();
        const info = clienteSeleccionado
            ? `Cliente: ${document.getElementById('infoCliente').textContent.split(': ')[1] || 'Cliente'}`
            : 'Sin cliente';
        document.getElementById('infoClienteVenta').textContent = info;
        if (modalVender) modalVender.show();
    });

    // CAMBIAR CLIENTE
    document.getElementById('cambiarCliente')?.addEventListener('click', () => {
        if (modalVender) modalVender.hide();
        clienteSeleccionado = null;
        document.getElementById('infoClienteVenta').textContent = 'Sin cliente';
        if (modalCliente) modalCliente.show();
    });

    // === CONFIRMAR VENTA ===
    document.getElementById('confirmarVenta')?.addEventListener('click', async () => {
        if (!indiceVender) return;

        const cantidad = parseInt(document.getElementById('cantidadVender').value);
        if (cantidad < 1 || cantidad > indiceVender.stock) {
            alert('Cantidad inválida');
            return;
        }

        const venta = {
            repuesto_id: indiceVender.id,
            nombre_producto: indiceVender.nombre,
            descripcion: indiceVender.descripcion,
            cantidad: cantidad,
            precio_unitario: indiceVender.precio,
            cliente_id: clienteSeleccionado?.id || null
        };

        try {
            const res = await fetch('api.php?action=guardar_venta', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(venta)
            });
            const result = await res.json();
            if (result.success) {
                if (modalVender) modalVender.hide();
                tocarSonido();
                alert(`¡Venta registrada! Total: $${(cantidad * indiceVender.precio).toLocaleString('es-CO')}`);
                cargarRepuestos();
            } else {
                alert('Error: ' + (result.error || 'Desconocido'));
            }
        } catch {
            alert('Error de conexión');
        }
    });

    // === VER VENTAS ===
    if (verVentasBtn) {
        verVentasBtn.addEventListener('click', () => {
            console.log('Abriendo ventas.html');
            const win = window.open('ventas.html', '_blank');
            if (!win) {
                alert('No se pudo abrir ventas.html. Permite ventanas emergentes.');
            }
        });
    }

    // === BÚSQUEDA Y EXPORTAR ===
    buscarInput?.addEventListener('input', renderizar);
    exportarBtn?.addEventListener('click', () => window.location = 'api.php?action=exportar_inventario');

    // === SONIDO ===
    function tocarSonido() {
        if (sonido) {
            sonido.currentTime = 0;
            sonido.play().catch(() => {});
        }
    }

    // === INICIAR ===
    cargarRepuestos();
});