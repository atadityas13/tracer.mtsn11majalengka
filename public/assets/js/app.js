document.addEventListener('DOMContentLoaded', () => {
  const swalTheme = {
    confirmButtonColor: '#10b981',
    cancelButtonColor: '#64748b',
  };

  const flashNode = document.getElementById('swal-flash');
  if (flashNode && window.Swal) {
    const rawType = (flashNode.getAttribute('data-type') || 'success').toLowerCase();
    const allowedTypes = ['success', 'error', 'warning', 'info'];
    const flashType = allowedTypes.includes(rawType) ? rawType : 'success';
    const flashMessage = flashNode.getAttribute('data-message') || '';
    const flashTitleMap = {
      success: 'Berhasil',
      error: 'Gagal',
      warning: 'Perhatian',
      info: 'Informasi',
    };

    if (flashMessage) {
      Swal.fire({
        ...swalTheme,
        icon: flashType,
        title: flashTitleMap[flashType],
        text: flashMessage,
        confirmButtonText: 'OK',
      });
    }
  }

  document.querySelectorAll('input, select, textarea').forEach((el) => {
    if (el.type === 'hidden') return;

    if (el.type === 'checkbox' || el.type === 'radio') {
      el.classList.add('form-check-input');
      return;
    }

    el.classList.add('form-control');
  });

  document.querySelectorAll('button').forEach((button) => {
    const hasBootstrapVariant = Array.from(button.classList).some((className) => className.startsWith('btn-'));
    button.classList.add('btn');

    if (hasBootstrapVariant) {
      return;
    }

    if (button.classList.contains('danger')) {
      button.classList.add('btn-danger');
      return;
    }

    if (button.classList.contains('secondary')) {
      button.classList.add('btn-outline-secondary');
      return;
    }

    button.classList.add('btn-success');
  });

  document.querySelectorAll('.table-wrap').forEach((wrap) => {
    wrap.classList.add('table-responsive');
  });

  document.querySelectorAll('table').forEach((table) => {
    table.classList.add('table', 'table-hover', 'align-middle', 'mb-0');
  });

  const openSwalConfirm = (title, text) => {
    if (!window.Swal) {
      return Promise.resolve(window.confirm(text || title || 'Yakin?'));
    }

    return Swal.fire({
      ...swalTheme,
      icon: 'question',
      title: title || 'Konfirmasi',
      text: text || 'Yakin?',
      showCancelButton: true,
      confirmButtonText: 'Ya, lanjutkan',
      cancelButtonText: 'Batal',
      reverseButtons: true,
    }).then((result) => result.isConfirmed);
  };

  document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      if (form.dataset.confirmed === '1') {
        return;
      }

      event.preventDefault();
      const text = form.getAttribute('data-confirm') || 'Yakin?';
      const title = form.getAttribute('data-confirm-title') || 'Konfirmasi';
      const confirmed = await openSwalConfirm(title, text);
      if (confirmed) {
        form.dataset.confirmed = '1';
        form.submit();
      }
    });
  });

  document.querySelectorAll('a[data-confirm], button[data-confirm]').forEach((el) => {
    el.addEventListener('click', async (event) => {
      event.preventDefault();
      const text = el.getAttribute('data-confirm') || 'Yakin?';
      const title = el.getAttribute('data-confirm-title') || 'Konfirmasi';
      const confirmed = await openSwalConfirm(title, text);
      if (!confirmed) {
        return;
      }

      if (el.tagName.toLowerCase() === 'a') {
        window.location.href = el.getAttribute('href') || '#';
      }
    });
  });
});
