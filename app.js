const post = (data) =>
  fetch('api.php', { method: 'POST', body: new URLSearchParams(data) })
    .then(r => r.json())
    .then(j => { if (!j.ok) throw new Error(j.error || 'save failed'); return j; });

const flash = (el, ok = true) => {
  el.classList.add(ok ? 'saved' : 'failed');
  setTimeout(() => el.classList.remove('saved', 'failed'), 900);
};


// Mirror of note_html() in config.php. The server formats a note on page load;
// this formats it again after an inline edit, so a saved note looks identical
// to a reloaded one instead of collapsing back to a single blob.
const esc = (t) => t.replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
const noteHtml = (raw) => {
  raw = (raw || '').trim();
  if (!raw) return '<i>add a note</i>';
  raw = raw.replace(/([.;:!?]) +(?=[A-Z][A-Z0-9 /&-]{2,}:)/g, '$1 | ');
  return raw.split(/\s*\|\s*|\r?\n+/).filter(Boolean).map(part =>
    '<span class="np">' +
    esc(part.trim()).replace(/^([A-Z][A-Z0-9 /&-]{2,}:)/, '<b class="lbl">$1</b>') +
    '</span>').join('');
};

const rowId = (el) => el.closest('tr').dataset.id;
const stampRow = (el, date) => {
  const cell = el.closest('tr').querySelector('.upd');
  if (cell && date) cell.textContent = date;
};

// status dropdown
document.querySelectorAll('select.status').forEach(sel => {
  sel.addEventListener('change', () => {
    post({ action: 'update', id: rowId(sel), field: 'status', value: sel.value })
      .then(j => {
        flash(sel);
        stampRow(sel, j.last_update);
        const tr = sel.closest('tr');
        tr.className = 'st-' + sel.value.split(' ')[0].toLowerCase();
      })
      .catch(err => { flash(sel, false); alert(err.message); });
  });
});

// interviewed checkbox
document.querySelectorAll('input.iv').forEach(cb => {
  cb.addEventListener('change', () => {
    post({ action: 'update', id: rowId(cb), field: 'interviewed', value: cb.checked ? '1' : '0' })
      .then(j => { flash(cb.parentElement); stampRow(cb, j.last_update); })
      .catch(err => { cb.checked = !cb.checked; alert(err.message); });
  });
});

// click-to-edit text cells
document.querySelectorAll('span.ed').forEach(span => {
  span.addEventListener('click', () => {
    if (span.dataset.editing) return;
    span.dataset.editing = '1';
    const field = span.dataset.field;
    const isNote = span.dataset.field === 'notes';
    // notes keep their source in data-raw -- textContent is the FORMATTED text,
    // and saving that back would silently drop every " | " entry separator
    const original = isNote ? (span.dataset.raw || '')
                            : (span.querySelector('i') ? '' : span.textContent.trim());
    const multiline = field === 'notes';
    const input = document.createElement(multiline ? 'textarea' : 'input');
    if (!multiline) input.type = field === 'applied' ? 'date' : 'text';
    input.value = original;
    input.className = 'inline';
    span.replaceWith(input);
    input.focus();
    if (multiline) {
      input.selectionStart = input.selectionEnd = input.value.length;
      const grow = () => { input.style.height = 'auto'; input.style.height = (input.scrollHeight + 2) + 'px'; };
      input.addEventListener('input', grow);
      grow();
    }

    const finish = (save) => {
      if (input.dataset.done) return;
      input.dataset.done = '1';
      const value = input.value.trim();
      const restore = (text) => {
        if (isNote) { span.dataset.raw = text; span.innerHTML = noteHtml(text); }
        else { span.innerHTML = text ? esc(text) : '<i>—</i>'; }
        delete span.dataset.editing;
        input.replaceWith(span);
      };
      if (!save || value === original) { restore(original); return; }
      post({ action: 'update', id: rowId(input), field, value })
        .then(j => { restore(value); flash(span); stampRow(span, j.last_update); })
        .catch(err => { restore(original); alert(err.message); });
    };

    input.addEventListener('blur', () => finish(true));
    input.addEventListener('keydown', ev => {
      // in the notes box Enter makes a new line; cmd/ctrl+Enter saves
      if (ev.key === 'Enter' && multiline && !(ev.metaKey || ev.ctrlKey)) return;
      if (ev.key === 'Enter') { ev.preventDefault(); finish(true); }
      if (ev.key === 'Escape') { finish(false); }
    });
  });
});

// delete
document.querySelectorAll('button.del').forEach(btn => {
  btn.addEventListener('click', () => {
    const tr = btn.closest('tr');
    const name = tr.querySelector('[data-field="company"]').textContent.trim();
    if (!confirm('Delete ' + name + '?')) return;
    post({ action: 'delete', id: tr.dataset.id })
      .then(() => tr.remove())
      .catch(err => alert(err.message));
  });
});

// add
const addForm = document.getElementById('addForm');
if (addForm) {
  addForm.addEventListener('submit', ev => {
    ev.preventDefault();
    const data = Object.fromEntries(new FormData(addForm));
    data.action = 'create';
    post(data).then(() => location.reload()).catch(err => alert(err.message));
  });
}
