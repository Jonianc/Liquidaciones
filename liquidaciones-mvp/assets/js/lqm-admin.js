(function(){
    const table = document.getElementById('lqm-noimp-table');
    const addBtn = document.getElementById('lqm-add-noimp');

    const form = document.getElementById('post');

    if (table && addBtn) {
        const tbody = table.querySelector('tbody');

        if (tbody) {
            addBtn.addEventListener('click', function(){
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><input type="text" name="lqm_noimp_nombre[]" value=""></td>
                    <td><input type="number" name="lqm_noimp_monto[]" min="0" step="1" value=""></td>
                    <td><button type="button" class="button lqm-del" aria-label="Quitar item no imponible">Quitar</button></td>
                `;
                tbody.appendChild(tr);
            });
        }
    }

    document.addEventListener('click', function(e){
        if (e.target && e.target.classList.contains('lqm-del')) {
            e.preventDefault();
            const tr = e.target.closest('tr');
            if (tr) tr.remove();
        }
    });

    if (!form) return;

    const field = {
        periodo: document.getElementById('lqm_periodo'),
        nombre: document.getElementById('lqm_nombre'),
        rut: document.getElementById('lqm_rut'),
        diasTrab: document.getElementById('lqm_dias_trab'),
        diasLic: document.getElementById('lqm_dias_lic'),
        diasInas: document.getElementById('lqm_dias_inas'),
        sueldoBase: document.getElementById('lqm_sueldo_base')
    };

    const errors = {
        periodo: document.getElementById('lqm_periodo_error'),
        nombre: document.getElementById('lqm_nombre_error'),
        rut: document.getElementById('lqm_rut_error'),
        diasTrab: document.getElementById('lqm_dias_trab_error'),
        dias: document.getElementById('lqm_dias_error'),
        sueldoBase: document.getElementById('lqm_sueldo_base_error')
    };

    const setError = (input, errorNode, message) => {
        if (!errorNode) return;
        errorNode.textContent = message || '';
        if (input) {
            if (message) {
                input.classList.add('lqm-input-error');
                input.setAttribute('aria-invalid', 'true');
            } else {
                input.classList.remove('lqm-input-error');
                input.removeAttribute('aria-invalid');
            }
        }
    };

    const cleanRut = (value) => (value || '').replace(/[^0-9kK]/g, '').toUpperCase();

    const isValidRut = (rut) => {
        const normalized = cleanRut(rut);
        if (normalized.length < 2) return false;

        const body = normalized.slice(0, -1);
        const dv = normalized.slice(-1);

        if (!/^\d+$/.test(body)) return false;

        let sum = 0;
        let multiplier = 2;

        for (let i = body.length - 1; i >= 0; i--) {
            sum += parseInt(body.charAt(i), 10) * multiplier;
            multiplier = multiplier < 7 ? multiplier + 1 : 2;
        }

        const remainder = 11 - (sum % 11);
        let expected = '0';

        if (remainder === 11) expected = '0';
        else if (remainder === 10) expected = 'K';
        else expected = String(remainder);

        return dv === expected;
    };

    const asInt = (value) => {
        const parsed = parseInt(value || '0', 10);
        return Number.isFinite(parsed) ? parsed : 0;
    };

    const validate = () => {
        let valid = true;

        const periodo = (field.periodo?.value || '').trim();
        const nombre = (field.nombre?.value || '').trim();
        const rut = (field.rut?.value || '').trim();
        const diasTrab = asInt(field.diasTrab?.value);
        const diasLic = asInt(field.diasLic?.value);
        const diasInas = asInt(field.diasInas?.value);
        const sueldoBase = asInt(field.sueldoBase?.value);

        setError(field.periodo, errors.periodo, '');
        setError(field.nombre, errors.nombre, '');
        setError(field.rut, errors.rut, '');
        setError(field.diasTrab, errors.diasTrab, '');
        setError(field.diasLic, errors.dias, '');
        setError(field.sueldoBase, errors.sueldoBase, '');

        if (!periodo) {
            setError(field.periodo, errors.periodo, 'El período es obligatorio.');
            valid = false;
        }

        if (!nombre) {
            setError(field.nombre, errors.nombre, 'El nombre es obligatorio.');
            valid = false;
        }

        if (!rut || !isValidRut(rut)) {
            setError(field.rut, errors.rut, 'Ingresa un RUT válido (con dígito verificador).');
            valid = false;
        }

        const dayFields = [field.diasTrab, field.diasLic, field.diasInas];
        const invalidDay = dayFields.some((input) => {
            const value = asInt(input?.value);
            return value < 0 || value > 31;
        });

        if (invalidDay) {
            setError(field.diasTrab, errors.diasTrab, 'Los días deben estar entre 0 y 31.');
            valid = false;
        }

        if ((diasTrab + diasLic + diasInas) > 31) {
            setError(field.diasLic, errors.dias, 'La suma de días trabajados/licencia/inasistencias no puede superar 31.');
            valid = false;
        }

        if (sueldoBase < 0) {
            setError(field.sueldoBase, errors.sueldoBase, 'El sueldo base no puede ser negativo.');
            valid = false;
        }

        return valid;
    };

    const watchFields = [
        field.periodo,
        field.nombre,
        field.rut,
        field.diasTrab,
        field.diasLic,
        field.diasInas,
        field.sueldoBase
    ].filter(Boolean);

    watchFields.forEach((input) => {
        input.addEventListener('input', validate);
        input.addEventListener('blur', validate);
    });

    form.addEventListener('submit', function(e){
        if (!validate()) {
            e.preventDefault();

            const firstErrorField = form.querySelector('.lqm-input-error');
            if (firstErrorField) {
                firstErrorField.focus();
            }
        }
    });
})();
