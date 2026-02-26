(function(){
    const table = document.getElementById('lqm-noimp-table');
    const addBtn = document.getElementById('lqm-add-noimp');

    if (!table || !addBtn) return;

    const tbody = table.querySelector('tbody');
    if (!tbody) return;

    addBtn.addEventListener('click', function(){
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input type="text" name="lqm_noimp_nombre[]" value=""></td>
            <td><input type="number" name="lqm_noimp_monto[]" value=""></td>
            <td><button type="button" class="button lqm-del">X</button></td>
        `;
        tbody.appendChild(tr);
    });

    document.addEventListener('click', function(e){
        if (e.target && e.target.classList.contains('lqm-del')) {
            e.preventDefault();
            const tr = e.target.closest('tr');
            if (tr) tr.remove();
        }
    });
})();
