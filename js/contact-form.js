/* Contact form — client-side validation for the required fields, then an
   AJAX submit to the PHP backend (php/contact.php), which re-validates,
   stores the submission as JSON and sends the two emails.
   If JavaScript is unavailable the form still works as a normal POST
   to the same endpoint (progressive enhancement, no JS dependency). */
(function () {
  const form = document.getElementById('contactForm');
  const status = document.getElementById('formStatus');
  const submitBtn = document.getElementById('contactSubmit');
  if (!form || !status || !submitBtn) return;

  const rules = {
    firstName: { required: true, label: 'First name' },
    lastName: { required: true, label: 'Last name' },
    email: { required: true, label: 'Email', pattern: /^[^\s@]+@[^\s@]+\.[^\s@]+$/ },
    phone: { required: false, label: 'Phone number', pattern: /^[0-9+()\-.\s]{6,20}$/ },
    comments: { required: true, label: 'Comments' }
  };

  function fieldEl(name) { return form.elements[name]; }
  function errorEl(name) { return document.getElementById(`err-${name}`); }

  function validateField(name) {
    const el = fieldEl(name);
    const rule = rules[name];
    const err = errorEl(name);
    const value = el.value.trim();
    el.dataset.touched = 'true';

    let message = '';
    if (rule.required && !value) {
      message = `${rule.label} is required.`;
    } else if (value && rule.pattern && !rule.pattern.test(value)) {
      message = `Please enter a valid ${rule.label.toLowerCase()}.`;
    }

    err.textContent = message;
    el.setAttribute('aria-invalid', message ? 'true' : 'false');
    return !message;
  }

  function validateAll() {
    return Object.keys(rules)
      .map(validateField)
      .every(Boolean);
  }

  Object.keys(rules).forEach((name) => {
    const el = fieldEl(name);
    el.addEventListener('blur', () => validateField(name));
    el.addEventListener('input', () => {
      if (el.dataset.touched === 'true') validateField(name);
    });
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    // Honeypot: if this hidden field got filled in, silently drop the submission.
    if (form.elements.website && form.elements.website.value) return;

    if (!validateAll()) {
      status.textContent = 'Please fix the highlighted fields.';
      status.className = 'form-status error';
      form.querySelector('[aria-invalid="true"]')?.focus();
      return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Sending…';
    status.textContent = '';
    status.className = 'form-status';

    try {
      const res = await fetch(form.action, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: new FormData(form)
      });
      const data = await res.json().catch(() => null);

      if (res.ok && data && data.success) {
        status.textContent = data.message || 'Thanks! We’ll be in touch shortly.';
        status.className = 'form-status success';
        form.reset();
        Object.keys(rules).forEach((name) => {
          errorEl(name).textContent = '';
          fieldEl(name).removeAttribute('aria-invalid');
          fieldEl(name).dataset.touched = 'false';
        });
      } else {
        status.textContent = (data && data.message) || 'Something went wrong. Please try again.';
        status.className = 'form-status error';
      }
    } catch (err) {
      status.textContent = 'Network error — please check your connection and try again.';
      status.className = 'form-status error';
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = 'Submit';
    }
  });
})();
