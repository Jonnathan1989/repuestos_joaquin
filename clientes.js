// clientes.js - Gestión de Clientes
const API = 'api.php';

// Cargar clientes al inicio
document.addEventListener('DOMContentLoaded', () => {
    console.log('✅ clientes.js cargado correctamente'); // Debug
    cargarClientes();
    
    // Event Listeners
    const formCliente = document.getElementById('form-cliente');
    if (formCliente) {
        formCliente.addEventListener('submit', agregarCliente);
    }
    
    const buscarInput = document.getElementById('buscar-cliente');
    if (buscarInput) {
        buscarInput.addEventListener('input', buscarCliente);
    }
    
    const exportarBtn = document.getElementById('exportar-clientes');
    if (exportarBtn) {
        exportarBtn.addEventListener('click', exportarClientes);
    }
    
    const guardarBtn = document.getElementById('guardar-edicion-cliente');
    if (guardarBtn) {
        guardarBtn.addEventListener('click', guardarEdicionCliente);
    }
});

// Cargar todos los clientes
async function cargarClientes() {
    try {
        const res = await fetch(`${API}?action=listar_clientes`);
        const clientes = await res.json();
        console.log('Clientes cargados:', clientes);
        mostrarClientes(clientes);
    } catch (error) {
        console.error('Error al cargar clientes:', error);
        document.getElementById('tabla-clientes').innerHTML = 
            '<tr><td colspan="7" class="text-center text-danger">Error al cargar clientes</td></tr>';
    }
}

// Mostrar clientes en la tabla
function mostrarClientes(clientes) {
    const tbody = document.getElementById('tabla-clientes');
    
    if (!clientes || clientes.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center">No hay clientes registrados</td></tr>';
        return;
    }
    
    tbody.innerHTML = clientes.map(c => `
        <tr>
            <td>${c.id}</td>
            <td><strong>${c.nombre}</strong></td>
            <td>${c.telefono || '-'}</td>
            <td>${c.email || '-'}</td>
            <td><span class="badge bg-info">${c.total_compras || 0}</span></td>
            <td><strong class="text-success">$${Number(c.total_gastado || 0).toLocaleString()}</strong></td>
            <td>
                <button class="btn btn-warning btn-sm" onclick="editarCliente(${c.id})">✏️</button>
                <button class="btn btn-info btn-sm" onclick="verHistorial(${c.id}, '${c.nombre.replace(/'/g, "\\'")}')">📋</button>
                <button class="btn btn-danger btn-sm" onclick="eliminarCliente(${c.id}, '${c.nombre.replace(/'/g, "\\'")}')">🗑️</button>
            </td>
        </tr>
    `).join('');
}

// Agregar nuevo cliente
async function agregarCliente(e) {
    e.preventDefault();
    
    const nombre = document.getElementById('cliente-nombre').value.trim();
    const telefono = document.getElementById('cliente-telefono').value.trim();
    const email = document.getElementById('cliente-email').value.trim();
    
    if (!nombre) {
        alert('⚠️ El nombre es obligatorio');
        return;
    }
    
    const data = {
        nombre: nombre,
        telefono: telefono,
        email: email
    };
    
    console.log('Enviando cliente:', data);
    
    try {
        const res = await fetch(`${API}?action=agregar_cliente`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await res.json();
        console.log('Respuesta:', result);
        
        if (result.success) {
            alert('✅ Cliente agregado correctamente');
            document.getElementById('form-cliente').reset();
            cargarClientes();
        } else {
            alert('❌ Error al agregar cliente');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('❌ Error: ' + error.message);
    }
}

// Buscar cliente
function buscarCliente() {
    const busqueda = document.getElementById('buscar-cliente').value.toLowerCase();
    const filas = document.querySelectorAll('#tabla-clientes tr');
    
    filas.forEach(fila => {
        const texto = fila.textContent.toLowerCase();
        fila.style.display = texto.includes(busqueda) ? '' : 'none';
    });
}

// Editar cliente
async function editarCliente(id) {
    try {
        const res = await fetch(`${API}?action=obtener_cliente&id=${id}`);
        const cliente = await res.json();
        
        document.getElementById('edit-cliente-id').value = cliente.id;
        document.getElementById('edit-cliente-nombre').value = cliente.nombre;
        document.getElementById('edit-cliente-telefono').value = cliente.telefono || '';
        document.getElementById('edit-cliente-email').value = cliente.email || '';
        
        const modal = new bootstrap.Modal(document.getElementById('modalEditarCliente'));
        modal.show();
    } catch (error) {
        console.error('Error:', error);
        alert('❌ Error al cargar datos del cliente');
    }
}

// Guardar edición
async function guardarEdicionCliente() {
    const data = {
        id: document.getElementById('edit-cliente-id').value,
        nombre: document.getElementById('edit-cliente-nombre').value,
        telefono: document.getElementById('edit-cliente-telefono').value,
        email: document.getElementById('edit-cliente-email').value
    };
    
    try {
        const res = await fetch(`${API}?action=editar_cliente`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await res.json();
        
        if (result.success) {
            alert('✅ Cliente actualizado');
            const modalElement = document.getElementById('modalEditarCliente');
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) modal.hide();
            cargarClientes();
        } else {
            alert('❌ Error al actualizar cliente');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('❌ Error al actualizar cliente');
    }
}

// Ver historial de compras
async function verHistorial(clienteId, nombreCliente) {
    try {
        const res = await fetch(`${API}?action=historial_cliente&id=${clienteId}`);
        const compras = await res.json();
        
        document.getElementById('nombre-cliente-historial').textContent = `Cliente: ${nombreCliente}`;
        
        const tbody = document.getElementById('tabla-historial-cliente');
        
        if (!compras || compras.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center">No hay compras registradas</td></tr>';
        } else {
            tbody.innerHTML = compras.map(c => `
                <tr>
                    <td>${c.fecha_venta}</td>
                    <td>${c.nombre_producto}</td>
                    <td>${c.cantidad}</td>
                    <td class="text-success fw-bold">$${Number(c.total).toLocaleString()}</td>
                </tr>
            `).join('');
        }
        
        const modal = new bootstrap.Modal(document.getElementById('modalHistorialCliente'));
        modal.show();
    } catch (error) {
        console.error('Error:', error);
        alert('❌ Error al cargar historial');
    }
}

// Eliminar cliente
async function eliminarCliente(id, nombre) {
    if (!confirm(`¿Eliminar al cliente "${nombre}"?\n\n⚠️ Esto NO eliminará su historial de compras.`)) return;
    
    try {
        const res = await fetch(`${API}?action=eliminar_cliente&id=${id}`, {
            method: 'POST'
        });
        
        const result = await res.json();
        
        if (result.success) {
            alert('✅ Cliente eliminado');
            cargarClientes();
        } else {
            alert('❌ Error al eliminar cliente');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('❌ Error al eliminar cliente');
    }
}

// Exportar clientes a Excel
function exportarClientes() {
    window.location.href = `${API}?action=exportar_clientes`;
}